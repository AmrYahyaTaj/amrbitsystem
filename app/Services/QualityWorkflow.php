<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\AuditLog;
use App\Models\QualityInspection;
use App\Models\StockLot;
use App\Models\Warehouse;
use App\Models\WorkspaceNotification;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QualityWorkflow
{
    public function role(): CompanyRole
    {
        return app(CompanyContext::class)->role;
    }

    public function qualityRole(): bool
    {
        return in_array($this->role(), [CompanyRole::QualityInspector, CompanyRole::QualityManager], true);
    }

    public function siteIds(?int $user = null)
    {
        $user ??= auth()->id();
        $native = DB::table('quality_site_access')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $user)->pluck('warehouse_id');
        $role = app(DelegationAccess::class)->member($user)?->role;
        $domain = $role === 'quality_inspector' ? 'quality_inspect' : 'quality_approve';

        return $native->merge(app(DelegationAccess::class)->qualitySites($user, $domain))->unique()->values();
    }

    public function lots()
    {
        $q = StockLot::query();
        if ($this->role()->managesOperations()) {
            return $q;
        }
        if ($this->qualityRole()) {
            return $q->whereIn('origin_warehouse_id', $this->siteIds());
        }
        if ($this->role() === CompanyRole::Production) {
            return $q->whereHas('production', fn ($p) => $p->where('operator_id', auth()->id()));
        }
        if ($this->role() === CompanyRole::WarehouseKeeper) {
            return $q->whereIn('id', DB::table('stock_lot_balances')->where('company_id', app(CompanyContext::class)->id())->whereIn('warehouse_id', Warehouse::where('custodian_id', auth()->id())->where('type', 'warehouse')->select('id'))->where('quantity', '>', 0)->select('lot_id'));
        }

        return $q->whereRaw('1 = 0');
    }

    public function visible()
    {
        return QualityInspection::whereIn('lot_id', $this->lots()->select('id'));
    }

    private function authority(QualityInspection $inspection, bool $approval, ?int $user = null): ?array
    {
        $user ??= auth()->id();
        $excluded = [$inspection->requested_by];
        if ($approval && $inspection->inspected_by) {
            $excluded[] = $inspection->inspected_by;
        }
        $production = $inspection->lot->production;
        if ($production) {
            $excluded[] = $production->operator_id;
            if ($production->completed_by) {
                $excluded[] = $production->completed_by;
            }
            $excluded = array_merge($excluded, AuditLog::where('subject_type', 'production_orders')->where('subject_id', $production->id)->where('action', 'production.complete')->pluck('actor_id')->all());
        }

        return app(DelegationAccess::class)->qualityAuthority($user, (int) $inspection->warehouse_id, $approval ? 'quality_approve' : 'quality_inspect', $excluded);
    }

    private function candidates(QualityInspection $inspection, bool $approval): array
    {
        $roles = $approval ? ['quality_manager', 'manager', 'supervisor'] : ['quality_inspector'];

        return app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->wherePivotIn('role', $roles)->get(['users.id', 'users.name'])->filter(fn ($u) => $this->authority($inspection, $approval, $u->id) !== null)->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
    }

    private function notify(QualityInspection $inspection, array $ids, string $event, string $title, string $body): void
    {
        $active = app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->whereIn('users.id', array_unique($ids))->pluck('users.id');
        foreach ($active as $id) {
            WorkspaceNotification::create(['user_id' => $id, 'handover_id' => null, 'quality_inspection_id' => $inspection->id, 'event' => $event, 'title' => $title, 'body' => $body, 'created_at' => now()]);
        }
    }

    private function routeTask(QualityInspection $inspection): void
    {
        $approval = $inspection->status === 'awaiting_approval';
        $ids = array_column($this->candidates($inspection, $approval), 'id');
        if (! $ids) {
            $ids = app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->wherePivot('role', 'manager')->pluck('users.id')->all();
        }
        $this->notify($inspection, $ids, 'quality.task', $ids && $this->candidates($inspection, $approval) ? ($approval ? 'فحص جودة ينتظر اعتمادك' : 'دفعة تنتظر فحص الجودة') : 'تدخل مطلوب: مسؤول جودة غير متاح', $inspection->code.' • '.$inspection->lot->code.' • راجع المهمة ومسؤولي مواقع الجودة.');
    }

    public function request(StockLot $lot, string $note): QualityInspection
    {
        // Callers hold the lot lock within the same transaction as production/request.
        $existing = QualityInspection::where('active_lot_id', $lot->id)->first();
        if ($existing) {
            return $existing;
        }
        abort_unless(in_array($lot->status, ['quarantine', 'blocked'], true), 422, 'طلب الفحص متاح لدفعة محجورة أو موقوفة. لا يغيّر الطلب حالتها.');
        $inspection = QualityInspection::create(['code' => 'QA-'.Str::upper(Str::random(10)), 'lot_id' => $lot->id, 'active_lot_id' => $lot->id, 'warehouse_id' => $lot->origin_warehouse_id, 'requested_by' => auth()->id(), 'lot_version' => $lot->quality_version, 'request_note' => $note, 'due_at' => now()->addHours(24)]);
        app(AuditRecorder::class)->record('quality.requested', $inspection, ['lot_id' => $lot->id, 'note' => $note]);
        $this->routeTask($inspection);

        return $inspection;
    }

    public function row(QualityInspection $inspection): array
    {
        $inspection->loadMissing(['lot.product:id,company_id,name,sku,unit', 'lot.production', 'warehouse:id,company_id,name', 'requester:id,name', 'inspector:id,name', 'decider:id,name']);
        $open = in_array($inspection->status, ['awaiting_inspection', 'awaiting_approval'], true);
        $owners = $open ? $this->candidates($inspection, $inspection->status === 'awaiting_approval') : [];
        $inspectAuthority = $this->authority($inspection, false);
        $decisionAuthority = $this->authority($inspection, true);

        return $inspection->toArray() + [
            'can_inspect' => $inspection->status === 'awaiting_inspection' && $this->role() === CompanyRole::QualityInspector && $inspectAuthority !== null,
            'can_decide' => $inspection->status === 'awaiting_approval' && ($this->role()->managesOperations() || $this->role() === CompanyRole::QualityManager) && $decisionAuthority !== null,
            'inspection_authority' => app(DelegationAccess::class)->describe($inspection->inspected_delegation_id), 'decision_authority' => app(DelegationAccess::class)->describe($inspection->decided_delegation_id),
            'responsible' => $owners, 'needs_assignment' => $open && ! $owners,
            'overdue' => $open && $inspection->due_at->isPast(),
            'next_step' => ! $open ? 'انتهى الطلب؛ راجع القرار والسجل.' : (! $owners ? 'مدير الشركة: عيّن مسؤولًا مستقلًا لهذا الموقع أو عالج تعليق حسابه.' : ($inspection->status === 'awaiting_inspection' ? 'فاحص الجودة يسجل العينة والنتائج ويرسلها للاعتماد.' : 'معتمد مستقل يراجع نتائج الفحص ويتخذ القرار.')),
        ];
    }

    public function inspect(Request $r, QualityInspection $inspection): void
    {
        abort_unless($this->role() === CompanyRole::QualityInspector && $this->row($inspection)['can_inspect'], 403, 'الفحص لفاحص الجودة المستقل فقط.');
        $d = $r->validate(['version' => 'required|integer|min:1', 'sample_size' => 'required|integer|between:1,1000000', 'checks' => 'required|array|list|min:1|max:50', 'checks.*.name' => 'required|string|min:2|max:200|distinct', 'checks.*.result' => 'required|in:pass,fail,not_applicable', 'checks.*.observation' => 'required|string|min:3|max:1000', 'recommendation' => 'required|in:released,quarantine,blocked', 'inspection_note' => 'required|string|min:3|max:2000']);
        abort_unless($inspection->status === 'awaiting_inspection' && (int) $inspection->version === (int) $d['version'], 409, 'تغير طلب الفحص. حدّثه قبل التسجيل.');
        if ($d['recommendation'] === 'released') {
            abort_unless(collect($d['checks'])->contains('result', 'pass') && ! collect($d['checks'])->contains('result', 'fail'), 422, 'توصية الإفراج تحتاج فحصًا ناجحًا دون نتائج فاشلة.');
        }
        $authority = $this->authority($inspection, false);
        abort_unless($authority !== null, 409, 'تغير مصدر الصلاحية أو انتهى التفويض. حدّث الصفحة.');
        $inspection->update(collect($d)->except('version')->all() + ['inspected_by' => auth()->id(), 'inspected_delegation_id' => $authority['delegation_id'], 'inspected_at' => now(), 'status' => 'awaiting_approval', 'version' => $inspection->version + 1, 'due_at' => now()->addHours(24)]);
        app(AuditRecorder::class)->record('quality.inspected', $inspection, $d + ['authority' => $authority]);
        $this->routeTask($inspection);
    }

    public function decide(Request $r, QualityInspection $inspection, StockLot $lot): void
    {
        abort_unless($this->row($inspection)['can_decide'], 403, 'الاعتماد لمسؤول مستقل عن الإنتاج وطلب الفحص وتنفيذه.');
        $d = $r->validate(['version' => 'required|integer|min:1', 'decision' => 'required|in:released,quarantine,blocked', 'decision_note' => 'required|string|min:3|max:2000', 'acknowledge' => 'required|accepted']);
        abort_unless($inspection->status === 'awaiting_approval' && (int) $inspection->version === (int) $d['version'] && (int) $lot->quality_version === (int) $inspection->lot_version && in_array($lot->status, ['quarantine', 'blocked'], true), 409, 'تغير الفحص أو حالة الدفعة. حدّث الشاشة وراجع الطلب.');
        if ($d['decision'] === 'released') {
            abort_unless($inspection->recommendation === 'released' && ! collect($inspection->checks)->contains('result', 'fail'), 422, 'لا إفراج دون توصية فحص ناجحة.');
            abort_if($lot->expires_on && $lot->expires_on->toDateString() < app(LotLedger::class)->today(), 422, 'الدفعة منتهية الصلاحية.');
        }
        $authority = $this->authority($inspection, true);
        abort_unless($authority !== null, 409, 'تغير مصدر الصلاحية أو انتهى التفويض. حدّث الصفحة.');
        $before = $lot->status;
        $lot->update(['status' => $d['decision'], 'quality_version' => $lot->quality_version + 1]);
        $inspection->update(['status' => $d['decision'] === 'released' ? 'approved' : 'rejected', 'decision' => $d['decision'], 'decision_note' => $d['decision_note'], 'decided_by' => auth()->id(), 'decided_delegation_id' => $authority['delegation_id'], 'decided_at' => now(), 'version' => $inspection->version + 1, 'active_lot_id' => null]);
        app(AuditRecorder::class)->record('lot.quality_changed', $lot, ['before' => $before, 'after' => $d['decision'], 'note' => $d['decision_note'], 'inspection_id' => $inspection->id]);
        app(AuditRecorder::class)->record('quality.decided', $inspection, $d + ['authority' => $authority]);
        $ids = [$inspection->requested_by, $inspection->inspected_by];
        $ids = array_merge($ids, Warehouse::where('id', $inspection->warehouse_id)->pluck('custodian_id')->filter()->all());
        $this->notify($inspection, $ids, 'quality.decided', 'صدر قرار الجودة للدفعة '.$lot->code, $inspection->code.' • '.$d['decision_note']);
    }

    public function hold(StockLot $lot, string $status, string $note): void
    {
        abort_unless($this->role()->managesOperations(), 403);
        abort_unless(in_array($status, ['quarantine', 'blocked'], true), 422, 'الإفراج يتم من قسم الجودة بعد الفحص واعتماد مسؤول مستقل.');
        $before = $lot->status;
        $lot->update(['status' => $status, 'quality_version' => $lot->quality_version + 1]);
        foreach (QualityInspection::where('active_lot_id', $lot->id)->lockForUpdate()->get() as $inspection) {
            $inspection->update(['status' => 'cancelled', 'active_lot_id' => null, 'decision_note' => 'تغيرت حالة الدفعة: '.$note, 'decided_by' => auth()->id(), 'decided_at' => now(), 'version' => $inspection->version + 1]);
            $this->notify($inspection, array_filter([$inspection->requested_by, $inspection->inspected_by]), 'quality.cancelled', 'أُلغي فحص بسبب تغيير حالة الدفعة', $note);
        }
        app(AuditRecorder::class)->record('lot.quality_changed', $lot, ['before' => $before, 'after' => $status, 'note' => $note]);
    }
}
