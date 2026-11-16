<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Area;
use App\Models\Collection;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Product;
use App\Models\RepresentativeVehicleAssignment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\Warehouse;
use App\Services\StockLedger;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TwoPartyHandoverTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $driverUser;

    private User $rep;

    private User $other;

    private User $keeper;

    private User $accountant;

    private Driver $driver;

    private Vehicle $vehicle;

    private Warehouse $source;

    private Warehouse $cargo;

    private Product $product;

    private Product $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Synthetic handovers', 'slug' => 'handover-tests']);
        foreach (['manager' => 'manager', 'driverUser' => 'driver', 'rep' => 'representative', 'other' => 'representative', 'keeper' => 'warehouse_keeper', 'accountant' => 'accountant'] as $property => $role) {
            $this->{$property} = User::factory()->create();
            $this->company->users()->attach($this->{$property}->id, ['role' => $role, 'is_active' => true, 'area_access' => 'all']);
        }
        $this->as($this->manager);
        $this->vehicle = Vehicle::create(['name' => 'Synthetic Dina', 'plate' => 'SYN-HO', 'vehicle_type' => 'light_truck', 'capacity_units' => 100, 'notes' => 'Scratch on left door']);
        $this->driver = Driver::create(['name' => 'Synthetic Driver', 'employee_code' => 'HD-1', 'license_number' => 'SYN-LICENSE', 'license_expires_on' => now()->addYear()->toDateString(), 'user_id' => $this->driverUser->id]);
        $this->source = Warehouse::create(['name' => 'Synthetic warehouse', 'type' => 'warehouse', 'custodian_id' => $this->keeper->id]);
        $this->cargo = Warehouse::create(['name' => 'Synthetic cargo', 'type' => 'vehicle', 'vehicle_id' => $this->vehicle->id]);
        $this->product = Product::create(['name' => 'Synthetic tissue', 'sku' => 'HO-P1', 'unit' => 'box', 'price_minor' => 100]);
        $this->second = Product::create(['name' => 'Synthetic paper', 'sku' => 'HO-P2', 'unit' => 'box', 'price_minor' => 200]);
        DB::transaction(function () {
            app(StockLedger::class)->move($this->source, $this->product, 100, 'receipt', 'SYN-OPEN');
            app(StockLedger::class)->move($this->source, $this->second, 50, 'receipt', 'SYN-OPEN');
        });
    }

    private function as(User $user): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->actingAs($user)->withSession(['company_id' => $this->company->id]);
    }

    private function create(array $data, ?string $key = null)
    {
        return $this->postJson('/ops/handovers', $data + ['request_id' => $key ?? (string) Str::uuid()]);
    }

    private function vehicleRequest(?string $key = null): int
    {
        return $this->create(['kind' => 'vehicle_delivery', 'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id, 'note' => 'Keys, tools, scratch on left door'], $key)->assertOk()->json('id');
    }

    private function stockData(int $quantity = 20): array
    {
        return ['kind' => 'stock_transfer', 'from_id' => $this->source->id, 'to_id' => $this->cargo->id, 'recipient_id' => $this->rep->id, 'note' => 'Sealed boxes, inspect quantities', 'items' => [['product_id' => $this->product->id, 'quantity' => $quantity], ['product_id' => $this->second->id, 'quantity' => 5]]];
    }

    private function respond(int $id, string $decision = 'accepted', ?string $key = null)
    {
        return $this->postJson('/ops/handovers/'.$id.'/decision', ['request_id' => $key ?? (string) Str::uuid(), 'decision' => $decision, 'note' => $decision === 'accepted' ? 'Checked and received as described' : 'Quantity or condition mismatch', 'acknowledge' => true]);
    }

    public function test_vehicle_is_only_assigned_after_the_linked_driver_acknowledges_and_replays_once(): void
    {
        $key = (string) Str::uuid();
        $id = $this->vehicleRequest($key);
        $this->vehicleRequest($key);
        $this->assertDatabaseCount('handover_requests', 1);
        $this->assertDatabaseCount('vehicle_assignments', 0);
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $this->driverUser->id, 'handover_id' => $id, 'event' => 'requested', 'read_at' => null]);
        $this->respond($id)->assertForbidden();
        $this->as($this->driverUser);
        $this->getJson('/ops/handovers')->assertOk()->assertJsonPath('summary.awaiting_me', 1);
        $decisionKey = (string) Str::uuid();
        $this->respond($id, 'accepted', $decisionKey)->assertOk();
        $this->respond($id, 'accepted', $decisionKey)->assertOk();
        $this->respond($id)->assertConflict();
        $this->assertDatabaseCount('vehicle_assignments', 1);
        $this->assertDatabaseHas('handover_requests', ['id' => $id, 'status' => 'accepted', 'decided_by' => $this->driverUser->id]);
        $this->assertDatabaseCount('handover_locks', 0);
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $this->manager->id, 'handover_id' => $id, 'event' => 'accepted']);
    }

    public function test_only_the_recipient_can_decide_and_an_explicit_acknowledgment_is_required(): void
    {
        $id = $this->vehicleRequest();
        $this->as($this->other);
        $this->getJson('/ops/handovers/'.$id)->assertNotFound();
        $this->respond($id)->assertNotFound();
        $this->as($this->driverUser);
        $this->postJson('/ops/handovers/'.$id.'/decision', ['request_id' => Str::uuid(), 'decision' => 'accepted', 'note' => 'Checked but no checkbox'])->assertUnprocessable();
        $this->assertDatabaseCount('vehicle_assignments', 0);
    }

    public function test_pending_vehicle_and_driver_are_reserved_and_rejection_releases_them(): void
    {
        $id = $this->vehicleRequest();
        $this->create(['kind' => 'vehicle_delivery', 'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id, 'note' => 'Duplicate pending delivery'])->assertUnprocessable();
        $this->as($this->driverUser);
        $this->respond($id, 'rejected')->assertOk();
        $this->assertDatabaseCount('handover_locks', 0);
        $this->assertDatabaseCount('vehicle_assignments', 0);
        $this->as($this->manager);
        $this->vehicleRequest();
        $this->assertDatabaseCount('handover_requests', 2);
        $this->get('/documents/handovers/'.$id)->assertUnprocessable();
    }

    public function test_legacy_vehicle_action_also_creates_a_request_and_return_requires_driver_confirmation(): void
    {
        $id = $this->postJson('/ops/action/vehicle.assign', ['request_id' => Str::uuid(), 'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id, 'note' => 'Delivery from fleet screen'])->assertOk()->json('handover_id');
        $this->assertDatabaseCount('vehicle_assignments', 0);
        $this->as($this->driverUser);
        $this->respond($id)->assertOk();
        $assignment = DB::table('vehicle_assignments')->value('id');
        $this->as($this->manager);
        $returned = $this->postJson('/ops/action/vehicle.release', ['request_id' => Str::uuid(), 'id' => $assignment, 'note' => 'Return with original scratch'])->assertOk()->json('handover_id');
        $this->assertDatabaseHas('vehicle_assignments', ['id' => $assignment, 'returned_at' => null]);
        $this->as($this->driverUser);
        $this->respond($returned)->assertOk();
        $this->assertNotNull(DB::table('vehicle_assignments')->where('id', $assignment)->value('returned_at'));
        $this->get('/documents/handovers/'.$returned)->assertOk()->assertSee('إرجاع مركبة')->assertSee('Return with original scratch');
    }

    public function test_driver_account_link_is_required_and_cannot_change_while_pending(): void
    {
        DB::table('drivers')->where('id', $this->driver->id)->update(['user_id' => null]);
        $this->create(['kind' => 'vehicle_delivery', 'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id, 'note' => 'No account'])->assertUnprocessable();
        DB::table('drivers')->where('id', $this->driver->id)->update(['user_id' => $this->driverUser->id]);
        $this->vehicleRequest();
        $this->postJson('/ops/action/driver.update', ['request_id' => Str::uuid(), 'id' => $this->driver->id, 'name' => $this->driver->name, 'employee_code' => 'HD-1', 'status' => 'active', 'user_id' => $this->rep->id, 'reason' => 'Change account during review'])->assertUnprocessable();
        $this->assertDatabaseHas('drivers', ['id' => $this->driver->id, 'user_id' => $this->driverUser->id]);
    }

    public function test_stock_is_reserved_without_moving_and_acceptance_moves_all_lines_once(): void
    {
        $key = (string) Str::uuid();
        $this->as($this->keeper);
        $id = $this->create($this->stockData(), $key)->assertOk()->json('id');
        $this->create($this->stockData(), $key)->assertOk();
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->source->id, 'product_id' => $this->product->id, 'quantity' => 100]);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->getJson('/ops/data')->assertOk()->assertJsonPath('balances.0.reserved_quantity', 20)->assertJsonPath('balances.0.available_quantity', 80);
        $this->assertDatabaseCount('handover_stock_reservations', 2);
        $this->as($this->rep);
        $decisionKey = (string) Str::uuid();
        $this->respond($id, 'accepted', $decisionKey)->assertOk();
        $this->respond($id, 'accepted', $decisionKey)->assertOk();
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->source->id, 'product_id' => $this->product->id, 'quantity' => 80]);
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->cargo->id, 'product_id' => $this->product->id, 'quantity' => 20]);
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->cargo->id, 'product_id' => $this->second->id, 'quantity' => 5]);
        $this->assertDatabaseCount('stock_movements', 6);
        $this->assertSame(0, DB::table('handover_stock_reservations')->whereNull('released_at')->count());
    }

    public function test_pending_stock_blocks_overspending_and_rejection_or_cancellation_releases_it(): void
    {
        $data = $this->stockData(70);
        $id = $this->create($data)->assertOk()->json('id');
        $next = $this->stockData(40);
        $next['to_id'] = $this->source->id;
        $next['from_id'] = $this->cargo->id;
        $this->create($next)->assertForbidden();
        $this->as($this->manager);
        try {
            DB::transaction(fn () => app(StockLedger::class)->move($this->source, $this->product, -40, 'sale', 'MUST-NOT-SPEND'));
            $this->fail('Reserved stock was spent');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }
        $this->as($this->rep);
        $this->respond($id, 'rejected')->assertOk();
        $this->as($this->manager);
        $id2 = $this->create($this->stockData(90))->assertOk()->json('id');
        $this->postJson('/ops/handovers/'.$id2.'/cancel', ['request_id' => Str::uuid(), 'note' => 'Cancel planned load'])->assertOk();
        $this->assertSame(0, DB::table('handover_stock_reservations')->whereNull('released_at')->count());
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_capacity_includes_other_pending_loads_and_bad_line_rolls_back_everything(): void
    {
        $this->create($this->stockData(70))->assertOk();
        $this->create($this->stockData(30))->assertUnprocessable();
        $bad = $this->stockData(1);
        $bad['items'][1]['quantity'] = 999;
        $this->create($bad)->assertUnprocessable();
        $this->assertDatabaseCount('handover_requests', 1);
        $this->assertDatabaseCount('handover_stock_reservations', 2);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_stock_cannot_be_rerouted_to_a_different_representative_at_approval(): void
    {
        $id = $this->create($this->stockData())->assertOk()->json('id');
        $this->as($this->manager);
        RepresentativeVehicleAssignment::create(['representative_id' => $this->other->id, 'vehicle_id' => $this->vehicle->id, 'active_representative_id' => $this->other->id, 'active_vehicle_id' => $this->vehicle->id, 'assigned_by' => $this->manager->id, 'assigned_at' => now(), 'assignment_note' => 'Reassigned before delivery']);
        $this->as($this->rep);
        $this->respond($id)->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('handover_requests', ['id' => $id, 'status' => 'pending']);
    }

    public function test_representative_can_return_own_load_but_warehouse_staff_cannot_take_it_unilaterally(): void
    {
        $id = $this->create($this->stockData())->assertOk()->json('id');
        $this->as($this->rep);
        $this->respond($id)->assertOk();
        $return = ['kind' => 'stock_transfer', 'from_id' => $this->cargo->id, 'to_id' => $this->source->id, 'recipient_id' => $this->keeper->id, 'note' => 'Returning unopened goods', 'items' => [['product_id' => $this->product->id, 'quantity' => 3]]];
        $this->as($this->manager);
        $this->create($return)->assertForbidden();
        $this->as($this->rep);
        $returnId = $this->create($return)->assertOk()->json('id');
        $this->as($this->keeper);
        $this->respond($returnId)->assertOk();
        $this->assertDatabaseHas('stock_balances', ['warehouse_id' => $this->cargo->id, 'product_id' => $this->product->id, 'quantity' => 17]);
        $this->get('/documents/handovers/'.$returnId)->assertOk()->assertSee('Returning unopened goods');
    }

    public function test_other_party_messages_are_durable_and_notification_read_state_is_per_user(): void
    {
        $id = $this->vehicleRequest();
        $this->as($this->driverUser);
        $n = $this->getJson('/ops/notifications')->assertOk()->assertJsonPath('unread', 1)->json('notifications.data.0.id');
        $messageId = (string) Str::uuid();
        $body = ['request_id' => $messageId, 'body' => 'Please confirm spare tire and existing scratch'];
        $this->postJson('/ops/handovers/'.$id.'/messages', $body)->assertOk();
        $this->postJson('/ops/handovers/'.$id.'/messages', $body)->assertOk();
        $this->assertDatabaseCount('handover_messages', 1);
        $this->postJson('/ops/notifications/read', ['id' => $n])->assertOk();
        $this->getJson('/ops/notifications')->assertJsonPath('unread', 0);
        $this->as($this->manager);
        $this->getJson('/ops/notifications')->assertJsonPath('unread', 1);
        $this->getJson('/ops/handovers/'.$id)->assertOk()->assertJsonPath('messages.data.0.body', $body['body']);
        $this->as($this->other);
        $this->postJson('/ops/handovers/'.$id.'/messages', ['request_id' => Str::uuid(), 'body' => 'Not a participant'])->assertNotFound();
    }

    public function test_receipt_preserves_agreed_snapshot_escapes_text_and_detects_tampering(): void
    {
        $id = $this->vehicleRequest();
        $this->as($this->driverUser);
        $this->postJson('/ops/handovers/'.$id.'/decision', ['request_id' => Str::uuid(), 'decision' => 'accepted', 'note' => '<script>alert(1)</script> existing scratch accepted', 'acknowledge' => true])->assertOk();
        DB::table('vehicles')->where('id', $this->vehicle->id)->update(['name' => 'Later renamed vehicle', 'notes' => 'Later state']);
        DB::table('users')->where('id', $this->driverUser->id)->update(['name' => 'Later account name']);
        $this->get('/documents/handovers/'.$id)->assertOk()->assertSee('Synthetic Dina')->assertSee('Scratch on left door')->assertDontSee('Later renamed vehicle')->assertDontSee('Later account name')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $row = DB::table('handover_requests')->where('id', $id)->first();
        $receipt = json_decode($row->receipt, true);
        $receipt['response_note'] = 'Altered agreement';
        DB::table('handover_requests')->where('id', $id)->update(['receipt' => json_encode($receipt)]);
        $this->get('/documents/handovers/'.$id)->assertConflict();
    }

    public function test_signed_files_are_private_append_only_and_idempotent(): void
    {
        Storage::fake('local');
        $id = $this->vehicleRequest();
        $this->as($this->driverUser);
        $this->respond($id)->assertOk();
        $key = (string) Str::uuid();
        $file = UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.4\nSynthetic signed test document\n%%EOF");
        $url = '/ops/handovers/'.$id.'/archive';
        $fileId = $this->post($url, ['request_id' => $key, 'document' => $file], ['Accept' => 'application/json'])->assertOk()->json('id');
        $this->post($url, ['request_id' => $key, 'document' => $file], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('id', $fileId);
        $this->assertDatabaseCount('handover_files', 1);
        $this->post($url, ['request_id' => (string) Str::uuid(), 'document' => UploadedFile::fake()->createWithContent('signed-v2.pdf', "%PDF-1.4\nSecond version\n%%EOF")], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseCount('handover_files', 2);
        $this->assertDatabaseCount('archive_documents', 2);
        $this->assertDatabaseHas('archive_documents', ['source_handover_file_id' => $fileId, 'status' => 'pending_review']);
        $this->get('/archive')->assertForbidden();
        $this->get('/ops/handovers/files/'.$fileId)->assertOk()->assertDownload('signed.pdf');
        $this->getJson('/ops/handovers/'.$id)->assertOk()->assertJsonMissingPath('files.0.path');
        $this->as($this->other);
        $this->get('/ops/handovers/files/'.$fileId)->assertNotFound();
    }

    public function test_unapproved_or_executable_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $id = $this->vehicleRequest();
        $this->as($this->driverUser);
        $this->post('/ops/handovers/'.$id.'/archive', ['request_id' => (string) Str::uuid(), 'document' => UploadedFile::fake()->createWithContent('signed.pdf', '%PDF-1.4')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->respond($id)->assertOk();
        $this->post('/ops/handovers/'.$id.'/archive', ['request_id' => (string) Str::uuid(), 'document' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('handover_files', 0);
    }

    public function test_new_roles_are_confined_to_their_portal_and_inventory_without_customer_or_financial_leaks(): void
    {
        $area = Area::create(['name' => 'Secret area', 'city' => 'Test']);
        $customer = Customer::create(['name' => 'Secret customer', 'code' => 'SYN-C', 'area_id' => $area->id, 'address' => 'Synthetic', 'latitude' => 24, 'longitude' => 46, 'geofence_radius_m' => 150]);
        $trip = Trip::create(['code' => 'SYN-HO-TRIP', 'representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'warehouse_id' => $this->cargo->id, 'scheduled_for' => today()->toDateString()]);
        foreach ([$this->driverUser, $this->keeper] as $user) {
            $this->as($user);
            $this->get('/dashboard')->assertRedirect($user->id === $this->keeper->id ? '/warehouses' : '/handovers');
            $this->get('/handovers')->assertOk()->assertSee('custody.js')->assertDontSee('src="/js/app.js', false);
            $this->getJson('/ops/customers/'.$customer->id)->assertForbidden();
            $this->getJson('/ops/trips/'.$trip->id)->assertForbidden();
            $this->getJson('/ops/analytics?from=2026-10-01&to=2026-10-20')->assertForbidden();
            $this->get('/reports/export?from=2026-10-01&to=2026-10-20')->assertForbidden();
            $this->get('/team')->assertForbidden();
        }
        $this->as($this->driverUser);
        $this->getJson('/ops/data')->assertForbidden();
        $this->as($this->keeper);
        $this->getJson('/ops/data')->assertOk()->assertJsonCount(0, 'customers')->assertJsonCount(0, 'orders')->assertJsonPath('metrics.sales_minor', null);
        $this->get('/warehouses')->assertOk();
        $this->get('/inventory')->assertForbidden();
    }

    public function test_company_switch_and_revoked_membership_prevent_reading_and_deciding(): void
    {
        $id = $this->vehicleRequest();
        $foreign = Company::create(['name' => 'Foreign company', 'slug' => 'foreign-handover']);
        $foreign->users()->attach($this->manager->id, ['role' => 'manager']);
        $this->withSession(['company_id' => $foreign->id]);
        $this->getJson('/ops/handovers')->assertOk()->assertJsonPath('requests.total', 0);
        $this->getJson('/ops/handovers/'.$id)->assertNotFound();
        $this->get('/documents/handovers/'.$id)->assertNotFound();
        $this->as($this->driverUser);
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->driverUser->id)->update(['is_active' => false]);
        $this->respond($id)->assertForbidden();
        $this->getJson('/ops/notifications')->assertForbidden();
        $this->assertDatabaseCount('vehicle_assignments', 0);
    }

    public function test_cash_uses_its_existing_ledger_and_notifies_another_receiver_then_issues_one_receipt(): void
    {
        $area = Area::create(['name' => 'Cash area', 'city' => 'Synthetic']);
        $customer = Customer::create(['name' => 'Cash customer', 'code' => 'SYN-CASH', 'area_id' => $area->id, 'address' => 'Synthetic', 'latitude' => 24, 'longitude' => 46, 'geofence_radius_m' => 150]);
        $order = Order::create(['code' => 'SYN-CASH', 'customer_id' => $customer->id, 'representative_id' => $this->rep->id, 'warehouse_id' => $this->source->id, 'payment_terms' => 'cash']);
        $order->status = 'fulfilled';
        $order->total_minor = 5000;
        $order->paid_minor = 5000;
        $order->save();
        Collection::create(['order_id' => $order->id, 'actor_id' => $this->rep->id, 'receipt' => 'SYN-RC', 'amount_minor' => 5000, 'method' => 'cash', 'collected_at' => now()]);
        $this->as($this->rep);
        $cash = $this->postJson('/ops/action/cash.request', ['request_id' => Str::uuid(), 'collector_id' => $this->rep->id, 'amount_minor' => 3000, 'reference' => 'SYN-DEPOSIT', 'note' => 'Cash counted'])->assertOk()->json('id');
        $id = DB::table('handover_requests')->where('cash_handover_id', $cash)->value('id');
        $this->assertDatabaseHas('workspace_notifications', ['handover_id' => $id, 'user_id' => $this->accountant->id]);
        $this->respond($id)->assertForbidden();
        $this->as($this->accountant);
        $this->respond($id)->assertOk();
        $this->assertDatabaseHas('cash_handovers', ['id' => $cash, 'status' => 'accepted', 'reviewed_by' => $this->accountant->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'paid_minor' => 5000]);
        $this->assertDatabaseCount('collections', 1);
        $this->get('/documents/handovers/'.$id)->assertOk()->assertSee('SYN-DEPOSIT');
    }

    public function test_equipment_requests_support_multiple_items_and_keep_rejected_history(): void
    {
        $data = ['kind' => 'asset', 'recipient_id' => $this->rep->id, 'note' => 'Phone and scanner with accessories', 'items' => [['name' => 'Phone', 'serial' => 'SYN-01', 'quantity' => 1, 'condition' => 'Working with charger'], ['name' => 'Scanner', 'serial' => 'SYN-02', 'quantity' => 1, 'condition' => 'Working, strap included']]];
        $id = $this->create($data)->assertOk()->json('id');
        $this->as($this->rep);
        $this->respond($id, 'rejected')->assertOk();
        $this->getJson('/ops/handovers/'.$id)->assertOk()->assertJsonPath('request.snapshot.details.items.1.serial', 'SYN-02');
        $this->assertDatabaseCount('stock_movements', 2);
        $this->get('/documents/handovers/'.$id)->assertUnprocessable();
    }

    public function test_approval_revalidates_license_and_pending_handover_prevents_starting_a_trip(): void
    {
        $id = $this->vehicleRequest();
        $this->as($this->manager);
        $area = Area::create(['name' => 'Route area', 'city' => 'Synthetic']);
        $customer = Customer::create(['name' => 'Route customer', 'code' => 'SYN-ROUTE', 'area_id' => $area->id, 'address' => 'Synthetic', 'latitude' => 24, 'longitude' => 46, 'geofence_radius_m' => 150]);
        $trip = Trip::create(['code' => 'SYN-PENDING', 'representative_id' => $this->rep->id, 'vehicle_id' => $this->vehicle->id, 'warehouse_id' => $this->cargo->id, 'scheduled_for' => now()->timezone('Asia/Riyadh')->toDateString()]);
        $this->postJson('/ops/action/trip.start', ['request_id' => Str::uuid(), 'id' => $trip->id])->assertUnprocessable();
        DB::table('drivers')->where('id', $this->driver->id)->update(['license_expires_on' => '2000-01-01']);
        $this->as($this->driverUser);
        $this->respond($id)->assertUnprocessable();
        $this->assertDatabaseCount('vehicle_assignments', 0);
        $this->assertDatabaseHas('handover_requests', ['id' => $id, 'status' => 'pending']);
    }

    public function test_device_token_can_read_own_inbox_and_acknowledge_but_read_only_token_cannot_write(): void
    {
        $id = $this->vehicleRequest();
        $token = $this->driverUser->createToken('Synthetic custody device', ['operations:read', 'operations:write'], now()->addHour())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Company-ID' => (string) $this->company->id];
        $this->getJson('/api/v1/handovers', $headers)->assertOk()->assertJsonPath('summary.awaiting_me', 1);
        $this->getJson('/api/v1/workspace', $headers)->assertForbidden();
        $this->postJson('/api/v1/handovers/'.$id.'/decision', ['request_id' => Str::uuid(), 'decision' => 'accepted', 'acknowledge' => true, 'note' => 'Checked through device token'], $headers)->assertOk();
        $this->assertDatabaseCount('vehicle_assignments', 1);
        $readOnly = $this->driverUser->createToken('Read only', ['operations:read'], now()->addHour())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/handovers/'.$id.'/messages', ['request_id' => Str::uuid(), 'body' => 'Must not be written'], ['Authorization' => 'Bearer '.$readOnly, 'X-Company-ID' => (string) $this->company->id])->assertForbidden();
    }

    public function test_conflicting_idempotency_payload_and_revoked_role_cannot_replay_an_acceptance(): void
    {
        $id = $this->vehicleRequest();
        $this->as($this->driverUser);
        $key = (string) Str::uuid();
        $this->respond($id, 'accepted', $key)->assertOk();
        $this->postJson('/ops/handovers/'.$id.'/decision', ['request_id' => $key, 'decision' => 'accepted', 'note' => 'Different agreement text', 'acknowledge' => true])->assertConflict();
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->driverUser->id)->update(['role' => 'viewer']);
        $this->respond($id, 'accepted', $key)->assertForbidden();
        $this->assertDatabaseCount('vehicle_assignments', 1);
    }

    public function test_manager_can_create_new_operational_roles_without_granting_sales_or_collection(): void
    {
        foreach (['driver', 'warehouse_keeper'] as $role) {
            $response = $this->postJson('/ops/action/member.create', ['request_id' => Str::uuid(), 'name' => 'Synthetic '.$role, 'username' => 'synthetic_'.$role, 'password' => 'Synthetic-Only-123!', 'password_confirmation' => 'Synthetic-Only-123!', 'role' => $role, 'is_active' => true, 'area_access' => 'selected', 'area_ids' => [], 'can_sell' => true, 'can_collect' => true])->assertOk();
            $this->assertDatabaseHas('company_user', ['company_id' => $this->company->id, 'user_id' => $response->json('id'), 'role' => $role, 'can_sell' => false, 'can_collect' => false]);
        }
    }

    public function test_legacy_driver_can_be_linked_once_without_forging_a_historical_acknowledgment(): void
    {
        DB::table('drivers')->where('id', $this->driver->id)->update(['user_id' => null]);
        $assignment = VehicleAssignment::create(['vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id, 'active_vehicle_id' => $this->vehicle->id, 'active_driver_id' => $this->driver->id, 'assigned_by' => $this->manager->id, 'assigned_at' => now(), 'handover_note' => 'Legacy handover before acknowledgments']);
        $data = ['request_id' => Str::uuid(), 'id' => $this->driver->id, 'name' => $this->driver->name, 'employee_code' => 'HD-1', 'status' => 'active', 'license_number' => 'SYN-LICENSE', 'license_expires_on' => now()->addYear()->toDateString(), 'user_id' => $this->driverUser->id, 'reason' => 'Explicitly link the existing driver account'];
        $this->postJson('/ops/action/driver.update', $data)->assertOk();
        $this->assertDatabaseCount('handover_requests', 0);
        $this->assertDatabaseHas('vehicle_assignments', ['id' => $assignment->id, 'returned_at' => null]);
        $data['request_id'] = Str::uuid();
        $data['user_id'] = $this->other->id;
        $this->postJson('/ops/action/driver.update', $data)->assertUnprocessable();
    }

    public function test_creator_role_and_sender_authority_are_checked_before_replay_and_acceptance(): void
    {
        $key = (string) Str::uuid();
        $id = $this->vehicleRequest($key);
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->manager->id)->update(['role' => 'driver']);
        $this->create(['kind' => 'vehicle_delivery', 'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id, 'note' => 'Keys, tools, scratch on left door'], $key)->assertForbidden();
        $this->as($this->driverUser);
        $this->respond($id)->assertUnprocessable();
        $this->assertDatabaseCount('vehicle_assignments', 0);
    }

    public function test_driver_vehicle_custody_does_not_replace_the_representative_who_received_the_load(): void
    {
        $vehicle = $this->vehicleRequest();
        $this->as($this->driverUser);
        $this->respond($vehicle)->assertOk();
        $this->as($this->manager);
        $load = $this->create($this->stockData())->assertOk()->json('id');
        $other = $this->stockData(1);
        $other['recipient_id'] = $this->other->id;
        $this->create($other)->assertUnprocessable();
        $this->as($this->rep);
        $this->respond($load)->assertOk();
        $return = ['kind' => 'stock_transfer', 'from_id' => $this->cargo->id, 'to_id' => $this->source->id, 'recipient_id' => $this->keeper->id, 'note' => 'Representative returns own acknowledged load', 'items' => [['product_id' => $this->product->id, 'quantity' => 2]]];
        $this->create($return)->assertOk();
        $this->as($this->driverUser);
        $this->create($return)->assertForbidden();
        $this->as($this->manager);
        $this->create($other)->assertUnprocessable();
    }
}
