<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\ArchiveDepartment;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

class ArchiveStructureSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(CompanyContext::class);
        try {
            foreach (Company::where('is_active', true)->get() as $company) {
                $context->set($company, CompanyRole::Manager);
                foreach (['production' => 'الإنتاج', 'quality' => 'الجودة', 'warehouses' => 'المخازن', 'transport' => 'النقل والمركبات', 'sales' => 'المبيعات والعملاء', 'finance' => 'المالية', 'purchases' => 'المشتريات والموردون', 'personnel' => 'الموظفون', 'management' => 'الإدارة والعهد', 'licenses' => 'التراخيص والسياسات'] as $code => $name) {
                    ArchiveDepartment::firstOrCreate(['code' => $code], ['name' => $name, 'description' => 'ملفات ومحاضر '.$name.'.']);
                }
            }
        } finally {
            $context->clear();
        }
        $this->command?->info('Archive sections initialized. No user permissions, documents or approvals changed.');
    }
}
