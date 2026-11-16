<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FleetRegistry
{
    public function vehicle(Request $request, bool $creating): Vehicle
    {
        $model = $creating ? new Vehicle : Vehicle::lockForUpdate()->findOrFail($request->integer('id'));
        $d = $request->validate([
            'name' => 'required|string|max:150',
            'plate' => ['required', 'string', 'max:40', Rule::unique('vehicles', 'plate')->where('company_id', app(CompanyContext::class)->id())->ignore($model->id)],
            'vehicle_type' => ['required', Rule::in(array_keys(Vehicle::TYPES))],
            'status' => 'required|in:available,maintenance,inactive',
            'capacity_units' => 'required|integer|between:1,100000',
            'make' => 'nullable|string|max:80', 'model' => 'nullable|string|max:80',
            'model_year' => 'nullable|integer|between:1950,'.(now()->year + 1),
            'vin' => 'nullable|string|max:80', 'ownership' => 'required|in:owned,leased,rented',
            'payload_kg' => 'nullable|integer|between:1,200000',
            'volume_m3' => 'nullable|numeric|between:0.01,1000',
            'odometer_km' => 'nullable|integer|between:0,9999999',
            'registration_expires_on' => 'nullable|date_format:Y-m-d',
            'insurance_expires_on' => 'nullable|date_format:Y-m-d',
            'notes' => 'nullable|string|max:1000',
            'reason' => $creating ? 'nullable|string|max:500' : 'required|string|min:3|max:500',
        ]);
        if (! $creating) {
            abort_if(\Illuminate\Support\Facades\DB::table('handover_locks')->where('company_id', app(CompanyContext::class)->id())->where('resource_key', 'vehicle:'.$model->id)->exists(), 422, 'يوجد طلب تسليم معلق؛ ألغِ الطلب قبل تغيير بيانات المركبة المذكورة فيه.');
            abort_if($d['status'] !== $model->status && Trip::where('vehicle_id', $model->id)->where('status', 'active')->exists(), 422, 'السيارة في رحلة نشطة؛ لا يمكن تغيير جاهزيتها قبل معالجة الرحلة.');
            abort_if(isset($d['odometer_km'], $model->odometer_km) && $d['odometer_km'] < $model->odometer_km, 422, 'قراءة العداد لا يمكن أن تقل عن القراءة السابقة.');
        }
        $before = $model->exists ? $model->only($model->getFillable()) : null;
        $model->fill($d)->save();
        if ($creating) {
            Warehouse::create(['name' => 'عهدة '.$model->name, 'type' => 'vehicle', 'vehicle_id' => $model->id]);
        }
        app(AuditRecorder::class)->record($creating ? 'vehicle.created' : 'vehicle.updated', $model, ['before' => $before, 'after' => $model->only($model->getFillable()), 'reason' => $d['reason'] ?? null]);

        return $model;
    }

    public function driver(Request $request, bool $creating): Driver
    {
        $model = $creating ? new Driver : Driver::lockForUpdate()->findOrFail($request->integer('id'));
        $d = $request->validate([
            'name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30',
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', app(CompanyContext::class)->id())->where('is_active', true)->whereIn('role', ['driver', 'representative']), Rule::unique('drivers', 'user_id')->where('company_id', app(CompanyContext::class)->id())->ignore($model->id)],
            'employee_code' => ['required', 'string', 'max:40', Rule::unique('drivers', 'employee_code')->where('company_id', app(CompanyContext::class)->id())->ignore($model->id)],
            'license_number' => 'nullable|string|max:80', 'license_expires_on' => 'nullable|date_format:Y-m-d',
            'status' => 'required|in:active,inactive', 'notes' => 'nullable|string|max:1000',
            'reason' => $creating ? 'nullable|string|max:500' : 'required|string|min:3|max:500',
        ]);
        if (! $creating) {
            abort_if(\Illuminate\Support\Facades\DB::table('handover_locks')->where('company_id', app(CompanyContext::class)->id())->where('resource_key', 'driver:'.$model->id)->exists(), 422, 'للسائق طلب تسليم أو إرجاع معلق؛ عالجه قبل تعديل ملفه.');
            abort_if($model->user_id !== null && array_key_exists('user_id', $d) && (int) $model->user_id !== (int) $d['user_id'] && VehicleAssignment::where('driver_id', $model->id)->whereNull('returned_at')->exists(), 422, 'استرجع عهدة المركبة قبل تغيير حساب السائق المرتبط.');
        }
        abort_if(! $creating && $d['status'] === 'inactive' && VehicleAssignment::where('driver_id', $model->id)->whereNull('returned_at')->exists(), 422, 'استرجع عهدة السيارة من السائق قبل تعطيله.');
        $before = $model->exists ? $model->only($model->getFillable()) : null;
        $model->fill($d)->save();
        app(AuditRecorder::class)->record($creating ? 'driver.created' : 'driver.updated', $model, ['before' => $before, 'after' => $d]);

        return $model;
    }

    public function assign(Request $request): \App\Models\HandoverRequest
    {
        return app(HandoverWorkflow::class)->vehicle($request);
    }

    public function release(Request $request): \App\Models\HandoverRequest
    {
        return app(HandoverWorkflow::class)->vehicleReturn($request);
    }
}
