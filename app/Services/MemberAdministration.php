<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\RepresentativeVehicleAssignment;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberAdministration
{
    public function save(Request $request, bool $creating): User
    {
        $companyId = app(CompanyContext::class)->id();
        $target = null;
        if (! $creating) {
            $request->validate(['id' => 'required|integer']);
            $membership = DB::table('company_user')->where('company_id', $companyId)->where('user_id', $request->integer('id'))->lockForUpdate()->first();
            abort_unless($membership, 404);
            abort_if($membership->role === 'manager' || $request->integer('id') === auth()->id(), 422, 'لا يمكن تغيير حساب المدير أو حسابك من إدارة أعضاء الفريق.');
            $target = User::lockForUpdate()->findOrFail($membership->user_id);
        }
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $minimumPassword = app()->environment(['local', 'testing']) && config('fieldflow.demo') ? 6 : 12;
        $d = $request->validate([
            'name' => 'required|string|max:150',
            'username' => ['required', 'string', 'max:40', 'regex:/\A[a-z0-9][a-z0-9._-]{1,39}\z/', Rule::unique('users', 'username')->ignore($target?->id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:'.$minimumPassword, 'max:128', 'confirmed'],
            'role' => 'required|in:representative,supervisor,accountant,viewer,driver,warehouse_keeper,production,quality_inspector,quality_manager',
            'is_active' => 'required|boolean',
            'phone' => 'nullable|string|max:30',
            'employee_code' => ['nullable', 'string', 'max:40', Rule::unique('company_user', 'employee_code')->where('company_id', $companyId)->ignore($target?->id, 'user_id')],
            'area_access' => 'required|in:all,selected',
            'area_ids' => 'present|array|max:500',
            'area_ids.*' => ['integer', 'distinct', Rule::exists('areas', 'id')->where('company_id', $companyId)],
            'can_sell' => 'required|boolean', 'can_collect' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
            'reason' => $creating ? 'nullable|string|max:500' : 'required|string|min:3|max:500',
        ]);
        if ($target && Warehouse::where('custodian_id', $target->id)->exists() && $d['role'] !== $membership->role) {
            abort(422, 'انقل مسؤولية الموقع أولًا قبل تغيير دور صاحب العهدة.');
        }
        $shared = $target && DB::table('company_user')->where('user_id', $target->id)->where('company_id', '!=', $companyId)->exists();
        if ($target && ! in_array($d['role'], ['driver', 'representative'], true)) {
            abort_if(Driver::where('user_id', $target->id)->exists(), 422, 'فك ربط حساب السائق من ملفه قبل تغيير دوره إلى دور آخر.');
        }
        if (in_array($d['role'], ['driver', 'warehouse_keeper', 'production', 'quality_inspector', 'quality_manager'], true)) {
            $d['can_sell'] = false;
            $d['can_collect'] = false;
        }
        abort_if($shared && ($target->name !== $d['name'] || $target->username !== $d['username'] || ! empty($d['password'])), 422, 'الحساب مشترك مع شركة أخرى؛ يمكن تعديل عضويته هنا، دون تغيير هويته أو كلمة مروره العامة.');
        if ($target && $d['role'] !== 'representative') {
            abort_if(RepresentativeVehicleAssignment::where('representative_id', $target->id)->whereNull('returned_at')->exists(), 422, 'أنه تخصيص مركبة المندوب قبل تغيير دوره.');
            abort_if(DB::table('trips')->where('company_id', $companyId)->where('representative_id', $target->id)->whereIn('status', ['active', 'planned'])->exists(), 422, 'عالج رحلات المندوب المفتوحة قبل تغيير دوره. يمكنك تعليق دخوله فورًا دون تغيير الدور.');
        }
        $before = $target ? DB::table('company_user')->where('company_id', $companyId)->where('user_id', $target->id)->first() : null;
        $target ??= new User;
        $target->name = $d['name'];
        $target->username = $d['username'];
        if (! empty($d['password'])) {
            $target->password = $d['password'];
        }
        $target->save();
        $pivot = array_intersect_key($d, array_flip(['role', 'is_active', 'phone', 'employee_code', 'area_access', 'can_sell', 'can_collect', 'notes']));
        $pivot['operations_version'] = $creating ? 1 : $before->operations_version + 1;
        if ($creating) {
            app(CompanyContext::class)->company->users()->attach($target->id, $pivot);
        } else {
            app(CompanyContext::class)->company->users()->updateExistingPivot($target->id, $pivot);
        }
        DB::table('representative_areas')->where('company_id', $companyId)->where('user_id', $target->id)->delete();
        if ($d['role'] === 'representative' && $d['area_access'] === 'selected') {
            foreach ($d['area_ids'] as $areaId) {
                DB::table('representative_areas')->insert(['company_id' => $companyId, 'user_id' => $target->id, 'area_id' => $areaId]);
            }
        }
        app(AuditRecorder::class)->record($creating ? 'member.created' : 'member.updated', $target, ['before' => $before, 'after' => $pivot, 'area_ids' => $d['area_ids'], 'reason' => $d['reason'] ?? null]);

        return $target;
    }

    public function status(Request $request): User
    {
        $d = $request->validate(['id' => 'required|integer', 'is_active' => 'required|boolean', 'reason' => 'required|string|min:3|max:500']);
        $company = app(CompanyContext::class)->company;
        $target = $company->users()->where('users.id', $d['id'])->lockForUpdate()->firstOrFail();
        abort_if($target->pivot->role === 'manager' || $target->id === auth()->id(), 422, 'لا يمكن تعليق المدير أو حسابك من هذه الشاشة.');
        $company->users()->updateExistingPivot($target->id, ['is_active' => $d['is_active']]);
        app(AuditRecorder::class)->record($d['is_active'] ? 'member.activated' : 'member.suspended', $target, ['reason' => $d['reason'], 'previously_active' => (bool) $target->pivot->is_active]);

        return $target;
    }
}
