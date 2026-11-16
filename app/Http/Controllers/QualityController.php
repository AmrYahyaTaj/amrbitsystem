<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\QualityInspection;
use App\Models\StockLot;
use App\Models\Warehouse;
use App\Models\WorkspaceNotification;
use App\Services\AuditRecorder;
use App\Services\OperationOnce;
use App\Services\QualityWorkflow;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QualityController extends Controller
{
    public function page(OperationsController $operations)
    {
        abort_unless(in_array('quality', $operations->sections(), true), 403);

        return view('app', ['section' => 'quality', 'sections' => $operations->sections(), 'qualityWorkspace' => true]);
    }

    public function index(Request $r, QualityWorkflow $workflow)
    {
        $this->allowed();
        $d = $r->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:awaiting_inspection,awaiting_approval,approved,rejected,cancelled', 'page' => 'nullable|integer|min:1', 'held_page' => 'nullable|integer|min:1']);
        $base = $workflow->visible();
        $records = (clone $base)->when($d['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($d['q'] ?? null, fn ($q, $s) => $q->where(fn ($b) => $b->where('code', 'like', '%'.$s.'%')->orWhereHas('lot', fn ($l) => $l->where('code', 'like', '%'.$s.'%'))))->with(['lot.product', 'lot.production', 'warehouse', 'requester', 'inspector', 'decider'])->orderByRaw("CASE WHEN status IN ('awaiting_inspection', 'awaiting_approval') THEN 0 ELSE 1 END")->orderByDesc('id')->paginate(20);
        $records->through(fn ($i) => $workflow->row($i));
        $held = $workflow->lots()->whereIn('status', ['quarantine', 'blocked'])->whereNotIn('id', QualityInspection::whereNotNull('active_lot_id')->select('active_lot_id'))->with('product:id,company_id,name,sku,unit')->orderByDesc('id')->paginate(10, ['*'], 'held_page');

        return response()->json(['records' => $records, 'held' => $held, 'summary' => [
            'awaiting_inspection' => (clone $base)->where('status', 'awaiting_inspection')->count(),
            'awaiting_approval' => (clone $base)->where('status', 'awaiting_approval')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'overdue' => (clone $base)->whereIn('status', ['awaiting_inspection', 'awaiting_approval'])->where('due_at', '<', now())->count(),
        ], 'can_manage_access' => app(CompanyContext::class)->role === CompanyRole::Manager, 'can_request' => app(CompanyContext::class)->role->managesOperations() || app(CompanyContext::class)->role === CompanyRole::Production, 'as_of' => now()->toIso8601String()]);
    }

    public function show(int $id, QualityWorkflow $workflow)
    {
        $this->allowed();

        return response()->json(['inspection' => $workflow->row($workflow->visible()->findOrFail($id))]);
    }

    private function allowed(): void
    {
        abort_unless(in_array(app(CompanyContext::class)->role, [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Production, CompanyRole::WarehouseKeeper, CompanyRole::QualityInspector, CompanyRole::QualityManager], true), 403);
    }

    public function requestInspection(Request $r, int $id, QualityWorkflow $workflow, OperationOnce $once)
    {
        abort_unless($workflow->role()->managesOperations() || $workflow->role() === CompanyRole::Production, 403);
        $d = $r->validate(['request_id' => 'required|uuid', 'note' => 'required|string|min:3|max:2000']);

        return response()->json($once->run('quality.request', $d['request_id'], ['id' => $id] + $r->except('request_id', '_token'), function () use ($workflow, $id, $d) {
            $lot = $workflow->lots()->lockForUpdate()->findOrFail($id);

            return ['id' => $workflow->request($lot, $d['note'])->id, 'message' => 'أرسل طلب الفحص. تظل حالة الدفعة كما هي حتى القرار.'];
        }));
    }

    public function execute(Request $r, int $id, string $action, QualityWorkflow $workflow, OperationOnce $once)
    {
        $this->allowed();
        $workflow->visible()->findOrFail($id);
        $r->validate(['request_id' => 'required|uuid']);

        return response()->json($once->run('quality.'.$action, $r->input('request_id'), ['id' => $id] + $r->except('request_id', '_token'), function () use ($r, $id, $action, $workflow) {
            $initial = $workflow->visible()->findOrFail($id);
            $lot = StockLot::lockForUpdate()->findOrFail($initial->lot_id);
            $inspection = $workflow->visible()->lockForUpdate()->findOrFail($id);
            if ($action === 'inspect') {
                $workflow->inspect($r, $inspection);
            } elseif ($action === 'decide') {
                $workflow->decide($r, $inspection, $lot);
            } else {
                abort(404);
            }

            return ['id' => $id, 'message' => $action === 'inspect' ? 'حُفظ الفحص وأُرسل لمسؤول اعتماد مستقل. لم يُفرج عن الدفعة بعد.' : 'حُفظ القرار وسجلت إشعارات الأطراف.'];
        }));
    }

    public function access(Request $r, OperationOnce $once)
    {
        abort_unless(app(CompanyContext::class)->role === CompanyRole::Manager, 403);
        $company = app(CompanyContext::class)->id();
        if ($r->isMethod('post')) {
            $d = $r->validate(['request_id' => 'required|uuid', 'user_id' => 'required|integer', 'warehouse_ids' => 'present|array|list|max:200', 'warehouse_ids.*' => 'required|integer|distinct', 'reason' => 'required|string|min:3|max:1000']);

            return response()->json($once->run('quality.access', $d['request_id'], $r->except('request_id', '_token'), function () use ($d, $company) {
                $member = app(CompanyContext::class)->company->users()->where('users.id', $d['user_id'])->wherePivot('is_active', true)->wherePivotIn('role', ['quality_inspector', 'quality_manager'])->lockForUpdate()->firstOrFail();
                $ids = Warehouse::whereIn('id', $d['warehouse_ids'])->where('type', '!=', 'vehicle')->where('is_active', true)->pluck('id');
                abort_unless($ids->count() === count($d['warehouse_ids']), 422, 'اختر مواقع فعالة من هذه الشركة.');
                $before = DB::table('quality_site_access')->where('company_id', $company)->where('user_id', $member->id)->pluck('warehouse_id')->all();
                DB::table('quality_site_access')->where('company_id', $company)->where('user_id', $member->id)->delete();
                foreach ($ids as $id) {
                    DB::table('quality_site_access')->insert(['company_id' => $company, 'user_id' => $member->id, 'warehouse_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
                app(AuditRecorder::class)->record('quality.access_changed', $member, ['before' => $before, 'after' => $ids->all(), 'reason' => $d['reason']]);
                // Notify newly responsible members about the still-open queue.
                foreach (QualityInspection::whereIn('warehouse_id', $ids)->whereIn('status', ['awaiting_inspection', 'awaiting_approval'])->get() as $inspection) {
                    WorkspaceNotification::create(['user_id' => $member->id, 'quality_inspection_id' => $inspection->id, 'event' => 'quality.assignment', 'title' => 'أُسند إليك نطاق جودة يتضمن طلبات مفتوحة', 'body' => $inspection->code.' • راجع المهمة ضمن صلاحيات دورك.', 'created_at' => now()]);
                }

                return ['message' => 'حُفظت مواقع الجودة وصلاحياتها. سحب الموقع يمنع الوصول إليه فورًا.'];
            }));
        }

        return response()->json(['users' => app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->wherePivotIn('role', ['quality_inspector', 'quality_manager'])->get(['users.id', 'name', 'username'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'role' => $u->pivot->role]), 'sites' => Warehouse::where('type', '!=', 'vehicle')->where('is_active', true)->get(['id', 'name', 'type']), 'assignments' => DB::table('quality_site_access')->where('company_id', $company)->get(['user_id', 'warehouse_id'])]);
    }
}
