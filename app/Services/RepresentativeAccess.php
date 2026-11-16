<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

class RepresentativeAccess
{
    public function membership(int $userId)
    {
        return DB::table('company_user')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $userId)->first();
    }

    public function assertCapability(int $userId, string $capability): void
    {
        $member = $this->membership($userId);
        abort_unless($member && $member->is_active && $member->role === 'representative', 422, 'المندوب غير فعال في هذه الشركة.');
        abort_unless((bool) $member->{$capability}, 403, $capability === 'can_sell' ? 'صلاحية البيع غير مفعلة لهذا المندوب.' : 'صلاحية استلام الأموال غير مفعلة لهذا المندوب.');
    }

    public function assertCustomer(int $userId, Customer $customer): void
    {
        $member = $this->membership($userId);
        abort_unless($member && $member->is_active && $member->role === 'representative', 422, 'المندوب غير فعال في هذه الشركة.');
        abort_if($customer->representative_id !== null && (int) $customer->representative_id !== $userId, 422, 'هذا العميل مسند لمندوب آخر. راجع مسؤول العميل أولًا.');
        if ($member->area_access !== 'all') {
            abort_unless(DB::table('representative_areas')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $userId)->where('area_id', $customer->area_id)->exists(), 422, 'منطقة العميل غير مسموحة لهذا المندوب. عدّل التغطية من إدارة الفريق.');
        }
    }
}
