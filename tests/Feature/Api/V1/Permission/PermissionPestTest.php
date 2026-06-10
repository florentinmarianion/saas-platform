<?php

declare(strict_types=1);

use App\Models\UserAppPermission;
use Tests\Traits\CreatesTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, CreatesTestData::class);

// ── Grant ────────────────────────────────────────────────────────────

it('allows owner to grant app permission', function (): void {
    $owner   = $this->createPlainUser('owner@test.com');
    $company = $this->createCompany($owner);
    $app     = $this->createAppForCompany($company, $owner);
    $user    = $this->createPlainUser();
    $this->addUserToCompany($user, $company);

    $this->withToken($this->tokenFor($owner, $company))
         ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
             'permission' => 'view',
         ])
         ->assertStatus(201)
         ->assertJsonPath('permission', 'view')
         ->assertJsonPath('granted', true);
});

it('blocks plain user from granting permissions', function (): void {
    $owner   = $this->createPlainUser('owner@test.com');
    $company = $this->createCompany($owner);
    $app     = $this->createAppForCompany($company, $owner);
    $user    = $this->createPlainUser();
    $this->addUserToCompany($user, $company);

    $this->withToken($this->tokenFor($user, $company))
         ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
             'permission' => 'view',
         ])
         ->assertStatus(403);
});

it('rejects undeclared permission', function (): void {
    $owner   = $this->createPlainUser('owner@test.com');
    $company = $this->createCompany($owner);
    $app     = $this->createAppForCompany($company, $owner);
    $user    = $this->createPlainUser();
    $this->addUserToCompany($user, $company);

    $this->withToken($this->tokenFor($owner, $company))
         ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
             'permission' => 'nonexistent.permission',
         ])
         ->assertStatus(422)
         ->assertJsonPath('code', 'PERMISSION_NOT_DECLARED');
});

it('marks sensitive permission as pending', function (): void {
    $owner   = $this->createPlainUser('owner@test.com');
    $company = $this->createCompany($owner);
    $app     = $this->createAppForCompany($company, $owner);
    $user    = $this->createPlainUser();
    $this->addUserToCompany($user, $company);

    $this->withToken($this->tokenFor($owner, $company))
         ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
             'permission' => 'delete',
         ])
         ->assertStatus(201)
         ->assertJsonPath('approval_status', 'pending');
});

// ── Versioning ───────────────────────────────────────────────────────

it('creates new version when granting same permission twice', function (): void {
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

    expect(
        UserAppPermission::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->where('app_id', $app->id)
            ->where('permission', 'view')
            ->whereNull('next_id')
            ->count()
    )->toBe(1);
});

// ── Revoke ───────────────────────────────────────────────────────────

it('allows owner to revoke permission', function (): void {
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
        ->where('company_id', $company->id)
        ->where('app_id', $app->id)
        ->where('permission', 'view')
        ->whereNull('next_id')
        ->first();

    expect($current->granted)->toBeFalse();
});

// ── History ──────────────────────────────────────────────────────────

it('returns permission history', function (): void {
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
         ->assertJsonCount(2);  // ← 'data' key
});

// ── Index ────────────────────────────────────────────────────────────

it('allows owner to list user permissions', function (): void {
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
});

// ── Context ──────────────────────────────────────────────────────────

it('requires company context for permission endpoints', function (): void {
    $owner   = $this->createPlainUser('owner@test.com');
    $company = $this->createCompany($owner);
    $app     = $this->createAppForCompany($company, $owner);
    $user    = $this->createPlainUser();
    $token   = $this->tokenFor($owner); // no company scope

    $this->withToken($token)
         ->postJson("/api/v1/company/users/{$user->id}/apps/{$app->id}/permissions", [
             'permission' => 'view',
         ])
         ->assertStatus(403)
         ->assertJsonPath('code', 'MISSING_COMPANY_CONTEXT');
});
