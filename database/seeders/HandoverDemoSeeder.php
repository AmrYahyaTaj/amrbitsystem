<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HandoverDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('fieldflow.demo')) {
            throw new \RuntimeException('Synthetic handover accounts are local demo only.');
        }
        $company = Company::where('slug', 'naba')->where('is_active', true)->firstOrFail();
        $context = app(CompanyContext::class);
        $created = [];
        try {
            $context->set($company, CompanyRole::Manager);
            DB::transaction(function () use ($company, &$created) {
                foreach ([['driver_demo', 'سالم • سائق تجريبي', 'driver', 'DEMO-HO-DRIVER'], ['store_demo', 'مازن • أمين مستودع تجريبي', 'warehouse_keeper', 'DEMO-HO-STORE']] as [$username, $name, $role, $code]) {
                    $user = User::firstOrCreate(['username' => $username], ['name' => $name, 'password' => '123456']);
                    if ($user->wasRecentlyCreated) {
                        $created[] = $username.' | 123456';
                    } elseif ($user->name !== $name) {
                        throw new \RuntimeException('A demo username is already used by another account; no account was taken over.');
                    }
                    $membership = $company->users()->where('users.id', $user->id)->first();
                    if ($membership && $membership->pivot->role !== $role) {
                        throw new \RuntimeException('Existing demo membership has a different role; no permissions were changed.');
                    }
                    if (! $membership) {
                        $company->users()->attach($user->id, ['role' => $role, 'is_active' => true, 'employee_code' => $code, 'area_access' => 'selected', 'can_sell' => false, 'can_collect' => false, 'notes' => 'حساب مصطنع لتجربة اعتماد العهد بين الطرفين فقط.']);
                    }
                    if ($role === 'driver') {
                        $driver = Driver::firstOrCreate(['employee_code' => $code], ['name' => $name, 'user_id' => $user->id, 'license_number' => 'LOCAL-DEMO-ONLY', 'license_expires_on' => now()->addYear()->toDateString(), 'status' => 'active', 'notes' => 'ملف تجريبي منفصل؛ لا يغير السائق أو المركبات السابقة.']);
                        if ((int) $driver->user_id !== $user->id) {
                            throw new \RuntimeException('Existing demo driver belongs to another account.');
                        }
                    }
                }
            });
        } finally {
            $context->clear();
        }
        if ($created && ! app()->environment('testing')) {
            if (! is_dir(base_path('.runtime'))) {
                mkdir(base_path('.runtime'), 0777, true);
            }
            file_put_contents(base_path('.runtime/demo-access.txt'), implode(PHP_EOL, $created).PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        $this->command?->info('Synthetic accounts ready: driver_demo and store_demo. No requests or acceptances were created. Existing passwords retained.');
    }
}
