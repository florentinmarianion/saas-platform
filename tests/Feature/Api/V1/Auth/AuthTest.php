<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    // ── Login ────────────────────────────────────────────────────────

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = $this->createPlainUser('login@test.com');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'login@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'token',
                     'user' => ['id', 'name', 'email'],
                     'companies',
                 ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $this->createPlainUser('login@test.com');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'login@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'code' => 'INVALID_CREDENTIALS',
                 ]);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'code' => 'INVALID_CREDENTIALS',
                 ]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
                 ->assertJson(['code' => 'VALIDATION_ERROR'])
                 ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_auto_scopes_token_when_user_has_one_company(): void
    {
        $user    = $this->createPlainUser('onecompany@test.com');
        $company = $this->createCompany($user, 'Single Company');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'onecompany@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('company_id', $company->id);
    }

    // ── Me ───────────────────────────────────────────────────────────

    public function test_me_returns_authenticated_user(): void
    {
        $user  = $this->createPlainUser();
        $token = $this->tokenFor($user);

        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('user.email', 'user@test.com');
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    // ── Logout ───────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user  = $this->createPlainUser();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // Refresh user from DB — token cache cleared
        $user->refresh();

        // Verify token no longer exists in DB
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    // ── Switch Company ───────────────────────────────────────────────

    public function test_user_can_switch_company(): void
    {
        $user     = $this->createPlainUser();
        $company  = $this->createCompany($user);
        $token    = $this->tokenFor($user);

        $response = $this->withToken($token)
                         ->postJson('/api/v1/auth/switch-company', [
                             'company_id' => $company->id,
                         ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'company_id'])
                 ->assertJsonPath('company_id', $company->id);
    }

    public function test_user_cannot_switch_to_company_they_dont_belong_to(): void
    {
        $user      = $this->createPlainUser();
        $otherUser = $this->createPlainUser('other@test.com');
        $company   = $this->createCompany($otherUser);
        $token     = $this->tokenFor($user);

        $response = $this->withToken($token)
                         ->postJson('/api/v1/auth/switch-company', [
                             'company_id' => $company->id,
                         ]);

        $response->assertStatus(403)
                 ->assertJson(['code' => 'COMPANY_ACCESS_DENIED']);
    }
}
