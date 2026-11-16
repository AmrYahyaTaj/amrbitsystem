<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Enums\VisitStatus;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\CashHandover;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerChange;
use App\Models\DebtFollowup;
use App\Models\Driver;
use App\Models\HandoverRequest;
use App\Models\LocationPoint;
use App\Models\Order;
use App\Models\Product;
use App\Models\RepresentativeVehicleAssignment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Models\WeeklyPlan;
use App\Services\ArchiveAccess;
use App\Services\AuditRecorder;
use App\Services\CashCustody;
use App\Services\CustomerMaintenance;
use App\Services\FleetRegistry;
use App\Services\HandoverWorkflow;
use App\Services\LotLedger;
use App\Services\MemberAdministration;
use App\Services\OperationalAnalytics;
use App\Services\OperationOnce;
use App\Services\RepresentativeAccess;
use App\Services\StockLedger;
use App\Services\TradingWorkflow;
use App\Services\TripPlanning;
use App\Services\VisitWorkflow;
use App\Services\WarehouseAccess;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class OperationsController extends Controller
{
    private function role(): CompanyRole
    {
        return app(CompanyContext::class)->role;
    }

    private function manager(): void
    {
        abort_unless($this->role()->managesOperations(), 403);
    }

    private function finance(): void
    {
        abort_unless(in_array($this->role(), [CompanyRole::Manager, CompanyRole::Accountant, CompanyRole::Representative], true), 403);
    }

    private function own($model): void
    {
        abort_unless($this->role()->managesOperations() || ($this->role() === CompanyRole::Representative && $model->representative_id === auth()->id()), 403);
    }

    private function companyRule(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('company_id', app(CompanyContext::class)->id());
    }

    private function representative(int $id): void
    {
        abort_unless(app(CompanyContext::class)->company->users()->where('users.id', $id)->wherePivot('is_active', true)->wherePivot('role', 'representative')->exists(), 422, 'المندوب ليس عضوًا فعالًا في الشركة.');
    }

    public function sections(): array
    {
        $sections = match ($this->role()) {
            CompanyRole::QualityInspector, CompanyRole::QualityManager => ['quality', 'handovers'],
            CompanyRole::Driver => ['handovers', 'traceability'],
            CompanyRole::WarehouseKeeper => ['warehouses', 'traceability', 'handovers', 'quality'],
            CompanyRole::Production => ['production', 'warehouses', 'traceability', 'handovers', 'quality'],
            CompanyRole::Representative => ['dashboard', 'schedule', 'trips', 'visits', 'customers', 'orders', 'collections', 'settlements', 'receivables', 'handovers', 'traceability'],
            CompanyRole::Accountant => ['dashboard', 'customers', 'orders', 'collections', 'settlements', 'receivables', 'reports', 'audit', 'handovers'],
            CompanyRole::Viewer => ['dashboard', 'tracking', 'trips', 'visits', 'customers', 'team', 'vehicles', 'inventory', 'reports', 'handovers', 'warehouses', 'traceability'],
            default => ['dashboard', 'tracking', 'schedule', 'trips', 'visits', 'customers', 'team', 'vehicles', 'inventory', 'orders', 'collections', 'settlements', 'receivables', 'reports', 'audit', 'settings', 'handovers', 'production', 'warehouses', 'traceability', 'quality', 'roadmap'],
        };
        if (app(ArchiveAccess::class)->mayEnter()) {
            $sections[] = 'archive';
        }

        $sections[] = 'delegations';

        return $sections;
    }

    public function page(Request $request, string $section = 'dashboard')
    {
        if ($section === 'dashboard' && in_array($this->role(), [CompanyRole::QualityInspector, CompanyRole::QualityManager], true)) {
            return redirect('/quality');
        }
        if ($section === 'dashboard' && in_array($this->role(), [CompanyRole::WarehouseKeeper, CompanyRole::Production], true)) {
            return redirect($this->role() === CompanyRole::Production ? '/production' : '/warehouses');
        }
        if ($section === 'dashboard' && in_array($this->role(), [CompanyRole::Driver, CompanyRole::WarehouseKeeper, CompanyRole::Production], true)) {
            return redirect('/handovers');
        }
        $allowed = $this->sections();
        abort_unless(in_array($section, $allowed, true), 403);
        if ($section === 'inventory') {
            return redirect('/warehouses');
        }

        return view('app', ['section' => $section, 'sections' => $allowed]);
    }

    private function stockWorkspace(): array
    {
        $empty = array_fill_keys(['member_directory', 'driver_accounts', 'drivers', 'vehicle_assignments', 'areas', 'customer_changes', 'representative_vehicle_assignments', 'customers', 'weekly_plans', 'team', 'representatives', 'vehicles', 'trips', 'visits', 'points', 'orders', 'debt_customers', 'debt_representatives', 'collections', 'followups', 'cash_balances', 'cash_handovers', 'audit'], []);

        return $empty + [
            'as_of' => now()->toIso8601String(), 'today' => now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString(),
            'company' => app(CompanyContext::class)->company->only(['id', 'name', 'currency', 'timezone', 'industry']), 'role' => $this->role()->value, 'user' => ['id' => auth()->id(), 'name' => auth()->user()->name],
            'my_permissions' => ['can_sell' => false, 'can_collect' => false, 'area_access' => 'selected'],
            'vehicle_types' => Vehicle::TYPES, 'password_min_length' => 12,
            'warehouses' => app(WarehouseAccess::class)->visible()->get(), 'products' => Product::where('is_active', true)->get(),
            'balances' => app(StockLedger::class)->balances()->whereIn('warehouse_id', app(WarehouseAccess::class)->visible()->pluck('id'))->values(), 'movements' => StockMovement::with('warehouse', 'product')->whereIn('warehouse_id', app(WarehouseAccess::class)->visible()->select('id'))->orderByDesc('id')->limit(100)->get(),
            'handover_recipients' => app(HandoverWorkflow::class)->recipients(),
            'pending_handovers_count' => app(HandoverWorkflow::class)->visible()->where('status', 'pending')->count(),
            'metrics' => ['today_visits' => 0, 'completed_today' => 0, 'active_trips' => 0, 'sales_minor' => null, 'paid_minor' => null, 'unpaid_minor' => null, 'overdue_minor' => null],
        ];
    }

    public function data(Request $request)
    {
        abort_if(in_array($this->role(), [CompanyRole::Driver, CompanyRole::QualityInspector, CompanyRole::QualityManager], true), 403);
        if (in_array($this->role(), [CompanyRole::WarehouseKeeper, CompanyRole::Production], true)) {
            return response()->json($this->stockWorkspace());
        }
        $rep = $this->role() === CompanyRole::Representative;
        $financeOnly = $this->role() === CompanyRole::Accountant;
        $visits = Visit::where('status', '!=', 'cancelled')->with('customer', 'representative')->when($rep, fn ($q) => $q->where('representative_id', auth()->id()));
        $trips = Trip::with('representative', 'vehicle', 'warehouse')->withCount(['visits as stops_count' => fn ($q) => $q->where('status', '!=', 'cancelled'), 'visits as completed_stops_count' => fn ($q) => $q->where('status', 'completed')])->when($rep, fn ($q) => $q->where('representative_id', auth()->id()));
        $orders = Order::with('customer', 'representative', 'items.product')->when($rep, fn ($q) => $q->where('orders.representative_id', auth()->id()));
        if ($financeOnly) {
            $orders->with(['customer:id,company_id,name,code,credit_limit_minor']);
        }
        $orderIds = (clone $orders)->select('orders.id');
        $canFinance = in_array($this->role(), [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Accountant, CompanyRole::Representative], true);
        $canOperational = ! $financeOnly;
        $points = LocationPoint::query()->when($rep, fn ($q) => $q->where('user_id', auth()->id()));
        $members = app(CompanyContext::class)->company->users()->withPivot(['area_access', 'can_sell', 'can_collect'])->get(['users.id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->pivot->role, 'is_active' => (bool) $u->pivot->is_active, 'area_access' => $u->pivot->area_access, 'can_sell' => (bool) $u->pivot->can_sell, 'can_collect' => (bool) $u->pivot->can_collect]);
        $companyId = app(CompanyContext::class)->id();
        $memberMeta = DB::table('company_user')->where('company_id', $companyId)->get()->keyBy('user_id');
        $members = $members->map(fn ($m) => $m + ['phone' => $memberMeta[$m['id']]->phone, 'employee_code' => $memberMeta[$m['id']]->employee_code, 'notes' => $memberMeta[$m['id']]->notes, 'operations_version' => $memberMeta[$m['id']]->operations_version]);
        $areaAssignments = DB::table('representative_areas')->where('company_id', $companyId)->get()->groupBy('user_id');
        $members = $members->map(fn ($m) => $m + ['area_ids' => ($areaAssignments[$m['id']] ?? collect())->pluck('area_id')->values()]);
        $directory = $this->role() === CompanyRole::Manager ? app(CompanyContext::class)->company->users()->withPivot(['phone', 'employee_code', 'area_access', 'can_sell', 'can_collect', 'notes'])->get()->map(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'role' => $u->pivot->role,
            'is_active' => (bool) $u->pivot->is_active, 'phone' => $u->pivot->phone, 'employee_code' => $u->pivot->employee_code,
            'area_access' => $u->pivot->area_access, 'area_ids' => ($areaAssignments[$u->id] ?? collect())->pluck('area_id')->values(),
            'can_sell' => (bool) $u->pivot->can_sell, 'can_collect' => (bool) $u->pivot->can_collect, 'notes' => $u->pivot->notes,
            'shared_account' => $u->companies()->where('companies.id', '!=', $companyId)->exists(),
            'operations_version' => $memberMeta[$u->id]->operations_version,
        ]) : [];
        $myAccess = app(RepresentativeAccess::class)->membership(auth()->id());

        return response()->json([
            'as_of' => now()->toIso8601String(), 'today' => now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString(),
            'company' => app(CompanyContext::class)->company->only(['id', 'name', 'currency', 'timezone', 'industry']), 'role' => $this->role()->value, 'user' => ['id' => auth()->id(), 'name' => auth()->user()->name],
            'member_directory' => $directory,
            'handover_recipients' => $this->role()->managesOperations() ? app(HandoverWorkflow::class)->recipients() : [],
            'driver_accounts' => $this->role()->managesOperations() ? app(CompanyContext::class)->company->users()->wherePivotIn('role', ['driver', 'representative'])->wherePivot('is_active', true)->get(['users.id', 'name', 'username']) : [],
            'pending_handovers_count' => app(HandoverWorkflow::class)->visible()->where('status', 'pending')->count(),
            'password_min_length' => app()->environment(['local', 'testing']) && config('fieldflow.demo') ? 6 : 12,
            'my_permissions' => ['can_sell' => (bool) $myAccess->can_sell, 'can_collect' => (bool) $myAccess->can_collect, 'area_access' => $myAccess->area_access],
            'vehicle_types' => Vehicle::TYPES,
            'drivers' => $rep || $financeOnly ? [] : Driver::orderBy('name')->get(),
            'vehicle_assignments' => $rep || $financeOnly ? [] : VehicleAssignment::with('driver')->orderByDesc('assigned_at')->get(),
            'areas' => Area::when($rep && $myAccess->area_access !== 'all', fn ($q) => $q->whereIn('id', DB::table('representative_areas')->where('company_id', $companyId)->where('user_id', auth()->id())->select('area_id')))->orderBy('name')->limit(500)->get(),
            'customer_changes' => $this->role() === CompanyRole::Viewer ? [] : CustomerChange::with(['customer:id,company_id,name', 'submitter:id,name', 'reviewer:id,name'])->when(! $this->role()->managesOperations(), fn ($q) => $q->where('submitted_by', auth()->id()))->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('id')->limit(100)->get(),
            'representative_vehicle_assignments' => $rep || $financeOnly ? [] : RepresentativeVehicleAssignment::with(['vehicle', 'assigner:id,name'])->orderByDesc('id')->get(),
            'customers' => $rep ? Customer::where('representative_id', auth()->id())->with(['area', 'representative:id,name'])->limit(500)->get() : ($financeOnly ? Customer::select('id', 'name', 'code', 'company_id', 'credit_limit_minor', 'representative_id', 'area_id', 'is_active')->with(['area', 'representative:id,name'])->limit(500)->get() : Customer::with(['area', 'representative:id,name'])->limit(500)->get()),
            'weekly_plans' => ($this->role()->managesOperations() || $rep) ? WeeklyPlan::with(['representative:id,name', 'vehicle'])->when($rep, fn ($q) => $q->where('representative_id', auth()->id()))->orderBy('weekday')->get() : [],
            'team' => $rep || $financeOnly ? [] : $members, 'representatives' => ($rep ? $members->where('id', auth()->id()) : $members->where('role', 'representative'))->where('is_active', true)->values(),
            'vehicles' => $rep || $financeOnly ? [] : Vehicle::limit(500)->get(), 'warehouses' => $rep ? Warehouse::whereIn('id', (clone $trips)->select('warehouse_id'))->get() : Warehouse::get(),
            'products' => Product::where('is_active', true)->get(),
            'trips' => $canOperational ? (clone $trips)->orderByDesc('scheduled_for')->orderByDesc('id')->limit(300)->get() : [],
            'visits' => $canOperational ? (clone $visits)->orderByDesc('scheduled_for')->orderBy('sequence')->limit(1000)->get() : [],
            'points' => $canOperational ? $points->where('captured_at', '>=', now()->subDays(2))->orderByDesc('captured_at')->limit(2000)->get()->reverse()->values() : [],
            'balances' => $rep || $financeOnly ? [] : app(StockLedger::class)->balances(),
            'movements' => $rep || $financeOnly ? [] : StockMovement::with('warehouse', 'product')->orderByDesc('id')->limit(100)->get(),
            'orders' => $canFinance ? (clone $orders)->orderByDesc('id')->limit(500)->get() : [],
            'debt_customers' => $canFinance ? (clone $orders)->withoutEagerLoads()->reorder()->where('status', 'fulfilled')->whereColumn('total_minor', '>', 'paid_minor')->join('customers', 'customers.id', '=', 'orders.customer_id')->selectRaw('orders.customer_id, customers.name, COUNT(*) as invoices, SUM(orders.total_minor-orders.paid_minor) as balance_minor')->groupBy('orders.customer_id', 'customers.name')->get() : [],
            'debt_representatives' => $canFinance ? (clone $orders)->withoutEagerLoads()->reorder()->where('status', 'fulfilled')->whereColumn('total_minor', '>', 'paid_minor')->join('users', 'users.id', '=', 'orders.representative_id')->selectRaw('orders.representative_id, users.name, COUNT(DISTINCT orders.customer_id) as customers, SUM(orders.total_minor-orders.paid_minor) as balance_minor')->groupBy('orders.representative_id', 'users.name')->get() : [],
            'collections' => $canFinance ? Collection::whereIn('order_id', $orderIds)->with($financeOnly ? 'order.customer:id,company_id,name,code' : 'order.customer')->orderByDesc('id')->limit(500)->get() : [],
            'followups' => $canFinance ? DebtFollowup::whereIn('order_id', (clone $orderIds))->with(['order.customer:id,company_id,name,code', 'actor:id,name'])->orderByDesc('id')->limit(100)->get() : [],
            'cash_balances' => $canFinance ? app(CashCustody::class)->balances($rep ? auth()->id() : null) : [],
            'cash_handovers' => $canFinance ? CashHandover::with(['collector:id,name', 'reviewer:id,name'])->when($rep, fn ($q) => $q->where('collector_id', auth()->id()))->orderByDesc('id')->limit(200)->get() : [],
            'audit' => in_array($this->role(), [CompanyRole::Manager, CompanyRole::Accountant], true) ? AuditLog::orderByDesc('id')->limit(100)->get() : [],
            'metrics' => [
                'today_visits' => $canOperational ? (clone $visits)->whereDate('scheduled_for', now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString())->count() : 0,
                'completed_today' => $canOperational ? (clone $visits)->whereDate('scheduled_for', now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString())->where('status', 'completed')->count() : 0,
                'active_trips' => $canOperational ? (clone $trips)->where('status', 'active')->count() : 0,
                'sales_minor' => $canFinance ? (clone $orders)->where('status', 'fulfilled')->sum('total_minor') : null,
                'paid_minor' => $canFinance ? (clone $orders)->where('status', 'fulfilled')->sum('paid_minor') : null,
                'unpaid_minor' => $canFinance ? (clone $orders)->where('status', 'fulfilled')->sum(DB::raw('total_minor-paid_minor')) : null,
                'overdue_minor' => $canFinance ? (clone $orders)->where('status', 'fulfilled')->where('due_date', '<', now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString())->sum(DB::raw('total_minor-paid_minor')) : null,
            ],
        ]);
    }

    public function execute(Request $request, string $action, OperationOnce $once, StockLedger $stock, TradingWorkflow $trading, VisitWorkflow $visits, AuditRecorder $audit)
    {
        // Replays must still pass the actor's current role, including after revocation.
        $allowed = match ($action) {
            'area.create','vehicle.create','vehicle.update','driver.create','driver.update','vehicle.assign','vehicle.release','trip.create','trip.update','trip.cancel','product.create','warehouse.create' => $this->role()->managesOperations(),
            'stock.receive' => $this->role()->managesOperations(),
            'stock.transfer' => $this->role()->managesOperations() || in_array($this->role(), [CompanyRole::WarehouseKeeper, CompanyRole::Production, CompanyRole::Representative, CompanyRole::Driver], true),
            'customer.create' => in_array($this->role(), [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Representative, CompanyRole::Accountant], true),
            'member.create','member.update','member.status' => $this->role() === CompanyRole::Manager,
            'customer.credit' => $this->role() === CompanyRole::Manager,
            'cash.request','cash.cancel' => in_array($this->role(), [CompanyRole::Manager, CompanyRole::Accountant, CompanyRole::Representative], true),
            'cash.accept','cash.reject' => in_array($this->role(), [CompanyRole::Manager, CompanyRole::Accountant], true),
            'debt.followup' => in_array($this->role(), [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Accountant, CompanyRole::Representative], true),
            'trip.start','trip.close' => $this->role()->managesOperations() || $this->role() === CompanyRole::Representative,
            'visit.checkin','visit.finish','location.record' => $this->role() === CompanyRole::Representative,
            'order.create','order.fulfill' => in_array($this->role(), [CompanyRole::Manager, CompanyRole::Representative], true),
            'collection.create' => in_array($this->role(), [CompanyRole::Manager, CompanyRole::Accountant, CompanyRole::Representative], true),
            default => false,
        };
        abort_unless($allowed, 403);
        if ($this->role() === CompanyRole::Representative) {
            if (in_array($action, ['order.create', 'order.fulfill'], true)) {
                app(RepresentativeAccess::class)->assertCapability(auth()->id(), 'can_sell');
            }
            if ($action === 'collection.create') {
                app(RepresentativeAccess::class)->assertCapability(auth()->id(), 'can_collect');
            }
        }
        $request->validate(['request_id' => ['required', 'uuid']]);
        $payload = $request->except('request_id', '_token');

        return response()->json($once->run($action, $request->input('request_id'), $payload, function () use ($request, $action, $stock, $trading, $visits, $audit) {
            switch ($action) {
                case 'member.create': case 'member.update':
                    $model = app(MemberAdministration::class)->save($request, $action === 'member.create');
                    break;
                case 'member.status':
                    $model = app(MemberAdministration::class)->status($request);
                    break;
                case 'driver.create': case 'driver.update':
                    $model = app(FleetRegistry::class)->driver($request, $action === 'driver.create');
                    break;
                case 'vehicle.assign':
                    $model = app(FleetRegistry::class)->assign($request);
                    break;
                case 'vehicle.release':
                    $model = app(FleetRegistry::class)->release($request);
                    break;
                case 'area.create':
                    $this->manager();
                    $d = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('areas', 'name')->where('company_id', app(CompanyContext::class)->id())], 'city' => 'required|string|max:100']);
                    $model = Area::create($d);
                    $audit->record('area.created', $model);
                    break;
                case 'customer.create':
                    if (! $this->role()->managesOperations()) {
                        $model = app(CustomerMaintenance::class)->registration($request);

                        return ['id' => $model->id, 'message' => 'تم تسجيل العميل وموقعه كطلب جديد بانتظار اعتماد المدير أو المشرف.'];
                    }
                    $this->manager();
                    $d = $request->validate(['representative_id' => 'required|integer', 'area_id' => ['required', $this->companyRule('areas')], 'name' => 'required|string|max:150', 'code' => ['required', 'string', 'max:40', Rule::unique('customers', 'code')->where('company_id', app(CompanyContext::class)->id())], 'address' => 'required|string|max:500', 'phone' => 'nullable|string|max:30', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'geofence_radius_m' => 'required|integer|between:25,500']);
                    $model = Customer::create($d);
                    if ($request->filled('representative_id')) {
                        $request->validate(['representative_id' => 'required|integer', 'service_notes' => 'nullable|string|max:2000']);
                        $model->representative_id = (int) $request->input('representative_id');
                        app(RepresentativeAccess::class)->assertCustomer($model->representative_id, $model);
                        $model->service_notes = $request->input('service_notes');
                        $model->save();
                    }
                    $audit->record('customer.created', $model);
                    break;
                case 'vehicle.create': case 'vehicle.update':
                    $model = app(FleetRegistry::class)->vehicle($request, $action === 'vehicle.create');
                    break;
                case 'trip.create': case 'trip.update':
                    $model = app(TripPlanning::class)->save($request, $action === 'trip.create');
                    break;
                case 'trip.cancel':
                    $model = app(TripPlanning::class)->cancel($request);
                    break;
                case 'trip.start': case 'trip.close':
                    $d = $request->validate(['id' => 'required|integer']);
                    $model = Trip::lockForUpdate()->findOrFail($d['id']);
                    $this->own($model);
                    if ($action === 'trip.start') {
                        abort_if(DB::table('handover_locks')->where('company_id', app(CompanyContext::class)->id())->where('resource_key', 'vehicle:'.$model->vehicle_id)->exists(), 422, 'للمركبة طلب تسليم أو إرجاع معلق؛ يجب إقرار الطرف الآخر أولًا.');
                        abort_unless($model->status === 'planned' && $model->scheduled_for->toDateString() === now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString(), 422, 'الرحلة لا يمكن بدؤها الآن.');
                        abort_if(Trip::where('status', 'active')->where(fn ($q) => $q->where('representative_id', $model->representative_id)->orWhere('vehicle_id', $model->vehicle_id))->exists(), 422, 'المندوب أو السيارة في رحلة نشطة.');
                        abort_unless(Vehicle::findOrFail($model->vehicle_id)->status === 'available', 422, 'السيارة غير متاحة.');
                        abort_if(RepresentativeVehicleAssignment::where('vehicle_id', $model->vehicle_id)->whereNull('returned_at')->where('representative_id', '!=', $model->representative_id)->exists(), 422, 'المركبة مخصصة لمندوب آخر. راجع التخصيص أو خطة الرحلة.');
                        $this->representative($model->representative_id);
                        abort_unless($model->visits()->where('status', 'planned')->exists(), 422, 'الرحلة لا تحتوي محطات مجدولة.');
                        foreach ($model->visits()->where('status', 'planned')->get() as $scheduledVisit) {
                            app(RepresentativeAccess::class)->assertCustomer($model->representative_id, Customer::findOrFail($scheduledVisit->customer_id));
                        }
                        if ($model->driver_id) {
                            $driver = Driver::findOrFail($model->driver_id);
                            abort_unless($driver->status === 'active' && $driver->license_expires_on && $driver->license_expires_on->toDateString() >= now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString() && VehicleAssignment::where('vehicle_id', $model->vehicle_id)->where('driver_id', $driver->id)->whereNull('returned_at')->exists(), 422, 'راجع إسناد السائق وصلاحية رخصته قبل بدء الرحلة.');
                        }
                        $model->status = 'active';
                        $model->started_at = now();
                        $model->active_representative_id = $model->representative_id;
                        $model->active_vehicle_id = $model->vehicle_id;
                    } else {
                        abort_unless($model->status === 'active', 422, 'الرحلة غير نشطة.');
                        abort_if($model->visits()->whereIn('status', ['planned', 'in_progress'])->exists(), 422, 'عالج الزيارات المفتوحة قبل إغلاق الرحلة.');
                        $model->status = 'closed';
                        $model->closed_at = now();
                        $model->active_representative_id = null;
                        $model->active_vehicle_id = null;
                    }
                    $model->save();
                    $audit->record($action, $model);
                    break;
                case 'visit.checkin': case 'visit.finish':
                    $d = $request->validate(['id' => 'required|integer']);
                    $model = Visit::findOrFail($d['id']);
                    abort_unless($this->role() === CompanyRole::Representative && $model->representative_id === auth()->id(), 403);
                    $trip = Trip::findOrFail($model->trip_id);
                    abort_unless($trip->status === 'active', 422, 'ابدأ الرحلة أولًا.');
                    if ($trip->weekly_plan_id) {
                        $firstOpen = $trip->visits()->whereIn('status', ['planned', 'in_progress'])->orderBy('sequence')->orderBy('id')->value('id');
                        abort_unless($firstOpen === $model->id, 422, 'أنهِ المحطة الحالية أو سجل سبب تعذرها قبل الانتقال إلى التالية.');
                    }
                    if ($action === 'visit.checkin') {
                        app(RepresentativeAccess::class)->assertCustomer($model->representative_id, Customer::findOrFail($model->customer_id));
                        $d = $request->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'accuracy_m' => 'required|numeric|between:0,100', 'captured_at' => 'required|date']);
                        $visits->checkIn($model, $d);
                    } else {
                        $d = $request->validate(['status' => 'required|in:completed,failed,skipped', 'note' => 'nullable|string|max:1000', 'reason_code' => 'nullable|in:no_answer,called_no_answer,closed,refused,no_time,vehicle_issue,no_sale,other']);
                        if ($d['status'] !== 'completed') {
                            $request->validate(['note' => 'required|string|min:3|max:1000']);
                        }
                        if ($trip->weekly_plan_id && $d['status'] !== 'completed') {
                            $request->validate(['reason_code' => 'required']);
                        }
                        if ($d['status'] === 'completed' && $trip->weekly_plan_id && ! Order::where('trip_id', $trip->id)->where('customer_id', $model->customer_id)->where('status', 'fulfilled')->exists()) {
                            $request->validate(['reason_code' => 'required|in:no_sale,other', 'note' => 'required|string|min:3|max:1000']);
                        }
                        $visits->finish($model, VisitStatus::from($d['status']), $d['note'] ?? null, $d['reason_code'] ?? null);
                    }
                    break;
                case 'location.record':
                    abort_unless($this->role() === CompanyRole::Representative, 403);
                    $d = $request->validate(['trip_id' => 'required|integer', 'event_id' => 'required|uuid', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'accuracy_m' => 'required|integer|between:0,100', 'captured_at' => 'required|date', 'speed_kmh' => 'nullable|numeric|between:0,180']);
                    $trip = Trip::lockForUpdate()->findOrFail($d['trip_id']);
                    $this->own($trip);
                    abort_unless($trip->status === 'active', 422, 'التتبع مرتبط برحلة عمل نشطة.');
                    $captured = CarbonImmutable::parse($d['captured_at']);
                    $age = now()->timestamp - $captured->timestamp;
                    abort_if($age < -15 || $age > 120, 422, 'نقطة الموقع قديمة أو وقتها غير صالح.');
                    $previous = LocationPoint::where('trip_id', $trip->id)->orderByDesc('captured_at')->first();
                    if ($previous) {
                        $seconds = $captured->timestamp - $previous->captured_at->timestamp;
                        abort_if($seconds <= 0, 422, 'النقطة أقدم من آخر نقطة مستقبلة.');
                        $distance = $visits->distance((float) $previous->latitude, (float) $previous->longitude, (float) $d['latitude'], (float) $d['longitude']);
                        abort_if($distance > 150 + ($seconds * 50) + $d['accuracy_m'] + $previous->accuracy_m, 422, 'قفزة غير منطقية في الموقع.');
                    }
                    abort_if(LocationPoint::where('event_id', $d['event_id'])->exists(), 409, 'معرف نقطة الموقع مكرر.');
                    $model = LocationPoint::create($d + ['user_id' => auth()->id(), 'received_at' => now(), 'source' => $request->is('api/*') ? 'device' : 'web', 'quality' => 'accepted']);
                    break;
                case 'customer.credit':
                    abort_unless($this->role() === CompanyRole::Manager, 403);
                    $d = $request->validate(['id' => 'required|integer', 'credit_limit_minor' => 'present|nullable|integer|between:0,100000000', 'reason' => 'required|string|min:3|max:300']);
                    $model = Customer::lockForUpdate()->findOrFail($d['id']);
                    $before = $model->credit_limit_minor;
                    $model->credit_limit_minor = $d['credit_limit_minor'];
                    $model->save();
                    $audit->record('customer.credit_limit_changed', $model, ['before' => $before, 'after' => $model->credit_limit_minor, 'reason' => $d['reason']]);
                    break;
                case 'product.create':
                    $this->manager();
                    $d = $request->validate(['name' => 'required|string|max:150', 'sku' => ['required', 'string', 'max:40', Rule::unique('products', 'sku')->where('company_id', app(CompanyContext::class)->id())], 'unit' => 'required|string|max:30', 'price_minor' => 'required|integer|between:1,10000000', 'reorder_level' => 'required|integer|between:0,100000']);
                    $model = Product::create($d);
                    $audit->record('product.created', $model);
                    break;
                case 'warehouse.create':
                    $this->manager();
                    $d = $request->validate(['name' => 'required|string|max:150']);
                    $model = Warehouse::create($d + ['type' => 'warehouse']);
                    $audit->record('warehouse.created', $model);
                    break;
                case 'stock.receive':
                    $today = app(LotLedger::class)->today();
                    $d = $request->validate(['warehouse_id' => ['required', $this->companyRule('warehouses')], 'product_id' => ['required', $this->companyRule('products')], 'quantity' => 'required|integer|between:1,100000', 'reference' => 'required|string|min:3|max:100', 'supplier' => 'nullable|string|max:150', 'lot_code' => ['nullable', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]{1,79}\z/', Rule::unique('stock_lots', 'code')->where('company_id', app(CompanyContext::class)->id())], 'manufactured_on' => 'nullable|date_format:Y-m-d|before_or_equal:'.$today, 'expires_on' => 'nullable|date_format:Y-m-d|after_or_equal:'.$today]);
                    abort_if(! empty($d['manufactured_on']) && ! empty($d['expires_on']) && $d['expires_on'] < $d['manufactured_on'], 422, 'انتهاء الصلاحية يسبق الإنتاج.');
                    $model = Warehouse::lockForUpdate()->findOrFail($d['warehouse_id']);
                    abort_unless($model->type === 'warehouse' && $model->is_active, 422, 'الاستلام الخارجي في مخزن فعال؛ استخدم التسليم الثنائي للحركة الداخلية.');
                    if ($model->capacity_units) {
                        $incoming = HandoverRequest::where('to_warehouse_id', $model->id)->where('status', 'pending')->sum('reserved_units');
                        abort_if(StockBalance::where('warehouse_id', $model->id)->sum('quantity') + $incoming + $d['quantity'] > $model->capacity_units, 422, 'الرصيد والطلبات الواردة يتجاوزان سعة المخزن.');
                    }
                    $product = Product::where('is_active', true)->findOrFail($d['product_id']);
                    $meta = array_filter(['code' => $d['lot_code'] ?? null, 'manufactured_on' => $d['manufactured_on'] ?? null, 'expires_on' => $d['expires_on'] ?? null, 'notes' => trim(($d['supplier'] ?? '').' • '.$d['reference'])], fn ($v) => $v !== null);
                    $lot = app(LotLedger::class)->newLot($model, $product, $meta);
                    $stock->move($model, $product, $d['quantity'], 'receipt', $d['reference'], null, [['lot_id' => $lot->id, 'quantity' => $d['quantity']]]);
                    $audit->record('stock.received', $model, ['product_id' => $d['product_id'], 'quantity' => $d['quantity'], 'reference' => $d['reference'], 'lot_id' => $lot->id]);
                    break;
                case 'stock.transfer':
                    $model = app(HandoverWorkflow::class)->stock($request);
                    break;
                case 'order.create':
                    $this->finance();
                    abort_if($this->role() === CompanyRole::Accountant, 403);
                    $d = $request->validate(['customer_id' => ['required', $this->companyRule('customers')], 'warehouse_id' => ['required', $this->companyRule('warehouses')], 'representative_id' => 'required|integer', 'trip_id' => ['nullable', $this->companyRule('trips')], 'payment_terms' => 'required|in:cash,partial,credit', 'due_date' => 'nullable|required_unless:payment_terms,cash|date_format:Y-m-d|after_or_equal:'.now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString(), 'initial_paid_minor' => 'nullable|required_if:payment_terms,partial|integer|between:1,100000000', 'items' => 'required|array|min:1|max:50', 'items.*.product_id' => ['required', 'distinct', $this->companyRule('products')], 'items.*.quantity' => 'required|integer|between:1,10000']);
                    $this->representative((int) $d['representative_id']);
                    app(RepresentativeAccess::class)->assertCapability((int) $d['representative_id'], 'can_sell');
                    app(RepresentativeAccess::class)->assertCustomer((int) $d['representative_id'], Customer::findOrFail($d['customer_id']));
                    if ($this->role() === CompanyRole::Representative) {
                        abort_unless((int) $d['representative_id'] === auth()->id() && ! empty($d['trip_id']), 403);
                        if ($d['payment_terms'] !== 'credit') {
                            app(RepresentativeAccess::class)->assertCapability(auth()->id(), 'can_collect');
                        }
                    }
                    if (! empty($d['trip_id'])) {
                        $trip = Trip::findOrFail($d['trip_id']);
                        abort_unless($trip->representative_id === (int) $d['representative_id'] && $trip->warehouse_id === (int) $d['warehouse_id'] && $trip->status === 'active' && $trip->visits()->where('customer_id', $d['customer_id'])->exists(), 422, 'الطلب لا يطابق عميل وعهدة الرحلة النشطة.');
                    }
                    abort_unless(Customer::findOrFail($d['customer_id'])->is_active, 422);
                    $model = $trading->create($d);
                    break;
                case 'order.fulfill':
                    $this->finance();
                    abort_if($this->role() === CompanyRole::Accountant, 403);
                    $d = $request->validate(['id' => 'required|integer']);
                    $model = Order::findOrFail($d['id']);
                    $this->own($model);
                    app(RepresentativeAccess::class)->assertCapability($model->representative_id, 'can_sell');
                    app(RepresentativeAccess::class)->assertCustomer($model->representative_id, Customer::findOrFail($model->customer_id));
                    if ($this->role() === CompanyRole::Representative) {
                        abort_unless($model->trip_id && Trip::findOrFail($model->trip_id)->status === 'active', 422, 'الرحلة ليست نشطة.');
                        if ($model->initial_paid_minor > 0) {
                            app(RepresentativeAccess::class)->assertCapability(auth()->id(), 'can_collect');
                        }
                    }
                    $trading->fulfill($model);
                    break;
                case 'debt.followup':
                    $d = $request->validate(['order_id' => 'required|integer', 'outcome' => 'required|in:contacted,no_answer,promised', 'note' => 'required|string|min:3|max:1000', 'next_followup_date' => 'required|date_format:Y-m-d|after_or_equal:'.now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString(), 'promised_minor' => 'nullable|required_if:outcome,promised|integer|between:1,100000000']);
                    $order = Order::lockForUpdate()->findOrFail($d['order_id']);
                    if ($this->role() === CompanyRole::Representative) {
                        abort_unless($order->representative_id === auth()->id(), 403);
                    }
                    abort_unless($order->status === 'fulfilled' && $order->total_minor > $order->paid_minor, 422, 'لا توجد مديونية مفتوحة لهذه الفاتورة.');
                    if ($d['outcome'] === 'promised') {
                        abort_if($d['promised_minor'] > $order->total_minor - $order->paid_minor, 422, 'الوعد يتجاوز المتبقي على الفاتورة.');
                    } else {
                        $d['promised_minor'] = null;
                    }
                    $model = DebtFollowup::create($d + ['actor_id' => auth()->id(), 'created_at' => now()]);
                    $audit->record('debt.followup_recorded', $model, ['order_id' => $order->id, 'outcome' => $d['outcome'], 'next_followup_date' => $d['next_followup_date']]);
                    break;
                case 'cash.request':
                    $d = $request->validate(['collector_id' => 'required|integer', 'amount_minor' => 'required|integer|between:1,100000000', 'reference' => 'required|string|min:3|max:100', 'note' => 'nullable|string|max:500']);
                    if ($this->role() === CompanyRole::Representative) {
                        abort_unless((int) $d['collector_id'] === auth()->id(), 403);
                    }
                    $model = app(CashCustody::class)->request((int) $d['collector_id'], (int) $d['amount_minor'], $d['reference'], $d['note'] ?? null);
                    break;
                case 'cash.accept':case 'cash.reject':
                    $d = $request->validate(['id' => 'required|integer', 'note' => 'required|string|min:3|max:500']);
                    $model = CashHandover::findOrFail($d['id']);
                    app(CashCustody::class)->review($model, $action === 'cash.accept', $d['note']);
                    break;
                case 'cash.cancel':
                    $d = $request->validate(['id' => 'required|integer', 'note' => 'required|string|min:3|max:500']);
                    $model = CashHandover::findOrFail($d['id']);
                    app(CashCustody::class)->cancel($model, $d['note']);
                    break;
                case 'collection.create':
                    $this->finance();
                    $d = $request->validate(['order_id' => 'required|integer', 'amount_minor' => 'required|integer|between:1,100000000', 'method' => 'required|in:cash,bank,card']);
                    $order = Order::findOrFail($d['order_id']);
                    if ($this->role() === CompanyRole::Representative) {
                        abort_unless($order->representative_id === auth()->id(), 403);
                    }
                    $model = $trading->collect($order, $d['amount_minor'], $d['method']);
                    break;
                default: abort(404);
            }

            if ($model instanceof HandoverRequest) {
                return ['ok' => true, 'id' => $model->id, 'handover_id' => $model->id, 'message' => 'أُرسل الطلب للمستلم. لا يثبت الاستلام أو نقل العهدة حتى يوافق عليه من حسابه.'];
            }

            return ['ok' => true, 'id' => $model->id, 'message' => 'تم حفظ العملية بنجاح.'];
        }));
    }

    private function reportFilters(Request $request): array
    {
        $d = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'representative_id' => 'nullable|integer', 'area_id' => ['nullable', $this->companyRule('areas')]]);
        abort_if(Carbon::parse($d['from'])->diffInDays(Carbon::parse($d['to'])) > 90, 422, 'اختر فترة لا تتجاوز 91 يومًا.');
        if (! empty($d['representative_id'])) {
            abort_unless(app(CompanyContext::class)->company->users()->where('users.id', $d['representative_id'])->exists(), 422, 'المندوب خارج الشركة.');
        }

        return $d;
    }

    public function analytics(Request $request, OperationalAnalytics $analytics)
    {
        abort_unless(in_array($this->role(), [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Accountant, CompanyRole::Viewer], true), 403);

        return response()->json($analytics->build($this->reportFilters($request)));
    }

    public function report(Request $request)
    {
        abort_unless(in_array($this->role(), [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Accountant, CompanyRole::Viewer], true), 403);
        $d = $this->reportFilters($request);
        $financial = $request->query('type') === 'sales';
        if ($financial) {
            abort_unless(in_array($this->role(), [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Accountant], true), 403);
        } else {
            abort_if($this->role() === CompanyRole::Accountant, 403);
        }
        $timezone = app(CompanyContext::class)->company->timezone;
        $from = CarbonImmutable::parse($d['from'], $timezone)->startOfDay()->utc();
        $until = CarbonImmutable::parse($d['to'], $timezone)->startOfDay()->addDay()->utc();
        $analytics = app(OperationalAnalytics::class);
        $records = $financial ? $analytics->orders($d)->with('customer', 'representative')->where('status', 'fulfilled')->where('created_at', '>=', $from)->where('created_at', '<', $until)->orderBy('id') : $analytics->visits($d)->with('customer', 'representative')->whereDate('scheduled_for', '>=', $d['from'])->whereDate('scheduled_for', '<=', $d['to'])->orderBy('id');

        $company = app(CompanyContext::class)->company;
        $role = $this->role();

        return response()->streamDownload(function () use ($records, $financial, $company, $role) {
            // Streaming happens after middleware returns. Restore only this verified tenant.
            $context = app(CompanyContext::class);
            $context->set($company, $role);
            try {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $financial ? ['الطلب', 'العميل', 'المندوب', 'الإجمالي هللة', 'المدفوع الحالي على الطلب هللة', 'المتبقي الحالي هللة'] : ['الزيارة', 'التاريخ', 'العميل', 'المندوب', 'الحالة', 'إثبات الموقع', 'المسافة متر'], escape: '');
                foreach ($records->lazyById(500) as $r) {
                    $row = $financial ? [$r->code, $r->customer->name, $r->representative->name, $r->total_minor, $r->paid_minor, $r->total_minor - $r->paid_minor] : [$r->id, $r->scheduled_for->toDateString(), $r->customer->name, $r->representative->name, $r->status->value, $r->location_verified ? 'نعم' : 'لا', $r->distance_m];
                    $row = array_map(fn ($v) => is_string($v) && preg_match('/^[\s]*[=+\-@]/u', $v) ? "'".$v : $v, $row);
                    fputcsv($out, $row, escape: '');
                }
                fclose($out);
            } finally {
                $context->clear();
            }
        }, $financial ? 'amrbit-sales.csv' : 'amrbit-visits.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
