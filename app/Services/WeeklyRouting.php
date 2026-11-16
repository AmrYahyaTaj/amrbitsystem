<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\RepresentativeVehicleAssignment;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\WeeklyPlan;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WeeklyRouting
{
    public function save(Request $request): WeeklyPlan
    {
        $companyId = app(CompanyContext::class)->id();
        $d = $request->validate([
            'representative_id' => 'required|integer', 'weekday' => 'required|integer|between:0,6',
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
            'customer_ids' => 'present|array|list|max:50',
            'customer_ids.*' => ['integer', 'distinct', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'origin_latitude' => 'required|numeric|between:-90,90', 'origin_longitude' => 'required|numeric|between:-180,180',
            'origin_label' => 'required|string|max:120', 'notes' => 'nullable|string|max:1000',
            'is_active' => 'required|boolean', 'version' => 'required|integer|min:0',
        ]);
        $member = app(RepresentativeAccess::class)->membership((int) $d['representative_id']);
        abort_unless($member && $member->is_active && $member->role === 'representative', 422, 'اختر مندوبًا فعالًا.');
        $vehicle = Vehicle::lockForUpdate()->findOrFail($d['vehicle_id']);
        abort_if(RepresentativeVehicleAssignment::where('vehicle_id', $vehicle->id)->whereNull('returned_at')->where('representative_id', '!=', $d['representative_id'])->exists(), 422, 'المركبة مخصصة لمندوب آخر. اختر مركبة المندوب أو مركبة غير مخصصة.');
        abort_unless($vehicle->status === 'available', 422, 'السيارة غير متاحة.');
        foreach ($d['customer_ids'] as $id) {
            $customer = Customer::findOrFail($id);
            abort_unless($customer->is_active && (int) $customer->representative_id === (int) $d['representative_id'], 422, 'اختر عملاء نشطين مسندين لهذا المندوب فقط.');
            app(RepresentativeAccess::class)->assertCustomer((int) $d['representative_id'], $customer);
        }
        abort_if($d['is_active'] && ! count($d['customer_ids']), 422, 'اليوم النشط يحتاج عميلًا واحدًا على الأقل.');
        $plan = WeeklyPlan::where('representative_id', $d['representative_id'])->where('weekday', $d['weekday'])->lockForUpdate()->first();
        abort_unless(($plan?->version ?? 0) === (int) $d['version'], 409, 'الجدول تغير. حدّث الصفحة قبل الحفظ.');
        $before = $plan?->toArray();
        $plan ??= new WeeklyPlan;
        $plan->fill($d);
        $plan->version = (int) $d['version'] + 1;
        $plan->save();
        app(AuditRecorder::class)->record('weekly_plan.saved', $plan, ['before' => $before, 'after' => $plan->toArray()]);

        return $plan;
    }

    public function publish(Request $request): Trip
    {
        $d = $request->validate(['id' => 'required|integer', 'date' => 'required|date_format:Y-m-d', 'version' => 'required|integer|min:1']);
        $plan = WeeklyPlan::lockForUpdate()->findOrFail($d['id']);
        $today = CarbonImmutable::now(app(CompanyContext::class)->company->timezone)->startOfDay();
        $date = CarbonImmutable::parse($d['date'], $today->timezone)->startOfDay();
        abort_unless($date->betweenIncluded($today, $today->addDays(35)) && $date->dayOfWeek === $plan->weekday, 422, 'اختر تاريخًا مطابقًا ليوم الخطة خلال 35 يومًا.');
        abort_unless($plan->is_active && $plan->version === (int) $d['version'], 409, 'الخطة معطلة أو تغيرت. حدّث الصفحة.');
        // A second request returns the already published occurrence, including cancelled history.
        $existing = Trip::where('weekly_plan_id', $plan->id)->whereDate('scheduled_for', $d['date'])->first();
        if ($existing) {
            return $existing;
        }
        abort_if(Trip::where('representative_id', $plan->representative_id)->whereDate('scheduled_for', $d['date'])->whereIn('status', ['planned', 'active'])->exists(), 422, 'للمندوب رحلة مفتوحة بهذا التاريخ. راجعها قبل نشر رحلة أخرى.');
        $customers = Customer::whereIn('id', $plan->customer_ids)->get()->keyBy('id');
        abort_unless($customers->count() === count($plan->customer_ids) && $customers->count() > 0, 422, 'راجع عملاء الخطة.');
        foreach ($customers as $customer) {
            abort_unless($customer->is_active && (int) $customer->representative_id === (int) $plan->representative_id, 422, 'تغير مسؤول أحد العملاء أو حالته؛ عدّل الخطة.');
        }
        [$ids, $meters] = $this->nearest($customers->all(), (float) $plan->origin_latitude, (float) $plan->origin_longitude);
        $trip = app(TripPlanning::class)->save(new Request([
            'representative_id' => $plan->representative_id, 'vehicle_id' => $plan->vehicle_id,
            'scheduled_for' => $d['date'], 'customer_ids' => $ids, 'planning_notes' => $plan->notes,
        ]), true);
        $trip->weekly_plan_id = $plan->id;
        $trip->weekly_plan_version = $plan->version;
        $trip->route_snapshot = json_encode(['origin_label' => $plan->origin_label, 'latitude' => $plan->origin_latitude, 'longitude' => $plan->origin_longitude, 'distance_m' => $meters, 'method' => 'nearest_neighbor_straight_line'], JSON_THROW_ON_ERROR);
        $trip->save();
        app(AuditRecorder::class)->record('weekly_plan.published', $plan, ['trip_id' => $trip->id, 'date' => $d['date'], 'ordered_customer_ids' => $ids]);

        return $trip;
    }

    public function nearest(array $customers, float $latitude, float $longitude): array
    {
        $ids = [];
        $meters = 0;
        $distance = app(VisitWorkflow::class);
        while (count($customers)) {
            usort($customers, fn ($a, $b) => ($distance->distance($latitude, $longitude, (float) $a->latitude, (float) $a->longitude) <=> $distance->distance($latitude, $longitude, (float) $b->latitude, (float) $b->longitude)) ?: ($a->id <=> $b->id));
            $next = array_shift($customers);
            $meters += $distance->distance($latitude, $longitude, (float) $next->latitude, (float) $next->longitude);
            $ids[] = $next->id;
            $latitude = (float) $next->latitude;
            $longitude = (float) $next->longitude;
        }

        return [$ids, (int) round($meters)];
    }
}
