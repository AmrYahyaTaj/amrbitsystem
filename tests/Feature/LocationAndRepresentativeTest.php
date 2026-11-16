<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationAndRepresentativeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $supervisor;

    private User $rep;

    private User $other;

    private Customer $customer;

    private Area $area;

    private Vehicle $vehicle;

    private Vehicle $replacement;

    private Trip $trip;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Synthetic', 'slug' => 'location-team']);
        $this->manager = User::factory()->create();
        $this->supervisor = User::factory()->create();
        $this->rep = User::factory()->create();
        $this->other = User::factory()->create();
        $this->company->users()->attach([$this->manager->id => ['role' => 'manager', 'is_active' => true], $this->supervisor->id => ['role' => 'supervisor', 'is_active' => true], $this->rep->id => ['role' => 'representative', 'is_active' => true], $this->other->id => ['role' => 'representative', 'is_active' => true]]);
        $this->context();
        $this->area = Area::create(['name' => 'Synthetic area', 'city' => 'Test']);
        $this->customer = Customer::create(['area_id' => $this->area->id, 'representative_id' => $this->rep->id, 'name' => 'Synthetic shop', 'code' => 'SYN', 'phone' => '000-test', 'address' => 'Synthetic location', 'latitude' => 24.7, 'longitude' => 46.7, 'geofence_radius_m' => 150]);
        $this->vehicle = $this->car('SYN-1', 'van');
        $this->replacement = $this->car('SYN-2', 'light_truck');
        $this->trip = Trip::create(['code' => 'TR-SYN', 'representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'warehouse_id' => Warehouse::where('vehicle_id', $this->vehicle->id)->value('id'), 'scheduled_for' => today('Asia/Riyadh')->toDateString()]);
        $this->trip->route_snapshot = '{"distance_m":1234}';
        $this->trip->save();
        $this->visit = Visit::create(['trip_id' => $this->trip->id, 'customer_id' => $this->customer->id, 'representative_id' => $this->rep->id, 'sequence' => 1, 'scheduled_for' => $this->trip->scheduled_for]);
        $this->actor($this->manager);
    }

    private function context(): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
    }

    private function actor(User $u): void
    {
        $this->actingAs($u)->withSession(['company_id' => $this->company->id]);
    }

    private function car(string $plate, string $type): Vehicle
    {
        $v = Vehicle::create(['name' => $plate, 'plate' => $plate, 'vehicle_type' => $type, 'capacity_units' => 100]);
        Warehouse::create(['name' => 'Custody '.$plate, 'type' => 'vehicle', 'vehicle_id' => $v->id]);

        return $v;
    }

    private function action(string $a, array $p = [], bool $ops = false, ?string $key = null)
    {
        return $this->postJson(($ops ? '/ops/action/' : '/ops/journey/').$a, ['request_id' => $key ?? (string) Str::uuid()] + $p);
    }

    private function location(): array
    {
        return ['id' => $this->customer->id, 'profile_version' => 1, 'latitude' => 24.71, 'longitude' => 46.72, 'reason' => 'Synthetic point correction'];
    }

    private function assignment(?int $vehicleId = null): array
    {
        return ['representative_id' => $this->rep->id, 'vehicle_id' => $vehicleId ?? $this->replacement->id, 'current_assignment_id' => 0, 'update_plans' => false, 'reason' => 'Synthetic allocation'];
    }

    public function test_manager_can_correct_location_with_planned_stops_without_deleting_history(): void
    {
        $this->action('customer.location', $this->location())->assertOk();
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'latitude' => 24.71, 'longitude' => 46.72, 'profile_version' => 2, 'geofence_radius_m' => 150]);
        $this->assertDatabaseHas('visits', ['id' => $this->visit->id, 'status' => 'planned', 'location_verified' => false]);
        $this->assertDatabaseHas('trips', ['id' => $this->trip->id, 'route_snapshot' => null, 'plan_version' => 2]);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->action('customer.location', $this->location())->assertStatus(409);
    }

    public function test_in_progress_visit_freezes_the_proof_point(): void
    {
        DB::table('visits')->where('id', $this->visit->id)->update(['status' => 'in_progress', 'location_verified' => true]);
        $this->action('customer.location', $this->location())->assertUnprocessable();
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'latitude' => 24.7]);
    }

    public function test_rep_can_edit_own_contacts_but_location_needs_review_and_other_customer_is_forbidden(): void
    {
        $this->actor($this->other);
        $this->action('customer.location', $this->location())->assertForbidden();
        $this->actor($this->rep);
        $this->action('customer.contact', ['id' => $this->customer->id, 'profile_version' => 1, 'name' => 'Synthetic updated', 'phone' => '000-updated', 'address' => 'Synthetic address', 'service_notes' => 'Instruction', 'reason' => 'Synthetic contact correction', 'latitude' => 30, 'representative_id' => $this->other->id])->assertOk();
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'phone' => '000-updated', 'latitude' => 24.7, 'representative_id' => $this->rep->id]);
        $id = $this->action('customer.location', array_replace($this->location(), ['profile_version' => 2]))->assertOk()->json('id');
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'latitude' => 24.7]);
        $this->action('customer.review', ['id' => $id, 'decision' => 'approved', 'review_note' => 'Cannot approve self'])->assertForbidden();
        $this->actor($this->supervisor);
        $this->action('customer.review', ['id' => $id, 'decision' => 'approved', 'review_note' => 'Synthetic verified point'])->assertOk();
        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'latitude' => 24.71]);
        $this->assertDatabaseHas('customer_changes', ['id' => $id, 'status' => 'approved', 'reviewed_by' => $this->supervisor->id]);
        $this->action('customer.review', ['id' => $id, 'decision' => 'approved', 'review_note' => 'Repeated approval'])->assertStatus(409);
    }

    public function test_rep_registration_stays_pending_then_creates_one_owned_customer_when_approved(): void
    {
        $this->actor($this->rep);
        $id = $this->action('customer.create', ['name' => 'Synthetic new customer', 'code' => 'NEW-SYN', 'area_id' => $this->area->id, 'representative_id' => $this->other->id, 'address' => 'Synthetic place', 'latitude' => 24.8, 'longitude' => 46.8, 'geofence_radius_m' => 150], true)->assertOk()->json('id');
        $this->assertDatabaseCount('customers', 1);
        $this->actor($this->other);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'customer_changes');
        $this->actor($this->manager);
        $key = (string) Str::uuid();
        $payload = ['id' => $id, 'decision' => 'approved', 'review_note' => 'Synthetic registration verified'];
        $this->action('customer.review', $payload, false, $key)->assertOk();
        $this->action('customer.review', $payload, false, $key)->assertOk();
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseHas('customers', ['code' => 'NEW-SYN', 'representative_id' => $this->rep->id]);
    }

    public function test_supervisor_edits_operational_member_fields_without_changing_login_or_roles(): void
    {
        $this->actor($this->supervisor);
        $p = ['id' => $this->rep->id, 'operations_version' => 1, 'name' => 'Synthetic renamed rep', 'phone' => '000-demo', 'employee_code' => 'REP-X', 'notes' => 'Synthetic note', 'reason' => 'Synthetic edit', 'role' => 'manager', 'username' => 'cannot-change'];
        $this->action('representative.details', $p)->assertOk();
        $this->assertDatabaseHas('users', ['id' => $this->rep->id, 'username' => $this->rep->username, 'name' => 'Synthetic renamed rep']);
        $this->assertDatabaseHas('company_user', ['company_id' => $this->company->id, 'user_id' => $this->rep->id, 'role' => 'representative', 'phone' => '000-demo', 'operations_version' => 2]);
        $this->action('representative.details', $p)->assertStatus(409);
        $this->actor($this->rep);
        $this->action('representative.details', $p)->assertForbidden();
    }

    public function test_vehicle_allocation_updates_selected_plans_and_preserves_replacement_history_and_stock(): void
    {
        $this->actor($this->supervisor);
        $id = $this->action('representative.vehicle', $this->assignment() + [])->assertOk()->json('id');
        $this->assertDatabaseHas('trips', ['id' => $this->trip->id, 'vehicle_id' => $this->vehicle->id]);
        $this->action('representative.vehicle', ['representative_id' => $this->other->id, 'vehicle_id' => $this->replacement->id, 'current_assignment_id' => 0, 'update_plans' => false, 'reason' => 'Duplicate allocation'])->assertUnprocessable();
        $this->action('representative.vehicle', array_replace($this->assignment($this->vehicle->id), ['current_assignment_id' => $id, 'update_plans' => true]))->assertOk();
        $this->assertDatabaseHas('trips', ['id' => $this->trip->id, 'vehicle_id' => $this->vehicle->id, 'plan_version' => 2]);
        $this->assertDatabaseCount('representative_vehicle_assignments', 2);
        $this->assertNotNull(DB::table('representative_vehicle_assignments')->where('id', $id)->value('returned_at'));
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('vehicle_assignments', 0);
        $this->action('representative.vehicle', $this->assignment())->assertStatus(409);
    }

    public function test_active_trip_blocks_vehicle_change_and_foreign_company_resources_are_rejected(): void
    {
        DB::table('trips')->where('id', $this->trip->id)->update(['status' => 'active']);
        $this->action('representative.vehicle', $this->assignment())->assertUnprocessable();
        DB::table('trips')->where('id', $this->trip->id)->update(['status' => 'planned']);
        $foreign = Company::create(['name' => 'Foreign', 'slug' => 'foreign-location']);
        app(CompanyContext::class)->set($foreign,CompanyRole::Manager);
        $v = $this->car('FOREIGN','truck');
        $this->action('representative.vehicle',$this->assignment($v->id))->assertNotFound();
        $this->assertDatabaseCount('representative_vehicle_assignments',0);
    }
}
