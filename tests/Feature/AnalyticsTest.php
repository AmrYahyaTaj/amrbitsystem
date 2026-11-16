<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\OperationsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00', 'Asia/Riyadh'));
        config(['fieldflow.demo' => true]);
        $this->seed(OperationsDemoSeeder::class);
        $this->company = Company::where('slug', 'naba')->firstOrFail();
        $this->actingAs(User::where('username', 'amr')->firstOrFail())->withSession(['company_id' => $this->company->id]);
    }

    public function test_daily_sales_reconcile_to_period_total_and_visits(): void
    {
        $json = $this->getJson('/ops/analytics?from=2026-10-07&to=2026-10-13')->assertOk()->json();
        $this->assertSame(885300, $json['summary']['sales_minor']);
        $this->assertSame(885300, array_sum(array_column($json['daily'], 'sales_minor')));
        $this->assertSame(13, $json['summary']['completed']);
        $this->assertSame(13, array_sum(array_column($json['daily'], 'completed')));
    }

    public function test_representative_filter_matches_export(): void
    {
        $rep = User::where('username', 'khalid')->firstOrFail();
        $qs = 'from=2026-10-13&to=2026-10-13&representative_id='.$rep->id;
        $this->getJson('/ops/analytics?'.$qs)->assertOk()->assertJsonPath('summary.sales_minor', 14400)->assertJsonPath('summary.orders', 1);
        $csv = $this->get('/reports/export?type=sales&'.$qs)->assertOk()->streamedContent();
        $this->assertStringContainsString('SO-DEMO-0-0', $csv);
        $this->assertStringNotContainsString('SO-DEMO-1-0', $csv);
    }

    public function test_collections_during_period_can_include_old_invoices_without_new_sales(): void
    {
        DB::table('orders')->where('company_id', $this->company->id)->update(['created_at' => CarbonImmutable::parse('2026-09-01')->utc()]);
        $this->getJson('/ops/analytics?from=2026-10-13&to=2026-10-13')->assertOk()->assertJsonPath('summary.sales_minor', 0)->assertJsonPath('summary.collections_minor', 56700);
    }

    public function test_foreign_filters_are_rejected_and_viewer_cannot_read_money(): void
    {
        $foreign = User::where('username', 'khalid2')->firstOrFail();
        $this->getJson('/ops/analytics?from=2026-10-13&to=2026-10-13&representative_id='.$foreign->id)->assertUnprocessable();
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', auth()->id())->update(['role' => 'viewer']);
        $this->getJson('/ops/analytics?from=2026-10-13&to=2026-10-13')->assertOk()->assertJsonPath('summary.sales_minor', null)->assertJsonCount(0, 'aging');
    }

    public function test_performance_and_payment_methods_reconcile_to_filtered_period_totals(): void
    {
        $json = $this->getJson('/ops/analytics?from=2026-10-07&to=2026-10-13')->assertOk()->json();
        $this->assertSame($json['summary']['sales_minor'], array_sum(array_column($json['performance'], 'sales_minor')));
        $this->assertSame($json['summary']['collections_minor'], array_sum(array_column($json['performance'], 'collections_minor')));
        $this->assertSame($json['summary']['collections_minor'], array_sum(array_column($json['collection_methods'], 'amount_minor')));
        $this->assertSame($json['summary']['visits'], array_sum(array_column($json['performance'], 'visits')));
        $this->assertSame($json['summary']['completed'], array_sum(array_column($json['performance'], 'completed')));
        $rep = User::where('username', 'khalid')->firstOrFail();
        $filtered = $this->getJson('/ops/analytics?from=2026-10-07&to=2026-10-13&representative_id='.$rep->id)->assertOk()->json();
        $this->assertCount(1, $filtered['performance']);
        $this->assertSame($rep->id, $filtered['performance'][0]['representative_id']);
    }

    public function test_viewer_performance_does_not_expose_financial_breakdown(): void
    {
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', auth()->id())->update(['role' => 'viewer']);
        $json = $this->getJson('/ops/analytics?from=2026-10-07&to=2026-10-13')->assertOk()->json();
        $this->assertSame([], $json['collection_methods']);
        $this->assertNotEmpty($json['performance']);
        foreach ($json['performance'] as $row) {
            $this->assertNull($row['sales_minor']);
            $this->assertNull($row['collections_minor']);
            $this->assertNull($row['remaining_minor']);
        }
    }
}
