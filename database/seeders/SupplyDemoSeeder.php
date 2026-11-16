<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplyDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('fieldflow.demo')) {
            throw new \RuntimeException('Local synthetic accounts only.');
        }
        $company = Company::where('slug', 'naba')->where('is_active', true)->firstOrFail();
        $context = app(CompanyContext::class);
        try {
            $context->set($company, CompanyRole::Manager);
            DB::transaction(function () use ($company) {
                foreach ([['AMR-PAPER-BOX', 'كرتون مناديل • صنف إنتاج تجريبي', 'كرتون'], ['AMR-RAW-ROLL', 'رول ورق • خامة تجريبية', 'رول']] as [$sku,$productName,$unit]) {
                    Product::firstOrCreate(['sku' => $sku], ['name' => $productName, 'unit' => $unit, 'price_minor' => 1000, 'reorder_level' => 20]);
                }
                foreach ([
                    ['store_demo', 'مازن • أمين مستودع تجريبي', 'warehouse_keeper', 'DEMO-HO-STORE', 'STORE-DEMO', 'المخزن • تجربة سلسلة الإمداد', 'warehouse'],
                    ['production_demo', 'علي • مسؤول إنتاج تجريبي', 'production', 'DEMO-PRODUCTION', 'PROD-DEMO', 'الإنتاج • تجربة سلسلة الإمداد', 'production'],
                ] as [$username,$name,$role,$employee,$code,$site,$type]) {
                    $user = User::firstOrCreate(['username' => $username], ['name' => $name, 'password' => '123456']);
                    if (! $user->wasRecentlyCreated && $user->name !== $name) {
                        throw new \RuntimeException('Demo username conflict; no existing account changed.');
                    }
                    $member = $company->users()->where('users.id', $user->id)->first();
                    if ($member && $member->pivot->role !== $role) {
                        throw new \RuntimeException('Demo role conflict; no existing permissions changed.');
                    }
                    if (! $member) {
                        $company->users()->attach($user->id, ['role' => $role, 'is_active' => true, 'employee_code' => $employee, 'area_access' => 'selected', 'can_sell' => false, 'can_collect' => false, 'notes' => 'حساب تجريبي لدورة الإنتاج والمخزون؛ لا بيانات حقيقية.']);
                    }
                    $w = Warehouse::firstOrCreate(['code' => $code], ['name' => $site, 'type' => $type, 'custodian_id' => $user->id, 'notes' => 'موقع تجريبي مستقل؛ ابدأ أمر إنتاج وطلبات تسليم من الحسابات نفسها.']);
                    if ((int) $w->custodian_id !== $user->id || $w->type !== $type) {
                        throw new \RuntimeException('Demo location conflict; no existing custody reassigned.');
                    }
                }
            });
        } finally {
            $context->clear();
        }
        $this->command?->info('production_demo and store_demo are ready. Initial password 123456 for newly created accounts only. No balances, requests or approvals created.');
    }
}
