<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\ArchiveDepartment;
use App\Models\ArchiveDocument;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

class ArchiveAccess
{
    public const RIGHTS = ['view', 'preview', 'upload', 'download', 'print', 'approve', 'audit', 'export'];

    public const LEVELS = ['internal', 'confidential', 'restricted'];

    public function manager(): bool
    {
        return app(CompanyContext::class)->role === CompanyRole::Manager;
    }

    public function grants()
    {
        return DB::table('archive_permissions')->where('company_id', app(CompanyContext::class)->id())->where('user_id', auth()->id());
    }

    public function effectiveGrants(?int $user = null)
    {
        $user ??= auth()->id();

        return DB::table('archive_permissions')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $user)->get()->concat(app(DelegationAccess::class)->archiveGrants($user));
    }

    public function departments()
    {
        return ArchiveDepartment::when(! $this->manager(), fn ($q) => $q->where('is_active', true)->whereIn('id', $this->effectiveGrants()->where('can_view', true)->pluck('department_id')));
    }

    public function mayEnter(): bool
    {
        return $this->manager() || $this->departments()->exists();
    }

    public function allowsForUser(ArchiveDepartment $department, int $user, string $right, string $level = 'internal'): bool
    {
        if (! in_array($right, self::RIGHTS, true) || ! in_array($level, self::LEVELS, true)) {
            return false;
        }
        $access = app(DelegationAccess::class);
        $member = $access->member($user);
        if (! $member) {
            return false;
        }
        if ($access->nativeArchive($user, $department->id, $right, $level)) {
            return true;
        }
        if (! $department->is_active || ($member->role === 'viewer' && in_array($right, ['upload', 'approve'], true))) {
            return false;
        }

        return $this->effectiveGrants($user)->contains(fn ($g) => (int) $g->department_id === (int) $department->id && $g->can_view && $g->{'can_'.$right} && array_search($level, self::LEVELS, true) <= array_search($g->max_confidentiality, self::LEVELS, true));
    }

    public function allows(ArchiveDepartment $department, string $right, string $level = 'internal'): bool
    {
        return $this->allowsForUser($department, auth()->id(), $right, $level);
    }

    public function documents(string $right = 'view')
    {
        abort_unless(in_array($right, self::RIGHTS, true), 403);
        $q = ArchiveDocument::query();
        if ($this->manager()) {
            return $q;
        }
        $ids = ArchiveDepartment::where('is_active', true)->pluck('id');
        $grants = $this->effectiveGrants()->filter(fn ($g) => $g->can_view && $g->{'can_'.$right} && $ids->contains($g->department_id));

        return $q->where(function ($b) use ($grants) {
            $b->whereRaw('1 = 0');
            foreach ($grants as $g) {
                $levels = array_slice(self::LEVELS, 0, array_search($g->max_confidentiality, self::LEVELS, true) + 1);
                $b->orWhere(fn ($s) => $s->where('department_id', $g->department_id)->whereIn('confidentiality', $levels));
            }
        });
    }

    public function reviewAuthority(ArchiveDocument $document, ?int $user = null): ?array
    {
        $user ??= auth()->id();
        $latest = $document->versions()->where('number', $document->current_version)->first();
        if (! $latest) {
            return null;
        }

        return app(DelegationAccess::class)->archiveAuthority($user, $document->department_id, $document->confidentiality, [$document->created_by, $latest->uploaded_by]);
    }

    public function requireDocument(ArchiveDocument $document, string $right): void
    {
        abort_unless($this->allows($document->department, $right, $document->confidentiality), 403, 'هذه الصلاحية غير ممنوحة لك في قسم المستند ودرجة سريته.');
    }
}
