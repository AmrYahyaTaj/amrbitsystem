<?php

namespace App\Http\Controllers;

use App\Models\ArchiveDepartment;
use App\Models\ArchiveDocument;
use App\Models\ArchiveVersion;
use App\Models\AuditLog;
use App\Models\HandoverFile;
use App\Services\ArchiveAccess;
use App\Services\ArchiveWorkflow;
use App\Services\AuditRecorder;
use App\Services\DelegationAccess;
use App\Services\OperationOnce;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArchiveController extends Controller
{
    public function page(ArchiveAccess $access, OperationsController $operations)
    {
        abort_unless($access->mayEnter(), 403);

        return view('app', ['section' => 'archive', 'sections' => $operations->sections(), 'archiveWorkspace' => true]);
    }

    private function filtered(Request $r, ArchiveAccess $access, string $right = 'view')
    {
        $d = $r->validate(['department_id' => 'nullable|integer', 'q' => 'nullable|string|max:100', 'status' => 'nullable|in:pending_review,approved,returned', 'page' => 'nullable|integer|min:1']);
        if (! empty($d['department_id'])) {
            $access->departments()->findOrFail($d['department_id']);
        }

        return $access->documents($right)->when($d['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))->when($d['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($d['q'] ?? null, fn ($q, $s) => $q->where(fn ($b) => $b->where('code', 'like', '%'.$s.'%')->orWhere('title', 'like', '%'.$s.'%')->orWhere('reference', 'like', '%'.$s.'%')->orWhere('document_type', 'like', '%'.$s.'%')));
    }

    public function index(Request $r, ArchiveAccess $access)
    {
        abort_unless($access->mayEnter(), 403);
        $base = $this->filtered($r, $access);

        return response()->json(['documents' => (clone $base)->with(['department:id,company_id,name', 'creator:id,name'])->orderByDesc('id')->paginate(20), 'departments' => $access->departments()->orderBy('name')->get()->map(fn ($d) => $d->toArray() + ['rights' => collect(ArchiveAccess::RIGHTS)->mapWithKeys(fn ($right) => [$right => $access->allows($d, $right)])->all()]), 'can_manage' => $access->manager(), 'summary' => ['documents' => (clone $base)->count(), 'pending' => (clone $base)->where('status', 'pending_review')->count(), 'approved' => (clone $base)->where('status', 'approved')->count(), 'expired' => (clone $base)->whereNotNull('expires_on')->where('expires_on', '<', now()->toDateString())->count()], 'as_of' => now()->toIso8601String()]);
    }

    private function mayReadVersion(ArchiveAccess $access, ArchiveDocument $doc, ArchiveVersion $v, string $right): bool
    {
        return $access->allows($doc->department, $right, $doc->confidentiality) && ($v->status === 'approved' || (int) $doc->created_by === auth()->id() || (int) $v->uploaded_by === auth()->id() || $access->allows($doc->department, 'approve', $doc->confidentiality));
    }

    public function show(int $id, ArchiveAccess $access, ArchiveWorkflow $workflow)
    {
        $doc = $access->documents()->with(['department', 'creator:id,name'])->findOrFail($id);
        $versions = $doc->versions()->with(['uploader:id,name', 'decider:id,name'])->orderByDesc('number')->get();
        $latest = $versions->first();
        $prefix = request()->is('api/*') ? '/api/v1/archive/documents/' : '/ops/archive/documents/';
        $rows = $versions->map(function ($v) use ($access, $doc, $prefix) {
            $urls = [];
            foreach (['preview', 'download', 'print'] as $mode) {
                $urls[$mode.'_url'] = $this->mayReadVersion($access, $doc, $v, $mode) ? $prefix.$doc->id.'/versions/'.$v->number.'/'.$mode : null;
            }

            return $v->toArray() + $urls + ['decision_authority' => app(DelegationAccess::class)->describe($v->decided_delegation_id)];
        });
        app(AuditRecorder::class)->record('archive.viewed', $doc);

        return response()->json(['document' => $doc, 'versions' => $rows, 'can_upload' => $access->allows($doc->department, 'upload', $doc->confidentiality), 'can_approve' => $doc->status === 'pending_review' && $access->reviewAuthority($doc) !== null, 'reviewers' => $doc->status === 'pending_review' ? $workflow->reviewers($doc) : [], 'can_audit' => $access->allows($doc->department, 'audit', $doc->confidentiality)]);
    }

    public function upload(Request $r, ArchiveAccess $access, ArchiveWorkflow $workflow, OperationOnce $once, ?int $id = null)
    {
        abort_unless($access->mayEnter(), 403);
        $existing = $id ? $access->documents('upload')->with('department')->findOrFail($id) : null;
        $d = $r->validate(['request_id' => 'required|uuid', 'department_id' => ($id ? 'nullable' : 'required').'|integer', 'title' => ($id ? 'sometimes|required' : 'required').'|string|min:3|max:200', 'document_type' => ($id ? 'sometimes|required' : 'required').'|string|min:2|max:80', 'confidentiality' => ($id ? 'nullable' : 'required').'|in:internal,confidential,restricted', 'document_date' => ($id ? 'sometimes|required' : 'required').'|date_format:Y-m-d', 'expires_on' => 'nullable|date_format:Y-m-d', 'retain_until' => 'nullable|date_format:Y-m-d', 'reference' => 'nullable|string|max:150', 'physical_reference' => 'nullable|string|max:200', 'notes' => ($id ? 'sometimes|required' : 'required').'|string|min:3|max:2000', 'version' => $id ? 'required|integer|min:1' : 'nullable', 'version_note' => 'required|string|min:3|max:2000', 'signed_copy' => 'required|boolean', 'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $department = $existing ? $existing->department : ArchiveDepartment::findOrFail($d['department_id']);
        $level = $existing ? $existing->confidentiality : $d['confidentiality'];
        abort_unless($access->allows($department, 'upload', $level), 403, 'رفع الملفات غير مصرح به في هذا القسم أو لهذه السرية.');
        $file = $r->file('document');
        $payload = $r->except('request_id', '_token', 'document') + ['id' => $id, 'sha256' => hash_file('sha256', $file->getRealPath()), 'file_name' => $file->getClientOriginalName()];
        $path = null;
        try {
            return response()->json($once->run($id ? 'archive.version' : 'archive.create', $d['request_id'], $payload, function () use ($id, $d, $department, $access, $level, $file, $workflow, &$path) {
                $department = ArchiveDepartment::lockForUpdate()->findOrFail($department->id);
                abort_unless($access->allows($department, 'upload', $level), 403);
                if ($id) {
                    $doc = $access->documents('upload')->lockForUpdate()->findOrFail($id);
                    abort_unless((int) $doc->version === (int) $d['version'], 409, 'تغير المستند. حدّثه قبل إضافة نسخة.');
                    $doc->fill(collect($d)->only(['title', 'document_type', 'document_date', 'expires_on', 'retain_until', 'reference', 'physical_reference', 'notes'])->all());
                    $doc->save();
                } else {
                    $fields = collect($d)->only(['title', 'document_type', 'confidentiality', 'document_date', 'expires_on', 'retain_until', 'reference', 'physical_reference', 'notes'])->all();
                    $doc = ArchiveDocument::create($fields + ['department_id' => $department->id, 'code' => 'AR-'.Str::upper(Str::random(12)), 'created_by' => auth()->id()]);
                }
                $workflow->storeVersion($doc, $file, $d['version_note'], (bool) $d['signed_copy'], $path);

                return ['id' => $doc->id, 'message' => 'حُفظت نسخة جديدة بتاريخها وأُرسلت للمراجعة؛ النسخ السابقة محفوظة.'];
            }));
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
    }

    public function decision(Request $r, int $id, ArchiveAccess $access, ArchiveWorkflow $workflow, OperationOnce $once)
    {
        $doc = $access->documents('approve')->with('department')->findOrFail($id);
        $access->requireDocument($doc, 'approve');
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'decision' => 'required|in:approved,returned', 'note' => 'required|string|min:3|max:2000', 'acknowledge' => 'required|accepted']);

        return response()->json($once->run('archive.decision', $d['request_id'], ['id' => $id] + $r->except('request_id', '_token'), function () use ($id, $d, $access, $workflow) {
            $doc = $access->documents('approve')->lockForUpdate()->findOrFail($id);
            $v = $doc->versions()->where('number', $doc->current_version)->lockForUpdate()->firstOrFail();
            $authority = $access->reviewAuthority($doc);
            abort_unless($authority !== null, 403, 'اعتماد النسخة يتطلب مستخدمًا ومصدر تفويض مستقلين عن منشئها ورافعها.');
            abort_unless($doc->status === 'pending_review' && $v->status === 'pending_review' && (int) $doc->version === (int) $d['version'], 409, 'تغير المستند أو عولجت النسخة. حدّث الملف.');
            if ($d['decision'] === 'approved') {
                abort_unless(Storage::disk('local')->exists($v->path) && hash_equals($v->sha256, hash_file('sha256', Storage::disk('local')->path($v->path))), 409, 'النسخة غير موجودة أو تغيرت بصمتها؛ لا يمكن اعتمادها.');
            }
            $v->update(['status' => $d['decision'], 'decided_by' => auth()->id(), 'decided_delegation_id' => $authority['delegation_id'], 'decided_at' => now(), 'decision_note' => $d['note']]);
            $doc->update(['status' => $d['decision'], 'version' => $doc->version + 1]);
            app(AuditRecorder::class)->record('archive.'.$d['decision'], $doc, ['number' => $v->number, 'note' => $d['note'], 'authority' => $authority]);
            $workflow->notify($doc, [$doc->created_by, $v->uploaded_by], 'archive.decided', 'صدر قرار مراجعة مستند', $doc->code.' • '.$d['note']);

            return ['id' => $id, 'message' => 'حُفظ قرار المراجعة وأُبلغ أصحاب المستند.'];
        }));
    }

    public function file(int $id, int $number, string $mode, ArchiveAccess $access)
    {
        abort_unless(in_array($mode, ['preview', 'download', 'print'], true), 404);
        $doc = $access->documents()->with('department')->findOrFail($id);
        $v = $doc->versions()->where('number', $number)->firstOrFail();
        abort_unless($this->mayReadVersion($access, $doc, $v, $mode), 403, 'صلاحية الملف غير ممنوحة أو أن النسخة لا تزال تنتظر المراجعة.');
        abort_unless(Storage::disk('local')->exists($v->path), 404, 'ملف النسخة غير موجود.');
        abort_unless(hash_equals($v->sha256, hash_file('sha256', Storage::disk('local')->path($v->path))), 409, 'تغيرت بصمة الملف. أوقف الاستخدام وراجع المسؤول.');
        app(AuditRecorder::class)->record('archive.'.($mode === 'print' ? 'print_requested' : $mode), $doc, ['number' => $v->number, 'sha256' => $v->sha256]);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private', 'Content-Type' => $v->mime];

        return $mode === 'download' ? Storage::disk('local')->download($v->path, $v->original_name, $headers) : Storage::disk('local')->response($v->path, $v->original_name, $headers, 'inline');
    }

    public function audit(Request $r, int $id, ArchiveAccess $access)
    {
        $r->validate(['page' => 'nullable|integer|min:1']);
        $doc = $access->documents('audit')->with('department')->findOrFail($id);
        $access->requireDocument($doc, 'audit');

        $events = AuditLog::where('subject_type', 'archive_documents')->where('subject_id', $id)->orderByDesc('id')->paginate(30);
        $names = app(CompanyContext::class)->company->users()->whereIn('users.id', $events->pluck('actor_id'))->pluck('name', 'users.id');
        $events->through(fn ($event) => $event->toArray() + ['actor_name' => $names[$event->actor_id] ?? 'حساب سابق']);

        return response()->json(['events' => $events]);
    }

    public function department(Request $r, ArchiveAccess $access, OperationOnce $once)
    {
        abort_unless($access->manager(), 403);
        $company = app(CompanyContext::class)->id();
        $d = $r->validate(['request_id' => 'required|uuid', 'id' => 'nullable|integer', 'code' => ['required', 'string', 'max:40', 'regex:/\A[a-z][a-z0-9_-]{1,39}\z/', Rule::unique('archive_departments', 'code')->where('company_id', $company)->ignore($r->integer('id'))], 'name' => 'required|string|min:2|max:150', 'description' => 'nullable|string|max:1000', 'is_active' => 'required|boolean', 'version' => 'required_with:id|integer|min:1', 'reason' => 'required|string|min:3|max:1000']);

        return response()->json($once->run('archive.department', $d['request_id'], $r->except('request_id', '_token'), function () use ($d) {
            $doc = ! empty($d['id']) ? ArchiveDepartment::lockForUpdate()->findOrFail($d['id']) : new ArchiveDepartment;
            if ($doc->exists) {
                abort_unless((int) $doc->version === (int) $d['version'], 409, 'تغير القسم. حدّث بياناته.');
            }
            if ($doc->exists) {
                abort_unless($doc->code === $d['code'], 422, 'رمز القسم ثابت للحفاظ على ربط المحاضر. يمكن تعديل اسمه ووصفه وحالته.');
            }
            $before = $doc->exists ? $doc->only(['name', 'code', 'is_active', 'description']) : null;
            $doc->fill(collect($d)->only(['name', 'code', 'description', 'is_active'])->all());
            $doc->version = $doc->exists ? $doc->version + 1 : 1;
            $doc->save();
            app(AuditRecorder::class)->record('archive.department_saved', $doc, ['before' => $before, 'after' => $doc->only(['name', 'code', 'is_active', 'description']), 'reason' => $d['reason']]);

            return ['id' => $doc->id, 'message' => 'حُفظ القسم. لا تحذف هذه العملية ملفاته.'];
        }));
    }

    public function permissions(Request $r, ArchiveAccess $access, OperationOnce $once)
    {
        abort_unless($access->manager(), 403);
        $company = app(CompanyContext::class)->id();
        if ($r->isMethod('get')) {
            return response()->json(['users' => app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->get(['users.id', 'name', 'username'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'role' => $u->pivot->role]), 'departments' => ArchiveDepartment::orderBy('name')->get(), 'grants' => DB::table('archive_permissions')->where('company_id', $company)->get(), 'rights' => ArchiveAccess::RIGHTS]);
        }
        $rules = ['request_id' => 'required|uuid', 'user_id' => 'required|integer', 'department_id' => 'required|integer', 'max_confidentiality' => 'required|in:internal,confidential,restricted', 'version' => 'required|integer|min:0', 'reason' => 'required|string|min:3|max:1000'];
        foreach (ArchiveAccess::RIGHTS as $right) {
            $rules['can_'.$right] = 'required|boolean';
        }
        $d = $r->validate($rules);

        return response()->json($once->run('archive.permissions', $d['request_id'], $r->except('request_id', '_token'), function () use ($company, $d) {
            $department = ArchiveDepartment::lockForUpdate()->findOrFail($d['department_id']);
            $user = app(CompanyContext::class)->company->users()->where('users.id', $d['user_id'])->wherePivot('is_active', true)->lockForUpdate()->firstOrFail();
            abort_if($user->pivot->role === 'manager', 422, 'صلاحيات مدير الشركة الكاملة موروثة من دوره ولا تعدل من هذا النموذج.');
            $rights = collect($d)->only(array_map(fn ($v) => 'can_'.$v, ArchiveAccess::RIGHTS))->map(fn ($v) => (bool) $v)->all();
            abort_if(! $rights['can_view'] && collect($rights)->except('can_view')->contains(true), 422, 'الصلاحيات الأخرى تتطلب رؤية المستندات.');
            abort_if($user->pivot->role === 'viewer' && ($rights['can_upload'] || $rights['can_approve']), 422, 'حساب المشاهد للقراءة؛ لا تمنح له رفعًا أو اعتمادًا.');
            $key = ['company_id' => $company, 'department_id' => $department->id, 'user_id' => $user->id];
            $before = DB::table('archive_permissions')->where($key)->first();
            abort_unless((int) ($before?->version ?? 0) === (int) $d['version'], 409, 'تغيرت الصلاحيات منذ فتحها. أعد تحميل المستخدم والقسم.');
            if (! $rights['can_view']) {
                DB::table('archive_permissions')->where($key)->delete();
            } else {
                DB::table('archive_permissions')->updateOrInsert($key, $rights + ['version' => ($before?->version ?? 0) + 1, 'max_confidentiality' => $d['max_confidentiality'], 'created_at' => $before?->created_at ?? now(), 'updated_at' => now()]);
            }
            app(AuditRecorder::class)->record('archive.permissions_changed', $department, ['user_id' => $user->id, 'before' => (array) $before, 'after' => $rights + ['max_confidentiality' => $d['max_confidentiality']], 'reason' => $d['reason']]);

            return ['message' => 'حُفظت الصلاحيات. سحب العرض يمنع وصول المستخدم فورًا.'];
        }));
    }

    public function importHandovers(Request $r, ArchiveAccess $access, ArchiveWorkflow $workflow, OperationOnce $once)
    {
        abort_unless($access->manager(), 403);
        $d = $r->validate(['request_id' => 'required|uuid', 'file_id' => 'required|integer', 'note' => 'required|string|min:3|max:1000']);

        return response()->json($once->run('archive.import', $d['request_id'], $r->except('request_id', '_token'), function () use ($d, $workflow) {
            $file = HandoverFile::lockForUpdate()->findOrFail($d['file_id']);
            $doc = $workflow->registerHandover($file);
            app(AuditRecorder::class)->record('archive.import_requested', $doc, ['file_id' => $file->id, 'note' => $d['note']]);

            return ['id' => $doc->id, 'message' => 'رُبطت النسخة بالأرشيف دون نقل أو نسخ الملف ودون تغيير المحضر.'];
        }));
    }

    public function imports(ArchiveAccess $access)
    {
        abort_unless($access->manager(), 403);

        return response()->json(['files' => HandoverFile::whereNotIn('id', ArchiveDocument::whereNotNull('source_handover_file_id')->select('source_handover_file_id'))->orderByDesc('id')->paginate(20)]);
    }

    public function export(Request $r, ArchiveAccess $access)
    {
        abort_unless($access->manager() || $access->grants()->where('can_view', true)->where('can_export', true)->exists(), 403);
        $query = $this->filtered($r, $access, 'export')->orderBy('id')->select(['code', 'title', 'department_id', 'document_type', 'confidentiality', 'status', 'current_version', 'document_date', 'expires_on'])->toBase();
        app(AuditRecorder::class)->record('archive.export_requested', app(CompanyContext::class)->company, ['filters' => $r->only('q', 'department_id', 'status')]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['المرجع', 'العنوان', 'القسم', 'النوع', 'السرية', 'الحالة', 'النسخة', 'تاريخ المستند', 'الانتهاء'], ',', '"', '');
            foreach ($query->cursor() as $row) {
                $cells = array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/u', (string) $v) ? "'".$v : $v, array_values((array) $row));
                fputcsv($out, $cells, ',', '"', '');
            }fclose($out);
        }, 'amrbit-archive.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
