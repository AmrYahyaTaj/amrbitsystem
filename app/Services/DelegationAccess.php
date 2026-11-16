<?php

namespace App\Services;

use App\Models\ArchiveDepartment;
use App\Models\TemporaryDelegation;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

class DelegationAccess
{
    public const DOMAINS = ['quality_inspect' => 'فحص الجودة', 'quality_approve' => 'اعتماد الجودة', 'archive_review' => 'مراجعة الأرشيف'];

    public function member(int $id)
    {
        return DB::table('company_user')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $id)->where('is_active', true)->first();
    }

    public function nativeQuality(int $id, int $site, string $domain): bool
    {
        $m = $this->member($id);
        if (! $m) {
            return false;
        }
        if ($domain === 'quality_approve' && in_array($m->role, ['manager', 'supervisor'], true)) {
            return Warehouse::whereKey($site)->where('is_active', true)->where('type', '!=', 'vehicle')->exists();
        }
        if ($m->role !== ($domain === 'quality_inspect' ? 'quality_inspector' : 'quality_manager')) {
            return false;
        }

        return DB::table('quality_site_access')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $id)->where('warehouse_id', $site)->whereIn('warehouse_id', Warehouse::where('is_active', true)->where('type', '!=', 'vehicle')->select('id'))->exists();
    }

    public function nativeArchive(int $id, int $department, string $right, string $level): bool
    {
        $member = $this->member($id);
        if (! $member || ! in_array($right, ArchiveAccess::RIGHTS, true) || ! in_array($level, ArchiveAccess::LEVELS, true)) {
            return false;
        }
        $active = ArchiveDepartment::whereKey($department)->value('is_active');
        if ($active === null) {
            return false;
        }
        if (! $active && in_array($right, ['upload', 'approve'], true)) {
            return false;
        }
        if ($member->role === 'manager') {
            return true;
        }
        if (! $active || ($member->role === 'viewer' && in_array($right, ['upload', 'approve'], true))) {
            return false;
        }
        $grant = DB::table('archive_permissions')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $id)->where('department_id', $department)->where('can_view', true)->where('can_'.$right, true)->first();

        return $grant && array_search($level, ArchiveAccess::LEVELS, true) <= array_search($grant->max_confidentiality, ArchiveAccess::LEVELS, true);
    }

    public function validSource(TemporaryDelegation $d): bool
    {
        $p = $this->member($d->principal_id);
        $u = $this->member($d->delegate_id);
        if (! $p || ! $u || $p->role !== $d->principal_role || $u->role !== $d->delegate_role || $p->user_id === $u->user_id) {
            return false;
        }
        if ($d->domain === 'archive_review') {
            return $u->role !== 'viewer' && ArchiveDepartment::whereKey($d->department_id)->where('is_active', true)->exists() && $this->nativeArchive($p->user_id, $d->department_id, 'preview', $d->max_confidentiality) && $this->nativeArchive($p->user_id, $d->department_id, 'approve', $d->max_confidentiality);
        }
        if (! in_array($d->domain, ['quality_inspect', 'quality_approve'], true)) {
            return false;
        }
        $roles = $d->domain === 'quality_inspect' ? ['quality_inspector'] : ['quality_manager', 'manager', 'supervisor'];

        return in_array($u->role, $roles, true) && $this->nativeQuality($p->user_id, $d->warehouse_id, $d->domain);
    }

    public function activeFor(int $user, ?string $domain = null)
    {
        return TemporaryDelegation::where('delegate_id', $user)->where('status', 'accepted')->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())->when($domain, fn ($q) => $q->where('domain', $domain))->orderBy('id')->get()->filter(fn ($d) => $this->validSource($d))->values();
    }

    public function qualitySites(int $user, string $domain)
    {
        return $this->activeFor($user, $domain)->pluck('warehouse_id')->unique()->values();
    }

    public function qualityAuthority(int $user, int $site, string $domain, array $excluded): ?array
    {
        $excluded = array_map('intval', $excluded);
        if (in_array($user, $excluded, true)) {
            return null;
        }
        if ($this->nativeQuality($user, $site, $domain)) {
            return ['delegation_id' => null, 'principal_id' => $user, 'mode' => 'native'];
        }
        $d = $this->activeFor($user, $domain)->first(fn ($d) => (int) $d->warehouse_id === $site && ! in_array((int) $d->principal_id, $excluded, true));

        return $d ? ['delegation_id' => $d->id, 'principal_id' => $d->principal_id, 'mode' => 'delegated'] : null;
    }

    public function archiveGrants(int $user)
    {
        return $this->activeFor($user, 'archive_review')->map(function ($d) {
            $grant = ['department_id' => $d->department_id, 'max_confidentiality' => $d->max_confidentiality, 'delegation_id' => $d->id];
            foreach (ArchiveAccess::RIGHTS as $right) {
                $grant['can_'.$right] = in_array($right, ['view', 'preview', 'approve'], true) || ($right === 'audit' && $this->nativeArchive($d->principal_id, $d->department_id, 'audit', $d->max_confidentiality));
            }

            return (object) $grant;
        });
    }

    public function archiveAuthority(int $user, int $department, string $level, array $excluded): ?array
    {
        $excluded = array_map('intval', $excluded);
        if (in_array($user, $excluded, true)) {
            return null;
        }
        if ($this->nativeArchive($user, $department, 'approve', $level)) {
            return ['delegation_id' => null, 'principal_id' => $user, 'mode' => 'native'];
        }
        $d = $this->activeFor($user, 'archive_review')->first(fn ($d) => (int) $d->department_id === $department && ! in_array((int) $d->principal_id, $excluded, true) && array_search($level, ArchiveAccess::LEVELS, true) <= array_search($d->max_confidentiality, ArchiveAccess::LEVELS, true));

        return $d ? ['delegation_id' => $d->id, 'principal_id' => $d->principal_id, 'mode' => 'delegated'] : null;
    }

    public function describe(?int $id): ?array
    {
        if (! $id) {
            return null;
        }$d = TemporaryDelegation::with(['principal:id,name', 'delegate:id,name'])->find($id);

        return $d ? ['id' => $d->id, 'code' => $d->code, 'principal' => $d->principal->only(['id', 'name']), 'delegate' => $d->delegate->only(['id', 'name']), 'domain' => self::DOMAINS[$d->domain]] : null;
    }
}
