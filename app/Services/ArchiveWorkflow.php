<?php

namespace App\Services;

use App\Models\ArchiveDepartment;
use App\Models\ArchiveDocument;
use App\Models\ArchiveVersion;
use App\Models\HandoverFile;
use App\Models\HandoverRequest;
use App\Models\WorkspaceNotification;
use App\Support\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchiveWorkflow
{
    public function reviewers(ArchiveDocument $document): array
    {
        if (! $document->department->is_active) {
            return [];
        }

        return app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->wherePivot('role', '!=', 'viewer')->get(['users.id', 'users.name'])->filter(fn ($u) => app(ArchiveAccess::class)->reviewAuthority($document, $u->id) !== null)->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();
    }

    public function requestReview(ArchiveDocument $document): void
    {
        $ids = array_column($this->reviewers($document), 'id');
        if (! $ids) {
            $ids = app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->wherePivot('role', 'manager')->pluck('users.id')->all();
        }
        $this->notify($document, $ids, 'archive.review', 'مستند ينتظر مراجعة مستقلة', $document->code.' • '.$document->title);
    }

    public function notify(ArchiveDocument $document, array $ids, string $event, string $title, string $body): void
    {
        $active = app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->whereIn('users.id', array_unique(array_filter($ids)))->get(['users.id']);
        foreach ($active as $user) {
            $allowed = app(ArchiveAccess::class)->allowsForUser($document->department, $user->id, 'view', $document->confidentiality);
            if ($allowed) {
                WorkspaceNotification::create(['user_id' => $user->id, 'archive_document_id' => $document->id, 'event' => $event, 'title' => $title, 'body' => $body, 'created_at' => now()]);
            }
        }
    }

    public function storeVersion(ArchiveDocument $document, UploadedFile $file, string $note, bool $signed, ?string &$createdPath): ArchiveVersion
    {
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$file->getMimeType()] ?? null;
        abort_unless($extension, 422, 'يسمح بملف PDF أو صورة JPEG/PNG فقط.');
        $name = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 180, '');
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'archive-document.'.$extension;
        $createdPath = $file->storeAs('archive/'.$document->company_id.'/'.$document->id, Str::uuid().'.'.$extension, 'local');
        abort_unless($createdPath, 500, 'تعذر حفظ النسخة.');
        $version = ArchiveVersion::create(['document_id' => $document->id, 'number' => $document->current_version + 1, 'uploaded_by' => auth()->id(), 'original_name' => $name, 'path' => $createdPath, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'note' => $note, 'signed_copy' => $signed, 'metadata_snapshot' => $document->only(['title', 'document_type', 'document_date', 'expires_on', 'retain_until', 'reference', 'physical_reference', 'notes', 'confidentiality']), 'created_at' => now()]);
        $document->update(['current_version' => $version->number, 'status' => 'pending_review', 'version' => $document->version + 1]);
        app(AuditRecorder::class)->record('archive.version_uploaded', $document, ['version' => $version->number, 'sha256' => $version->sha256, 'note' => $note]);
        $this->requestReview($document);

        return $version;
    }

    public function registerHandover(HandoverFile $file): ArchiveDocument
    {
        $existing = ArchiveDocument::where('source_handover_file_id', $file->id)->first();
        if ($existing) {
            return $existing;
        }
        $handover = HandoverRequest::findOrFail($file->handover_id);
        abort_unless($handover->status === 'accepted', 422, 'لا يؤرشف محضر غير معتمد.');
        abort_unless(Storage::disk('local')->exists($file->path), 404, 'ملف المحضر غير موجود.');
        abort_unless(hash_equals($file->sha256, hash_file('sha256', Storage::disk('local')->path($file->path))), 409, 'بصمة الملف لا تطابق النسخة المحفوظة.');
        [$code,$name] = match ($handover->kind) {
            'vehicle_delivery','vehicle_return' => ['transport', 'النقل والمركبات'],'stock_transfer' => ['warehouses', 'المخازن'],'cash' => ['finance', 'المالية'],default => ['management', 'الإدارة والعهد']
        };
        $department = ArchiveDepartment::firstOrCreate(['code' => $code], ['name' => $name, 'description' => 'محاضر ومستندات القسم.']);
        // Internal registration follows the already-authorized handover upload; it grants nobody archive access.
        $document = ArchiveDocument::create(['department_id' => $department->id, 'code' => 'AR-'.Str::upper(Str::random(12)), 'title' => 'نسخة محضر '.$handover->code, 'document_type' => 'محضر تسليم واستلام', 'reference' => $handover->code, 'document_date' => $file->created_at->toDateString(), 'notes' => 'نسخة مرفوعة من مسار التسليم والاستلام. وصفها موقعة إقرار من الرافع، وليس تحقق توقيع آليًا.', 'created_by' => $file->user_id, 'source_handover_file_id' => $file->id, 'confidentiality' => 'internal', 'current_version' => 1]);
        ArchiveVersion::create(['document_id' => $document->id, 'number' => 1, 'uploaded_by' => $file->user_id, 'original_name' => $file->original_name, 'path' => $file->path, 'mime' => $file->mime, 'size' => $file->size, 'sha256' => $file->sha256, 'note' => 'ربط النسخة الأصلية دون نسخ الملف أو تغيير المحضر.', 'signed_copy' => true, 'metadata_snapshot' => $document->only(['title', 'document_type', 'document_date', 'reference', 'notes', 'confidentiality']), 'created_at' => $file->created_at]);
        app(AuditRecorder::class)->record('archive.handover_registered', $document, ['handover_id' => $handover->id, 'file_id' => $file->id]);
        $this->requestReview($document);

        return $document;
    }
}
