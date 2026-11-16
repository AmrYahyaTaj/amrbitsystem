<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Services\StockLedger;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionSupplyTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $producer;

    private User $keeper;

    private User $other;

    private User $rep;

    private User $inspector;

    private User $approver;

    private Warehouse $production;

    private Warehouse $store;

    private Warehouse $cargo;

    private Product $product;

    private Product $raw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Synthetic supply', 'slug' => 'supply']);
        foreach (['manager' => 'manager', 'producer' => 'production', 'keeper' => 'warehouse_keeper', 'other' => 'warehouse_keeper', 'rep' => 'representative', 'inspector' => 'quality_inspector', 'approver' => 'quality_manager'] as $property => $role) {
            $this->$property = User::factory()->create();
            $this->company->users()->attach($this->$property->id, ['role' => $role, 'is_active' => true, 'area_access' => 'all', 'can_sell' => true, 'can_collect' => true]);
        }
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->production = Warehouse::create(['name' => 'Synthetic factory', 'type' => 'production', 'code' => 'FACT-1', 'custodian_id' => $this->producer->id]);
        $this->store = Warehouse::create(['name' => 'Synthetic store', 'type' => 'warehouse', 'code' => 'STORE-1', 'custodian_id' => $this->keeper->id]);
        $v = Vehicle::create(['name' => 'Synthetic truck', 'plate' => 'SUPPLY-1', 'capacity_units' => 1000]);
        $this->cargo = Warehouse::create(['name' => 'Synthetic cargo', 'type' => 'vehicle', 'vehicle_id' => $v->id]);
        $this->product = Product::create(['name' => 'Synthetic paper', 'sku' => 'PAPER', 'unit' => 'box', 'price_minor' => 100]);
        $this->raw = Product::create(['name' => 'Synthetic raw paper', 'sku' => 'RAW', 'unit' => 'roll', 'price_minor' => 100]);
        foreach ([$this->inspector, $this->approver] as $qa) {
            DB::table('quality_site_access')->insert(['company_id' => $this->company->id, 'user_id' => $qa->id, 'warehouse_id' => $this->production->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actor($this->producer);
    }

    private function actor(User $user): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->actingAs($user)->withSession(['company_id' => $this->company->id]);
    }

    private function command(string $url, array $data = [], ?string $key = null)
    {
        return $this->postJson($url, $data + ['request_id' => $key ?? (string) Str::uuid()]);
    }

    private function planData(): array
    {
        return ['warehouse_id' => $this->production->id, 'operator_id' => $this->producer->id, 'product_id' => $this->product->id, 'planned_quantity' => 50, 'line' => 'Line 3', 'shift' => 'Morning', 'scheduled_on' => now()->toDateString(), 'notes' => 'Synthetic production instructions'];
    }

    private function completion(string $code = 'P-SYN-01', string $quality = 'quarantine'): array
    {
        return ['good_quantity' => 45, 'scrap_quantity' => 5, 'lot_code' => $code, 'manufactured_on' => now()->toDateString(), 'expires_on' => now()->addMonth()->toDateString(), 'quality' => $quality, 'quality_note' => 'Synthetic quality inspection', 'note' => 'Synthetic closing and scrap note', 'materials' => []];
    }

    private function batch(string $code = 'P-SYN-01', string $quality = 'released'): int
    {
        $this->actor($this->producer);
        $id = $this->command('/ops/supply/production', $this->planData())->assertOk()->json('id');
        $this->command('/ops/supply/production/'.$id.'/start')->assertOk();
        $this->command('/ops/supply/production/'.$id.'/complete', $this->completion($code, 'quarantine'))->assertOk();

        $lot = (int) DB::table('stock_lots')->where('code', $code)->value('id');
        if ($quality === 'released') {
            $this->releaseQuality($lot);
        }

        return $lot;
    }

    private function releaseQuality(int $lot): void
    {
        $this->actor($this->producer);
        $inspection = $this->command('/ops/quality/lots/'.$lot.'/request', ['note' => 'Synthetic inspection request'])->assertOk()->json('id');
        $this->actor($this->inspector);
        $this->command('/ops/quality/'.$inspection.'/inspect', ['version' => 1, 'sample_size' => 3, 'checks' => [['name' => 'Synthetic specification', 'result' => 'pass', 'observation' => 'Measured synthetic sample is within limits']], 'recommendation' => 'released', 'inspection_note' => 'Synthetic independent inspection'])->assertOk();
        $this->actor($this->approver);
        $this->command('/ops/quality/'.$inspection.'/decide', ['version' => 2, 'decision' => 'released', 'decision_note' => 'Synthetic independent approval', 'acknowledge' => true])->assertOk();
        $this->actor($this->producer);
    }

    private function transfer(Warehouse $from, Warehouse $to, User $recipient, int $qty = 20, ?string $key = null)
    {
        return $this->command('/ops/handovers', ['kind' => 'stock_transfer', 'from_id' => $from->id, 'to_id' => $to->id, 'recipient_id' => $recipient->id, 'note' => 'Synthetic sealed quantities for inspection', 'items' => [['product_id' => $this->product->id, 'quantity' => $qty]]], $key);
    }

    private function accept(int $id)
    {
        return $this->command('/ops/handovers/'.$id.'/decision', ['decision' => 'accepted', 'note' => 'Checked actual quantities and received', 'acknowledge' => true]);
    }

    public function test_handover_source_picker_reports_quarantine_instead_of_inventing_a_reservation(): void
    {
        $this->batch('P-HELD', 'quarantine');
        $this->getJson('/ops/handovers/stock?warehouse_id='.$this->production->id)->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.id', $this->product->id)->assertJsonPath('products.0.physical', 45)->assertJsonPath('products.0.reserved', 0)->assertJsonPath('products.0.quarantine', 45)->assertJsonPath('products.0.available', 0)->assertJsonMissingPath('products.0.price_minor');
        $response = $this->transfer($this->production, $this->store, $this->keeper, 20)->assertUnprocessable();
        $this->assertStringContainsString('تحت حجر الجودة 45', $response->json('message'));
        $this->assertStringContainsString('الإفراج عن الجودة', $response->json('message'));
        $this->assertStringNotContainsString('محجوز لطلبات تسليم', $response->json('message'));
        $this->assertDatabaseCount('handover_requests', 0);
    }

    public function test_empty_source_says_product_is_absent_and_does_not_offer_other_company_products(): void
    {
        $this->getJson('/ops/handovers/stock?warehouse_id='.$this->production->id)->assertOk()->assertJsonCount(0, 'products');
        $response = $this->transfer($this->production, $this->store, $this->keeper)->assertUnprocessable();
        $this->assertStringContainsString('لا يوجد رصيد لهذا الصنف في المصدر المختار', $response->json('message'));
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_picker_reports_reserved_balance_and_explains_the_actual_remaining_quantity(): void
    {
        $this->batch();
        $this->transfer($this->production, $this->store, $this->keeper, 40)->assertOk();
        $this->getJson('/ops/handovers/stock?warehouse_id='.$this->production->id)->assertOk()->assertJsonPath('products.0.physical', 45)->assertJsonPath('products.0.reserved', 40)->assertJsonPath('products.0.available', 5)->assertJsonPath('products.0.quarantine', 0);
        $response = $this->transfer($this->production, $this->store, $this->keeper, 6)->assertUnprocessable();
        $this->assertStringContainsString('المطلوب 6', $response->json('message'));
        $this->assertStringContainsString('المتاح للصرف 5', $response->json('message'));
        $this->assertStringContainsString('محجوز لطلبات تسليم 40', $response->json('message'));
        $this->assertDatabaseCount('handover_requests', 1);
    }

    public function test_stock_picker_keeps_expiry_and_quality_blocks_distinct(): void
    {
        $lot = $this->batch();
        DB::table('stock_lots')->where('id', $lot)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->getJson('/ops/handovers/stock?warehouse_id='.$this->production->id)->assertOk()->assertJsonPath('products.0.expired', 45)->assertJsonPath('products.0.available', 0);
        $r = $this->transfer($this->production, $this->store, $this->keeper)->assertUnprocessable();
        $this->assertStringContainsString('منتهي الصلاحية 45', $r->json('message'));
        DB::table('stock_lots')->where('id', $lot)->update(['status' => 'blocked', 'expires_on' => null]);
        $this->getJson('/ops/handovers/stock?warehouse_id='.$this->production->id)->assertOk()->assertJsonPath('products.0.blocked', 45)->assertJsonPath('products.0.expired', 0)->assertJsonPath('products.0.available', 0);
    }

    public function test_stock_picker_enforces_warehouse_custody_and_company_scope_for_web_and_api(): void
    {
        $this->batch();
        $this->actor($this->other);
        $this->getJson('/ops/handovers/stock?warehouse_id='.$this->production->id)->assertForbidden();
        $this->actor($this->manager);
        $this->getJson('/ops/handovers/stock?warehouse_id='.$this->production->id)->assertOk();
        $foreign = Company::create(['name' => 'Foreign picker', 'slug' => 'foreign-picker']);
        $foreign->users()->attach($this->manager->id, ['role' => 'manager', 'is_active' => true]);
        $this->withSession(['company_id' => $foreign->id]);
        $this->getJson('/ops/handovers/stock?warehouse_id='.$this->production->id)->assertNotFound();
        Sanctum::actingAs($this->producer, ['operations:read']);
        $this->withHeader('X-Company-ID', (string) $this->company->id);
        $this->getJson('/api/v1/handovers/stock?warehouse_id='.$this->production->id)->assertOk()->assertJsonPath('products.0.available', 45);
    }

    private function sellingTrip(Customer $customer): int
    {
        $trip = Trip::create(['code' => 'TR-SUP-'.Str::random(8), 'representative_id' => $this->rep->id, 'vehicle_id' => $this->cargo->vehicle_id, 'warehouse_id' => $this->cargo->id, 'scheduled_for' => now()->toDateString()]);
        $trip->status = 'active';
        $trip->started_at = now();
        $trip->active_representative_id = $this->rep->id;
        $trip->active_vehicle_id = $this->cargo->vehicle_id;
        $trip->save();
        Visit::create(['customer_id' => $customer->id, 'representative_id' => $this->rep->id, 'trip_id' => $trip->id, 'scheduled_for' => $trip->scheduled_for]);

        return $trip->id;
    }

    public function test_production_planning_completion_and_retry_are_atomic_and_do_not_create_warehouse_stock(): void
    {
        $key = (string) Str::uuid();
        $data = $this->planData();
        $id = $this->command('/ops/supply/production', $data, $key)->assertOk()->json('id');
        $this->command('/ops/supply/production', $data, $key)->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('production_orders', 1);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->command('/ops/supply/production/'.$id.'/complete', $this->completion())->assertConflict();
        $this->command('/ops/supply/production/'.$id.'/start')->assertOk();
        $key = (string) Str::uuid();
        $this->command('/ops/supply/production/'.$id.'/complete', $this->completion(), $key)->assertOk();
        $this->command('/ops/supply/production/'.$id.'/complete', $this->completion(), $key)->assertOk();
        $this->command('/ops/supply/production/'.$id.'/complete', $this->completion('P-SECOND'))->assertConflict();
        $this->assertDatabaseHas('production_orders', ['id' => $id, 'status' => 'completed', 'good_quantity' => 45, 'scrap_quantity' => 5]);
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->production->id, 'quantity' => 45]);
        $this->assertDatabaseMissing('stock_balances', ['warehouse_id' => $this->store->id]);
        $this->assertDatabaseCount('stock_lots', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_planned_orders_can_be_edited_with_reason_and_stale_versions_are_rejected(): void
    {
        $id = $this->command('/ops/supply/production', $this->planData())->assertOk()->json('id');
        $d = array_replace($this->planData(), ['planned_quantity' => 60, 'version' => 1, 'reason' => 'Updated synthetic client demand']);
        $key = (string) Str::uuid();
        $this->command('/ops/supply/production/'.$id.'/update', $d, $key)->assertOk();
        $this->command('/ops/supply/production/'.$id.'/update', $d, $key)->assertOk();
        $this->command('/ops/supply/production/'.$id.'/update', $d)->assertConflict();
        $this->assertDatabaseHas('production_orders', ['id' => $id, 'version' => 2, 'planned_quantity' => 60]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'production.updated', 'subject_id' => $id]);
        $this->command('/ops/supply/production/'.$id.'/start')->assertOk();
        $d['version'] = 2;
        $this->command('/ops/supply/production/'.$id.'/update', $d)->assertConflict();
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_external_receipt_records_supplier_batch_and_checks_capacity_and_retry(): void
    {
        $this->actor($this->manager);
        $data = ['warehouse_id' => $this->store->id, 'product_id' => $this->raw->id, 'quantity' => 12, 'reference' => 'SYN-EXTERNAL', 'supplier' => 'Synthetic supplier', 'lot_code' => 'SUPPLIER-BATCH-1', 'manufactured_on' => now()->subWeek()->toDateString(), 'expires_on' => now()->addYear()->toDateString()];
        $key = (string) Str::uuid();
        $this->command('/ops/action/stock.receive', $data, $key)->assertOk();
        $this->command('/ops/action/stock.receive', $data, $key)->assertOk();
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_lots', ['code' => 'SUPPLIER-BATCH-1', 'source' => 'external']);
        $this->get('/inventory')->assertRedirect('/warehouses');
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->store->update(['capacity_units' => 13]);
        $data['lot_code'] = 'SUPPLIER-BATCH-2';
        $data['quantity'] = 2;
        $this->command('/ops/action/stock.receive', $data)->assertUnprocessable();
        $this->assertDatabaseCount('stock_lots', 1);
        $data['quantity'] = 1;
        $data['expires_on'] = now()->subDay()->toDateString();
        $this->command('/ops/action/stock.receive', $data)->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_free_text_receipt_reference_cannot_falsely_link_a_customer_sale(): void
    {
        $this->actor($this->manager);
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $area = Area::create(['name' => 'Synthetic reference area', 'city' => 'Test']);
        $customer = Customer::create(['name' => 'Synthetic reference customer', 'code' => 'C-REF', 'area_id' => $area->id, 'address' => 'Synthetic', 'representative_id' => $this->rep->id, 'latitude' => 24.7, 'longitude' => 46.7]);
        $order = Order::create(['code' => 'SO-REFERENCE-ONLY', 'customer_id' => $customer->id, 'warehouse_id' => $this->cargo->id, 'representative_id' => $this->rep->id]);
        $this->command('/ops/action/stock.receive', ['warehouse_id' => $this->store->id, 'product_id' => $this->raw->id, 'quantity' => 1, 'reference' => $order->code, 'lot_code' => 'EXTERNAL-NOT-SOLD'])->assertOk();
        $lot = DB::table('stock_lots')->where('code', 'EXTERNAL-NOT-SOLD')->value('id');
        $this->getJson('/ops/supply/lots/'.$lot)->assertOk()->assertJsonCount(0, 'sales');
    }

    public function test_capacity_includes_pending_incoming_and_cancellation_preserves_history(): void
    {
        $this->batch();
        $this->actor($this->manager);
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->store->update(['capacity_units' => 25]);
        $this->actor($this->producer);
        $id = $this->transfer($this->production, $this->store, $this->keeper, 20)->assertOk()->json('id');
        $this->transfer($this->production, $this->store, $this->keeper, 6)->assertUnprocessable();
        $this->command('/ops/handovers/'.$id.'/cancel', ['note' => 'Synthetic packaging plan changed'])->assertOk();
        $this->assertDatabaseHas('handover_requests', ['id' => $id, 'status' => 'cancelled']);
        $this->assertSame(0, DB::table('handover_lot_reservations')->whereNull('released_at')->count());
        $this->transfer($this->production, $this->store, $this->keeper, 25)->assertOk();
        $this->actor($this->manager);
        $this->command('/ops/supply/warehouses', ['id' => $this->store->id, 'name' => $this->store->name, 'code' => 'STORE-1', 'type' => 'warehouse', 'custodian_id' => $this->keeper->id, 'is_active' => true, 'capacity_units' => 10, 'notes' => 'Synthetic reduced warehouse capacity'])->assertUnprocessable();
    }

    public function test_supply_api_requires_company_and_write_ability_and_keeps_data_scoped(): void
    {
        $this->batch();
        $this->getJson('/api/v1/supply?section=production')->assertStatus(422);
        Sanctum::actingAs($this->producer, ['operations:read']);
        $this->withHeader('X-Company-ID', (string) $this->company->id);
        $this->getJson('/api/v1/supply?section=production')->assertOk()->assertJsonPath('records.total', 1);
        $this->command('/api/v1/supply/production', $this->planData())->assertForbidden();
    }

    public function test_full_batch_chain_requires_both_parties_and_keeps_identity_through_customer_invoice(): void
    {
        $lot = $this->batch();
        $h1 = $this->transfer($this->production, $this->store, $this->keeper)->assertOk()->json('id');
        $this->accept($h1)->assertForbidden();
        $this->assertDatabaseMissing('stock_balances', ['warehouse_id' => $this->store->id]);
        $this->assertDatabaseHas('handover_lot_reservations', ['handover_id' => $h1, 'lot_id' => $lot, 'quantity' => 20, 'released_at' => null]);
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $this->keeper->id, 'handover_id' => $h1, 'event' => 'requested']);
        $this->actor($this->keeper);
        $this->accept($h1)->assertOk();
        $this->get('/documents/handovers/'.$h1)->assertOk()->assertSee('P-SYN-01');
        $h2 = $this->transfer($this->store, $this->cargo, $this->rep, 10)->assertOk()->json('id');
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->store->id, 'quantity' => 20]);
        $this->actor($this->rep);
        $this->accept($h2)->assertOk();
        app(CompanyContext::class)->set($this->company, CompanyRole::Representative);
        $customer = Customer::create(['name' => 'Synthetic customer', 'code' => 'C-SUPPLY', 'area_id' => Area::create(['name' => 'Synthetic area', 'city' => 'Test'])->id, 'address' => 'Synthetic address', 'representative_id' => $this->rep->id, 'latitude' => 24.7, 'longitude' => 46.7]);
        $trip = $this->sellingTrip($customer);
        $order = $this->command('/ops/action/order.create', ['trip_id' => $trip, 'customer_id' => $customer->id, 'warehouse_id' => $this->cargo->id, 'representative_id' => $this->rep->id, 'payment_terms' => 'credit', 'due_date' => now()->addWeek()->toDateString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 3]]])->assertOk()->json('id');
        $this->command('/ops/action/order.fulfill', ['id' => $order])->assertOk();
        $this->getJson('/ops/supply/lots/'.$lot)->assertOk()->assertJsonPath('sales.0.customer.name', 'Synthetic customer')->assertJsonCount(2, 'handovers');
        $this->assertDatabaseHas('stock_lot_balances', ['lot_id' => $lot, 'warehouse_id' => $this->cargo->id, 'quantity' => 7]);
        $this->assertSame(42, (int) DB::table('stock_lot_balances')->where('lot_id', $lot)->sum('quantity'));
        $this->assertSame(42, (int) DB::table('stock_balances')->sum('quantity'));
    }

    public function test_assigned_warehouse_limits_sender_recipient_and_production_visibility(): void
    {
        $lot = $this->batch();
        $this->transfer($this->production, $this->store, $this->other)->assertUnprocessable();
        $this->actor($this->other);
        $this->getJson('/ops/supply?section=warehouses')->assertOk()->assertJsonCount(0, 'options.warehouses')->assertJsonPath('records.total', 0);
        $this->transfer($this->production, $this->store, $this->keeper)->assertForbidden();
        $this->getJson('/ops/supply/lots/'.$lot)->assertNotFound();
        $this->getJson('/ops/supply?section=production')->assertForbidden();
        $this->getJson('/ops/supply/production/1')->assertForbidden();
    }

    public function test_production_and_keeper_accounts_do_not_get_financial_customer_or_team_endpoints(): void
    {
        foreach ([$this->producer, $this->keeper] as $user) {
            $this->actor($user);
            $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'customers')->assertJsonCount(0, 'orders')->assertJsonCount(0, 'collections')->assertJsonCount(0, 'member_directory')->assertJsonPath('metrics.sales_minor', null);
            foreach (['/team', '/orders', '/customers/1', '/ops/trips/1', '/ops/analytics', '/reports/export', '/documents/orders/1'] as $path) {
                $this->get($path)->assertForbidden();
            }
            $this->command('/ops/action/stock.receive', ['warehouse_id' => $this->store->id, 'product_id' => $this->product->id, 'quantity' => 10, 'reference' => 'Bypass attempt'])->assertForbidden();
        }
        $this->actor($this->producer);
        $this->get('/dashboard')->assertRedirect('/production');
        $this->actor($this->keeper);
        $this->get('/dashboard')->assertRedirect('/warehouses');
    }

    public function test_reserved_lots_cannot_be_double_promised_and_rejection_releases_all_reservations(): void
    {
        $lot = $this->batch();
        $id = $this->transfer($this->production, $this->store, $this->keeper, 40)->assertOk()->json('id');
        $this->transfer($this->production, $this->store, $this->keeper, 6)->assertUnprocessable();
        $this->actor($this->keeper);
        $this->command('/ops/handovers/'.$id.'/decision', ['decision' => 'rejected', 'note' => 'Boxes do not match requested count'])->assertOk();
        $this->assertSame(0, DB::table('handover_lot_reservations')->whereNull('released_at')->count());
        $this->actor($this->producer);
        $this->transfer($this->production, $this->store, $this->keeper, 45)->assertOk();
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_quarantined_expired_and_blocked_batches_are_unavailable_even_for_pending_acceptance(): void
    {
        $lot = $this->batch('P-QUALITY', 'quarantine');
        $this->transfer($this->production, $this->store, $this->keeper)->assertUnprocessable();
        $this->getJson('/ops/supply?section=warehouses')->assertJsonPath('records.data.0.available', 0)->assertJsonPath('records.data.0.unusable', 45);
        $this->command('/ops/supply/lots/'.$lot.'/status', ['status' => 'released', 'note' => 'Unauthorized self release'])->assertForbidden();
        $this->actor($this->manager);
        $this->command('/ops/supply/lots/'.$lot.'/status', ['status' => 'released', 'note' => 'Quality passed on inspection'])->assertUnprocessable();
        $this->releaseQuality($lot);
        $this->actor($this->producer);
        $id = $this->transfer($this->production, $this->store, $this->keeper)->assertOk()->json('id');
        $this->actor($this->manager);
        $this->command('/ops/supply/lots/'.$lot.'/status', ['status' => 'blocked', 'note' => 'Safety hold on same batch'])->assertOk();
        $this->actor($this->keeper);
        $this->accept($id)->assertUnprocessable();
        $this->assertDatabaseMissing('stock_balances', ['warehouse_id' => $this->store->id]);
        $this->actor($this->manager);
        $this->releaseQuality($lot);
        DB::table('stock_lots')->where('id', $lot)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->actor($this->keeper);
        $this->accept($id)->assertUnprocessable();
        $this->assertDatabaseHas('handover_requests', ['id' => $id, 'status' => 'pending']);
    }

    public function test_consumed_raw_material_batches_are_linked_to_output_and_insufficient_material_rolls_back_everything(): void
    {
        $this->actor($this->manager);
        DB::transaction(fn () => app(StockLedger::class)->move($this->production, $this->raw, 10, 'receipt', 'RAW-SYN'));
        $this->actor($this->producer);
        $id = $this->command('/ops/supply/production', $this->planData())->assertOk()->json('id');
        $this->command('/ops/supply/production/'.$id.'/start')->assertOk();
        $payload = $this->completion('P-MATERIAL') + [];
        $payload['materials'] = [['product_id' => $this->raw->id, 'quantity' => 11]];
        $this->command('/ops/supply/production/'.$id.'/complete', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('stock_lots', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('production_materials', 0);
        $payload['materials'][0]['quantity'] = 4;
        $this->command('/ops/supply/production/'.$id.'/complete', $payload)->assertOk();
        $output = DB::table('stock_lots')->where('code', 'P-MATERIAL')->value('id');
        $this->getJson('/ops/supply/lots/'.$output)->assertOk()->assertJsonPath('materials.0.quantity', 4)->assertJsonPath('materials.0.name', 'Synthetic raw paper');
        $rawLot = DB::table('stock_lots')->where('product_id', $this->raw->id)->value('id');
        $this->getJson('/ops/supply/lots/'.$rawLot)->assertOk()->assertJsonPath('descendants.0.code', 'P-MATERIAL');
        $this->assertDatabaseHas('stock_balances', ['product_id' => $this->raw->id, 'quantity' => 6]);
    }

    public function test_fefo_selects_the_earliest_expiry_and_skips_reserved_lots(): void
    {
        $later = $this->batch('P-LATER');
        $earlier = $this->batch('P-EARLIER');
        DB::table('stock_lots')->where('id', $earlier)->update(['expires_on' => now()->addDays(3)->toDateString()]);
        $h = $this->transfer($this->production, $this->store, $this->keeper, 40)->assertOk()->json('id');
        $this->assertDatabaseHas('handover_lot_reservations', ['handover_id' => $h, 'lot_id' => $earlier, 'quantity' => 40]);
        $h2 = $this->transfer($this->production, $this->store, $this->keeper, 10)->assertOk()->json('id');
        $this->assertDatabaseHas('handover_lot_reservations', ['handover_id' => $h2, 'lot_id' => $earlier, 'quantity' => 5]);
        $this->assertDatabaseHas('handover_lot_reservations', ['handover_id' => $h2, 'lot_id' => $later, 'quantity' => 5]);
    }

    public function test_operator_role_revocation_blocks_mutation_replays_and_pending_transfers(): void
    {
        $this->batch();
        $key = (string) Str::uuid();
        $id = $this->transfer($this->production, $this->store, $this->keeper, 20, $key)->assertOk()->json('id');
        $this->company->users()->updateExistingPivot($this->producer->id, ['role' => 'viewer']);
        $this->transfer($this->production, $this->store, $this->keeper, 20, $key)->assertForbidden();
        $this->actor($this->keeper);
        $this->accept($id)->assertUnprocessable();
        $this->assertDatabaseHas('handover_requests', ['id' => $id, 'status' => 'pending']);
    }

    public function test_cross_company_lots_warehouses_and_foreign_operator_are_rejected(): void
    {
        $lot = $this->batch();
        $foreign = Company::create(['name' => 'Other synthetic', 'slug' => 'supply-other']);
        $foreign->users()->attach($this->manager->id, ['role' => 'manager', 'is_active' => true]);
        $this->actor($this->manager);
        $this->withSession(['company_id' => $foreign->id]);
        $this->getJson('/ops/supply/lots/'.$lot)->assertNotFound();
        $this->getJson('/ops/supply/warehouses/'.$this->store->id)->assertNotFound();
        $this->command('/ops/supply/lots/'.$lot.'/status', ['status' => 'released', 'note' => 'Cross company attempt'])->assertNotFound();
        $this->command('/ops/supply/production', $this->planData())->assertNotFound();
    }

    public function test_warehouse_owner_cannot_change_with_stock_or_pending_requests_and_site_retry_is_once(): void
    {
        $this->batch();
        $h = $this->transfer($this->production, $this->store, $this->keeper)->assertOk()->json('id');
        $this->actor($this->manager);
        $data = ['id' => $this->store->id, 'name' => $this->store->name, 'code' => 'STORE-1', 'type' => 'warehouse', 'custodian_id' => $this->other->id, 'address' => 'Synthetic address', 'phone' => '0000', 'capacity_units' => 500, 'is_active' => true, 'notes' => 'Change stock responsibility'];
        $this->command('/ops/supply/warehouses', $data)->assertUnprocessable();
        $this->actor($this->keeper);
        $this->accept($h)->assertOk();
        $this->actor($this->manager);
        $this->command('/ops/supply/warehouses', $data)->assertUnprocessable();
        $data['custodian_id'] = $this->keeper->id;
        $this->command('/ops/supply/warehouses', $data)->assertOk();
        unset($data['id']);
        $data['code'] = 'NEW-SITE';
        $data['name'] = 'New synthetic site';
        $key = (string) Str::uuid();
        $id = $this->command('/ops/supply/warehouses', $data, $key)->assertOk()->json('id');
        $this->command('/ops/supply/warehouses', $data, $key)->assertOk()->assertJsonPath('id', $id);
    }

    public function test_customer_proof_is_private_appended_and_never_deducts_again(): void
    {
        Storage::fake('local');
        $lot = $this->batch();
        $h = $this->transfer($this->production, $this->store, $this->keeper)->assertOk()->json('id');
        $this->actor($this->keeper);
        $this->accept($h)->assertOk();
        $h = $this->transfer($this->store, $this->cargo, $this->rep)->assertOk()->json('id');
        $this->actor($this->rep);
        $this->accept($h)->assertOk();
        app(CompanyContext::class)->set($this->company, CompanyRole::Representative);
        $customer = Customer::create(['name' => 'Synthetic proof customer', 'code' => 'PROOF-1', 'area_id' => Area::create(['name' => 'Synthetic area', 'city' => 'Test'])->id, 'address' => 'Synthetic address', 'representative_id' => $this->rep->id, 'latitude' => 24.7, 'longitude' => 46.7]);
        $trip = $this->sellingTrip($customer);
        $order = $this->command('/ops/action/order.create', ['trip_id' => $trip, 'customer_id' => $customer->id, 'warehouse_id' => $this->cargo->id, 'representative_id' => $this->rep->id, 'payment_terms' => 'credit', 'due_date' => now()->addWeek()->toDateString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 2]]])->assertOk()->json('id');
        $this->command('/ops/action/order.fulfill', ['id' => $order])->assertOk();
        $count = DB::table('stock_movements')->count();
        $payload = ['request_id' => (string) Str::uuid(), 'receiver_name' => 'Synthetic receiver', 'note' => 'Signed delivery only, no real customer', 'acknowledge' => true, 'document' => UploadedFile::fake()->createWithContent('signed-test.pdf', "%PDF-1.4\nSynthetic test document")];
        $proof = $this->postJson('/ops/supply/deliveries/'.$order, $payload)->assertOk()->json('id');
        $this->postJson('/ops/supply/deliveries/'.$order, $payload)->assertOk()->assertJsonPath('id', $proof);
        $this->get('/ops/supply/proofs/'.$proof)->assertOk();
        $this->assertDatabaseCount('delivery_proofs', 1);
        $this->assertDatabaseCount('stock_movements', $count);
        $this->assertDatabaseCount('collections', 0);
        $this->actor($this->other);
        $this->get('/ops/supply/proofs/'.$proof)->assertForbidden();
        $this->getJson('/ops/supply/deliveries')->assertForbidden();
        $this->actor($this->producer);
        $this->getJson('/ops/supply/lots/'.$lot)->assertOk()->assertJsonPath('proofs.0.receiver_name', 'Synthetic receiver');
    }

    public function test_documents_and_views_escape_untrusted_notes_and_inventory_export_is_scoped(): void
    {
        $this->batch();
        $this->get('/production')->assertOk()->assertSee('/js/supply.js')->assertSee('supply-workspace');
        $this->get('/warehouses')->assertOk();
        $this->get('/traceability')->assertOk();
        $this->get('/ops/supply/export')->assertOk()->assertDownload();
        $this->actor($this->other);
        $this->get('/ops/supply/export')->assertOk()->assertStreamedContent("\xEF\xBB\xBFالموقع,الصنف,الرمز,الوحدة,فعلي,محجوز,\"غير صالح للصرف\",\"متاح للصرف\"\n");
    }
}
