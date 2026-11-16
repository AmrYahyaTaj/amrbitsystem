<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QualityDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('fieldflow.demo')) {
            throw new \RuntimeException('Quality demo accounts are restricted to local synthetic environments.');
        }
        $company = Company::where('slug', 'naba')->where('is_active', true)->firstOrFail();
        $context = app(CompanyContext::class);
        try {
            $context->set($company, CompanyRole::Manager);
            DB::transaction(function () use ($company) {
                foreach ([['qa_demo', 'نورة • فاحص جودة تجريبي', 'quality_inspector'], ['qa_manager_demo', 'عمر • معتمد جودة تجريبي', 'quality_manager']] as [$username, $name, $role]) {
                    $user = User::firstOrCreate(['username' => $username], ['name' => $name, 'password' => '123456']);
                    if (! $user->wasRecentlyCreated && $user->name !== $name) {
                        throw new \RuntimeException('Demo username conflict; existing account was not changed.');
                    }
                    $membership = $company->users()->where('users.id', $user->id)->first();
                    if ($membership && $membership->pivot->role !== $role) {
                        throw new \RuntimeException('Demo role conflict; existing role was not changed.');
                    }
                    if (! $membership) {
                        $company->users()->attach($user->id, ['role' => $role, 'is_active' => true, 'area_access' => 'selected', 'can_sell' => false, 'can_collect' => false, 'notes' => 'حساب جودة تجريبي؛ لا يغيّر أي اعتماد أو رصيد تلقائيًا.']);
                    }
                    // Preserve manager revocations and suspended accounts on repeated seeding.
                    if ($membership) {
                        continue;
                    }
                    foreach (Warehouse::where('type', '!=', 'vehicle')->where('is_active', true)->pluck('id') as $site) {
                        DB::table('quality_site_access')->insertOrIgnore(['company_id' => $company->id, 'user_id' => $user->id, 'warehouse_id' => $site, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            });
        } finally {
            $context->clear();
        }
        $this->command?->info('qa_demo and qa_manager_demo are ready. Initial password 123456 applies to newly created accounts only. No lots, inspections, approvals or stock movements created.');
    }
}
