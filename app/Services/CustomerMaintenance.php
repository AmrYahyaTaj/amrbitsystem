<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\Customer;
use App\Models\CustomerChange;
use App\Models\Trip;
use App\Models\Visit;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerMaintenance
{
    private function own(Customer $customer): void
    {
        $role = app(CompanyContext::class)->role;
        abort_unless($role->managesOperations() || ($role === CompanyRole::Representative && (int) $customer->representative_id === auth()->id()), 403);
    }

    public function contact(Request $request): Customer
    {
        $customer = Customer::lockForUpdate()->findOrFail($request->validate(['id' => 'required|integer'])['id']);
        $this->own($customer);
        $d = $request->validate(['profile_version' => 'required|integer|min:1', 'name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'address' => 'required|string|max:500', 'service_notes' => 'nullable|string|max:2000', 'reason' => 'required|string|min:3|max:500']);
        abort_unless((int) $customer->profile_version === (int) $d['profile_version'], 409, 'بيانات العميل تغيرت. حدّث الملف أولًا.');
        $before = $customer->only(['name', 'phone', 'address', 'service_notes']);
        $customer->fill(array_intersect_key($d, array_flip(['name', 'phone', 'address', 'service_notes'])));
        $customer->profile_version++;
        $customer->save();
        app(AuditRecorder::class)->record('customer.contact_updated', $customer, ['before' => $before, 'after' => $customer->only(array_keys($before)), 'reason' => $d['reason']]);

        return $customer;
    }

    public function location(Request $request): Customer|CustomerChange
    {
        $customer = Customer::lockForUpdate()->findOrFail($request->validate(['id' => 'required|integer'])['id']);
        $this->own($customer);
        $d = $request->validate(['profile_version' => 'required|integer|min:1', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'reason' => 'required|string|min:3|max:500']);
        abort_unless((int) $customer->profile_version === (int) $d['profile_version'], 409, 'ملف العميل تغير. حدّث الصفحة قبل تعديل الموقع.');
        if (! app(CompanyContext::class)->role->managesOperations()) {
            abort_if(CustomerChange::where('customer_id', $customer->id)->where('kind', 'location')->where('status', 'pending')->exists(), 422, 'يوجد تصحيح موقع بانتظار المراجعة لهذا العميل.');
            $change = CustomerChange::create(['customer_id' => $customer->id, 'submitted_by' => auth()->id(), 'kind' => 'location', 'payload' => $d + ['owner_id' => $customer->representative_id]]);
            app(AuditRecorder::class)->record('customer.location_requested', $change, ['customer_id' => $customer->id, 'reason' => $d['reason']]);

            return $change;
        }

        return $this->applyLocation($customer, $d);
    }

    private function applyLocation(Customer $customer, array $d): Customer
    {
        abort_unless((int) $customer->profile_version === (int) $d['profile_version'], 409, 'ملف العميل تغير منذ طلب التصحيح. حدّث بيانات الطلب وأرسله من جديد.');
        abort_if(Visit::where('customer_id', $customer->id)->where('status', 'in_progress')->exists(), 422, 'للعميل زيارة قيد التنفيذ. أنهِها قبل تغيير نقطة إثبات الحضور.');
        $before = $customer->only(['latitude', 'longitude']);
        $customer->latitude = $d['latitude'];
        $customer->longitude = $d['longitude'];
        $customer->profile_version++;
        $customer->save();
        $trips = Trip::whereIn('id', Visit::where('customer_id', $customer->id)->where('status', 'planned')->select('trip_id'))->whereIn('status', ['planned', 'active'])->lockForUpdate()->get();
        foreach ($trips as $trip) {
            $trip->route_snapshot = null;
            $trip->plan_version++;
            $trip->save();
        }
        app(AuditRecorder::class)->record('customer.location_updated', $customer, ['before' => $before, 'after' => $customer->only(['latitude', 'longitude']), 'reason' => $d['reason'], 'affected_trip_ids' => $trips->pluck('id')]);

        return $customer;
    }

    public function registration(Request $request): CustomerChange
    {
        $role = app(CompanyContext::class)->role;
        abort_unless(in_array($role, [CompanyRole::Representative, CompanyRole::Accountant], true), 403);
        if ($role === CompanyRole::Representative) {
            $request->merge(['representative_id' => auth()->id()]);
        }
        if (! $request->filled('code')) {
            $request->merge(['code' => 'C-'.Str::upper(Str::random(10))]);
        }
        $d = $this->customerData($request);
        $candidate = new Customer($d);
        app(RepresentativeAccess::class)->assertCustomer((int) $d['representative_id'], $candidate);
        $change = CustomerChange::create(['submitted_by' => auth()->id(), 'kind' => 'new_customer', 'payload' => $d]);
        app(AuditRecorder::class)->record('customer.registration_requested', $change, ['name' => $d['name']]);

        return $change;
    }

    private function customerData(Request $request): array
    {
        $companyId = app(CompanyContext::class)->id();

        return $request->validate(['name' => 'required|string|max:150', 'code' => ['required', 'string', 'max:40', Rule::unique('customers', 'code')->where('company_id', $companyId)], 'phone' => 'nullable|string|max:30', 'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('company_id', $companyId)], 'representative_id' => 'required|integer', 'address' => 'required|string|max:500', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'geofence_radius_m' => 'required|integer|between:25,500', 'service_notes' => 'nullable|string|max:2000']);
    }

    public function review(Request $request): CustomerChange
    {
        abort_unless(app(CompanyContext::class)->role->managesOperations(), 403);
        $d = $request->validate(['id' => 'required|integer', 'decision' => 'required|in:approved,rejected', 'review_note' => 'required|string|min:3|max:1000']);
        $change = CustomerChange::lockForUpdate()->findOrFail($d['id']);
        abort_unless($change->status === 'pending', 409, 'تمت مراجعة هذا الطلب مسبقًا.');
        if ($d['decision'] === 'approved') {
            if ($change->kind === 'location') {
                $customer = Customer::lockForUpdate()->findOrFail($change->customer_id);
                abort_unless((int) $customer->representative_id === (int) $change->payload['owner_id'], 409, 'مسؤول العميل تغير؛ راجع الإسناد قبل قبول الطلب.');
                $this->applyLocation($customer, $change->payload);
            } else {
                $payload = $change->payload;
                if ($request->filled('representative_id')) {
                    $payload['representative_id'] = $request->integer('representative_id');
                }
                $data = $this->customerData(new Request($payload));
                app(RepresentativeAccess::class)->assertCustomer((int) $data['representative_id'], new Customer($data));
                $customer = Customer::create($data);
                $change->customer_id = $customer->id;
                app(AuditRecorder::class)->record('customer.created', $customer, ['registration_id' => $change->id]);
            }
        }
        $change->status = $d['decision'];
        $change->review_note = $d['review_note'];
        $change->reviewed_by = auth()->id();
        $change->reviewed_at = now();
        $change->save();
        app(AuditRecorder::class)->record('customer.change_'.$d['decision'], $change, ['note' => $d['review_note']]);

        return $change;
    }
}
