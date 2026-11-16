<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['username' => 'device', 'email' => null, 'password' => 'LocalDevicePass2026!']);
        $company = Company::create(['name' => 'Device Demo', 'slug' => 'device']);
        $company->users()->attach($user->id, ['role' => 'representative', 'is_active' => true]);

        return [$user, $company];
    }

    public function test_device_login_returns_expiring_token_and_requires_explicit_membership(): void
    {
        [$user,$company] = $this->fixture();
        $r = $this->postJson('/api/v1/token', ['username' => $user->username, 'password' => 'LocalDevicePass2026!', 'device_name' => 'Test device'])->assertOk()->assertJsonPath('companies.0.id', $company->id);
        $token = $r->json('token');
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $token]);
        $this->withToken($token)->getJson('/api/v1/workspace')->assertUnprocessable();
        Auth::forgetGuards();
        $this->withToken($token)->withHeader('X-Company-ID', (string) $company->id)->getJson('/api/v1/workspace')->assertOk()->assertJsonPath('role', 'representative');
        Auth::forgetGuards();
        $this->withHeader('X-Company-ID', '99999')->getJson('/api/v1/workspace')->assertForbidden();
    }

    public function test_revoked_membership_and_expired_tokens_are_rejected(): void
    {
        [$user,$company] = $this->fixture();
        $token = $user->createToken('Test', ['operations:read', 'operations:write'], now()->addHour());
        DB::table('company_user')->update(['is_active' => false]);
        $this->withToken($token->plainTextToken)->withHeader('X-Company-ID', (string) $company->id)->getJson('/api/v1/workspace')->assertForbidden();
        Auth::forgetGuards();
        DB::table('company_user')->update(['is_active' => true]);
        DB::table('personal_access_tokens')->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/workspace')->assertUnauthorized();
    }

    public function test_device_logout_revokes_only_the_current_token(): void
    {
        [$user,$company] = $this->fixture();
        $a = $user->createToken('A', ['operations:read', 'operations:write'], now()->addHour());
        $b = $user->createToken('B', ['operations:read', 'operations:write'], now()->addHour());
        $this->withToken($a->plainTextToken)->deleteJson('/api/v1/token')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Auth::forgetGuards();
        $this->withToken($a->plainTextToken)->getJson('/api/v1/workspace')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($b->plainTextToken)->withHeader('X-Company-ID', (string) $company->id)->getJson('/api/v1/workspace')->assertOk();
    }
}
