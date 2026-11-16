<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\ArchiveDepartment;
use App\Models\Company;
use App\Models\TemporaryDelegation;
use App\Models\Warehouse;
use App\Models\WorkspaceNotification;
use App\Services\AuditRecorder;
use App\Services\DelegationAccess;
use App\Services\OperationOnce;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DelegationController extends Controller
{
    private function manager(): bool
    {
        return app(CompanyContext::class)->role === CompanyRole::Manager;
    }

    private function visible()
    {
        return TemporaryDelegation::when(! $this->manager(), fn ($q) => $q->where(fn ($b) => $b->where('principal_id', auth()->id())->orWhere('delegate_id', auth()->id())));
    }

    public function page(OperationsController $operations)
    {
        return view('app', ['section' => 'delegations', 'sections' => $operations->sections(), 'delegationWorkspace' => true]);
    }

    private function row(TemporaryDelegation $d, DelegationAccess $access): array
    {
        $d->loadMissing(['principal:id,name,username', 'delegate:id,name,username', 'creator:id,name', 'warehouse:id,company_id,name', 'department:id,company_id,name']);
        $state = $d->status;
        if (in_array($state, ['pending', 'accepted'], true)) {
            $state = $d->ends_at->isPast() || $d->ends_at->equalTo(now()) ? 'expired' : (! $access->validSource($d) ? 'invalid' : ($d->status === 'pending' ? 'pending' : ($d->starts_at->isFuture() ? 'scheduled' : 'active')));
        }

        return $d->toArray() + ['effective_status' => $state, 'domain_label' => DelegationAccess::DOMAINS[$d->domain], 'scope_label' => $d->warehouse?->name ?? $d->department?->name, 'can_accept' => $d->status === 'pending' && in_array($state, ['pending'], true) && (int) $d->delegate_id === auth()->id(), 'can_reject' => $d->status === 'pending' && $d->ends_at->isFuture() && (int) $d->delegate_id === auth()->id(), 'can_revoke' => $this->manager() && in_array($d->status, ['pending', 'accepted'], true) && $d->ends_at->isFuture(), 'next_step' => match ($state) {
            'pending' => 'ينتظر إقرار البديل؛ لا يمنحه صلاحيات قبل القبول وبدء الفترة.','scheduled' => 'قُبل التفويض، ويبدأ في الموعد المحدد.','active' => 'فعال ضمن النطاق المحدد فقط.','invalid' => 'تغيرت عضوية أحد الطرفين أو دوره أو صلاحية الأصل أو حالة النطاق؛ التفويض غير نافذ.','expired' => 'انتهت الفترة؛ الصلاحيات المؤقتة غير نافذة.','revoked' => 'ألغته الإدارة؛ الصلاحيات المؤقتة غير نافذة.',default => 'رُفض الطلب وحُفظ رده.'
        }];
    }

    public function index(Request $r, DelegationAccess $access)
    {
        $d = $r->validate(['page' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:80', 'domain' => 'nullable|in:quality_inspect,quality_approve,archive_review']);
        $query = $this->visible()->when($d['domain'] ?? null, fn ($q, $s) => $q->where('domain', $s))->when($d['q'] ?? null, fn ($q, $s) => $q->where(fn ($b) => $b->where('code', 'like', '%'.$s.'%')->orWhereHas('principal', fn ($p) => $p->where('name', 'like', '%'.$s.'%')->orWhere('username', 'like', '%'.$s.'%'))->orWhereHas('delegate', fn ($p) => $p->where('name', 'like', '%'.$s.'%')->orWhere('username', 'like', '%'.$s.'%'))));
        $records = $query->orderByDesc('id')->paginate(20);
        $records->through(fn ($item) => $this->row($item, $access));

        return response()->json(['records' => $records, 'can_manage' => $this->manager(), 'domains' => DelegationAccess::DOMAINS, 'max_days' => 30, 'timezone' => app(CompanyContext::class)->company->timezone, 'as_of' => now()->toIso8601String()]);
    }

    public function show(int $id, DelegationAccess $access)
    {
        return response()->json(['delegation' => $this->row($this->visible()->findOrFail($id), $access)]);
    }

    public function options()
    {
        abort_unless($this->manager(), 403);

        return response()->json(['users' => app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->get(['users.id', 'name', 'username'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'role' => $u->pivot->role]), 'warehouses' => Warehouse::where('is_active', true)->where('type', '!=', 'vehicle')->get(['id', 'name']), 'departments' => ArchiveDepartment::where('is_active', true)->get(['id', 'name']), 'domains' => DelegationAccess::DOMAINS, 'timezone' => app(CompanyContext::class)->company->timezone]);
    }

    private function notify(TemporaryDelegation $d, string $event): void
    {
        $title = ['requested' => 'طلب تفويض مؤقت يحتاج إقرار البديل', 'accepted' => 'قُبل التفويض المؤقت', 'rejected' => 'رُفض طلب التفويض', 'revoked' => 'أُلغي التفويض المؤقت'][$event];
        $ids = app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->whereIn('users.id', [$d->principal_id, $d->delegate_id])->pluck('users.id');
        foreach ($ids as $id) {
            WorkspaceNotification::create(['user_id' => $id, 'delegation_id' => $d->id, 'event' => 'delegation.'.$event, 'title' => $title, 'body' => $d->code.' • '.DelegationAccess::DOMAINS[$d->domain].' • راجع النطاق والفترة والملاحظات.', 'created_at' => now()]);
        }
    }

    public function create(Request $r, DelegationAccess $access, OperationOnce $once)
    {
        abort_unless($this->manager(), 403);
        $d = $r->validate(['request_id' => 'required|uuid', 'principal_id' => 'required|integer|different:delegate_id', 'delegate_id' => 'required|integer', 'domain' => 'required|in:quality_inspect,quality_approve,archive_review', 'warehouse_id' => 'required_unless:domain,archive_review|nullable|integer', 'department_id' => 'required_if:domain,archive_review|nullable|integer', 'max_confidentiality' => 'required_if:domain,archive_review|nullable|in:internal,confidential,restricted', 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i', 'reason' => 'required|string|min:3|max:2000']);
        $timezone = app(CompanyContext::class)->company->timezone;
        $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $d['starts_at'], $timezone)->utc();
        $end = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $d['ends_at'], $timezone)->utc();
        abort_unless($start >= now()->subMinutes(5) && $start <= now()->addDays(180) && $end > $start && $end > now() && $end <= $start->addDays(30), 422, 'الفترة تبدأ الآن أو لاحقًا، ومدتها حتى 30 يومًا. اختر تاريخي بداية ونهاية صالحين.');

        return response()->json($once->run('delegation.create', $d['request_id'], $r->except('request_id', '_token'), function () use ($d, $start, $end, $access) {
            Company::whereKey(app(CompanyContext::class)->id())->lockForUpdate()->firstOrFail();
            $p = $access->member((int) $d['principal_id']);
            $u = $access->member((int) $d['delegate_id']);
            abort_unless($p && $u, 422, 'اختر طرفين فعالين في هذه الشركة.');
            $archive = $d['domain'] === 'archive_review';
            if ($archive) {
                ArchiveDepartment::where('is_active', true)->findOrFail($d['department_id']);
                abort_if(! empty($d['warehouse_id']), 422, 'تفويض الأرشيف لقسم واحد دون موقع جودة.');
            } else {
                Warehouse::where('is_active', true)->where('type', '!=', 'vehicle')->findOrFail($d['warehouse_id']);
                abort_if(! empty($d['department_id']) || ! empty($d['max_confidentiality']), 422, 'تفويض الجودة لموقع واحد دون قسم أرشيف.');
            }
            $record = new TemporaryDelegation(['principal_id' => (int) $d['principal_id'], 'delegate_id' => (int) $d['delegate_id'], 'principal_role' => $p->role, 'delegate_role' => $u->role, 'domain' => $d['domain'], 'warehouse_id' => $archive ? null : $d['warehouse_id'], 'department_id' => $archive ? $d['department_id'] : null, 'max_confidentiality' => $archive ? $d['max_confidentiality'] : null]);
            abort_unless($access->validSource($record), 422, 'الأصل يحتاج صلاحية أصلية مناسبة للنطاق، والبديل يحتاج دورًا مناسبًا. لا يسمح بتفويض مشتق من تفويض آخر.');
            $overlap = TemporaryDelegation::where('principal_id', $record->principal_id)->where('domain', $record->domain)->where('warehouse_id', $record->warehouse_id)->where('department_id', $record->department_id)->whereIn('status', ['pending', 'accepted'])->where('ends_at', '>', now())->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
            abort_if($overlap, 409, 'يوجد تفويض متداخل لنفس الأصل والمهمة والنطاق. ألغِه أو اختر فترة أخرى.');
            $record->fill(['code' => 'DL-'.Str::upper(Str::random(12)), 'created_by' => auth()->id(), 'starts_at' => $start, 'ends_at' => $end, 'reason' => $d['reason']]);
            $record->save();
            app(AuditRecorder::class)->record('delegation.requested', $record, ['principal_id' => $record->principal_id, 'delegate_id' => $record->delegate_id, 'domain' => $record->domain, 'warehouse_id' => $record->warehouse_id, 'department_id' => $record->department_id, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'reason' => $d['reason']]);
            $this->notify($record, 'requested');

            return ['id' => $record->id, 'message' => 'أُرسل طلب التفويض للبديل. لا ينفذ قبل قبوله وبدء الفترة.'];
        }));
    }

    public function respond(Request $r, int $id, DelegationAccess $access, OperationOnce $once)
    {
        $record = $this->visible()->findOrFail($id);
        abort_unless((int) $record->delegate_id === auth()->id(), 403, 'الإقرار من حساب البديل نفسه.');
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'decision' => 'required|in:accepted,rejected', 'note' => 'required|string|min:3|max:2000', 'acknowledge' => $r->input('decision') === 'accepted' ? 'required|accepted' : 'nullable|boolean']);
        abort_unless($record->status !== 'revoked' && $record->ends_at->isFuture(), 409, 'أُلغي التفويض أو انتهت فترته.');
        if ($d['decision'] === 'accepted') {
            abort_unless($access->validSource($record), 409, 'تغيرت صلاحيات أو أدوار الطرفين؛ اطلب تفويضًا صحيحًا.');
        }

        return response()->json($once->run('delegation.respond', $d['request_id'], ['id' => $id] + $r->except('request_id', '_token'), function () use ($id, $d, $access) {
            $record = $this->visible()->lockForUpdate()->findOrFail($id);
            abort_unless((int) $record->delegate_id === auth()->id(), 403);
            abort_unless($record->status === 'pending' && (int) $record->version === (int) $d['version'] && $record->ends_at->isFuture(), 409, 'عولج الطلب أو تغير. حدّث الصفحة.');
            if ($d['decision'] === 'accepted') {
                abort_unless($access->validSource($record), 409, 'تغير مصدر الصلاحية.');
            }
            $record->update(['status' => $d['decision'], 'responded_at' => now(), 'response_note' => $d['note'], 'version' => $record->version + 1]);
            app(AuditRecorder::class)->record('delegation.'.$d['decision'], $record, ['note' => $d['note']]);
            $this->notify($record, $d['decision']);

            return ['id' => $id, 'message' => $d['decision'] === 'accepted' ? 'حُفظ إقرارك؛ تصبح الصلاحية نافذة خلال الفترة المحددة فقط.' : 'حُفظ الرفض وأُبلغ الطرف الآخر.'];
        }));
    }

    public function revoke(Request $r, int $id, OperationOnce $once)
    {
        abort_unless($this->manager(), 403);
        $this->visible()->findOrFail($id);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'note' => 'required|string|min:3|max:2000']);

        return response()->json($once->run('delegation.revoke', $d['request_id'], ['id' => $id] + $r->except('request_id', '_token'), function () use ($id, $d) {
            $record = $this->visible()->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($record->status, ['pending', 'accepted'], true) && (int) $record->version === (int) $d['version'], 409, 'تغير التفويض أو انتهى الإجراء عليه.');
            $record->update(['status' => 'revoked', 'revoked_by' => auth()->id(), 'revoked_at' => now(), 'revocation_note' => $d['note'], 'version' => $record->version + 1]);
            app(AuditRecorder::class)->record('delegation.revoked', $record, ['note' => $d['note']]);
            $this->notify($record, 'revoked');

            return ['id' => $id, 'message' => 'أُلغي التفويض. لا تنفذ صلاحياته في الطلبات التالية.'];
        }));
    }
}
