<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerChange;
use App\Models\DebtFollowup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Trip;
use App\Models\Visit;
use App\Models\WeeklyPlan;
use App\Services\AuditRecorder;
use App\Services\CustomerMaintenance;
use App\Services\OperationOnce;
use App\Services\RepresentativeAccess;
use App\Services\RepresentativeRegistry;
use App\Services\WeeklyRouting;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerJourneyController extends Controller
{
    private function customer(int $id): Customer
    {
        abort_unless(in_array(app(CompanyContext::class)->role, [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Representative, CompanyRole::Accountant, CompanyRole::Viewer], true), 403);
        $customer = Customer::with(['area', 'representative:id,name'])->findOrFail($id);
        $role = app(CompanyContext::class)->role;
        abort_if($role === CompanyRole::Representative && (int) $customer->representative_id !== auth()->id(), 403, 'ملف العميل متاح للمندوب المسؤول فقط.');

        return $customer;
    }

    public function page(Request $request, int $id)
    {
        $this->customer($id);

        return app(OperationsController::class)->page($request, 'customers');
    }

    public function schedule(Request $request)
    {
        abort_unless(app(CompanyContext::class)->role->managesOperations() || app(CompanyContext::class)->role === CompanyRole::Representative, 403);

        return app(OperationsController::class)->page($request, 'trips');
    }

    public function day(Request $request)
    {
        abort_unless(app(CompanyContext::class)->role === CompanyRole::Representative, 403);
        $d = $request->validate(['date' => 'required|date_format:Y-m-d']);
        $trips = Trip::where('representative_id', auth()->id())->whereDate('scheduled_for', $d['date'])->where('status', '!=', 'cancelled')->with(['vehicle', 'representative:id,name', 'warehouse'])->withCount(['visits as stops_count' => fn ($q) => $q->where('status', '!=', 'cancelled'), 'visits as completed_stops_count' => fn ($q) => $q->where('status', 'completed')])->orderBy('id')->get();

        return response()->json([
            'trips' => $trips,
            'open_trips' => Trip::where('representative_id', auth()->id())->where('status', 'active')->with(['vehicle', 'representative:id,name', 'warehouse'])->get(),
            'visits' => Visit::whereIn('trip_id', $trips->pluck('id'))->where('representative_id', auth()->id())->where('status', '!=', 'cancelled')->with(['customer.area', 'representative:id,name'])->orderBy('sequence')->orderBy('id')->get(),
            'orders' => Order::whereIn('trip_id', $trips->pluck('id'))->where('representative_id', auth()->id())->with(['customer', 'representative:id,name', 'items.product'])->orderBy('id')->get(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $customer = $this->customer($id);
        $rep = app(CompanyContext::class)->role === CompanyRole::Representative;
        $financial = app(CompanyContext::class)->role !== CompanyRole::Viewer;
        $orders = Order::where('customer_id', $id)->when($rep, fn ($q) => $q->where('representative_id', auth()->id()));
        $fulfilled = (clone $orders)->where('status', 'fulfilled');
        $visits = Visit::where('customer_id', $id)->when($rep, fn ($q) => $q->where('representative_id', auth()->id()));
        $ids = (clone $orders)->select('orders.id');
        $today = now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString();
        $d = $request->validate(['tab' => 'nullable|in:overview,orders,visits,collections,followups,ledger', 'page' => 'nullable|integer|min:1|max:100000']);
        $tab = $d['tab'] ?? 'overview';
        abort_if(! $financial && in_array($tab, ['orders', 'collections', 'followups', 'ledger']), 403);
        $history = match ($tab) {
            'orders' => (clone $orders)->with(['items.product', 'representative:id,name'])->orderByDesc('created_at')->orderByDesc('id')->paginate(20),
            'visits' => (clone $visits)->with('representative:id,name')->orderByDesc('scheduled_for')->orderByDesc('id')->paginate(20),
            'collections' => Collection::whereIn('order_id', (clone $ids))->with(['order:id,company_id,code', 'actor:id,name'])->orderByDesc('collected_at')->orderByDesc('id')->paginate(20),
            'followups' => DebtFollowup::whereIn('order_id', (clone $ids))->with(['order:id,company_id,code', 'actor:id,name'])->orderByDesc('id')->paginate(20),
            'ledger' => $this->ledger($fulfilled, $ids),
            default => null,
        };
        $products = $financial ? OrderItem::whereIn('order_id', (clone $fulfilled)->select('orders.id'))->with('product:id,company_id,name,unit')->select('product_id')->selectRaw('SUM(quantity) as quantity, SUM(total_minor) as total_minor, COUNT(DISTINCT order_id) as orders_count')->groupBy('product_id')->get() : [];

        return response()->json([
            'customer' => $customer, 'financial' => $financial,
            'financial_scope' => $rep ? 'الفواتير والتحصيلات التابعة لك. المديونية السابقة على مندوب آخر يراجعها المدير قبل تحويل المسؤولية.' : 'كل حركات العميل داخل الشركة الحالية.',
            'summary' => [
                'orders' => $financial ? (clone $orders)->count() : null,
                'sales_minor' => $financial ? (int) (clone $fulfilled)->sum('total_minor') : null,
                'paid_minor' => $financial ? (int) (clone $fulfilled)->sum('paid_minor') : null,
                'balance_minor' => $financial ? (int) (clone $fulfilled)->sum(DB::raw('total_minor-paid_minor')) : null,
                'overdue_minor' => $financial ? (int) (clone $fulfilled)->where('due_date', '<', $today)->sum(DB::raw('total_minor-paid_minor')) : null,
                'visits' => (clone $visits)->where('status', '!=', 'cancelled')->count(),
                'cancelled_visits' => (clone $visits)->where('status', 'cancelled')->count(),
                'completed' => (clone $visits)->where('status', 'completed')->count(),
                'last_visit' => (clone $visits)->where('status', 'completed')->latest('finished_at')->value('finished_at'),
                'last_order' => $financial ? (clone $fulfilled)->latest('created_at')->value('created_at') : null,
            ],
            'products' => $products, 'history' => $history,
            'recent_orders' => $financial ? (clone $orders)->with('items.product')->latest('created_at')->latest('id')->limit(3)->get() : [],
            'recent_visits' => (clone $visits)->with('representative:id,name')->latest('scheduled_for')->latest('id')->limit(5)->get(),
            'weekly_days' => WeeklyPlan::where('representative_id', $customer->representative_id)->where('is_active', true)->get()->filter(fn ($p) => in_array($customer->id, $p->customer_ids, true))->pluck('weekday')->values(),
        ]);
    }

    private function ledger($fulfilled, $ids)
    {
        $charges = (clone $fulfilled)->withoutEagerLoads()->selectRaw("created_at as occurred_at, 'invoice' as kind, code as reference, total_minor as debit_minor, 0 as credit_minor, id as source_id");
        $credits = Collection::whereIn('order_id', (clone $ids))->selectRaw("collected_at as occurred_at, 'receipt' as kind, receipt as reference, 0 as debit_minor, amount_minor as credit_minor, id as source_id");

        return DB::query()->fromSub($charges->unionAll($credits), 'ledger')->orderByDesc('occurred_at')->orderByDesc('kind')->orderByDesc('source_id')->paginate(20);
    }

    public function execute(Request $request, string $action, OperationOnce $once, WeeklyRouting $routing)
    {
        $role = app(CompanyContext::class)->role;
        $allowed = match ($action) {
            'customer.contact','customer.location' => $role->managesOperations() || $role === CompanyRole::Representative,
            'customer.update','customer.review','representative.details','representative.vehicle','weekly.save','weekly.publish' => $role->managesOperations(),
            default => false,
        };
        abort_unless($allowed, 403);
        $request->validate(['request_id' => 'required|uuid']);

        return response()->json($once->run($action, $request->input('request_id'), $request->except('request_id', '_token'), function () use ($request, $action, $routing) {
            $model = match ($action) {
                'weekly.save' => $routing->save($request),
                'weekly.publish' => $routing->publish($request),
                'customer.contact' => app(CustomerMaintenance::class)->contact($request),
                'customer.location' => app(CustomerMaintenance::class)->location($request),
                'customer.review' => app(CustomerMaintenance::class)->review($request),
                'representative.details' => app(RepresentativeRegistry::class)->details($request),
                'representative.vehicle' => app(RepresentativeRegistry::class)->vehicle($request),
                default => $this->update($request),
            };

            $message = $model instanceof CustomerChange && $action === 'customer.location' ? 'تم إرسال تصحيح الموقع للمراجعة. الموقع المعتمد لم يتغير بعد.' : ($action === 'weekly.publish' ? 'الرحلة جاهزة بتاريخها. لن يتكرر نشرها عند إعادة المحاولة.' : 'تم الحفظ بنجاح.');

            return ['id' => $model->id, 'message' => $message];
        }));
    }

    private function update(Request $request): Customer
    {
        $customer = Customer::lockForUpdate()->findOrFail($request->validate(['id' => 'required|integer'])['id']);
        $company = app(CompanyContext::class)->id();
        $d = $request->validate([
            'name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'address' => 'required|string|max:500',
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('company_id', $company)],
            'representative_id' => 'required|integer', 'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180', 'geofence_radius_m' => 'required|integer|between:25,500',
            'is_active' => 'required|boolean', 'service_notes' => 'nullable|string|max:2000', 'reason' => 'required|string|min:3|max:500',
            'profile_version' => 'required|integer|min:1',
        ]);
        abort_unless((int) $customer->profile_version === (int) $d['profile_version'], 409, 'ملف العميل تغير. حدّث الصفحة ثم أعد التعديل.');
        $before = $customer->toArray();
        $pointChanged = (float) $d['latitude'] !== (float) $customer->latitude || (float) $d['longitude'] !== (float) $customer->longitude;
        $ownerChanged = (int) $customer->representative_id !== (int) $d['representative_id'];
        if ($ownerChanged || ! $d['is_active'] || (int) $d['area_id'] !== $customer->area_id || (int) $d['geofence_radius_m'] !== $customer->geofence_radius_m) {
            $open = Visit::where('customer_id', $customer->id)->whereIn('status', ['planned', 'in_progress']);
            if ($customer->representative_id === null && $d['is_active']) {
                $open->where('representative_id', '!=', $d['representative_id']);
            }
            abort_if($open->exists(), 422, 'للعميل محطات مفتوحة. أنهِها أو عدّل خطتها قبل تغيير المسؤول أو الموقع أو إيقاف العميل.');
            if ($ownerChanged || ! $d['is_active']) {
                abort_if(WeeklyPlan::where('is_active', true)->get()->contains(fn ($p) => in_array($customer->id, $p->customer_ids, true)), 422, 'أزل العميل من الجدول الأسبوعي الحالي قبل نقل مسؤوليته أو إيقافه.');
            }
        }
        $candidate = clone $customer;
        $candidate->fill($d);
        app(RepresentativeAccess::class)->assertCustomer((int) $d['representative_id'], $candidate);
        if ($pointChanged) {
            app(CustomerMaintenance::class)->location(new Request(['id' => $customer->id, 'profile_version' => $customer->profile_version, 'latitude' => $d['latitude'], 'longitude' => $d['longitude'], 'reason' => $d['reason']]));
            $customer->refresh();
        }
        unset($d['reason'], $d['profile_version']);
        $customer->fill($d);
        $customer->profile_version++;
        $customer->save();
        app(AuditRecorder::class)->record('customer.updated', $customer, ['before' => $before, 'after' => $customer->toArray(), 'reason' => $request->input('reason')]);

        return $customer;
    }
}
