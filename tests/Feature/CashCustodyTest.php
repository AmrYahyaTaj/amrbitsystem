<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Area;
use App\Models\Collection;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashCustodyTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $rep;

    private User $accountant;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Synthetic', 'slug' => 'cash']);
        $this->manager = User::factory()->create();
        $this->rep = User::factory()->create();
        $this->accountant = User::factory()->create();
        $this->company->users()->attach([$this->manager->id => ['role' => 'manager', 'is_active' => true], $this->rep->id => ['role' => 'representative', 'is_active' => true], $this->accountant->id => ['role' => 'accountant', 'is_active' => true]]);
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $area = Area::create(['name' => 'Demo', 'city' => 'Riyadh']);
        $customer = Customer::create(['name' => 'Synthetic', 'code' => 'C1', 'area_id' => $area->id, 'address' => 'Demo', 'latitude' => 24.78, 'longitude' => 46.64]);
        $warehouse = Warehouse::create(['name' => 'Main']);
        $this->order = Order::create(['code' => 'SO1', 'customer_id' => $customer->id, 'representative_id' => $this->rep->id, 'warehouse_id' => $warehouse->id]);
        $this->order->status = 'fulfilled';
        $this->order->total_minor = 3000;
        $this->order->paid_minor = 3000;
        $this->order->save();
        Collection::create(['order_id' => $this->order->id, 'actor_id' => $this->rep->id, 'receipt' => 'RC1', 'amount_minor' => 1000, 'method' => 'cash', 'collected_at' => now()]);
        Collection::create(['order_id' => $this->order->id, 'actor_id' => $this->rep->id, 'receipt' => 'RC2', 'amount_minor' => 2000, 'method' => 'bank', 'collected_at' => now()]);
        $this->actingAs($this->rep)->withSession(['company_id' => $this->company->id]);
    }

    private function act(string $action, array $payload, ?string $key = null)
    {
        return $this->postJson('/ops/action/'.$action, ['request_id' => $key ?? (string) Str::uuid()] + $payload);
    }

    private function requestCash(int $amount = 500): int
    {
        return $this->act('cash.request', ['collector_id' => $this->rep->id, 'amount_minor' => $amount, 'reference' => 'DEMO-CH'])->assertOk()->json('id');
    }

    public function test_pending_handover_reserves_cash_without_reducing_custody_or_customer_payment(): void
    {
        $this->requestCash();
        $this->getJson('/ops/data')->assertJsonPath('cash_balances.0.received_minor', 1000)->assertJsonPath('cash_balances.0.balance_minor', 1000)->assertJsonPath('cash_balances.0.pending_minor', 500)->assertJsonPath('cash_balances.0.available_minor', 500);
        $this->assertDatabaseHas('orders', ['id' => $this->order->id, 'paid_minor' => 3000]);
        $this->act('cash.request', ['collector_id' => $this->rep->id, 'amount_minor' => 501, 'reference' => 'DEMO-CH2'])->assertUnprocessable();
    }

    public function test_acceptance_and_replay_reduce_custody_exactly_once(): void
    {
        $id = $this->requestCash();
        $this->actingAs($this->manager);
        $key = (string) Str::uuid();
        $p = ['id' => $id, 'note' => 'Received test cash'];
        $this->act('cash.accept', $p, $key)->assertOk();
        $this->act('cash.accept', $p, $key)->assertOk();
        $this->act('cash.accept', $p)->assertUnprocessable();
        $this->assertDatabaseHas('cash_handovers', ['id' => $id, 'status' => 'accepted', 'reviewed_by' => $this->manager->id]);
        $this->actingAs($this->rep);
        $this->getJson('/ops/data')->assertJsonPath('cash_balances.0.balance_minor', 500)->assertJsonPath('cash_balances.0.available_minor', 500);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'cash.handover_accepted')->count());
    }

    public function test_reject_and_cancel_release_reservation_but_keep_history(): void
    {
        $id = $this->requestCash();
        $this->actingAs($this->accountant);
        $this->act('cash.reject', ['id' => $id, 'note' => 'Wrong test reference'])->assertOk();
        $this->actingAs($this->rep);
        $id2 = $this->requestCash(1000);
        $this->act('cash.cancel', ['id' => $id2, 'note' => 'Replace request later'])->assertOk();
        $this->getJson('/ops/data')->assertJsonPath('cash_balances.0.available_minor', 1000);
        $this->assertDatabaseCount('cash_handovers', 2);
    }

    public function test_initiator_cannot_approve_own_handover_even_as_manager(): void
    {
        $this->actingAs($this->manager);
        $id = $this->requestCash();
        $this->act('cash.accept', ['id' => $id, 'note' => 'Self approval attempt'])->assertForbidden();
        $this->actingAs($this->accountant);
        $this->act('cash.accept', ['id' => $id, 'note' => 'Another recipient received'])->assertOk();
    }

    public function test_representative_cannot_request_cash_of_others_or_approve(): void
    {
        $this->act('cash.request', ['collector_id' => $this->manager->id, 'amount_minor' => 1, 'reference' => 'DEMO'])->assertForbidden();
        $id = $this->requestCash();
        $this->act('cash.accept', ['id' => $id, 'note' => 'Not allowed'])->assertForbidden();
    }

    public function test_company_switch_hides_handover_and_prevents_review(): void
    {
        $id = $this->requestCash();
        $other = Company::create(['name' => 'Other', 'slug' => 'other']);
        $other->users()->attach($this->manager->id, ['role' => 'manager', 'is_active' => true]);
        $this->actingAs($this->manager)->withSession(['company_id' => $other->id]);
        $this->getJson('/ops/data')->assertJsonCount(0, 'cash_handovers');
        $this->act('cash.accept', ['id' => $id, 'note' => 'Different tenant'])->assertNotFound();
    }

    public function test_order_document_is_protected_by_company_and_representative(): void
    {
        $this->get('/documents/orders/'.$this->order->id)->assertOk()->assertSee('SO1')->assertSee('RC1');
        DB::table('orders')->where('id', $this->order->id)->update(['representative_id' => $this->manager->id]);
        $this->get('/documents/orders/'.$this->order->id)->assertForbidden();
    }
}
