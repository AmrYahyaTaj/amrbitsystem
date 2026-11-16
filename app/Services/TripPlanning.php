<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Customer;
use App\Models\LocationPoint;
use App\Models\Order;
use App\Models\RepresentativeVehicleAssignment;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TripPlanning
{
    public function blockers(Trip $trip): array
    {
        $reasons = [];
        if ($trip->status !== 'planned' || $trip->started_at || $trip->closed_at) {
            $reasons[] = 'التعديل والإلغاء متاحان للخطة التي لم يبدأ تنفيذها فقط.';
        }
        if ($trip->visits()->whereNotIn('status', ['planned', 'cancelled'])->exists() || $trip->visits()->where(fn ($q) => $q->whereNotNull('checked_in_at')->orWhereNotNull('finished_at')->orWhere('location_verified', true))->exists()) {
            $reasons[] = 'توجد زيارة نُفذت أو لها إثبات حضور؛ لا يمكن إعادة كتابة خطتها.';
        }
        if (Order::where('trip_id', $trip->id)->exists()) {
            $reasons[] = 'توجد طلبات مرتبطة بالرحلة؛ راجع مستنداتها قبل أي تغيير.';
        }
        if (LocationPoint::where('trip_id', $trip->id)->exists()) {
            $reasons[] = 'توجد نقاط تتبع مسجلة للرحلة.';
        }

        return $reasons;
    }

    public function snapshot(Trip $trip): array
    {
        return $trip->only(['representative_id', 'vehicle_id', 'warehouse_id', 'driver_id', 'status', 'plan_version', 'planning_notes']) + [
            'scheduled_for' => $trip->scheduled_for->toDateString(),
            'stops' => $trip->visits()->orderBy('sequence')->orderBy('id')->get()->map(fn ($v) => ['id' => $v->id, 'customer_id' => $v->customer_id, 'sequence' => $v->sequence, 'status' => $v->status->value])->all(),
        ];
    }

    private function editable(Request $request): Trip
    {
        $d = $request->validate(['id' => 'required|integer', 'plan_version' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:500']);
        $trip = Trip::lockForUpdate()->findOrFail($d['id']);
        abort_unless($trip->plan_version === (int) $d['plan_version'], 409, 'الخطة تغيرت منذ فتحها. حدّث البيانات وافتحها من جديد قبل الحفظ.');
        $blockers = $this->blockers($trip);
        abort_if(count($blockers) > 0, 422, implode(' ', $blockers));

        return $trip;
    }

    public function save(Request $request, bool $creating): Trip
    {
        $trip = $creating ? new Trip : $this->editable($request);
        $context = app(CompanyContext::class);
        $tenantExists = fn ($table) => Rule::exists($table, 'id')->where('company_id', $context->id());
        $d = $request->validate([
            'representative_id' => 'required|integer', 'vehicle_id' => ['required', 'integer', $tenantExists('vehicles')],
            'scheduled_for' => 'required|date_format:Y-m-d|after_or_equal:'.now()->timezone($context->company->timezone)->toDateString(),
            'customer_ids' => 'required|array|list|min:1|max:50', 'customer_ids.*' => ['required', 'integer', 'distinct', $tenantExists('customers')],
            'planning_notes' => 'nullable|string|max:1000',
        ]);
        $member = app(RepresentativeAccess::class)->membership((int) $d['representative_id']);
        abort_unless($member && $member->is_active && $member->role === 'representative', 422, 'المندوب ليس فعالًا في هذه الشركة.');
        $vehicle = Vehicle::lockForUpdate()->findOrFail($d['vehicle_id']);
        abort_if(RepresentativeVehicleAssignment::where('vehicle_id', $vehicle->id)->whereNull('returned_at')->where('representative_id', '!=', $d['representative_id'])->exists(), 422, 'المركبة مخصصة لمندوب آخر. غيّر تخصيصها أو اختر مركبة أخرى.');
        abort_unless($vehicle->status === 'available', 422, 'السيارة غير متاحة للتخطيط.');
        $customers = Customer::where('is_active', true)->whereIn('id', $d['customer_ids'])->get()->keyBy('id');
        foreach ($d['customer_ids'] as $id) {
            abort_unless($customers->has($id), 422, 'أحد العملاء غير نشط.');
            app(RepresentativeAccess::class)->assertCustomer((int) $d['representative_id'], $customers[$id]);
        }
        $before = $creating ? null : $this->snapshot($trip);
        $trip->fill([
            'code' => $creating ? 'TR-'.strtoupper(Str::random(8)) : $trip->code,
            'representative_id' => $d['representative_id'], 'vehicle_id' => $vehicle->id,
            'warehouse_id' => Warehouse::where('vehicle_id', $vehicle->id)->firstOrFail()->id,
            'driver_id' => VehicleAssignment::where('vehicle_id', $vehicle->id)->whereNull('returned_at')->value('driver_id'),
            'scheduled_for' => $d['scheduled_for'],
        ]);
        $trip->planning_notes = $d['planning_notes'] ?? null;
        $trip->plan_version = $creating ? 1 : $trip->plan_version + 1;
        // Manual replanning no longer represents the original nearest-neighbour distance.
        if (! $creating) {
            $trip->route_snapshot = null;
        }
        $trip->save();
        // Removed stops remain as cancelled records. Existing identifiers and their history survive edits.
        foreach ($trip->visits()->where('status', 'planned')->whereNotIn('customer_id', $d['customer_ids'])->get() as $visit) {
            $visit->status = VisitStatus::Cancelled;
            $visit->save();
        }
        foreach ($d['customer_ids'] as $index => $id) {
            $visit = $trip->visits()->where('customer_id', $id)->orderBy('id')->first() ?? new Visit(['trip_id' => $trip->id, 'customer_id' => $id]);
            $visit->fill(['representative_id' => $trip->representative_id, 'sequence' => $index + 1, 'scheduled_for' => $d['scheduled_for']]);
            $visit->status = VisitStatus::Planned;
            $visit->save();
        }
        app(AuditRecorder::class)->record($creating ? 'trip.planned' : 'trip.replanned', $trip, ['reason' => $request->input('reason'), 'before' => $before, 'after' => $this->snapshot($trip)]);

        return $trip;
    }

    public function cancel(Request $request): Trip
    {
        $trip = $this->editable($request);
        $before = $this->snapshot($trip);
        $trip->status = 'cancelled';
        $trip->cancelled_at = now();
        $trip->cancelled_by = auth()->id();
        $trip->cancellation_reason = $request->input('reason');
        $trip->plan_version++;
        $trip->save();
        foreach ($trip->visits()->where('status', 'planned')->get() as $visit) {
            $visit->status = VisitStatus::Cancelled;
            $visit->save();
        }
        app(AuditRecorder::class)->record('trip.cancelled', $trip, ['reason' => $trip->cancellation_reason, 'before' => $before, 'after' => $this->snapshot($trip)]);

        return $trip;
    }
}
