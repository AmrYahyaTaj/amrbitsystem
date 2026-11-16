<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visit;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('fieldflow.demo')) {
            throw new \RuntimeException('Demo data is allowed only in local/testing with FIELDFLOW_DEMO=true.');
        }
        $context = app(CompanyContext::class);
        $credentials = [];
        try {
            DB::transaction(function () use ($context, &$credentials) {
                $password = Str::password(22);
                $manager = $this->demoUser('مدير العرض التجريبي', 'manager@fieldflow.test', $password, $credentials);
                foreach ([
                    ['slug' => 'demo-water', 'name' => 'شركة الينبوع التجريبية', 'industry' => 'water', 'rep' => 'rep.water@fieldflow.test'],
                    ['slug' => 'demo-food', 'name' => 'شركة المدار التجريبية', 'industry' => 'food', 'rep' => 'rep.food@fieldflow.test'],
                ] as $i => $data) {
                    $company = Company::firstOrCreate(['slug' => $data['slug']], ['name' => $data['name'], 'industry' => $data['industry']]);
                    $rep = $this->demoUser('مندوب تجريبي '.($i + 1), $data['rep'], $password, $credentials);
                    foreach ([$manager->id => 'manager', $rep->id => 'representative'] as $userId => $role) {
                        if (! $company->users()->where('users.id', $userId)->exists()) {
                            $company->users()->attach($userId, ['role' => $role, 'is_active' => true]);
                        }
                    }
                    $context->set($company, CompanyRole::Manager);
                    $area = Area::firstOrCreate(['name' => 'المنطقة التجريبية'], ['city' => 'الرياض']);
                    foreach (['متجر ألف التجريبي', 'متجر باء التجريبي', 'متجر جيم التجريبي'] as $j => $name) {
                        $customer = Customer::firstOrCreate(['code' => 'DEMO-'.($j + 1)], [
                            'name' => $name, 'area_id' => $area->id, 'address' => 'عنوان مصطنع للعرض فقط',
                            'latitude' => 24.7136 + $j * 0.002, 'longitude' => 46.6753 + $j * 0.002,
                            'geofence_radius_m' => 150,
                        ]);
                        Visit::where('customer_id', $customer->id)
                            ->where('representative_id', $rep->id)
                            ->whereDate('scheduled_for', now()->timezone($company->timezone)->toDateString())
                            ->firstOrCreate([], [
                                'customer_id' => $customer->id, 'representative_id' => $rep->id,
                                'scheduled_for' => now()->timezone($company->timezone)->toDateString(),
                            ]);
                    }
                    Vehicle::firstOrCreate(['plate' => 'DEMO-'.($i + 1)], ['name' => 'سيارة توزيع تجريبية']);
                }
            });
        } finally {
            $context->clear();
        }
        if ($credentials && ! app()->environment('testing')) {
            $directory = base_path('.runtime');
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            file_put_contents($directory.'/demo-access.txt', implode(PHP_EOL, $credentials).PHP_EOL, FILE_APPEND | LOCK_EX);
            $this->command?->info('New demo credentials saved in .runtime/demo-access.txt. Passwords are not reset on re-run.');
        }
    }

    private function demoUser(string $name, string $email, string $password, array &$credentials): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);
        if ($user->wasRecentlyCreated) {
            $credentials[] = $email.' | '.$password;
        }

        return $user;
    }
}
