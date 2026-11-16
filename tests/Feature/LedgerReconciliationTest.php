<?php

namespace Tests\Feature;

use Database\Seeders\CreditDemoSeeder;
use Database\Seeders\OperationsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_dataset_reconciles_stocks_payments_and_company_debts(): void
    {
        config(['fieldflow.demo' => true]);
        $this->seed([OperationsDemoSeeder::class, CreditDemoSeeder::class]);
        foreach (DB::table('stock_balances')->get() as $b) {
            $quantity = DB::table('stock_movements')->where('company_id', $b->company_id)->where('warehouse_id', $b->warehouse_id)->where('product_id', $b->product_id)->sum('quantity');
            $this->assertSame((int) $quantity, (int) $b->quantity);
            $this->assertGreaterThanOrEqual(0, $b->quantity);
        }
        foreach (DB::table('orders')->get() as $o) {
            $paid = DB::table('collections')->where('company_id', $o->company_id)->where('order_id', $o->id)->sum('amount_minor');
            $lines = DB::table('order_items')->where('company_id', $o->company_id)->where('order_id', $o->id)->sum('total_minor');
            $this->assertSame((int) $paid, (int) $o->paid_minor);
            $this->assertSame((int) $lines, (int) $o->total_minor);
            $this->assertGreaterThanOrEqual($o->paid_minor, $o->total_minor);
        }
        $counts = [DB::table('orders')->count(), DB::table('stock_movements')->count(), DB::table('collections')->count()];
        $this->seed([OperationsDemoSeeder::class, CreditDemoSeeder::class]);
        $this->assertSame($counts, [DB::table('orders')->count(), DB::table('stock_movements')->count(), DB::table('collections')->count()]);
    }
}
