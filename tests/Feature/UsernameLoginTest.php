<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\OperationsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_login_accepts_trimmed_case_and_does_not_require_email(): void
    {
        $user = User::factory()->create(['username' => 'amr', 'email' => null, 'password' => '123456']);
        $this->post('/login', ['username' => ' AMR ', 'password' => '123456'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_and_email_only_login_are_rejected(): void
    {
        User::factory()->create(['username' => 'sara', 'email' => null, 'password' => '123456']);
        $this->post('/login', ['username' => 'sara', 'password' => 'incorrect'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['email' => 'old@example.test', 'password' => '123456'])->assertSessionHasErrors('username');
    }

    public function test_login_template_requests_username_as_text(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="username"', false)->assertDontSee('name="email"', false);
    }

    public function test_demo_seed_uses_unique_named_logins_without_emails_and_does_not_reset_existing_passwords(): void
    {
        config(['fieldflow.demo' => true]);
        $this->seed(OperationsDemoSeeder::class);
        $this->assertDatabaseCount('users', 15);
        $this->assertSame(0, User::whereNotNull('email')->count());
        $this->assertSame(15, User::whereNotNull('username')->distinct()->count('username'));
        $user = User::where('username', 'amr')->sole();
        $user->password = 'Changed-By-Owner-123!';
        $user->save();
        $this->seed(OperationsDemoSeeder::class);
        $this->assertDatabaseCount('users', 15);
        $this->assertTrue(password_verify('Changed-By-Owner-123!', $user->fresh()->password));
    }

    public function test_short_demo_password_is_accepted_only_in_enabled_demo_mode(): void
    {
        $manager = User::factory()->create();
        $company = Company::create(['name' => 'Synthetic', 'slug' => 'synthetic']);
        $company->users()->attach($manager, ['role' => 'manager']);
        $this->actingAs($manager)->withSession(['company_id' => $company->id]);
        $data = ['request_id' => (string) Str::uuid(), 'name' => 'Synthetic', 'username' => 'newuser', 'password' => '123456', 'password_confirmation' => '123456', 'role' => 'representative', 'is_active' => true, 'area_access' => 'selected', 'area_ids' => [], 'can_sell' => true, 'can_collect' => true];
        config(['fieldflow.demo' => false]);
        $this->postJson('/ops/action/member.create', $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        config(['fieldflow.demo' => true]);
        $this->postJson('/ops/action/member.create', $data)->assertOk();
        $this->assertDatabaseHas('users', ['username' => 'newuser', 'email' => null]);
    }
}
