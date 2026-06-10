<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Permission;

use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use App\Models\UserAppPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    // ── Grant ────────────────────────────────────────────────────────

    public function test_owner_can_grant_app_permission(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($owner, $company);

        $this->withToken($token)
             ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                 'permission' => 'view',
             ])
             ->assertStatus(201)
             ->assertJsonPath('permission', 'view')
             ->assertJsonPath('granted', true);
    }

    public function test_plain_user_cannot_grant_permission(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($user, $company);

        $this->withToken($token)
             ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                 'permission' => 'view',
             ])
             ->assertStatus(403);
    }

    public function test_cannot_grant_undeclared_permission(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($owner, $company);

        $this->withToken($token)
             ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                 'permission' => 'nonexistent.permission',
             ])
             ->assertStatus(422)
             ->assertJsonPath('code', 'PERMISSION_NOT_DECLARED');
    }

    public function test_sensitive_permission_gets_pending_status(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($owner, $company);

        $this->withToken($token)
             ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                 'permission' => 'delete',
             ])
             ->assertStatus(201)
             ->assertJsonPath('approval_status', 'pending');
    }

    // ── Versioning ───────────────────────────────────────────────────

    public function test_granting_same_permission_twice_creates_new_version(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($owner, $company);

        $this->withToken($token)
             ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                 'permission' => 'view',
             ]);

        $this->withToken($token)
             ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                 'permission' => 'view',
             ])
             ->assertStatus(201)
             ->assertJsonPath('version', 2);

        $this->assertEquals(1, UserAppPermission::where('user_id', $user->id)
            ->where('company_id', $company->id)  // ← adaugă asta
            ->where('app_id', $app->id)
            ->where('permission', 'view')
            ->whereNull('next_id')
            ->count());

    }

    // ── Revoke ───────────────────────────────────────────────────────

    public function test_owner_can_revoke_permission(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($owner, $company);

        $this->withToken($token)
            ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                'permission' => 'view',
            ]);

        $this->withToken($token)
            ->deleteJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions/view")
            ->assertStatus(204);

        $current = UserAppPermission::where('user_id', $user->id)
            ->where('app_id', $app->id)
            ->where('permission', 'view')
            ->whereNull('next_id')
            ->first();

        $this->assertFalse($current->granted);
    }

    // ── History ──────────────────────────────────────────────────────

    public function test_owner_can_view_permission_history(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($owner, $company);

        $this->withToken($token)
            ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                'permission' => 'view',
            ]);

        $this->withToken($token)
            ->deleteJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions/view");

        $this->withToken($token)
            ->getJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions/history/view")
            ->assertStatus(200)
            ->assertJsonCount(2);
    }

    // ── Index ────────────────────────────────────────────────────────

    public function test_owner_can_list_user_permissions(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($owner, $company);

        $this->withToken($token)
             ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                 'permission' => 'view',
             ]);

        $this->withToken($token)
             ->getJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions")
             ->assertStatus(200)
             ->assertJsonCount(1);
    }

    // ── Context ──────────────────────────────────────────────────────

    public function test_permission_endpoints_require_company_context(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $app     = $this->createAppForCompany($company, $owner);
        $user    = $this->createPlainUser();
        $token   = $this->tokenFor($owner);

        $this->withToken($token)
             ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
                 'permission' => 'view',
             ])
             ->assertStatus(403)
             ->assertJsonPath('code', 'MISSING_COMPANY_CONTEXT');
    }
}
