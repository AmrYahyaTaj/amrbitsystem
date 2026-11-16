<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\HandoverRequest;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductionWorkflow
{
    public function orders()
    {
        $q = ProductionOrder::query();
        if (! app(CompanyContext::class)->role->managesOperations()) {
            $q->where('operator_id', auth()->id())->whereIn('warehouse_id', app(WarehouseAccess::class)->visible()->select('id'));
        }

        return $q;
    }

    public function create(Request $r, ?int $id = null): ProductionOrder
    {
        abort_unless(app(CompanyContext::class)->role->managesOperations() || app(CompanyContext::class)->role === CompanyRole::Production, 403);
        $existing = $id ? $this->orders()->lockForUpdate()->findOrFail($id) : null;
        if ($existing) {
            $r->validate(['version' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:2000']);
            abort_unless($existing->status === 'planned' && (int) $existing->version === $r->integer('version'), 409, 'الأمر بدأ أو تغير منذ فتحه. افتحه مجددًا قبل التعديل.');
        }
        $d = $r->validate(['product_id' => 'required|integer', 'warehouse_id' => 'required|integer', 'operator_id' => 'required|integer', 'line' => 'required|string|max:100', 'shift' => 'required|string|max:60', 'scheduled_on' => 'required|date_format:Y-m-d', 'planned_quantity' => 'required|integer|between:1,1000000', 'notes' => 'required|string|min:3|max:2000']);
        $w = Warehouse::lockForUpdate()->findOrFail($d['warehouse_id']);
        abort_unless($w->type === 'production' && app(WarehouseAccess::class)->managed($w), 403, 'اختر موقع إنتاج ضمن مسؤوليتك.');
        abort_unless((int) $w->custodian_id === (int) $d['operator_id'], 422, 'مسؤول الأمر يجب أن يطابق مسؤول موقع الإنتاج.');
        app(HandoverWorkflow::class)->member($d['operator_id'], ['production']);
        Product::where('is_active', true)->findOrFail($d['product_id']);
        if ($existing) {
            $before = $existing->only(array_keys($d));
            $existing->update($d + ['version' => (int) $existing->version + 1]);
            $order = $existing;
            app(AuditRecorder::class)->record('production.updated', $order, ['before' => $before, 'after' => $d, 'reason' => $r->input('reason')]);
        } else {
            $order = ProductionOrder::create($d + ['code' => 'PR-'.Str::upper(Str::random(10)), 'created_by' => auth()->id()]);
            app(AuditRecorder::class)->record('production.planned', $order, $d);
        }

        return $order;
    }

    public function execute(Request $r, int $id, string $action): ProductionOrder
    {
        $order = $this->orders()->lockForUpdate()->findOrFail($id);
        $w = Warehouse::lockForUpdate()->findOrFail($order->warehouse_id);
        abort_unless($w->type === 'production' && app(WarehouseAccess::class)->managed($w), 403);
        app(HandoverWorkflow::class)->member($order->operator_id, ['production']);
        abort_unless((int) $w->custodian_id === (int) $order->operator_id, 409, 'تغير مسؤول الموقع؛ راجع الأمر.');
        if ($action === 'start') {
            abort_unless($order->status === 'planned', 409, 'يمكن بدء الأمر المخطط فقط.');
            $order->update(['status' => 'in_progress', 'started_at' => now()]);
        } elseif ($action === 'cancel') {
            $d = $r->validate(['note' => 'required|string|min:3|max:2000']);
            abort_unless(in_array($order->status, ['planned', 'in_progress'], true), 409, 'الأمر مغلق.');
            $order->update(['status' => 'cancelled', 'closing_note' => $d['note'], 'completed_at' => now()]);
        } else {
            abort_unless($action === 'complete', 404);
            abort_unless($order->status === 'in_progress', 409, 'ابدأ الأمر قبل إثبات الناتج.');
            $today = app(LotLedger::class)->today();
            $d = $r->validate(['good_quantity' => 'required|integer|between:1,1000000', 'scrap_quantity' => 'required|integer|between:0,1000000', 'lot_code' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]{1,79}\z/', Rule::unique('stock_lots', 'code')->where('company_id', app(CompanyContext::class)->id())], 'manufactured_on' => 'required|date_format:Y-m-d|before_or_equal:'.$today, 'expires_on' => 'nullable|date_format:Y-m-d|after_or_equal:manufactured_on|after_or_equal:'.$today, 'quality' => 'nullable|in:quarantine', 'quality_note' => 'required|string|min:3|max:2000', 'note' => 'required|string|min:3|max:2000', 'materials' => 'present|array|list|max:50', 'materials.*.product_id' => 'required|integer|distinct', 'materials.*.quantity' => 'required|integer|between:1,1000000']);
            if ($w->capacity_units) {
                $physical = StockBalance::where('warehouse_id', $w->id)->sum('quantity');
                $incoming = HandoverRequest::where('to_warehouse_id', $w->id)->where('status', 'pending')->sum('reserved_units');
                // Conservative capacity check; material consumption never grants hidden capacity.
                abort_if($physical + $incoming + $d['good_quantity'] > $w->capacity_units, 422, 'الناتج يتجاوز سعة موقع الإنتاج.');
            }
            foreach ($d['materials'] as $line) {
                abort_if((int) $line['product_id'] === (int) $order->product_id, 422, 'المادة الداخلة لا تكون نفس الصنف الناتج؛ استخدم صنفًا مستقلًا للخامة.');
                $material = Product::where('is_active', true)->findOrFail($line['product_id']);
                $parts = app(StockLedger::class)->move($w, $material, -$line['quantity'], 'production_consumption', $order->code);
                DB::table('production_materials')->insert(['company_id' => $order->company_id, 'production_order_id' => $order->id, 'product_id' => $material->id, 'quantity' => $line['quantity'], 'lots' => json_encode($parts, JSON_THROW_ON_ERROR)]);
            }
            $product = Product::where('is_active', true)->findOrFail($order->product_id);
            $lot = app(LotLedger::class)->newLot($w, $product, ['code' => $d['lot_code'], 'source' => 'production', 'production_order_id' => $order->id, 'status' => 'quarantine', 'manufactured_on' => $d['manufactured_on'], 'expires_on' => $d['expires_on'] ?? null, 'notes' => $d['quality_note']]);
            app(StockLedger::class)->move($w, $product, $d['good_quantity'], 'production_output', $order->code, null, [['lot_id' => $lot->id, 'quantity' => $d['good_quantity']]]);
            $order->update(['status' => 'completed', 'good_quantity' => $d['good_quantity'], 'scrap_quantity' => $d['scrap_quantity'], 'quality_note' => $d['quality_note'], 'closing_note' => $d['note'], 'completed_at' => now(), 'completed_by' => auth()->id()]);
            app(QualityWorkflow::class)->request($lot, 'دفعة ناتجة عن أمر الإنتاج '.$order->code.'؛ يلزم فحص واعتماد مستقلان.');
        }
        app(AuditRecorder::class)->record('production.'.$action, $order, ['status' => $order->status, 'note' => $r->input('note')]);

        return $order;
    }
}
