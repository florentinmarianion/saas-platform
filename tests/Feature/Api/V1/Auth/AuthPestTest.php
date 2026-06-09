<?php

declare(strict_types=1);

use Tests\Traits\CreatesTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, CreatesTestData::class);

// ── Login ────────────────────────────────────────────────────────────

it('allows user to login with valid credentials', function (): void {
    $this->createPlainUser('login@test.com');

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
});

it('rejects login with invalid password', function (): void {
    $this->createPlainUser('login@test.com');

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'login@test.com',
        'password' => 'wrong-password',
    ])->assertStatus(401)
      ->assertJson(['code' => 'INVALID_CREDENTIALS']);
});

it('rejects login with nonexistent email', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email'    => 'nobody@test.com',
        'password' => 'password',
    ])->assertStatus(401)
      ->assertJson(['code' => 'INVALID_CREDENTIALS']);
});

it('validates email and password are required', function (): void {
    $this->postJson('/api/v1/auth/login', [])
         ->assertStatus(422)
         ->assertJson(['code' => 'VALIDATION_ERROR'])
         ->assertJsonValidationErrors(['email', 'password']);
});

it('auto-scopes token when user belongs to one company', function (): void {
    $user    = $this->createPlainUser('onecompany@test.com');
    $company = $this->createCompany($user, 'Single Company');

    $this->postJson('/api/v1/auth/login', [
        'email'    => 'onecompany@test.com',
        'password' => 'password',
    ])->assertStatus(200)
      ->assertJsonPath('company_id', $company->id);
});

// ── Me ───────────────────────────────────────────────────────────────

it('returns authenticated user on me endpoint', function (): void {
    $user  = $this->createPlainUser();
    $token = $this->tokenFor($user);

    $this->withToken($token)
         ->getJson('/api/v1/auth/me')
         ->assertStatus(200)
         ->assertJsonPath('user.email', 'user@test.com');
});

it('requires authentication on me endpoint', function (): void {
    $this->getJson('/api/v1/auth/me')
         ->assertStatus(401);
});

// ── Logout ───────────────────────────────────────────────────────────

it('allows user to logout and revokes token', function (): void {
    $user  = $this->createPlainUser();
    $token = $this->tokenFor($user);

    $this->withToken($token)
         ->postJson('/api/v1/auth/logout')
         ->assertStatus(200);

    // Verify token was deleted from DB
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});

// ── Switch Company ────────────────────────────────────────────────────

it('allows user to switch company', function (): void {
    $user    = $this->createPlainUser();
    $company = $this->createCompany($user);
    $token   = $this->tokenFor($user);

    $this->withToken($token)
         ->postJson('/api/v1/auth/switch-company', [
             'company_id' => $company->id,
         ])
         ->assertStatus(200)
         ->assertJsonStructure(['token', 'company_id'])
         ->assertJsonPath('company_id', $company->id);
});

it('blocks switching to a company user does not belong to', function (): void {
    $user      = $this->createPlainUser();
    $otherUser = $this->createPlainUser('other@test.com');
    $company   = $this->createCompany($otherUser);
    $token     = $this->tokenFor($user);

    $this->withToken($token)
         ->postJson('/api/v1/auth/switch-company', [
             'company_id' => $company->id,
         ])
         ->assertStatus(403)
         ->assertJson(['code' => 'COMPANY_ACCESS_DENIED']);
});
