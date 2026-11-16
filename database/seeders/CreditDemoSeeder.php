<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Order;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

class CreditDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('fieldflow.demo')) {
            throw new \RuntimeException('Local demo only.');
        }
        $context = app(CompanyContext::class);
        try {
            foreach (Company::whereIn('slug', ['naba', 'madar'])->get() as $c) {
                $context->set($c, CompanyRole::Manager);
                foreach (Order::where('code', 'like', 'SO-DEMO-%')->whereNull('due_date')->get() as $i => $o) {
                    $o->due_date = today('Asia/Riyadh')->addDays(($i % 3) - 2)->toDateString();
                    $o->payment_terms = $o->paid_minor === $o->total_minor ? 'cash' : ($o->paid_minor > 0 ? 'partial' : 'credit');
                    $o->initial_paid_minor = $o->paid_minor;
                    $o->save();
                }
            }
        } finally {
            $context->clear();
        }
    }
}
