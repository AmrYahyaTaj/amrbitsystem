<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Services\LotLedger;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $rep;

    private User $otherRep;

    private Customer $customer;

    private Warehouse $warehouse;

    private Product $product;

    private Trip $trip;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Test', 'slug' => 'test']);
        $this->manager = User::factory()->create();
        $this->rep = User::factory()->create();
        $this->otherRep = User::factory()->create();
        $this->company->users()->attach([$this->manager->id => ['role' => 'manager', 'is_active' => true], $this->rep->id => ['role' => 'representative', 'is_active' => true], $this->otherRep->id => ['role' => 'representative', 'is_active' => true]]);
        $this->context();
        $area = Area::create(['name' => 'Area', 'city' => 'Riyadh']);
        $this->customer = Customer::create(['area_id' => $area->id, 'name' => 'Synthetic', 'code' => 'C-1', 'address' => 'Demo', 'latitude' => 24.78, 'longitude' => 46.64, 'geofence_radius_m' => 150]);
        $vehicle = Vehicle::create(['name' => 'Van', 'plate' => 'DEMO', 'capacity_units' => 100]);
        $this->warehouse = Warehouse::create(['name' => 'Custody', 'type' => 'vehicle', 'vehicle_id' => $vehicle->id]);
        $this->product = Product::create(['name' => 'Product', 'sku' => 'P1', 'unit' => 'unit', 'price_minor' => 1999, 'reorder_level' => 10]);
        StockBalance::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => 50]);
        app(LotLedger::class)->opening(Warehouse::findOrFail($this->warehouse->id), Product::findOrFail($this->product->id));
        $this->trip = Trip::create(['code' => 'TR-1', 'representative_id' => $this->rep->id, 'vehicle_id' => $vehicle->id, 'warehouse_id' => $this->warehouse->id, 'scheduled_for' => today('Asia/Riyadh')->toDateString()]);
        $this->trip->status = 'active';
        $this->trip->started_at = now();
        $this->trip->active_representative_id = $this->rep->id;
        $this->trip->active_vehicle_id = $vehicle->id;
        $this->trip->save();
        $this->visit = Visit::create(['customer_id' => $this->customer->id, 'representative_id' => $this->rep->id, 'trip_id' => $this->trip->id, 'scheduled_for' => $this->trip->scheduled_for]);
        $this->actingAs($this->manager)->withSession(['company_id' => $this->company->id]);
    }

    private function context(): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
    }

    private function postAction(string $action, array $payload = [], ?string $key = null)
    {
        return $this->postJson('/ops/action/'.$action, ['request_id' => $key ?? (string) Str::uuid()] + $payload);
    }

    private function orderData(string $terms = 'credit'): array
    {
        return ['customer_id' => $this->customer->id, 'representative_id' => $this->rep->id, 'warehouse_id' => $this->warehouse->id, 'trip_id' => $this->trip->id, 'payment_terms' => $terms, 'due_date' => today('Asia/Riyadh')->toDateString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 2]], 'total_minor' => 1];
    }

    private function makeOrder(string $terms = 'credit'): int
    {
        return $this->postAction('order.create', $this->orderData($terms))->assertOk()->json('id');
    }

    public function test_prices_are_computed_on_server_and_credit_is_posted_only_on_delivery(): void
    {
        $id = $this->makeOrder();
        $this->assertDatabaseHas('orders', ['id' => $id, 'total_minor' => 3998, 'paid_minor' => 0, 'status' => 'draft']);
        $this->getJson('/ops/data')->assertOk()->assertJsonPath('metrics.unpaid_minor', 0);
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $this->assertDatabaseHas('stock_balances', ['quantity' => 48]);
        $this->assertDatabaseHas('stock_movements', ['quantity' => -2, 'reason' => 'sale']);
        $this->getJson('/ops/data')->assertOk()->assertJsonPath('metrics.unpaid_minor', 3998)->assertJsonPath('debt_customers.0.balance_minor', 3998)->assertJsonPath('debt_representatives.0.balance_minor', 3998);
    }

    public function test_cash_delivery_records_full_payment_exactly_once(): void
    {
        $id = $this->makeOrder('cash');
        $key = (string) Str::uuid();
        $this->postAction('order.fulfill', ['id' => $id], $key)->assertOk();
        $this->postAction('order.fulfill', ['id' => $id], $key)->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $id, 'paid_minor' => 3998]);
        $this->assertDatabaseCount('collections', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->postAction('order.fulfill', ['id' => $id])->assertUnprocessable();
    }

    public function test_partial_delivery_and_later_settlement_close_credit_without_rounding(): void
    {
        $d = $this->orderData('partial');
        $d['initial_paid_minor'] = 1001;
        $id = $this->postAction('order.create', $d)->assertOk()->json('id');
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $this->assertDatabaseHas('orders', ['paid_minor' => 1001, 'total_minor' => 3998]);
        $this->postAction('collection.create', ['order_id' => $id, 'amount_minor' => 2997, 'method' => 'bank'])->assertOk();
        $this->assertDatabaseHas('orders', ['paid_minor' => 3998]);
        $this->getJson('/ops/data')->assertJsonPath('metrics.unpaid_minor', 0);
        $this->assertDatabaseCount('collections', 2);
    }

    public function test_collection_replay_does_not_duplicate_and_changed_payload_is_rejected(): void
    {
        $id = $this->makeOrder();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $key = (string) Str::uuid();
        $p = ['order_id' => $id, 'amount_minor' => 100, 'method' => 'cash'];
        $this->postAction('collection.create', $p, $key)->assertOk();
        $this->postAction('collection.create', $p, $key)->assertOk();
        $p['amount_minor'] = 101;
        $this->postAction('collection.create', $p, $key)->assertStatus(409);
        $this->assertDatabaseCount('collections', 1);
        $this->assertDatabaseHas('orders', ['paid_minor' => 100]);
    }

    public function test_overpayment_and_unfulfilled_collection_leave_no_receipt(): void
    {
        $id = $this->makeOrder();
        $this->postAction('collection.create', ['order_id' => $id, 'amount_minor' => 1, 'method' => 'cash'])->assertUnprocessable();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $this->postAction('collection.create', ['order_id' => $id, 'amount_minor' => 3999, 'method' => 'cash'])->assertUnprocessable();
        $this->assertDatabaseCount('collections', 0);
    }

    public function test_insufficient_stock_rolls_back_delivery_and_ledger(): void
    {
        $id = $this->makeOrder();
        DB::table('stock_balances')->update(['quantity' => 1]);
        $this->postAction('order.fulfill', ['id' => $id])->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['status' => 'draft']);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('stock_balances', ['quantity' => 1]);
    }

    public function test_credit_limit_prevents_delivery_without_changing_inventory(): void
    {
        $id = $this->makeOrder();
        DB::table('customers')->where('id', $this->customer->id)->update(['credit_limit_minor' => 3997]);
        $this->postAction('order.fulfill', ['id' => $id])->assertUnprocessable();
        $this->assertDatabaseHas('stock_balances', ['quantity' => 50]);
    }

    public function test_representative_cannot_read_or_collect_another_representatives_credit(): void
    {
        $id = $this->makeOrder();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $this->actingAs($this->otherRep);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'orders')->assertJsonCount(0, 'debt_customers')->assertJsonPath('metrics.unpaid_minor', 0);
        $this->postAction('collection.create', ['order_id' => $id, 'amount_minor' => 100, 'method' => 'cash'])->assertForbidden();
    }

    public function test_supervisor_can_see_credit_but_cannot_collect(): void
    {
        $id = $this->makeOrder();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        DB::table('company_user')->where('user_id', $this->otherRep->id)->update(['role' => 'supervisor']);
        $this->actingAs($this->otherRep);
        $this->getJson('/ops/data')->assertOk()->assertJsonPath('metrics.unpaid_minor', 3998);
        $this->get('/receivables')->assertOk();
        $this->postAction('collection.create', ['order_id' => $id, 'amount_minor' => 100, 'method' => 'cash'])->assertForbidden();
    }

    public function test_switching_company_does_not_leak_orders_or_aggregates(): void
    {
        $id = $this->makeOrder();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $other = Company::create(['name' => 'Other', 'slug' => 'other']);
        $other->users()->attach($this->manager->id, ['role' => 'manager', 'is_active' => true]);
        $this->withSession(['company_id' => $other->id]);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'orders')->assertJsonCount(0, 'debt_customers')->assertJsonPath('metrics.unpaid_minor', 0);
        $this->postAction('collection.create', ['order_id' => $id, 'amount_minor' => 100, 'method' => 'cash'])->assertNotFound();
    }

    public function test_visit_requires_location_and_active_trip_and_assigned_user(): void
    {
        $this->actingAs($this->rep);
        $this->postAction('visit.checkin', ['id' => $this->visit->id])->assertUnprocessable();
        $p = ['id' => $this->visit->id, 'latitude' => 24.78, 'longitude' => 46.64, 'accuracy_m' => 10, 'captured_at' => now()->toIso8601String()];
        $this->actingAs($this->otherRep);
        $this->postAction('visit.checkin', $p)->assertForbidden();
        $this->actingAs($this->rep);
        DB::table('trips')->update(['status' => 'planned']);
        $this->postAction('visit.checkin', $p)->assertUnprocessable();
        DB::table('trips')->update(['status' => 'active']);
        $this->postAction('visit.checkin', $p)->assertOk();
        $this->postAction('visit.finish', ['id' => $this->visit->id, 'status' => 'completed'])->assertOk();
        $this->assertDatabaseHas('visits', ['status' => 'completed', 'location_verified' => 1]);
    }

    public function test_visit_rejects_stale_inaccurate_and_outside_locations(): void
    {
        $this->actingAs($this->rep);
        $p = ['id' => $this->visit->id, 'latitude' => 24.78, 'longitude' => 46.64, 'accuracy_m' => 10, 'captured_at' => now()->subMinutes(3)->toIso8601String()];
        $this->postAction('visit.checkin', $p)->assertUnprocessable();
        $p['captured_at'] = now()->toIso8601String();
        $p['accuracy_m'] = 101;
        $this->postAction('visit.checkin', $p)->assertUnprocessable();
        $p['accuracy_m'] = 10;
        $p['latitude'] = 24.99;
        $this->postAction('visit.checkin', $p)->assertUnprocessable();
    }

    public function test_location_history_rejects_jump_and_replays_safely(): void
    {
        $this->actingAs($this->rep);
        $key = (string) Str::uuid();
        $p = ['trip_id' => $this->trip->id, 'event_id' => (string) Str::uuid(), 'latitude' => 24.78, 'longitude' => 46.64, 'accuracy_m' => 10, 'captured_at' => now()->subSeconds(20)->toIso8601String()];
        $this->postAction('location.record', $p, $key)->assertOk();
        $this->postAction('location.record', $p, $key)->assertOk();
        $this->assertDatabaseCount('location_points', 1);
        $p['event_id'] = (string) Str::uuid();
        $p['latitude'] = 25.8;
        $p['captured_at'] = now()->toIso8601String();
        $this->postAction('location.record', $p)->assertUnprocessable();
        $this->assertDatabaseCount('location_points', 1);
    }

    public function test_trip_cannot_close_with_pending_visits(): void
    {
        $this->postAction('trip.close', ['id' => $this->trip->id])->assertUnprocessable();
        $this->assertDatabaseHas('trips', ['status' => 'active']);
    }

    public function test_transfer_is_balanced_and_capacity_is_enforced(): void
    {
        $source = Warehouse::create(['name' => 'Source', 'type' => 'warehouse']);
        StockBalance::create(['warehouse_id' => $source->id, 'product_id' => $this->product->id, 'quantity' => 200]);
        app(LotLedger::class)->opening(Warehouse::findOrFail($source->id), Product::findOrFail($this->product->id));
        $p = ['from_id' => $source->id, 'to_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => 20, 'recipient_id' => $this->rep->id, 'note' => 'Synthetic checked load'];
        $request = $this->postAction('stock.transfer', $p)->assertOk()->json('handover_id');
        $this->assertDatabaseCount('stock_movements', 0);
        $this->actingAs($this->rep);
        $this->postJson('/ops/handovers/'.$request.'/decision', ['request_id' => (string) Str::uuid(), 'decision' => 'accepted', 'note' => 'Checked every item and quantity', 'acknowledge' => true])->assertOk();
        $this->actingAs($this->manager);
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $source->id, 'quantity' => 180]);
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->warehouse->id, 'quantity' => 70]);
        $this->assertSame(0, (int) DB::table('stock_movements')->sum('quantity'));
        $p['quantity'] = 31;
        $this->postAction('stock.transfer', $p)->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_inactive_membership_cannot_read(): void
    {
        DB::table('company_user')->where('user_id', $this->manager->id)->update(['is_active' => false]);
        $this->getJson('/ops/data')->assertForbidden();
    }

    public function test_catalog_receipt_is_audited_and_does_not_allow_direct_vehicle_receiving(): void
    {
        $id = $this->postAction('product.create', ['name' => 'Generic food', 'sku' => 'FOOD-1', 'unit' => 'bag', 'price_minor' => 1234, 'reorder_level' => 15])->assertOk()->json('id');
        $this->postAction('product.create', ['name' => 'Duplicate', 'sku' => 'FOOD-1', 'unit' => 'bag', 'price_minor' => 1234, 'reorder_level' => 15])->assertUnprocessable();
        $warehouse = $this->postAction('warehouse.create', ['name' => 'Second warehouse'])->assertOk()->json('id');
        $p = ['warehouse_id' => $warehouse, 'product_id' => $id, 'quantity' => 30, 'reference' => 'DEMO-IN-30'];
        $key = (string) Str::uuid();
        $this->postAction('stock.receive', $p, $key)->assertOk();
        $this->postAction('stock.receive', $p, $key)->assertOk();
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $warehouse, 'product_id' => $id, 'quantity' => 30]);
        $this->assertDatabaseCount('stock_movements', 1);
        $p['warehouse_id'] = $this->warehouse->id;
        $this->postAction('stock.receive', $p)->assertUnprocessable();
    }

    public function test_credit_limit_change_requires_manager_and_preserves_existing_debt(): void
    {
        $id = $this->makeOrder();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $this->postAction('customer.credit', ['id' => $this->customer->id, 'credit_limit_minor' => 0, 'reason' => 'Cash only from now'])->assertOk();
        $this->getJson('/ops/data')->assertJsonPath('metrics.unpaid_minor', 3998);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.credit_limit_changed', 'subject_id' => $this->customer->id]);
        $this->actingAs($this->rep);
        $this->postAction('customer.credit', ['id' => $this->customer->id, 'credit_limit_minor' => 100000, 'reason' => 'Not authorized'])->assertForbidden();
    }

    public function test_role_revocation_also_blocks_cached_operation_replay(): void
    {
        $key = (string) Str::uuid();
        $p = $this->orderData();
        $this->postAction('order.create', $p, $key)->assertOk();
        DB::table('company_user')->where('user_id', $this->manager->id)->update(['role' => 'viewer']);
        $this->postAction('order.create', $p, $key)->assertForbidden();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_payment_promise_is_a_recorded_followup_not_a_collection(): void
    {
        $id = $this->makeOrder();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $this->actingAs($this->rep);
        $p = ['order_id' => $id, 'outcome' => 'promised', 'note' => 'Synthetic promise next week', 'next_followup_date' => today('Asia/Riyadh')->addWeek()->toDateString(), 'promised_minor' => 1000];
        $key = (string) Str::uuid();
        $this->postAction('debt.followup', $p, $key)->assertOk();
        $this->postAction('debt.followup', $p, $key)->assertOk();
        $this->assertDatabaseCount('debt_followups', 1);
        $this->assertDatabaseCount('collections', 0);
        $this->assertDatabaseHas('orders', ['id' => $id, 'paid_minor' => 0]);
        $this->getJson('/ops/data')->assertJsonPath('metrics.unpaid_minor', 3998)->assertJsonPath('followups.0.promised_minor', 1000);
        $p['promised_minor'] = 4000;
        $this->postAction('debt.followup', $p)->assertUnprocessable();
        $this->actingAs($this->otherRep);
        $this->getJson('/ops/data')->assertJsonCount(0, 'followups');
        $this->postAction('debt.followup', $p)->assertForbidden();
    }

    public function test_supervisor_can_follow_up_credit_without_receiving_money(): void
    {
        $id = $this->makeOrder();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        DB::table('company_user')->where('user_id', $this->otherRep->id)->update(['role' => 'supervisor']);
        $this->actingAs($this->otherRep);
        $this->postAction('debt.followup', ['order_id' => $id, 'outcome' => 'contacted', 'note' => 'Demo followup', 'next_followup_date' => today('Asia/Riyadh')->toDateString()])->assertOk();
        $this->postAction('collection.create', ['order_id' => $id, 'amount_minor' => 1000, 'method' => 'cash'])->assertForbidden();
        $this->assertDatabaseHas('orders', ['paid_minor' => 0]);
    }

    public function test_multi_product_delivery_rolls_back_all_lines_if_one_product_has_insufficient_stock(): void
    {
        $second = Product::create(['name' => 'Second', 'sku' => 'SECOND', 'unit' => 'bag', 'price_minor' => 502, 'reorder_level' => 10]);
        StockBalance::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $second->id, 'quantity' => 1]);
        app(LotLedger::class)->opening(Warehouse::findOrFail($this->warehouse->id), Product::findOrFail($second->id));
        $p = $this->orderData();
        $p['items'][] = ['product_id' => $second->id, 'quantity' => 2];
        $id = $this->postAction('order.create', $p)->assertOk()->json('id');
        $this->assertDatabaseHas('orders', ['id' => $id, 'total_minor' => 5002]);
        $this->postAction('order.fulfill', ['id' => $id])->assertUnprocessable();
        $this->assertDatabaseHas('stock_balances', ['product_id' => $this->product->id, 'quantity' => 50]);
        $this->assertDatabaseHas('stock_balances', ['product_id' => $second->id, 'quantity' => 1]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('orders', ['id' => $id, 'status' => 'draft', 'paid_minor' => 0]);
    }

    public function test_export_uses_company_timezone_at_midnight(): void
    {
        $id = $this->makeOrder();
        $this->postAction('order.fulfill', ['id' => $id])->assertOk();
        $this->context();
        $code = Order::findOrFail($id)->code;
        $date = today('Asia/Riyadh')->toDateString();
        $utc = CarbonImmutable::parse($date.' 00:30:00', 'Asia/Riyadh')->utc();
        DB::table('orders')->where('id', $id)->update(['created_at' => $utc]);
        $response = $this->get('/reports/export?type=sales&from='.$date.'&to='.$date)->assertOk();
        $this->assertStringContainsString($code, $response->streamedContent());
    }

    public function test_report_exports_requested_range_and_escapes_spreadsheet_formulas(): void
    {
        DB::table('customers')->where('id', $this->customer->id)->update(['name' => '=HYPERLINK("evil")']);
        $response = $this->get('/reports/export?type=visits&from='.today('Asia/Riyadh')->toDateString().'&to='.today('Asia/Riyadh')->toDateString());
        $response->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }
}
