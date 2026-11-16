<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Enums\VisitStatus;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StockBalance;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TripPlanningTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $rep;

    private User $secondRep;

    private Vehicle $vehicle;

    private array $customers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Synthetic', 'slug' => 'synthetic']);
        $this->manager = User::factory()->create();
        $this->rep = User::factory()->create();
        $this->secondRep = User::factory()->create();
        $this->company->users()->attach([$this->manager->id => ['role' => 'manager'], $this->rep->id => ['role' => 'representative'], $this->secondRep->id => ['role' => 'representative']]);
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $area = Area::create(['name' => 'Synthetic area', 'city' => 'Synthetic']);
        $this->customers = [];
        for ($i = 1; $i <= 3; $i++) {
            $this->customers[] = Customer::create(['name' => 'Synthetic '.$i, 'code' => 'C-'.$i, 'area_id' => $area->id, 'address' => 'Synthetic', 'latitude' => 24.78, 'longitude' => 46.64, 'geofence_radius_m' => 150]);
        }
        $this->vehicle = $this->vehicle('SYN-1');
        $this->actingAs($this->manager)->withSession(['company_id' => $this->company->id]);
    }

    private function vehicle(string $plate): Vehicle
    {
        $vehicle = Vehicle::create(['name' => 'Synthetic vehicle', 'plate' => $plate, 'capacity_units' => 500]);
        Warehouse::create(['name' => 'Custody '.$plate, 'type' => 'vehicle', 'vehicle_id' => $vehicle->id]);

        return $vehicle;
    }

    private function action(string $action, array $payload, ?string $key = null)
    {
        return $this->postJson('/ops/action/'.$action, ['request_id' => $key ?? (string) Str::uuid()] + $payload);
    }

    private function planData(): array
    {
        return ['representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'scheduled_for' => today('Asia/Riyadh')->toDateString(), 'customer_ids' => [$this->customers[1]->id, $this->customers[0]->id], 'planning_notes' => 'Synthetic instructions'];
    }

    private function plan(): Trip
    {
        $id = $this->action('trip.create', $this->planData())->assertOk()->json('id');
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);

        return Trip::findOrFail($id);
    }

    private function revision(Trip $trip): array
    {
        return $this->planData() + ['id' => $trip->id, 'plan_version' => $trip->plan_version, 'reason' => 'Synthetic plan correction'];
    }

    public function test_replanning_preserves_stops_reassigns_resources_and_records_before_and_after(): void
    {
        $trip = $this->plan();
        $oldIds = $trip->visits()->pluck('id', 'customer_id');
        $vehicle = $this->vehicle('SYN-2');
        $data = array_replace($this->revision($trip), ['customer_ids' => [$this->customers[2]->id, $this->customers[0]->id], 'representative_id' => $this->secondRep->id, 'vehicle_id' => $vehicle->id, 'scheduled_for' => today('Asia/Riyadh')->addDay()->toDateString()]);
        $key = (string) Str::uuid();
        $this->action('trip.update', $data, $key)->assertOk();
        $this->action('trip.update', $data, $key)->assertOk();
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $trip->refresh();
        $this->assertSame(2, $trip->plan_version);
        $this->assertSame($this->secondRep->id, $trip->representative_id);
        $this->assertSame($vehicle->id, $trip->vehicle_id);
        $this->assertSame(Warehouse::where('vehicle_id', $vehicle->id)->value('id'), $trip->warehouse_id);
        $stops = $trip->visits()->get()->keyBy('customer_id');
        $this->assertSame(VisitStatus::Cancelled, $stops[$this->customers[1]->id]->status);
        $this->assertSame($oldIds[$this->customers[0]->id], $stops[$this->customers[0]->id]->id);
        $this->assertSame(2, $stops[$this->customers[0]->id]->sequence);
        $this->assertSame(1, $stops[$this->customers[2]->id]->sequence);
        $this->assertSame($this->secondRep->id, $stops[$this->customers[0]->id]->representative_id);
        $this->assertSame($data['scheduled_for'], $stops[$this->customers[0]->id]->scheduled_for->toDateString());
        $event = AuditLog::where('action', 'trip.replanned')->sole();
        $this->assertSame($this->vehicle->id, $event->metadata['before']['vehicle_id']);
        $this->assertSame($vehicle->id, $event->metadata['after']['vehicle_id']);
        $this->assertSame($data['reason'], $event->metadata['reason']);
        $this->assertSame(0, StockBalance::count());
    }

    public function test_stale_revision_and_missing_reason_are_rejected_without_changing_plan(): void
    {
        $trip = $this->plan();
        $data = $this->revision($trip);
        $this->action('trip.update', $data)->assertOk();
        $this->action('trip.update', $data)->assertStatus(409);
        $this->action('trip.cancel', ['id' => $trip->id, 'plan_version' => 1, 'reason' => 'Stale cancellation'])->assertStatus(409);
        $this->action('trip.update', array_replace($data, ['reason' => '', 'plan_version' => 2]))->assertUnprocessable();
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'planned', 'plan_version' => 2]);
    }

    public function test_cancellation_preserves_records_and_excludes_stops_from_execution_metrics_and_exports(): void
    {
        $trip = $this->plan();
        $data = ['id' => $trip->id, 'plan_version' => 1, 'reason' => 'Synthetic cancelled day'];
        $key = (string) Str::uuid();
        $this->action('trip.cancel', $data, $key)->assertOk();
        $this->action('trip.cancel', $data, $key)->assertOk();
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'cancelled', 'cancelled_by' => $this->manager->id, 'cancellation_reason' => $data['reason']]);
        $this->assertDatabaseCount('visits', 2);
        $this->assertDatabaseHas('visits', ['trip_id' => $trip->id, 'status' => 'cancelled']);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'visits')->assertJsonPath('metrics.today_visits', 0)->assertJsonPath('trips.0.stops_count', 0);
        $today = today('Asia/Riyadh')->toDateString();
        $this->getJson('/ops/analytics?from='.$today.'&to='.$today)->assertOk()->assertJsonPath('summary.visits', 0);
        $csv = $this->get('/reports/export?type=visits&from='.$today.'&to='.$today)->assertOk()->streamedContent();
        $this->assertStringNotContainsString($this->customers[0]->name, $csv);
        $this->getJson('/ops/trips/'.$trip->id)->assertOk()->assertJsonCount(2, 'visits')->assertJsonPath('events.0.action', 'trip.cancelled');
        $this->action('trip.start', ['id' => $trip->id])->assertUnprocessable();
    }

    public function test_active_closed_and_started_plans_cannot_be_rewritten_or_cancelled(): void
    {
        $trip = $this->plan();
        foreach (['active', 'closed'] as $status) {
            DB::table('trips')->where('id', $trip->id)->update(['status' => $status]);
            $this->action('trip.update', $this->revision($trip))->assertUnprocessable();
            $this->action('trip.cancel', $this->revision($trip))->assertUnprocessable();
        }
        DB::table('trips')->where('id', $trip->id)->update(['status' => 'planned', 'started_at' => now()]);
        $this->action('trip.cancel', $this->revision($trip))->assertUnprocessable();
    }

    public function test_linked_orders_and_visit_evidence_block_edits_even_if_trip_status_is_planned(): void
    {
        $trip = $this->plan();
        $order = Order::create(['code' => 'SYN-ORDER', 'customer_id' => $this->customers[0]->id, 'representative_id' => $this->rep->id, 'trip_id' => $trip->id, 'warehouse_id' => $trip->warehouse_id, 'payment_terms' => 'credit']);
        $this->action('trip.cancel', $this->revision($trip))->assertUnprocessable();
        $this->action('trip.update', $this->revision($trip))->assertUnprocessable();
        $this->getJson('/ops/trips/'.$trip->id)->assertOk()->assertJsonCount(1, 'orders');
        $order->delete();
        DB::table('visits')->where('trip_id', $trip->id)->update(['location_verified' => true]);
        $this->action('trip.cancel', $this->revision($trip))->assertUnprocessable();
    }

    public function test_selected_coverage_and_inactive_customer_validation_are_atomic(): void
    {
        $trip = $this->plan();
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->rep->id)->update(['area_access' => 'selected']);
        $this->action('trip.update', $this->revision($trip))->assertUnprocessable();
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'plan_version' => 1]);
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->rep->id)->update(['area_access' => 'all']);
        DB::table('customers')->where('id', $this->customers[0]->id)->update(['is_active' => false]);
        $this->action('trip.update', $this->revision($trip))->assertUnprocessable();
        $this->assertDatabaseCount('visits', 2);
    }

    public function test_representative_cannot_change_plan_or_view_another_representatives_file(): void
    {
        $trip = $this->plan();
        $this->actingAs($this->secondRep);
        $this->getJson('/ops/trips/'.$trip->id)->assertForbidden();
        $this->action('trip.update', $this->revision($trip))->assertForbidden();
        $this->action('trip.cancel', $this->revision($trip))->assertForbidden();
        $this->actingAs($this->rep);
        $this->getJson('/ops/trips/'.$trip->id)->assertOk()->assertJsonPath('can_plan', false)->assertJsonPath('events.0.before', null);
    }

    public function test_viewer_has_operational_file_without_financial_details_and_accountant_cannot_open_it(): void
    {
        $trip = $this->plan();
        foreach (['viewer' => 200, 'accountant' => 403] as $role => $code) {
            $user = User::factory()->create();
            $this->company->users()->attach($user, ['role' => $role]);
            $this->actingAs($user);
            $response = $this->getJson('/ops/trips/'.$trip->id)->assertStatus($code);
            if ($code === 200) {
                $response->assertJsonPath('financial', null)->assertJsonCount(0, 'orders');
            }
        }
    }

    public function test_foreign_company_trip_is_not_found_and_foreign_customer_is_rejected(): void
    {
        $trip = $this->plan();
        $other = Company::create(['name' => 'Other', 'slug' => 'other']);
        $other->users()->attach($this->manager, ['role' => 'manager']);
        $this->withSession(['company_id' => $other->id]);
        $this->getJson('/ops/trips/'.$trip->id)->assertNotFound();
        $this->action('trip.cancel', $this->revision($trip))->assertNotFound();
        app(CompanyContext::class)->set($other, CompanyRole::Manager);
        $area = Area::create(['name' => 'Foreign', 'city' => 'Synthetic']);
        $customer = Customer::create(['name' => 'Foreign', 'code' => 'F', 'area_id' => $area->id, 'address' => 'Synthetic', 'latitude' => 24, 'longitude' => 46]);
        $this->withSession(['company_id' => $this->company->id]);
        $this->action('trip.update', array_replace($this->revision($trip), ['customer_ids' => [$customer->id]]))->assertUnprocessable();
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'plan_version' => 1]);
    }

    public function test_removed_stop_can_be_restored_with_same_id_and_is_not_started_as_a_cancelled_stop(): void
    {
        $trip = $this->plan();
        $originalId = $trip->visits()->where('customer_id', $this->customers[1]->id)->value('id');
        $this->action('trip.update', array_replace($this->revision($trip), ['customer_ids' => [$this->customers[0]->id]]))->assertOk();
        $this->action('trip.start', ['id' => $trip->id])->assertOk();
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(1, 'visits');
        DB::table('trips')->where('id', $trip->id)->update(['status' => 'planned', 'started_at' => null, 'active_representative_id' => null, 'active_vehicle_id' => null]);
        $this->action('trip.update', array_replace($this->revision($trip), ['plan_version' => 2]))->assertOk();
        $this->assertDatabaseHas('visits', ['id' => $originalId, 'status' => 'planned', 'sequence' => 1]);
        $this->assertDatabaseCount('visits', 2);
    }

    public function test_saved_tracking_points_block_cancellation_and_empty_or_unordered_stop_payloads_are_rejected(): void
    {
        $trip = $this->plan();
        foreach ([[], [4 => $this->customers[0]->id], [$this->customers[0]->id, $this->customers[0]->id]] as $ids) {
            $this->action('trip.update', array_replace($this->revision($trip), ['customer_ids' => $ids]))->assertUnprocessable();
        }
        DB::table('location_points')->insert(['company_id' => $this->company->id, 'trip_id' => $trip->id, 'user_id' => $this->rep->id, 'event_id' => (string) Str::uuid(), 'latitude' => 24.78, 'longitude' => 46.64, 'accuracy_m' => 10, 'captured_at' => now(), 'received_at' => now(), 'source' => 'demo']);
        $this->action('trip.cancel', $this->revision($trip))->assertUnprocessable();
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'plan_version' => 1, 'status' => 'planned']);
    }

    public function test_supervisor_can_replan_and_unavailable_vehicle_cannot_be_assigned(): void
    {
        $trip = $this->plan();
        $supervisor = User::factory()->create();
        $this->company->users()->attach($supervisor, ['role' => 'supervisor']);
        $this->actingAs($supervisor);
        $this->action('trip.update', $this->revision($trip))->assertOk();
        DB::table('vehicles')->where('id', $this->vehicle->id)->update(['status' => 'maintenance']);
        $this->action('trip.update', array_replace($this->revision($trip), ['plan_version' => 2]))->assertUnprocessable();
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'plan_version' => 2]);
    }
}
