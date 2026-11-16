<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Database\Seeders\QualityDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QualityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $producer;

    private User $inspector;

    private User $approver;

    private Warehouse $site;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Synthetic quality', 'slug' => 'quality-test']);
        foreach (['manager' => 'manager', 'producer' => 'production', 'inspector' => 'quality_inspector', 'approver' => 'quality_manager'] as $property => $role) {
            $this->$property = User::factory()->create();
            $this->company->users()->attach($this->$property->id, ['role' => $role, 'is_active' => true, 'area_access' => 'selected', 'can_sell' => false, 'can_collect' => false]);
        }
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->site = Warehouse::create(['name' => 'Quality synthetic line', 'type' => 'production', 'code' => 'QA-SITE', 'custodian_id' => $this->producer->id]);
        $this->product = Product::create(['name' => 'Synthetic tissue', 'sku' => 'QA-TISSUE', 'unit' => 'box', 'price_minor' => 100]);
        foreach ([$this->inspector, $this->approver] as $u) {
            DB::table('quality_site_access')->insert(['company_id' => $this->company->id, 'user_id' => $u->id, 'warehouse_id' => $this->site->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actor($this->producer);
    }

    private function actor(User $u): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->actingAs($u)->withSession(['company_id' => $this->company->id]);
    }

    private function command(string $url, array $d = [], ?string $key = null)
    {
        return $this->postJson($url, $d + ['request_id' => $key ?? (string) Str::uuid()]);
    }

    private function plan(): int
    {
        return $this->command('/ops/supply/production', ['warehouse_id' => $this->site->id, 'operator_id' => $this->producer->id, 'product_id' => $this->product->id, 'planned_quantity' => 20, 'line' => 'QA line', 'shift' => 'Test shift', 'scheduled_on' => now()->toDateString(), 'notes' => 'Synthetic test instructions'])->assertOk()->json('id');
    }

    private function completion(): array
    {
        return ['good_quantity' => 18, 'scrap_quantity' => 2, 'lot_code' => 'QA-'.Str::random(8), 'manufactured_on' => now()->toDateString(), 'quality' => 'quarantine', 'quality_note' => 'Production observations only', 'note' => 'Synthetic completion', 'materials' => []];
    }

    private function batch(): int
    {
        $this->actor($this->producer);
        $id = $this->plan();
        $this->command('/ops/supply/production/'.$id.'/start')->assertOk();
        $this->command('/ops/supply/production/'.$id.'/complete', $this->completion())->assertOk();

        return (int) DB::table('quality_inspections')->orderByDesc('id')->value('id');
    }

    private function inspectData(): array
    {
        return ['version' => 1, 'sample_size' => 3, 'checks' => [['name' => 'Packaging integrity', 'result' => 'pass', 'observation' => 'Three intact synthetic samples']], 'recommendation' => 'released', 'inspection_note' => 'Synthetic specification and measurement'];
    }

    private function inspect(int $id): void
    {
        $this->actor($this->inspector);
        $this->command('/ops/quality/'.$id.'/inspect', $this->inspectData())->assertOk();
    }

    private function decision(): array
    {
        return ['version' => 2, 'decision' => 'released', 'decision_note' => 'Independent review completed', 'acknowledge' => true];
    }

    public function test_production_cannot_release_and_missing_choice_still_creates_quarantine_task_once(): void
    {
        $id = $this->plan();
        $this->command('/ops/supply/production/'.$id.'/start')->assertOk();
        $d = $this->completion();
        $d['quality'] = 'released';
        $this->command('/ops/supply/production/'.$id.'/complete', $d)->assertUnprocessable();
        $this->assertDatabaseCount('stock_lots', 0);
        unset($d['quality']);
        $key = (string) Str::uuid();
        $this->command('/ops/supply/production/'.$id.'/complete', $d, $key)->assertOk();
        $this->command('/ops/supply/production/'.$id.'/complete', $d, $key)->assertOk();
        $this->assertDatabaseCount('quality_inspections', 1);
        $this->assertDatabaseHas('stock_lots', ['code' => $d['lot_code'], 'status' => 'quarantine']);
        $this->assertDatabaseHas('production_orders', ['id' => $id, 'completed_by' => $this->producer->id]);
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $this->inspector->id, 'quality_inspection_id' => 1, 'handover_id' => null]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->getJson('/ops/supply/production/'.$id)->assertOk()->assertJsonPath('quality_requests.0.status', 'awaiting_inspection');
    }

    public function test_inspection_and_independent_decision_release_exactly_once_without_moving_stock(): void
    {
        $id = $this->batch();
        $this->inspect($id);
        $this->assertDatabaseHas('stock_lots', ['status' => 'quarantine']);
        $this->actor($this->approver);
        $key = (string) Str::uuid();
        $d = $this->decision();
        $this->command('/ops/quality/'.$id.'/decide', $d, $key)->assertOk();
        $this->command('/ops/quality/'.$id.'/decide', $d, $key)->assertOk();
        $this->assertDatabaseHas('quality_inspections', ['id' => $id, 'status' => 'approved', 'active_lot_id' => null, 'inspected_by' => $this->inspector->id, 'decided_by' => $this->approver->id]);
        $this->assertDatabaseHas('stock_lots', ['status' => 'released', 'quality_version' => 2]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $this->producer->id, 'event' => 'quality.decided']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quality.decided']);
    }

    public function test_quality_cannot_bypass_role_or_expose_regular_operations_data(): void
    {
        $id = $this->batch();
        $this->command('/ops/quality/'.$id.'/inspect', $this->inspectData())->assertForbidden();
        $this->actor($this->inspector);
        $this->get('/dashboard')->assertRedirect('/quality');
        $this->get('/quality')->assertOk()->assertSee('quality.js');
        $this->getJson('/ops/data')->assertForbidden();
        $this->get('/roadmap')->assertForbidden();
        $this->command('/ops/action/product.create', ['name' => 'Unauthorized'])->assertForbidden();
        $this->command('/ops/quality/'.$id.'/decide', $this->decision())->assertForbidden();
        $this->command('/ops/supply/lots/1/status', ['status' => 'released', 'note' => 'Bypass attempt'])->assertForbidden();
        $this->actor($this->manager);
        $this->command('/ops/supply/lots/1/status', ['status' => 'released', 'note' => 'Old path attempt'])->assertUnprocessable();
        $this->get('/roadmap')->assertOk()->assertSee('خطة واحدة');
    }

    public function test_scope_revocation_blocks_list_detail_and_write_immediately(): void
    {
        $id = $this->batch();
        $this->actor($this->inspector);
        $this->getJson('/ops/quality')->assertOk()->assertJsonPath('records.total', 1);
        $this->actor($this->manager);
        $this->command('/ops/quality/access', ['user_id' => $this->inspector->id, 'warehouse_ids' => [], 'reason' => 'Revoke synthetic access'])->assertOk();
        $this->actor($this->inspector);
        $this->getJson('/ops/quality')->assertOk()->assertJsonPath('records.total', 0);
        $this->getJson('/ops/quality/'.$id)->assertNotFound();
        $this->command('/ops/quality/'.$id.'/inspect', $this->inspectData())->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'quality.access_changed']);
    }

    public function test_failed_or_unperformed_checks_cannot_recommend_release(): void
    {
        $id = $this->batch();
        $this->actor($this->inspector);
        $d = $this->inspectData();
        $d['checks'][0]['result'] = 'fail';
        $this->command('/ops/quality/'.$id.'/inspect', $d)->assertUnprocessable();
        $d['checks'][0]['result'] = 'not_applicable';
        $this->command('/ops/quality/'.$id.'/inspect', $d)->assertUnprocessable();
        $d['checks'] = [];
        $this->command('/ops/quality/'.$id.'/inspect', $d)->assertUnprocessable();
        $this->assertDatabaseHas('quality_inspections', ['id' => $id, 'status' => 'awaiting_inspection']);
    }

    public function test_expired_batch_cannot_be_released(): void
    {
        $id = $this->batch();
        $this->inspect($id);
        DB::table('stock_lots')->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->actor($this->approver);
        $this->command('/ops/quality/'.$id.'/decide', $this->decision())->assertUnprocessable();
        $this->assertDatabaseHas('stock_lots', ['status' => 'quarantine']);
        $this->assertDatabaseHas('quality_inspections', ['status' => 'awaiting_approval']);
    }

    public function test_safety_hold_cancels_old_inspection_and_requires_new_request(): void
    {
        $id = $this->batch();
        $this->inspect($id);
        $this->actor($this->manager);
        $this->command('/ops/supply/lots/1/status', ['status' => 'blocked', 'note' => 'New defect discovered'])->assertOk();
        $this->assertDatabaseHas('quality_inspections', ['id' => $id, 'status' => 'cancelled', 'active_lot_id' => null]);
        $this->actor($this->approver);
        $this->command('/ops/quality/'.$id.'/decide', $this->decision())->assertForbidden();
        $this->actor($this->producer);
        $this->command('/ops/quality/lots/1/request', ['note' => 'Reinspect new defect'])->assertOk();
        $this->assertDatabaseCount('quality_inspections', 2);
        $this->assertDatabaseHas('stock_lots', ['status' => 'blocked']);
    }

    public function test_inspector_cannot_approve_after_changing_role(): void
    {
        $id = $this->batch();
        $this->inspect($id);
        $this->company->users()->updateExistingPivot($this->inspector->id, ['role' => 'quality_manager']);
        $this->actor($this->inspector);
        $this->command('/ops/quality/'.$id.'/decide', $this->decision())->assertForbidden();
    }

    public function test_manager_who_completed_production_cannot_approve_its_quality(): void
    {
        $this->actor($this->manager);
        $id = $this->plan();
        $this->command('/ops/supply/production/'.$id.'/start')->assertOk();
        $this->command('/ops/supply/production/'.$id.'/complete', $this->completion())->assertOk();
        $this->inspect(1);
        $this->actor($this->manager);
        $this->command('/ops/quality/1/decide', $this->decision())->assertForbidden();
        $this->actor($this->approver);
        $this->command('/ops/quality/1/decide', $this->decision())->assertOk();
    }

    public function test_no_available_inspector_identifies_manager_action_and_notifies_manager(): void
    {
        DB::table('quality_site_access')->where('user_id', $this->inspector->id)->delete();
        $id = $this->batch();
        $this->getJson('/ops/quality/'.$id)->assertOk()->assertJsonPath('inspection.needs_assignment', true)->assertJsonCount(0, 'inspection.responsible');
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $this->manager->id, 'event' => 'quality.task']);
        $this->assertDatabaseMissing('workspace_notifications', ['user_id' => $this->inspector->id]);
    }

    public function test_only_manager_grants_scope_and_foreign_site_is_rejected(): void
    {
        $this->actor($this->approver);
        $this->command('/ops/quality/access', ['user_id' => $this->inspector->id, 'warehouse_ids' => [], 'reason' => 'No authorization'])->assertForbidden();
        $this->actor($this->manager);
        $this->command('/ops/quality/access', ['user_id' => $this->inspector->id, 'warehouse_ids' => [999999], 'reason' => 'Wrong company site'])->assertUnprocessable();
        $this->assertDatabaseCount('quality_site_access', 2);
    }

    public function test_other_company_cannot_read_or_change_quality_or_read_its_notification(): void
    {
        $id = $this->batch();
        $other = Company::create(['name' => 'Other synthetic tenant', 'slug' => 'other-quality']);
        $other->users()->attach($this->approver->id, ['role' => 'quality_manager', 'is_active' => true]);
        $this->actor($this->approver);
        $this->withSession(['company_id' => $other->id]);
        $this->getJson('/ops/quality')->assertOk()->assertJsonPath('records.total', 0);
        $this->getJson('/ops/quality/'.$id)->assertNotFound();
        $this->command('/ops/quality/'.$id.'/decide', $this->decision())->assertNotFound();
        $this->getJson('/ops/notifications')->assertOk()->assertJsonPath('notifications.total', 0);
    }

    public function test_api_read_token_cannot_record_quality_and_write_token_remains_scoped(): void
    {
        $id = $this->batch();
        Sanctum::actingAs($this->inspector, ['operations:read']);
        $this->getJson('/api/v1/quality', ['X-Company-ID' => $this->company->id])->assertOk()->assertJsonPath('records.total', 1);
        $this->postJson('/api/v1/quality/'.$id.'/inspect', $this->inspectData() + ['request_id' => (string) Str::uuid()], ['X-Company-ID' => $this->company->id])->assertForbidden();
    }

    public function test_stale_inspection_version_does_not_save_results(): void
    {
        $id = $this->batch();
        $this->actor($this->inspector);
        $d = $this->inspectData();
        $d['version'] = 99;
        $this->command('/ops/quality/'.$id.'/inspect', $d)->assertConflict();
        $this->assertDatabaseHas('quality_inspections', ['id' => $id, 'version' => 1, 'checks' => null]);
    }

    public function test_non_release_decision_is_documented_and_new_inspection_can_follow(): void
    {
        $id = $this->batch();
        $this->actor($this->inspector);
        $d = $this->inspectData();
        $d['recommendation'] = 'blocked';
        $d['checks'][0]['result'] = 'fail';
        $this->command('/ops/quality/'.$id.'/inspect', $d)->assertOk();
        $this->actor($this->approver);
        $d = $this->decision();
        $this->command('/ops/quality/'.$id.'/decide', $d)->assertUnprocessable();
        $d['decision'] = 'blocked';
        $this->command('/ops/quality/'.$id.'/decide', $d)->assertOk();
        $this->assertDatabaseHas('quality_inspections', ['id' => $id, 'status' => 'rejected', 'decision' => 'blocked']);
        $this->assertDatabaseHas('stock_lots', ['status' => 'blocked']);
    }

    public function test_browser_form_numeric_strings_work_for_inspection_and_decision(): void
    {
        $id = $this->batch();
        $this->actor($this->inspector);
        $d = $this->inspectData();
        $d['version'] = '1';
        $d['sample_size'] = '3';
        $this->command('/ops/quality/'.$id.'/inspect', $d)->assertOk();
        $this->actor($this->approver);
        $d = $this->decision();
        $d['version'] = '2';
        $this->command('/ops/quality/'.$id.'/decide', $d)->assertOk();
        $this->assertDatabaseHas('quality_inspections', ['id' => $id, 'status' => 'approved']);
    }

    public function test_both_quality_roles_are_denied_customer_trip_and_report_direct_urls(): void
    {
        foreach ([$this->inspector, $this->approver] as $user) {
            $this->actor($user);
            foreach (['/customers/1', '/ops/customers/1', '/ops/trips/1', '/ops/analytics', '/reports/export', '/documents/orders/1', '/ops/supply?section=warehouses'] as $url) {
                $this->getJson($url)->assertForbidden();
            }
            Sanctum::actingAs($user, ['operations:read']);
            $this->getJson('/api/v1/workspace', ['X-Company-ID' => $this->company->id])->assertForbidden();
        }
    }

    public function test_demo_seeder_does_not_reset_password_or_restore_revoked_site_access(): void
    {
        config(['fieldflow.demo' => true]);
        $this->company->update(['slug' => 'naba']);
        $this->seed(QualityDemoSeeder::class);
        $qa = User::where('username', 'qa_demo')->firstOrFail();
        $qa->password = 'SyntheticChangedPassword123';
        $qa->save();
        $hash = $qa->getRawOriginal('password');
        DB::table('quality_site_access')->where('user_id', $qa->id)->delete();
        $this->seed(QualityDemoSeeder::class);
        $this->assertSame($hash, $qa->fresh()->getRawOriginal('password'));
        $this->assertDatabaseMissing('quality_site_access', ['user_id' => $qa->id]);
        $this->assertDatabaseCount('quality_inspections', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
