<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LotLedger
{
    private function table(string $table)
    {
        return DB::table($table)->where('company_id', app(CompanyContext::class)->id());
    }

    public function today(): string
    {
        return now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString();
    }

    public function reserved(int $warehouse, int $lot, ?int $except = null): int
    {
        return (int) $this->table('handover_lot_reservations')->where('warehouse_id', $warehouse)->where('lot_id', $lot)->whereNull('released_at')->when($except, fn ($q) => $q->where('handover_id', '!=', $except))->sum('quantity');
    }

    public function allocate(Warehouse $w, Product $p, int $quantity, ?int $except = null): array
    {
        $lots = StockLot::where('product_id', $p->id)->where('status', 'released')->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', $this->today()))->whereIn('id', $this->table('stock_lot_balances')->where('warehouse_id', $w->id)->where('quantity', '>', 0)->select('lot_id'))->orderByRaw('CASE WHEN expires_on IS NULL THEN 1 ELSE 0 END')->orderBy('expires_on')->orderBy('id')->lockForUpdate()->get();
        $remaining = $quantity;
        $allocations = [];
        foreach ($lots as $lot) {
            $balance = (int) $this->table('stock_lot_balances')->where('warehouse_id', $w->id)->where('lot_id', $lot->id)->lockForUpdate()->value('quantity');
            $take = min($remaining, max(0, $balance - $this->reserved($w->id, $lot->id, $except)));
            if ($take) {
                $allocations[] = ['lot_id' => $lot->id, 'code' => $lot->code, 'quantity' => $take];
                $remaining -= $take;
            }
            if (! $remaining) {
                break;
            }
        }
        abort_if($remaining > 0, 422, 'لا يكفي رصيد الدفعات الصالحة المتاحة للصنف '.$p->name.'؛ راجع الحجز والحجر والصلاحية.');

        return $allocations;
    }

    public function newLot(Warehouse $w, Product $p, array $meta = []): StockLot
    {
        return StockLot::create($meta + ['product_id' => $p->id, 'origin_warehouse_id' => $w->id, 'code' => 'LOT-'.Str::upper(Str::random(12)), 'source' => 'external', 'status' => 'released']);
    }

    public function usable(int $warehouse, int $product): int
    {
        $ids = StockLot::where('product_id', $product)->where('status', 'released')->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', $this->today()))->pluck('id');
        $physical = (int) $this->table('stock_lot_balances')->where('warehouse_id', $warehouse)->whereIn('lot_id', $ids)->sum('quantity');
        $reserved = (int) $this->table('handover_lot_reservations')->where('warehouse_id', $warehouse)->whereIn('lot_id', $ids)->whereNull('released_at')->sum('quantity');

        return max(0, $physical - $reserved);
    }

    // Explicit opening import for synthetic fixtures / historical balances only.
    public function opening(Warehouse $w, Product $p): void
    {
        $quantity = (int) StockBalance::where('warehouse_id', $w->id)->where('product_id', $p->id)->value('quantity');
        if ($quantity <= 0) {
            return;
        }
        abort_if($this->table('stock_lot_entries')->where('warehouse_id', $w->id)->whereIn('lot_id', StockLot::where('product_id', $p->id)->select('id'))->exists(), 409, 'يوجد تتبع لهذا الرصيد؛ لا يمكن استبداله بافتتاحي.');
        $lot = $this->newLot($w, $p, ['code' => 'LEG-'.$w->id.'-'.$p->id, 'source' => 'legacy', 'notes' => 'رصيد افتتاحي بلا تاريخ دفعة سابق.']);
        $key = ['company_id' => $lot->company_id, 'lot_id' => $lot->id, 'warehouse_id' => $w->id];
        DB::table('stock_lot_balances')->insert($key + ['quantity' => $quantity]);
        DB::table('stock_lot_entries')->insert($key + ['quantity' => $quantity, 'reason' => 'legacy_opening', 'reference' => 'TRACE-OPENING', 'created_at' => now()]);
    }

    public function record(StockMovement $movement, Warehouse $w, Product $p, int $quantity, ?array $allocations = null, ?int $handover = null): array
    {
        abort_unless(DB::transactionLevel() > 0, 500, 'يجب تسجيل الحركة داخل معاملة.');
        if ($quantity < 0) {
            $allocations ??= $this->allocate($w, $p, -$quantity, $handover);
        } else {
            $allocations ??= [['lot_id' => $this->newLot($w, $p)->id, 'quantity' => $quantity]];
        }
        abort_unless(array_sum(array_column($allocations, 'quantity')) === abs($quantity), 422, 'توزيع الدفعات لا يطابق كمية الحركة.');
        $seen = [];
        foreach ($allocations as &$part) {
            abort_if(isset($seen[$part['lot_id']]) || $part['quantity'] < 1, 422, 'توزيع الدفعات غير صحيح.');
            $seen[$part['lot_id']] = true;
            $lot = StockLot::lockForUpdate()->findOrFail($part['lot_id']);
            abort_unless((int) $lot->product_id === (int) $p->id, 422, 'الدفعة تخص صنفًا آخر.');
            $part['code'] = $lot->code;
            $key = ['company_id' => $movement->company_id, 'warehouse_id' => $w->id, 'lot_id' => $lot->id];
            if ($quantity < 0) {
                abort_unless($lot->status === 'released' && (! $lot->expires_on || $lot->expires_on->toDateString() >= $this->today()), 422, 'الدفعة محجورة أو منتهية الصلاحية ولا يمكن صرفها.');
                $reserved = $this->reserved($w->id, $lot->id, $handover);
                $updated = DB::table('stock_lot_balances')->where($key)->where('quantity', '>=', $part['quantity'] + $reserved)->decrement('quantity', $part['quantity']);
                abort_unless($updated, 422, 'رصيد الدفعة محجوز أو غير كافٍ.');
            } else {
                DB::table('stock_lot_balances')->insertOrIgnore($key + ['quantity' => 0]);
                DB::table('stock_lot_balances')->where($key)->increment('quantity', $part['quantity']);
            }
            DB::table('stock_lot_entries')->insert($key + ['stock_movement_id' => $movement->id, 'quantity' => ($quantity < 0 ? -1 : 1) * $part['quantity'], 'reason' => $movement->reason, 'reference' => $movement->reference, 'created_at' => now()]);
        }

        return $allocations;
    }
}
