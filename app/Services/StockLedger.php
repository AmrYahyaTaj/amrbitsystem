<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedger
{
    // Caller holds a database transaction; balance and ledger commit together.
    public function reserved(int $warehouseId, int $productId, ?int $exceptHandover = null): int
    {
        return (int) DB::table('handover_stock_reservations')->where('company_id', app(CompanyContext::class)->id())->where('warehouse_id', $warehouseId)->where('product_id', $productId)->whereNull('released_at')->when($exceptHandover, fn ($q) => $q->where('handover_id', '!=', $exceptHandover))->sum('quantity');
    }

    public function available(int $warehouseId, int $productId): int
    {
        return max(0, min((int) StockBalance::where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity') - $this->reserved($warehouseId, $productId), app(LotLedger::class)->usable($warehouseId, $productId)));
    }

    public function availabilityDetails(int $warehouseId, int $productId): array
    {
        $companyId = app(CompanyContext::class)->id();
        $physical = (int) StockBalance::where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity');
        $rows = DB::table('stock_lot_balances as b')->join('stock_lots as l', 'l.id', '=', 'b.lot_id')->where('b.company_id', $companyId)->where('l.company_id', $companyId)->where('b.warehouse_id', $warehouseId)->where('l.product_id', $productId)->get(['l.status', 'l.expires_on', 'b.quantity']);
        $quarantine = $blocked = $expired = 0;
        $today = app(LotLedger::class)->today();
        foreach ($rows as $row) {
            if ($row->status === 'quarantine') {
                $quarantine += (int) $row->quantity;
            } elseif ($row->status === 'blocked') {
                $blocked += (int) $row->quantity;
            } elseif ($row->expires_on && $row->expires_on < $today) {
                $expired += (int) $row->quantity;
            }
        }

        return ['physical' => $physical, 'reserved' => $this->reserved($warehouseId, $productId), 'quarantine' => $quarantine, 'blocked' => $blocked, 'expired' => $expired, 'available' => $this->available($warehouseId, $productId)];
    }

    public function shortageMessage(Warehouse $warehouse, Product $product, int $quantity, ?array $details = null): string
    {
        $d = $details ?? $this->availabilityDetails($warehouse->id, $product->id);
        $prefix = 'الصنف «'.$product->name.'» في «'.$warehouse->name.'»: ';
        if ($d['physical'] === 0) {
            return $prefix.'لا يوجد رصيد لهذا الصنف في المصدر المختار. اختر الصنف الذي أُثبت ناتجه وموقعه الصحيح.';
        }
        $reasons = [];
        if ($d['quarantine'] > 0) {
            $reasons[] = 'تحت حجر الجودة '.$d['quarantine'];
        }
        if ($d['blocked'] > 0) {
            $reasons[] = 'موقوف '.$d['blocked'];
        }
        if ($d['expired'] > 0) {
            $reasons[] = 'منتهي الصلاحية '.$d['expired'];
        }
        if ($d['reserved'] > 0) {
            $reasons[] = 'محجوز لطلبات تسليم '.$d['reserved'];
        }
        $message = $prefix.'المطلوب '.$quantity.' '.$product->unit.'، والمتاح للصرف '.$d['available'].'، والرصيد الفعلي '.$d['physical'].'.';
        if ($reasons) {
            $message .= ' '.implode('؛ ', $reasons).'.';
        }
        if ($d['quarantine'] > 0 || $d['blocked'] > 0) {
            $message .= ' راجع قسم الجودة والاعتمادات لمعرفة الفاحص والمعتمد والخطوة التالية؛ الإفراج عن الجودة بعد الفحص والاعتماد المستقل.';
        }
        if ($d['reserved'] > 0) {
            $message .= ' راجع طلبات التسليم المعلقة قبل إنشاء طلب إضافي.';
        }

        return $message;
    }

    public function balances()
    {
        $reserved = DB::table('handover_stock_reservations')->where('company_id', app(CompanyContext::class)->id())->whereNull('released_at')->selectRaw('warehouse_id,product_id,SUM(quantity) as quantity')->groupBy('warehouse_id', 'product_id')->get()->keyBy(fn ($r) => $r->warehouse_id.':'.$r->product_id);

        return StockBalance::with('warehouse', 'product')->get()->map(function ($balance) use ($reserved) {
            $balance->reserved_quantity = (int) ($reserved[$balance->warehouse_id.':'.$balance->product_id]->quantity ?? 0);
            $balance->available_quantity = $this->available($balance->warehouse_id, $balance->product_id);

            return $balance;
        });
    }

    public function move(Warehouse $warehouse, Product $product, int $quantity, string $reason, string $reference, ?int $handoverId = null, ?array $lots = null): array
    {
        abort_unless(DB::transactionLevel() > 0 && $quantity !== 0, 500);
        $balance = StockBalance::firstOrCreate(['warehouse_id' => $warehouse->id, 'product_id' => $product->id], ['quantity' => 0]);
        $balance = StockBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();
        if ($quantity < 0) {
            $reserved = $this->reserved($warehouse->id, $product->id, $handoverId);
            $updated = StockBalance::whereKey($balance->id)->where('quantity', '>=', -$quantity + $reserved)->decrement('quantity', -$quantity);
            if (! $updated) {
                throw ValidationException::withMessages(['quantity' => 'الرصيد المتاح بعد حجز طلبات التسليم لا يكفي. لم يتم تغيير المخزون.']);
            }
        } else {
            $balance->increment('quantity', $quantity);
        }
        $movement = StockMovement::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => $quantity, 'reason' => $reason, 'reference' => $reference, 'actor_id' => auth()->id(), 'created_at' => now()]);

        return app(LotLedger::class)->record($movement, $warehouse, $product, $quantity, $lots, $handoverId);
    }
}
