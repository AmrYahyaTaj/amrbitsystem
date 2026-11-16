<?php

namespace App\Services;

use App\Models\RepresentativeVehicleAssignment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WeeklyPlan;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RepresentativeRegistry
{
    public function details(Request $request): User
    {
        $d = $request->validate(['id' => 'required|integer', 'operations_version' => 'required|integer|min:1', 'name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'employee_code' => ['nullable', 'string', 'max:40', Rule::unique('company_user', 'employee_code')->where('company_id', app(CompanyContext::class)->id())->ignore($request->integer('id'), 'user_id')], 'notes' => 'nullable|string|max:1000', 'reason' => 'required|string|min:3|max:500']);
        $member = DB::table('company_user')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $d['id'])->lockForUpdate()->first();
        abort_unless($member && $member->role === 'representative', 404);
        abort_unless((int) $member->operations_version === (int) $d['operations_version'], 409, 'بيانات المندوب تغيرت. حدّث الملف قبل الحفظ.');
        $user = User::lockForUpdate()->findOrFail($d['id']);
        abort_if($user->name !== $d['name'] && $user->companies()->where('companies.id', '!=', app(CompanyContext::class)->id())->exists(), 422, 'الاسم مرتبط بحساب مشترك؛ يمكن تعديل بيانات عضويته فقط هنا.');
        $before = ['name' => $user->name, 'phone' => $member->phone, 'employee_code' => $member->employee_code, 'notes' => $member->notes];
        $user->name = $d['name'];
        $user->save();
        app(CompanyContext::class)->company->users()->updateExistingPivot($user->id, ['phone' => $d['phone'] ?? null, 'employee_code' => $d['employee_code'] ?? null, 'notes' => $d['notes'] ?? null, 'operations_version' => $member->operations_version + 1]);
        app(AuditRecorder::class)->record('representative.details_updated', $user, ['before' => $before, 'after' => array_intersect_key($d, $before), 'reason' => $d['reason']]);

        return $user;
    }

    public function vehicle(Request $request): RepresentativeVehicleAssignment
    {
        $d = $request->validate(['representative_id' => 'required|integer', 'vehicle_id' => 'present|nullable|integer', 'current_assignment_id' => 'required|integer|min:0', 'update_plans' => 'required|boolean', 'reason' => 'required|string|min:3|max:500']);
        $member = DB::table('company_user')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $d['representative_id'])->lockForUpdate()->first();
        abort_unless($member && $member->role === 'representative', 404);
        $current = RepresentativeVehicleAssignment::where('representative_id', $member->user_id)->whereNull('returned_at')->lockForUpdate()->first();
        abort_unless((int) ($current?->id ?? 0) === (int) $d['current_assignment_id'], 409, 'إسناد المركبة تغير. حدّث الملف أولًا.');
        abort_if(Trip::where('representative_id', $member->user_id)->where('status', 'active')->exists(), 422, 'المندوب في رحلة نشطة. أغلقها أو عالجها قبل تغيير مركبته.');
        $vehicle = $d['vehicle_id'] ? Vehicle::lockForUpdate()->findOrFail($d['vehicle_id']) : null;
        if ($vehicle) {
            abort_unless($member->is_active && $vehicle->status === 'available', 422, 'المندوب أو المركبة غير متاح للإسناد.');
            abort_if($current?->vehicle_id === $vehicle->id, 422, 'هذه المركبة مخصصة للمندوب بالفعل.');
            abort_if(Trip::where('vehicle_id', $vehicle->id)->where('status', 'active')->exists(), 422, 'المركبة في رحلة نشطة.');
            abort_if(RepresentativeVehicleAssignment::where('vehicle_id', $vehicle->id)->whereNull('returned_at')->exists(), 422, 'المركبة مخصصة لمندوب آخر. أنهِ تخصيصها السابق أولًا.');
        } else {
            abort_unless($current, 422, 'لا توجد مركبة مخصصة لإرجاعها.');
            abort_if($d['update_plans'], 422, 'اختر مركبة بديلة لتحديث الخطط.');
        }
        if ($vehicle && $d['update_plans']) {
            foreach (Trip::where('representative_id', $member->user_id)->where('status', 'planned')->lockForUpdate()->get() as $trip) {
                abort_if($trip->scheduled_for->toDateString() < now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString(), 422, 'توجد رحلة مخططة بتاريخ سابق. أعد جدولتها أو ألغها قبل تطبيق المركبة على الخطط.');
                app(TripPlanning::class)->save(new Request(['id' => $trip->id, 'plan_version' => $trip->plan_version, 'representative_id' => $member->user_id, 'vehicle_id' => $vehicle->id, 'scheduled_for' => $trip->scheduled_for->toDateString(), 'customer_ids' => $trip->visits()->where('status', 'planned')->orderBy('sequence')->pluck('customer_id')->all(), 'reason' => $d['reason'], 'planning_notes' => $trip->planning_notes]), false);
            }
            foreach (WeeklyPlan::where('representative_id', $member->user_id)->lockForUpdate()->get() as $plan) {
                $before = $plan->vehicle_id;
                $plan->vehicle_id = $vehicle->id;
                $plan->version++;
                $plan->save();
                app(AuditRecorder::class)->record('weekly_plan.vehicle_changed', $plan, ['before' => $before, 'after' => $vehicle->id, 'reason' => $d['reason']]);
            }
        }
        if ($current) {
            Vehicle::lockForUpdate()->findOrFail($current->vehicle_id);
            $current->returned_at = now();
            $current->returned_by = auth()->id();
            $current->return_note = $d['reason'];
            $current->active_representative_id = null;
            $current->active_vehicle_id = null;
            $current->save();
            app(AuditRecorder::class)->record('representative.vehicle_released', $current, ['reason' => $d['reason']]);
        }
        if (! $vehicle) {
            return $current;
        }
        $assignment = RepresentativeVehicleAssignment::create(['representative_id' => $member->user_id, 'vehicle_id' => $vehicle->id, 'active_representative_id' => $member->user_id, 'active_vehicle_id' => $vehicle->id, 'assigned_by' => auth()->id(), 'assigned_at' => now(), 'assignment_note' => $d['reason']]);
        app(AuditRecorder::class)->record('representative.vehicle_assigned',$assignment,['reason' => $d['reason'], 'updated_plans' => $d['update_plans']]);

        return $assignment;
    }
}
