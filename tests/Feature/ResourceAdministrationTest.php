<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResourceAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $rep;

    private Area $area;

    private Area $otherArea;

    private Vehicle $vehicle;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Synthetic', 'slug' => 'synthetic']);
        $this->manager = User::factory()->create();
        $this->rep = User::factory()->create();
        $this->company->users()->attach([$this->manager->id => ['role' => 'manager', 'area_access' => 'all'], $this->rep->id => ['role' => 'representative', 'area_access' => 'selected']]);
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->area = Area::create(['name' => 'Allowed', 'city' => 'Test']);
        $this->otherArea = Area::create(['name' => 'Denied', 'city' => 'Test']);
        DB::table('representative_areas')->insert(['company_id' => $this->company->id, 'user_id' => $this->rep->id, 'area_id' => $this->area->id]);
        $this->vehicle = Vehicle::create(['name' => 'Vehicle', 'plate' => 'SYN-1', 'capacity_units' => 100]);
        $this->warehouse = Warehouse::create(['name' => 'Vehicle custody', 'type' => 'vehicle', 'vehicle_id' => $this->vehicle->id]);
        $this->actingAs($this->manager)->withSession(['company_id' => $this->company->id]);
    }

    private function action(string $name, array $payload, ?string $requestId = null)
    {
        return $this->postJson('/ops/action/'.$name, $payload + ['request_id' => $requestId ?? (string) Str::uuid()]);
    }

    private function acknowledgeVehicle(int $handoverId): int
    {
        $this->actingAs($this->rep);
        $this->postJson('/ops/handovers/'.$handoverId.'/decision', ['request_id' => (string) Str::uuid(), 'decision' => 'accepted', 'note' => 'Synthetic counterpart checked and acknowledged', 'acknowledge' => true])->assertOk();
        $assignmentId = (int) DB::table('handover_requests')->where('id', $handoverId)->value('assignment_id');
        $this->actingAs($this->manager);
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);

        return $assignmentId;
    }

    private function memberData(): array
    {
        return ['name' => 'Synthetic new rep', 'username' => 'newrep', 'password' => 'Synthetic-Only-123!', 'password_confirmation' => 'Synthetic-Only-123!', 'role' => 'representative', 'is_active' => true, 'employee_code' => 'E-100', 'area_access' => 'selected', 'area_ids' => [$this->area->id], 'can_sell' => true, 'can_collect' => false];
    }

    private function customer(int $areaId): Customer
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);

        return Customer::create(['area_id' => $areaId, 'name' => 'Synthetic', 'code' => Str::random(8), 'address' => 'Synthetic', 'latitude' => 24.78, 'longitude' => 46.64, 'geofence_radius_m' => 150]);
    }

    private function tripData(int $customerId): array
    {
        return ['representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'scheduled_for' => now()->timezone('Asia/Riyadh')->toDateString(), 'customer_ids' => [$customerId]];
    }

    public function test_manager_creates_member_with_scoped_permissions_and_password_is_never_returned(): void
    {
        $requestId = (string) Str::uuid();
        $id = $this->action('member.create', $this->memberData(), $requestId)->assertOk()->json('id');
        $this->action('member.create', $this->memberData(), $requestId)->assertOk()->assertJsonPath('id', $id);
        $this->assertSame(1, User::where('username', 'newrep')->count());
        $this->assertTrue(Hash::check('Synthetic-Only-123!', User::findOrFail($id)->password));
        $this->assertDatabaseHas('representative_areas', ['company_id' => $this->company->id, 'user_id' => $id, 'area_id' => $this->area->id]);
        $json = $this->getJson('/ops/data')->assertOk()->json();
        $member = collect($json['member_directory'])->firstWhere('id', $id);
        $this->assertFalse($member['can_collect']);
        $this->assertArrayNotHasKey('password', $member);
        $this->assertStringNotContainsString('Synthetic-Only-123!', json_encode($json));
        $this->assertStringNotContainsString('Synthetic-Only-123!', (string) DB::table('audit_logs')->value('metadata'));
    }

    public function test_foreign_area_and_existing_username_do_not_create_or_take_over_an_account(): void
    {
        $other = Company::create(['name' => 'Other', 'slug' => 'other']);
        app(CompanyContext::class)->set($other, CompanyRole::Manager);
        $foreignArea = Area::create(['name' => 'Foreign', 'city' => 'Test']);
        $data = $this->memberData();
        $data['area_ids'] = [$foreignArea->id];
        $this->action('member.create', $data)->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['username' => 'newrep']);
        $data = $this->memberData();
        $data['username'] = $this->rep->username;
        $this->action('member.create', $data)->assertUnprocessable();
    }

    public function test_supervisor_cannot_manage_accounts_or_replay_a_previously_authorized_creation(): void
    {
        $requestId = (string) Str::uuid();
        $this->action('member.create', $this->memberData(), $requestId)->assertOk();
        DB::table('company_user')->where('user_id', $this->manager->id)->update(['role' => 'supervisor']);
        $this->action('member.create', $this->memberData(), $requestId)->assertForbidden();
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'member_directory');
    }

    public function test_suspension_revokes_existing_session_and_preserves_historical_membership(): void
    {
        $this->action('member.status', ['id' => $this->rep->id, 'is_active' => false, 'reason' => 'Synthetic suspension'])->assertOk();
        $this->assertDatabaseHas('company_user', ['user_id' => $this->rep->id, 'company_id' => $this->company->id, 'is_active' => false]);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'representatives');
        $this->actingAs($this->rep)->getJson('/ops/data')->assertForbidden();
        $this->actingAs($this->manager);
        $this->action('member.status', ['id' => $this->rep->id, 'is_active' => true, 'reason' => 'Synthetic reactivation'])->assertOk();
        $this->actingAs($this->rep)->getJson('/ops/data')->assertOk();
    }

    public function test_self_suspension_and_cross_company_account_updates_are_rejected(): void
    {
        $this->action('member.status', ['id' => $this->manager->id, 'is_active' => false, 'reason' => 'Must fail'])->assertUnprocessable();
        $foreign = User::factory()->create();
        $this->action('member.status', ['id' => $foreign->id, 'is_active' => false, 'reason' => 'Must fail'])->assertNotFound();
    }

    public function test_representative_area_policy_is_checked_on_planning_and_again_on_start(): void
    {
        $allowed = $this->customer($this->area->id);
        $denied = $this->customer($this->otherArea->id);
        $this->action('trip.create', $this->tripData($denied->id))->assertUnprocessable();
        $this->assertDatabaseCount('trips', 0);
        $id = $this->action('trip.create', $this->tripData($allowed->id))->assertOk()->json('id');
        DB::table('representative_areas')->where('user_id', $this->rep->id)->delete();
        $this->action('trip.start', ['id' => $id])->assertUnprocessable();
        $this->assertDatabaseHas('trips', ['id' => $id, 'status' => 'planned']);
    }

    public function test_revoking_sales_or_collection_capability_blocks_actions_before_validation(): void
    {
        DB::table('company_user')->where('user_id', $this->rep->id)->update(['can_sell' => false, 'can_collect' => false]);
        $this->actingAs($this->rep);
        $this->action('order.create', [])->assertForbidden();
        $this->action('order.fulfill', [])->assertForbidden();
        $this->action('collection.create', [])->assertForbidden();
    }

    public function test_manager_updates_rep_and_shared_identity_cannot_be_changed_by_one_company(): void
    {
        $data = $this->memberData();
        $data['id'] = $this->rep->id;
        $data['username'] = $this->rep->username;
        $data['reason'] = 'Synthetic reassignment';
        unset($data['password'], $data['password_confirmation']);
        $this->action('member.update', $data)->assertOk();
        $other = Company::create(['name' => 'Other', 'slug' => 'shared']);
        $other->users()->attach($this->rep->id, ['role' => 'representative']);
        $data['name'] = 'Attempt shared rename';
        $this->action('member.update', $data)->assertUnprocessable();
        $this->assertSame('Synthetic new rep', $this->rep->fresh()->name);
    }

    public function test_vehicle_type_is_explicit_and_legacy_records_are_not_invented_as_vans(): void
    {
        $this->getJson('/ops/data')->assertOk()->assertJsonPath('vehicles.0.vehicle_type', 'unspecified');
        $data = ['name' => 'Synthetic dyna', 'plate' => 'SYN-2', 'vehicle_type' => 'light_truck', 'status' => 'available', 'ownership' => 'owned', 'capacity_units' => 100, 'payload_kg' => 3000, 'make' => 'Synthetic'];
        $id = $this->action('vehicle.create', $data)->assertOk()->json('id');
        $this->assertDatabaseHas('vehicles', ['id' => $id, 'vehicle_type' => 'light_truck', 'payload_kg' => 3000]);
        $this->assertDatabaseHas('warehouses', ['vehicle_id' => $id, 'type' => 'vehicle']);
        $data['plate'] = 'SYN-3';
        $data['vehicle_type'] = 'invented-type';
        $this->action('vehicle.create', $data)->assertUnprocessable();
        $this->assertDatabaseMissing('vehicles', ['plate' => 'SYN-3']);
        $data['id'] = $id;
        $data['plate'] = 'SYN-2';
        $data['vehicle_type'] = 'truck';
        $data['reason'] = 'Correct documented type';
        $this->action('vehicle.update', $data)->assertOk();
        $this->assertDatabaseHas('vehicles', ['id' => $id, 'vehicle_type' => 'truck']);
    }

    public function test_vehicle_handover_history_is_exclusive_and_survives_return_and_reassignment(): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $driver = Driver::create(['name' => 'Synthetic driver', 'employee_code' => 'D-1', 'user_id' => $this->rep->id, 'license_number' => 'SYNTHETIC', 'license_expires_on' => now()->addYear()->toDateString()]);
        $firstRequest = $this->action('vehicle.assign', ['vehicle_id' => $this->vehicle->id, 'driver_id' => $driver->id, 'note' => 'Synthetic handover'])->assertOk()->json('id');
        $this->assertDatabaseCount('vehicle_assignments', 0);
        $first = $this->acknowledgeVehicle($firstRequest);
        $this->action('vehicle.assign', ['vehicle_id' => $this->vehicle->id, 'driver_id' => $driver->id, 'note' => 'Double assignment'])->assertUnprocessable();
        $return = $this->action('vehicle.release', ['id' => $first, 'note' => 'Synthetic return'])->assertOk()->json('id');
        $this->acknowledgeVehicle($return);
        $second = $this->action('vehicle.assign', ['vehicle_id' => $this->vehicle->id, 'driver_id' => $driver->id, 'note' => 'Second synthetic handover'])->assertOk()->json('id');
        $this->acknowledgeVehicle($second);
        $this->assertDatabaseCount('vehicle_assignments', 2);
        $this->assertDatabaseHas('vehicle_assignments', ['id' => $first, 'active_vehicle_id' => null, 'active_driver_id' => null]);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(2, 'vehicle_assignments');
    }

    public function test_expired_or_foreign_driver_cannot_receive_vehicle(): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $driver = Driver::create(['name' => 'Expired synthetic', 'employee_code' => 'D-2', 'license_number' => 'SYN', 'license_expires_on' => now()->subDay()->toDateString()]);
        $this->action('vehicle.assign', ['vehicle_id' => $this->vehicle->id, 'driver_id' => $driver->id, 'note' => 'Must fail'])->assertUnprocessable();
        $other = Company::create(['name' => 'Other', 'slug' => 'foreign-driver']);
        app(CompanyContext::class)->set($other, CompanyRole::Manager);
        $foreign = Driver::create(['name' => 'Foreign synthetic', 'employee_code' => 'D-3']);
        $this->action('vehicle.assign', ['vehicle_id' => $this->vehicle->id, 'driver_id' => $foreign->id, 'note' => 'Must fail'])->assertNotFound();
        $this->assertDatabaseCount('vehicle_assignments', 0);
    }

    public function test_direct_visit_and_order_requests_cannot_bypass_area_restrictions(): void
    {
        $customer = $this->customer($this->otherArea->id);
        $trip = Trip::create(['code' => 'SYN-DENIED', 'representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'warehouse_id' => $this->warehouse->id, 'scheduled_for' => now()->timezone('Asia/Riyadh')->toDateString()]);
        DB::table('trips')->where('id', $trip->id)->update(['status' => 'active']);
        $visit = Visit::create(['trip_id' => $trip->id, 'representative_id' => $this->rep->id, 'customer_id' => $customer->id, 'scheduled_for' => $trip->scheduled_for]);
        $product = Product::create(['name' => 'Synthetic item', 'sku' => 'SYN-ITEM', 'unit' => 'unit', 'price_minor' => 100]);
        $this->actingAs($this->rep);
        $this->action('visit.checkin', ['id' => $visit->id])->assertUnprocessable()->assertJsonPath('message', 'منطقة العميل غير مسموحة لهذا المندوب. عدّل التغطية من إدارة الفريق.');
        $this->action('order.create', ['customer_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'representative_id' => $this->rep->id, 'trip_id' => $trip->id, 'payment_terms' => 'credit', 'due_date' => now()->addWeek()->toDateString(), 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertUnprocessable()->assertJsonPath('message', 'منطقة العميل غير مسموحة لهذا المندوب. عدّل التغطية من إدارة الفريق.');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('visits', ['id' => $visit->id, 'status' => 'planned']);
    }

    public function test_active_trip_blocks_vehicle_return_and_assigned_driver_cannot_be_disabled(): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $driver = Driver::create(['name' => 'Synthetic', 'employee_code' => 'D-ACTIVE', 'user_id' => $this->rep->id, 'license_number' => 'SYN', 'license_expires_on' => now()->addYear()->toDateString()]);
        $request = $this->action('vehicle.assign', ['vehicle_id' => $this->vehicle->id, 'driver_id' => $driver->id, 'note' => 'Synthetic handover'])->assertOk()->json('id');
        $assignment = $this->acknowledgeVehicle($request);
        $customer = $this->customer($this->area->id);
        $trip = Trip::create(['code' => 'SYN-ACTIVE', 'representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'warehouse_id' => $this->warehouse->id, 'scheduled_for' => now()->toDateString(), 'driver_id' => $driver->id]);
        DB::table('trips')->where('id', $trip->id)->update(['status' => 'active']);
        $this->action('vehicle.release', ['id' => $assignment, 'note' => 'Must not return during trip'])->assertUnprocessable();
        $this->action('driver.update', ['id' => $driver->id, 'name' => $driver->name, 'employee_code' => $driver->employee_code, 'status' => 'inactive', 'reason' => 'Must not disable custody holder'])->assertUnprocessable();
        $this->assertDatabaseHas('vehicle_assignments', ['id' => $assignment, 'returned_at' => null]);
        $this->assertDatabaseHas('drivers', ['id' => $driver->id, 'status' => 'active']);
    }
}
