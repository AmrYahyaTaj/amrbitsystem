<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Models\WeeklyPlan;
use App\Services\LotLedger;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $rep;

    private User $other;

    private Vehicle $vehicle;

    private Warehouse $warehouse;

    private array $customers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Synthetic Company', 'slug' => 'journey']);
        $this->manager = User::factory()->create();
        $this->rep = User::factory()->create();
        $this->other = User::factory()->create();
        $this->company->users()->attach([$this->manager->id => ['role' => 'manager', 'is_active' => true], $this->rep->id => ['role' => 'representative', 'is_active' => true], $this->other->id => ['role' => 'representative', 'is_active' => true]]);
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $area = Area::create(['name' => 'Test Area', 'city' => 'Test']);
        $this->customers = [];
        foreach ([24.73, 24.71, 24.72] as $i => $lat) {
            $this->customers[] = Customer::create(['name' => 'Synthetic '.$i, 'code' => 'SYN-'.$i, 'area_id' => $area->id, 'representative_id' => $this->rep->id, 'address' => 'Synthetic address', 'latitude' => $lat, 'longitude' => 46.7, 'geofence_radius_m' => 150]);
        }
        $this->vehicle = Vehicle::create(['name' => 'Test vehicle', 'plate' => 'SYN', 'capacity_units' => 200]);
        $this->warehouse = Warehouse::create(['name' => 'Custody', 'type' => 'vehicle', 'vehicle_id' => $this->vehicle->id]);
        $this->actor($this->manager);
    }

    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['company_id' => $this->company->id]);
    }

    private function journey(string $action, array $data, ?string $key = null)
    {
        return $this->postJson('/ops/journey/'.$action, ['request_id' => $key ?? (string) Str::uuid()] + $data);
    }

    private function action(string $action, array $data)
    {
        return $this->postJson('/ops/action/'.$action, ['request_id' => (string) Str::uuid()] + $data);
    }

    private function template(array $override = []): array
    {
        return array_replace(['representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'weekday' => today('Asia/Riyadh')->dayOfWeek, 'customer_ids' => array_column($this->customers, 'id'), 'origin_label' => 'Synthetic warehouse', 'origin_latitude' => 24.7, 'origin_longitude' => 46.7, 'notes' => 'Synthetic instructions', 'is_active' => true, 'version' => 0], $override);
    }

    private function publish(): int
    {
        $id = $this->journey('weekly.save', $this->template())->assertOk()->json('id');

        return $this->journey('weekly.publish', ['id' => $id, 'version' => 1, 'date' => today('Asia/Riyadh')->toDateString()])->assertOk()->json('id');
    }

    public function test_customer_ownership_is_enforced_for_data_profile_and_direct_requests(): void
    {
        $this->actor($this->other);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'customers');
        $this->getJson('/ops/customers/'.$this->customers[0]->id)->assertForbidden();
        $this->get('/customers/'.$this->customers[0]->id)->assertForbidden();
        $this->journey('weekly.save', $this->template())->assertForbidden();
        $this->actor($this->rep);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(3, 'customers');
        $this->get('/customers/'.$this->customers[0]->id)->assertOk();
        $this->getJson('/ops/customers/'.$this->customers[0]->id)->assertOk()->assertJsonPath('customer.representative.id', $this->rep->id);
        $this->actor($this->manager);
        $foreign = Company::create(['name' => 'Other', 'slug' => 'other']);
        app(CompanyContext::class)->set($foreign, CompanyRole::Manager);
        $area = Area::create(['name' => 'Other', 'city' => 'Other']);
        $c = Customer::create(['name' => 'Private', 'code' => 'P', 'area_id' => $area->id, 'address' => 'Private', 'latitude' => 25, 'longitude' => 47]);
        $this->getJson('/ops/customers/'.$c->id)->assertNotFound();
    }

    public function test_profile_totals_cover_full_history_and_history_is_paginated(): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $product = Product::create(['name' => 'Synthetic paper', 'sku' => 'P', 'unit' => 'box', 'price_minor' => 100, 'reorder_level' => 5]);
        for ($i = 0; $i < 23; $i++) {
            $o = Order::create(['code' => 'O-'.$i, 'customer_id' => $this->customers[0]->id, 'representative_id' => $this->rep->id, 'warehouse_id' => $this->warehouse->id, 'payment_terms' => 'credit']);
            DB::table('orders')->where('id', $o->id)->update(['status' => 'fulfilled', 'total_minor' => 200, 'paid_minor' => 0]);
            $o->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price_minor' => 100, 'total_minor' => 200]);
        }
        $this->getJson('/ops/customers/'.$this->customers[0]->id.'?tab=orders')->assertOk()->assertJsonPath('summary.balance_minor', 4600)->assertJsonPath('history.total', 23)->assertJsonCount(20, 'history.data')->assertJsonPath('products.0.quantity', 46);
        $this->getJson('/ops/customers/'.$this->customers[0]->id.'?tab=orders&page=2')->assertOk()->assertJsonCount(3, 'history.data');
        $this->getJson('/ops/customers/'.$this->customers[0]->id.'?tab=ledger')->assertOk()->assertJsonPath('history.total', 23);
    }

    public function test_weekly_publish_orders_stops_and_is_idempotent_without_rewriting_occurrences(): void
    {
        $tripId = $this->publish();
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $plan = WeeklyPlan::sole();
        $payload = ['id' => $plan->id, 'version' => 1, 'date' => today('Asia/Riyadh')->toDateString()];
        $this->journey('weekly.publish', $payload)->assertOk()->assertJsonPath('id', $tripId);
        $this->assertDatabaseCount('trips', 1);
        $this->assertDatabaseCount('visits', 3);
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->assertSame([$this->customers[1]->id, $this->customers[2]->id, $this->customers[0]->id], Visit::where('trip_id', $tripId)->orderBy('sequence')->pluck('customer_id')->all());
        $this->journey('weekly.save', $this->template(['version' => 1, 'customer_ids' => [$this->customers[0]->id]]))->assertOk();
        $this->assertDatabaseCount('visits', 3);
        $this->assertDatabaseHas('trips', ['id' => $tripId, 'weekly_plan_version' => 1]);
        $this->journey('weekly.save', $this->template(['version' => 1]))->assertStatus(409);
    }

    public function test_weekly_plans_reject_other_owners_foreign_customers_and_wrong_weekday(): void
    {
        $this->journey('weekly.save', $this->template(['representative_id' => $this->other->id]))->assertUnprocessable();
        $id = $this->journey('weekly.save', $this->template())->assertOk()->json('id');
        $this->journey('weekly.publish', ['id' => $id, 'version' => 1, 'date' => today('Asia/Riyadh')->addDay()->toDateString()])->assertUnprocessable();
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_rep_must_resolve_each_stop_with_reason_and_real_location_before_closing(): void
    {
        $tripId = $this->publish();
        $this->actor($this->rep);
        $this->action('trip.start', ['id' => $tripId])->assertOk();
        app(CompanyContext::class)->set($this->company, CompanyRole::Representative);
        $stops = Visit::where('trip_id', $tripId)->orderBy('sequence')->get();
        $this->action('visit.finish', ['id' => $stops[1]->id, 'status' => 'skipped', 'reason_code' => 'no_time', 'note' => 'Synthetic reason'])->assertUnprocessable();
        $this->action('visit.finish', ['id' => $stops[0]->id, 'status' => 'skipped', 'note' => 'Synthetic reason'])->assertUnprocessable();
        $this->action('trip.close', ['id' => $tripId])->assertUnprocessable();
        $this->action('visit.finish', ['id' => $stops[0]->id, 'status' => 'skipped', 'reason_code' => 'called_no_answer', 'note' => 'Synthetic attempted call'])->assertOk();
        $customer = collect($this->customers)->firstWhere('id', $stops[1]->customer_id);
        $this->action('visit.checkin', ['id' => $stops[1]->id, 'latitude' => $customer->latitude, 'longitude' => $customer->longitude, 'accuracy_m' => 10, 'captured_at' => now()->toIso8601String()])->assertOk();
        $this->action('visit.finish', ['id' => $stops[1]->id, 'status' => 'completed'])->assertUnprocessable();
        $this->action('visit.finish', ['id' => $stops[1]->id, 'status' => 'completed', 'reason_code' => 'no_sale', 'note' => 'Synthetic no sale visit'])->assertOk();
        $this->action('visit.finish', ['id' => $stops[2]->id, 'status' => 'skipped', 'reason_code' => 'no_time', 'note' => 'Synthetic reason'])->assertOk();
        $this->action('trip.close', ['id' => $tripId])->assertOk();
        $this->assertDatabaseHas('visits', ['id' => $stops[0]->id, 'reason_code' => 'called_no_answer']);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_customer_edit_preserves_history_and_rejects_stale_or_unsafe_reassignment(): void
    {
        $c = $this->customers[0];
        $payload = $c->only(['name', 'phone', 'address', 'area_id', 'representative_id', 'latitude', 'longitude', 'geofence_radius_m']) + ['is_active' => true, 'service_notes' => 'Synthetic service instruction', 'reason' => 'Synthetic ownership change', 'profile_version' => 1, 'id' => $c->id];
        $this->journey('customer.update', $payload)->assertOk();
        $this->journey('customer.update', $payload)->assertStatus(409);
        $this->assertDatabaseHas('customers', ['id' => $c->id, 'service_notes' => 'Synthetic service instruction', 'profile_version' => 2]);
        $this->publish();
        $this->journey('customer.update', array_replace($payload, ['profile_version' => 2, 'representative_id' => $this->other->id]))->assertUnprocessable();
        $this->actor($this->rep);
        $this->journey('customer.update', array_replace($payload, ['profile_version' => 2]))->assertForbidden();
    }

    public function test_visit_result_does_not_deduct_delivered_stock_twice(): void
    {
        $tripId = $this->publish();
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $product = Product::create(['name' => 'Synthetic paper boxes', 'sku' => 'P-DELIVERY', 'unit' => 'box', 'price_minor' => 500, 'reorder_level' => 2]);
        StockBalance::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id, 'quantity' => 10]);
        app(LotLedger::class)->opening(Warehouse::findOrFail($this->warehouse->id), Product::findOrFail($product->id));
        $visit = Visit::with('customer')->where('trip_id', $tripId)->orderBy('sequence')->firstOrFail();
        $this->actor($this->rep);
        $this->action('trip.start', ['id' => $tripId])->assertOk();
        $this->action('visit.checkin', ['id' => $visit->id, 'latitude' => $visit->customer->latitude, 'longitude' => $visit->customer->longitude, 'accuracy_m' => 8, 'captured_at' => now()->toIso8601String()])->assertOk();
        $id = $this->action('order.create', ['customer_id' => $visit->customer_id, 'representative_id' => $this->rep->id, 'trip_id' => $tripId, 'warehouse_id' => $this->warehouse->id, 'payment_terms' => 'credit', 'due_date' => today('Asia/Riyadh')->addDays(7)->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 2]]])->assertOk()->json('id');
        $this->action('order.fulfill', ['id' => $id])->assertOk();
        $this->action('visit.finish', ['id' => $visit->id, 'status' => 'completed', 'note' => 'Synthetic two boxes delivered'])->assertOk();
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id, 'quantity' => 8]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->getJson('/ops/customers/'.$visit->customer_id)->assertOk()->assertJsonPath('summary.balance_minor', 1000)->assertJsonPath('products.0.quantity', 2);
        $this->action('order.fulfill', ['id' => $id])->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_manual_trip_edit_removes_outdated_distance_without_deleting_weekly_origin(): void
    {
        $tripId = $this->publish();
        $this->assertNotNull(DB::table('trips')->where('id', $tripId)->value('route_snapshot'));
        $this->action('trip.update', ['id' => $tripId, 'plan_version' => 1, 'representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'scheduled_for' => today('Asia/Riyadh')->toDateString(), 'customer_ids' => array_column($this->customers, 'id'), 'reason' => 'Synthetic manual reordering'])->assertOk();
        $trip = DB::table('trips')->where('id', $tripId)->first();
        $this->assertNull($trip->route_snapshot);
        $this->assertNotNull($trip->weekly_plan_id);
        $this->assertSame(2, $trip->plan_version);
    }

    public function test_dated_day_endpoint_returns_full_own_stops_and_no_other_representative_records(): void
    {
        $this->publish();
        $this->actor($this->other);
        $url = '/ops/my-day?date='.today('Asia/Riyadh')->toDateString();
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'trips')->assertJsonCount(0, 'visits');
        $this->actor($this->rep);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'trips')->assertJsonCount(3, 'visits');
        $this->getJson('/ops/my-day?date=invalid')->assertUnprocessable();
        $this->actor($this->manager);
        $this->getJson($url)->assertForbidden();
    }
}
