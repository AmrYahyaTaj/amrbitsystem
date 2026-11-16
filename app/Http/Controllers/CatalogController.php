<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\AuditRecorder;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function areas()
    {
        Gate::authorize('view-directory');

        return view('catalog.areas', ['areas' => Area::orderBy('name')->paginate(20)]);
    }

    public function storeArea(Request $request, CompanyContext $context, AuditRecorder $audit)
    {
        Gate::authorize('manage-operations');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('areas')->where('company_id', $context->id())],
            'city' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($data, $audit) {
            $audit->record('area.created', Area::create($data));
        });

        return back()->with('success', 'تمت إضافة المنطقة.');
    }

    public function customers(Request $request)
    {
        Gate::authorize('view-directory');
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $query = Customer::with('area')->orderByDesc('id');
        if (! empty($data['search'])) {
            $query->where(function ($q) use ($data) {
                $q->where('name', 'like', '%'.$data['search'].'%')->orWhere('code', 'like', '%'.$data['search'].'%');
            });
        }

        return view('catalog.customers', ['customers' => $query->paginate(15)->withQueryString(), 'areas' => Area::orderBy('name')->get()]);
    }

    public function storeCustomer(Request $request, CompanyContext $context, AuditRecorder $audit)
    {
        Gate::authorize('manage-operations');
        $data = $request->validate([
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('company_id', $context->id())],
            'code' => ['required', 'string', 'max:40', Rule::unique('customers')->where('company_id', $context->id())],
            'name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['required', 'integer', 'between:25,1000'],
        ]);
        DB::transaction(function () use ($data, $audit) {
            $audit->record('customer.created', Customer::create($data));
        });

        return back()->with('success', 'تمت إضافة العميل.');
    }

    public function vehicles()
    {
        Gate::authorize('view-directory');

        return view('catalog.vehicles', ['vehicles' => Vehicle::orderByDesc('id')->paginate(20)]);
    }

    public function storeVehicle(Request $request, CompanyContext $context, AuditRecorder $audit)
    {
        Gate::authorize('manage-operations');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plate' => ['required', 'string', 'max:40', Rule::unique('vehicles')->where('company_id', $context->id())],
            'status' => ['required', Rule::in(['available', 'maintenance', 'inactive'])],
        ]);
        DB::transaction(function () use ($data, $audit) {
            $audit->record('vehicle.created', Vehicle::create($data));
        });

        return back()->with('success', 'تمت إضافة السيارة.');
    }

    public function team(CompanyContext $context)
    {
        Gate::authorize('manage-operations');

        return view('catalog.team', ['members' => $context->company->users()->orderBy('name')->get()]);
    }

    public function audit()
    {
        Gate::authorize('manage-operations');

        return view('catalog.audit', ['logs' => AuditLog::orderByDesc('id')->paginate(30)]);
    }
}
