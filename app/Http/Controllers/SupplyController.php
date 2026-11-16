<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\AuditLog;
use App\Models\HandoverRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\QualityInspection;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Services\AuditRecorder;
use App\Services\HandoverWorkflow;
use App\Services\LotLedger;
use App\Services\OperationOnce;
use App\Services\ProductionWorkflow;
use App\Services\QualityWorkflow;
use App\Services\WarehouseAccess;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplyController extends Controller
{
    private function role(): CompanyRole
    {
        return app(CompanyContext::class)->role;
    }

    private function allowed(): void
    {
        abort_unless($this->role()->managesOperations() || in_array($this->role(), [CompanyRole::WarehouseKeeper, CompanyRole::Production, CompanyRole::Viewer, CompanyRole::Driver, CompanyRole::Representative], true), 403);
    }

    private function table(string $table)
    {
        return DB::table($table)->where($table.'.company_id', app(CompanyContext::class)->id());
    }

    private function sites()
    {
        return app(WarehouseAccess::class)->visible();
    }

    private function lots()
    {
        $q = StockLot::query();
        if (! $this->role()->managesOperations() && $this->role() !== CompanyRole::Viewer) {
            $q->whereIn('id', $this->table('stock_lot_entries')->whereIn('warehouse_id', $this->sites()->select('id'))->select('lot_id'));
        }

        return $q;
    }

    private function products()
    {
        return Product::orderBy('name')->get(['id', 'name', 'sku', 'unit', 'is_active', 'reorder_level']);
    }

    public function page(Request $r, OperationsController $operations)
    {
        $this->allowed();
        $section = $r->path();
        abort_unless(in_array($section, $operations->sections(), true), 403);

        return view('app', ['section' => $section, 'sections' => $operations->sections(), 'supplyWorkspace' => true]);
    }

    public function index(Request $r)
    {
        $this->allowed();
        $d = $r->validate(['section' => 'required|in:production,warehouses,traceability', 'q' => 'nullable|string|max:100', 'status' => 'nullable|in:planned,in_progress,completed,cancelled,released,quarantine,blocked', 'warehouse_id' => 'nullable|integer', 'product_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1']);
        $admin = $this->role()->managesOperations();
        $ids = $this->sites()->pluck('id');
        $sites = $this->sites()->with('custodian:id,name,username')->orderBy('name')->get();
        $q = $d['q'] ?? null;
        $section = $d['section'];
        $canProduction = $admin || $this->role() === CompanyRole::Production;
        $options = ['warehouses' => $sites, 'products' => $this->products(), 'operators' => $admin ? app(HandoverWorkflow::class)->recipients()->where('role', 'production')->values() : [], 'custodians' => $admin ? app(HandoverWorkflow::class)->recipients()->whereIn('role', ['production', 'warehouse_keeper'])->values() : []];
        if ($section === 'production') {
            abort_unless($canProduction, 403);
            $base = app(ProductionWorkflow::class)->orders();
            $query = (clone $base)->when($q, fn ($b) => $b->where(fn ($s) => $s->where('code', 'like', '%'.$q.'%')->orWhere('line', 'like', '%'.$q.'%')->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$q.'%'))));
            $query->when($d['status'] ?? null, fn ($b, $s) => $b->where('status', $s))->when($d['warehouse_id'] ?? null, fn ($b, $id) => $b->where('warehouse_id', $id))->when($d['product_id'] ?? null, fn ($b, $id) => $b->where('product_id', $id));
            $records = $query->with(['product:id,company_id,name,sku,unit', 'warehouse', 'operator:id,name'])->orderByDesc('id')->paginate(20);
            $summary = ['planned' => (clone $base)->where('status', 'planned')->count(), 'active' => (clone $base)->where('status', 'in_progress')->count(), 'good' => (clone $base)->sum('good_quantity'), 'scrap' => (clone $base)->sum('scrap_quantity')];
        } elseif ($section === 'warehouses') {
            $query = $this->balanceQuery($ids)->when($q, fn ($b) => $b->where(fn ($s) => $s->where('products.name', 'like', '%'.$q.'%')->orWhere('products.sku', 'like', '%'.$q.'%')->orWhere('warehouses.name', 'like', '%'.$q.'%')))->when($d['warehouse_id'] ?? null, fn ($b, $id) => $b->where('stock_balances.warehouse_id', $id))->when($d['product_id'] ?? null, fn ($b, $id) => $b->where('stock_balances.product_id', $id));
            $records = $query->orderBy('warehouses.name')->orderBy('products.name')->paginate(30);
            $records->through(fn ($b) => $this->balanceRow($b));
            $lotIds = $this->table('stock_lot_balances')->whereIn('warehouse_id', $ids)->where('quantity', '>', 0)->pluck('lot_id');
            $summary = ['sites' => $sites->count(), 'physical' => (int) StockBalance::whereIn('warehouse_id', $ids)->sum('quantity'), 'reserved' => (int) $this->table('handover_stock_reservations')->whereIn('warehouse_id', $ids)->whereNull('released_at')->sum('quantity'), 'attention' => StockLot::whereIn('id', $lotIds)->where(fn ($b) => $b->where('status', '!=', 'released')->orWhere('expires_on', '<', app(LotLedger::class)->today())->orWhereBetween('expires_on', [app(LotLedger::class)->today(), now()->addDays(30)->toDateString()]))->count()];
        } else {
            $query = $this->lots()->when($q, fn ($b) => $b->where(fn ($s) => $s->where('code', 'like', '%'.$q.'%')->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$q.'%')->orWhere('sku', 'like', '%'.$q.'%'))))->when($d['status'] ?? null, fn ($b, $s) => $b->where('status', $s))->when($d['product_id'] ?? null, fn ($b, $id) => $b->where('product_id', $id));
            if (! empty($d['warehouse_id'])) {
                abort_unless($ids->contains($d['warehouse_id']), 403);
                $query->whereIn('id', $this->table('stock_lot_balances')->where('warehouse_id', $d['warehouse_id'])->where('quantity', '>', 0)->select('lot_id'));
            }
            $records = $query->with('product:id,company_id,name,sku,unit')->orderByDesc('id')->paginate(20);
            $base = $this->lots();
            $summary = ['lots' => (clone $base)->count(), 'production' => (clone $base)->where('source', 'production')->count(), 'legacy' => (clone $base)->where('source', 'legacy')->count(), 'held' => (clone $base)->where('status', '!=', 'released')->count()];
        }

        return response()->json(['records' => $records, 'summary' => $summary, 'options' => $options, 'role' => $this->role()->value, 'user_id' => auth()->id(), 'can_manage' => $admin, 'can_produce' => $canProduction, 'today' => app(LotLedger::class)->today(), 'as_of' => now()->toIso8601String()]);
    }

    private function balanceQuery($ids)
    {
        return $this->table('stock_balances')->join('products', 'products.id', '=', 'stock_balances.product_id')->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')->whereIn('stock_balances.warehouse_id', $ids)->select('stock_balances.*', 'products.name as product_name', 'products.sku', 'products.unit', 'products.reorder_level', 'warehouses.name as warehouse_name');
    }

    private function balanceRow($b)
    {
        $physical = (int) $b->quantity;
        $reserved = (int) $this->table('handover_stock_reservations')->where('warehouse_id', $b->warehouse_id)->where('product_id', $b->product_id)->whereNull('released_at')->sum('quantity');
        $goodLots = StockLot::where('product_id', $b->product_id)->where('status', 'released')->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', app(LotLedger::class)->today()))->pluck('id');
        $usable = (int) $this->table('stock_lot_balances')->where('warehouse_id', $b->warehouse_id)->whereIn('lot_id', $goodLots)->sum('quantity');
        $lotReserved = (int) $this->table('handover_lot_reservations')->where('warehouse_id', $b->warehouse_id)->whereIn('lot_id', $goodLots)->whereNull('released_at')->sum('quantity');

        return (array) $b + ['reserved' => $reserved, 'unusable' => $physical - $usable, 'available' => max(0, min($physical - $reserved, $usable - $lotReserved))];
    }

    public function production(Request $r, OperationOnce $once, ProductionWorkflow $workflow, ?int $id = null, string $action = 'create')
    {
        abort_unless($this->role()->managesOperations() || $this->role() === CompanyRole::Production, 403);
        $r->validate(['request_id' => 'required|uuid']);
        $result = $once->run('production.'.$action, $r->input('request_id'), ['id' => $id] + $r->except('request_id', '_token'), function () use ($r, $id, $action, $workflow) {
            $order = in_array($action, ['create', 'update'], true) ? $workflow->create($r, $id) : $workflow->execute($r, $id, $action);

            return ['id' => $order->id, 'message' => 'حفظت عملية الإنتاج وسجلها. الناتج لا ينتقل للمخزن إلا بطلب وموافقة المستلم.'];
        });

        return response()->json($result);
    }

    public function warehouse(Request $r, OperationOnce $once, WarehouseAccess $access)
    {
        abort_unless($this->role()->managesOperations(), 403);
        $r->validate(['request_id' => 'required|uuid']);

        return response()->json($once->run('warehouse.configure', $r->input('request_id'), $r->except('request_id', '_token'), function () use ($r, $access) {
            $company = app(CompanyContext::class)->id();
            $d = $r->validate(['request_id' => 'required|uuid', 'id' => 'nullable|integer', 'name' => 'required|string|max:150', 'code' => ['required', 'string', 'max:40', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]{1,39}\z/', Rule::unique('warehouses', 'code')->where('company_id', $company)->ignore($r->integer('id'))], 'type' => 'required|in:warehouse,production', 'custodian_id' => 'required|integer', 'address' => 'nullable|string|max:300', 'phone' => 'nullable|string|max:30', 'capacity_units' => 'nullable|integer|between:1,10000000', 'is_active' => 'required|boolean', 'notes' => 'required|string|min:3|max:2000']);
            unset($d['request_id']);

            return ['id' => $access->save($d)->id, 'message' => 'حفظ الموقع ومسؤوله. لا تنقل هذه العملية أي رصيد أو عهدة.'];
        }));
    }

    public function detail(Request $r, int $id)
    {
        abort_unless($this->role()->managesOperations() || $this->role() === CompanyRole::Production, 403);
        $order = app(ProductionWorkflow::class)->orders()->with(['product:id,company_id,name,sku,unit', 'warehouse', 'operator:id,name'])->findOrFail($id);

        return response()->json(['order' => $order, 'lots' => StockLot::where('production_order_id', $id)->get(), 'quality_requests' => QualityInspection::whereIn('lot_id', StockLot::where('production_order_id', $id)->select('id'))->whereIn('status', ['awaiting_inspection', 'awaiting_approval'])->get(['id', 'lot_id', 'code', 'status']), 'materials' => $this->table('production_materials')->where('production_order_id', $id)->join('products', 'products.id', '=', 'production_materials.product_id')->get(['production_materials.*', 'products.name', 'products.unit'])]);
    }

    public function warehouseDetail(Request $r, int $id)
    {
        $this->allowed();
        $r->validate(['page' => 'nullable|integer|min:1']);
        $w = $this->sites()->with('custodian:id,name,username')->findOrFail($id);
        $movements = $this->table('stock_movements')->where('warehouse_id', $id)->join('products', 'products.id', '=', 'stock_movements.product_id')->leftJoin('users', 'users.id', '=', 'stock_movements.actor_id')->orderByDesc('stock_movements.id')->paginate(30, ['stock_movements.*', 'products.name as product_name', 'products.unit', 'users.name as actor_name']);
        $pending = app(HandoverWorkflow::class)->visible()->where('status', 'pending')->where(fn ($q) => $q->where('from_warehouse_id', $id)->orWhere('to_warehouse_id', $id))->with(['sender:id,name', 'recipient:id,name'])->get(['id', 'code', 'sender_id', 'recipient_id', 'reserved_units', 'from_warehouse_id', 'to_warehouse_id', 'created_at']);

        return response()->json(['warehouse' => $w, 'movements' => $movements, 'pending' => $pending]);
    }

    public function trace(Request $r, int $id)
    {
        $this->allowed();
        $r->validate(['page' => 'nullable|integer|min:1']);
        $lot = $this->lots()->with(['product:id,company_id,name,sku,unit', 'production'])->findOrFail($id);
        $entries = $this->table('stock_lot_entries')->where('lot_id', $id)->leftJoin('stock_movements', 'stock_movements.id', '=', 'stock_lot_entries.stock_movement_id')->join('warehouses', 'warehouses.id', '=', 'stock_lot_entries.warehouse_id')->leftJoin('users', 'users.id', '=', 'stock_movements.actor_id')->orderBy('stock_lot_entries.id')->paginate(40, ['stock_lot_entries.*', 'warehouses.name as warehouse_name', 'users.name as actor_name']);
        $saleRefs = $this->table('stock_lot_entries')->where('lot_id', $id)->where('reason', 'sale')->where('quantity', '<', 0)->pluck('reference');
        $transferRefs = $this->table('stock_lot_entries')->where('lot_id', $id)->whereIn('reason', ['transfer_out', 'transfer_in'])->pluck('reference');
        $sales = Order::whereIn('code', $saleRefs)->with('customer:id,company_id,name,code')->get(['id', 'company_id', 'code', 'customer_id', 'status', 'created_at']);
        $handovers = HandoverRequest::whereIn('code', $transferRefs)->with(['sender:id,name', 'recipient:id,name'])->get(['id', 'code', 'status', 'sender_id', 'recipient_id', 'created_at', 'decided_at']);
        $balances = $this->table('stock_lot_balances')->where('lot_id', $id)->where('quantity', '>', 0)->join('warehouses', 'warehouses.id', '=', 'stock_lot_balances.warehouse_id')->get(['warehouse_id', 'warehouses.name', 'quantity']);
        $materials = $lot->production_order_id ? $this->table('production_materials')->where('production_order_id', $lot->production_order_id)->join('products', 'products.id', '=', 'production_materials.product_id')->get(['production_materials.*', 'products.name', 'products.unit']) : [];
        $uses = $this->table('stock_lot_entries')->where('lot_id', $id)->where('reason', 'production_consumption')->pluck('reference');
        $descendants = StockLot::whereIn('production_order_id', ProductionOrder::whereIn('code', $uses)->select('id'))->get(['id', 'code', 'product_id', 'production_order_id']);
        $proofs = $this->table('delivery_proofs')->whereIn('order_id', $sales->pluck('id'))->get(['id', 'order_id', 'receiver_name', 'recorded_by', 'created_at']);

        $quality = AuditLog::where('subject_type', 'stock_lots')->where('subject_id', $id)->where('action', 'lot.quality_changed')->orderByDesc('id')->limit(50)->get(['actor_id', 'metadata', 'created_at']);

        return response()->json(['quality_history' => $quality, 'lot' => $lot, 'entries' => $entries, 'balances' => $balances, 'sales' => $sales, 'handovers' => $handovers, 'materials' => $materials, 'descendants' => $descendants, 'proofs' => $proofs]);
    }

    private function deliveryOrder(int $id): Order
    {
        abort_unless($this->role()->managesOperations() || $this->role() === CompanyRole::Representative, 403);
        $order = Order::findOrFail($id);
        abort_unless($this->role()->managesOperations() || (int) $order->representative_id === auth()->id(), 403);

        return $order;
    }

    public function deliveryOptions(Request $r)
    {
        abort_unless($this->role()->managesOperations() || $this->role() === CompanyRole::Representative, 403);
        $d = $r->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $orders = Order::where('status', 'fulfilled')->when(! $this->role()->managesOperations(), fn ($q) => $q->where('representative_id', auth()->id()))->when($d['q'] ?? null, fn ($q, $s) => $q->where('code', 'like', '%'.$s.'%'))->with('customer:id,company_id,name,code')->orderByDesc('id')->paginate(20, ['id', 'company_id', 'code', 'customer_id', 'status', 'representative_id']);

        return response()->json(['orders' => $orders, 'proofs' => $this->table('delivery_proofs')->whereIn('order_id', $orders->pluck('id'))->orderByDesc('id')->get(['id', 'order_id', 'receiver_name', 'note', 'created_at'])]);
    }

    public function deliveryUpload(Request $r, int $id, OperationOnce $once)
    {
        $order = $this->deliveryOrder($id);
        abort_unless($order->status === 'fulfilled', 422, 'أثبت صرف الفاتورة أولًا.');
        $d = $r->validate(['request_id' => 'required|uuid', 'receiver_name' => 'required|string|min:2|max:150', 'note' => 'required|string|min:3|max:2000', 'acknowledge' => 'required|accepted', 'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $file = $r->file('document');
        $hash = hash_file('sha256', $file->getRealPath());
        $name = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 180, '');
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'delivery-proof';
        $path = null;
        $uuid = (string) Str::uuid();
        try {
            return response()->json($once->run('delivery.proof', $d['request_id'], ['id' => $id, 'receiver_name' => $d['receiver_name'], 'note' => $d['note'], 'hash' => $hash, 'name' => $name], function () use ($d, $file, $id, $hash, $name, $uuid, &$path) {
                $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$file->getMimeType()] ?? null;
                abort_unless($extension, 422, 'ارفع PDF أو صورة JPEG/PNG.');
                $path = $file->storeAs('delivery-proofs/'.app(CompanyContext::class)->id().'/'.$id, $uuid.'.'.$extension, 'local');
                abort_unless($path, 500);
                $proof = $this->table('delivery_proofs')->insertGetId(['company_id' => app(CompanyContext::class)->id(), 'order_id' => $id, 'recorded_by' => auth()->id(), 'receiver_name' => $d['receiver_name'], 'note' => $d['note'], 'path' => $path, 'original_name' => $name, 'mime' => $file->getMimeType(), 'sha256' => $hash, 'size' => $file->getSize(), 'created_at' => now()]);
                app(AuditRecorder::class)->record('delivery.proof_archived', Order::findOrFail($id), ['proof_id' => $proof, 'sha256' => $hash]);

                return ['id' => $proof, 'message' => 'حفظ إثبات التسليم باسم المستلم وحساب من رفعه. لا ينشئ الرفع تحصيلًا أو صرفًا جديدًا.'];
            }));
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }
    }

    public function deliveryDownload(int $id)
    {
        $proof = $this->table('delivery_proofs')->where('id', $id)->first();
        abort_unless($proof, 404);
        $this->deliveryOrder($proof->order_id);
        abort_unless(Storage::disk('local')->exists($proof->path), 404);

        return Storage::disk('local')->download($proof->path, $proof->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    public function lotStatus(Request $r, int $id, OperationOnce $once)
    {
        abort_unless($this->role()->managesOperations(), 403);
        $d = $r->validate(['request_id' => 'required|uuid', 'status' => 'required|in:released,quarantine,blocked', 'note' => 'required|string|min:3|max:2000']);

        return response()->json($once->run('lot.status', $d['request_id'], ['id' => $id] + $r->except('request_id', '_token'), function () use ($id, $d) {
            $lot = StockLot::lockForUpdate()->findOrFail($id);
            app(QualityWorkflow::class)->hold($lot, $d['status'], $d['note']);

            return ['id' => $id, 'message' => 'حفظ قرار الجودة. الدفعات المحجورة أو الموقوفة لا تُصرف حتى الإفراج عنها.'];
        }));
    }

    public function export(Request $r)
    {
        $this->allowed();
        $r->validate(['warehouse_id' => 'nullable|integer', 'product_id' => 'nullable|integer']);
        $ids = $this->sites()->pluck('id');
        $query = $this->balanceQuery($ids)->when($r->integer('warehouse_id'), fn ($b, $id) => $b->where('warehouse_id', $id))->when($r->integer('product_id'), fn ($b, $id) => $b->where('product_id', $id))->orderBy('stock_balances.id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['الموقع', 'الصنف', 'الرمز', 'الوحدة', 'فعلي', 'محجوز', 'غير صالح للصرف', 'متاح للصرف'], ',', '"', '');
            foreach ($query->cursor() as $row) {
                $b = $this->balanceRow($row);
                $cells = [$b['warehouse_name'], $b['product_name'], $b['sku'], $b['unit'], $b['quantity'], $b['reserved'], $b['unusable'], $b['available']];
                $cells = array_map(fn ($v) => is_string($v) && preg_match('/^[\s]*[=+@-]/u', $v) ? "'".$v : $v, $cells);
                fputcsv($out, $cells, ',', '"', '');
            }
            fclose($out);
        }, 'amrbit-stock-'.app(LotLedger::class)->today().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
