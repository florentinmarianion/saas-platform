<?php

declare(strict_types=1);

use Tests\Traits\CreatesTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, CreatesTestData::class);

// ── Index ────────────────────────────────────────────────────────────

it('allows super admin to list companies', function (): void {
    $admin = $this->createSuperAdmin();
    $this->createCompany($admin, 'Company One');
    $this->createCompany($admin, 'Company Two');

    $this->withToken($this->tokenFor($admin))
         ->getJson('/api/v1/companies')
         ->assertStatus(200)
         ->assertJsonCount(2, 'data');
});

it('blocks plain user from listing companies', function (): void {
    $user = $this->createPlainUser();

    $this->withToken($this->tokenFor($user))
         ->getJson('/api/v1/companies')
         ->assertStatus(403);
});

it('blocks guest from listing companies', function (): void {
    $this->getJson('/api/v1/companies')
         ->assertStatus(401);
});

// ── Store ────────────────────────────────────────────────────────────

it('allows super admin to create company', function (): void {
    $admin = $this->createSuperAdmin();

    $this->withToken($this->tokenFor($admin))
         ->postJson('/api/v1/companies', [
             'name'    => 'New Company',
             'country' => 'RO',
         ])
         ->assertStatus(201)
         ->assertJsonPath('data.name', 'New Company');
});

it('validates name is required when creating company', function (): void {
    $admin = $this->createSuperAdmin();

    $this->withToken($this->tokenFor($admin))
         ->postJson('/api/v1/companies', [])
         ->assertStatus(422)
         ->assertJsonValidationErrors(['name']);
});

it('blocks plain user from creating company', function (): void {
    $user = $this->createPlainUser();

    $this->withToken($this->tokenFor($user))
         ->postJson('/api/v1/companies', ['name' => 'New Company'])
         ->assertStatus(403);
});

// ── Show ─────────────────────────────────────────────────────────────

it('allows company member to view their company', function (): void {
    $admin   = $this->createSuperAdmin();
    $company = $this->createCompany($admin);
    $user    = $this->createPlainUser();
    $this->addUserToCompany($user, $company);

    $this->withToken($this->tokenFor($user))
         ->getJson("/api/v1/companies/{$company->id}")
         ->assertStatus(200)
         ->assertJsonPath('data.id', $company->id);
});

it('blocks user from viewing company they dont belong to', function (): void {
    $admin   = $this->createSuperAdmin();
    $company = $this->createCompany($admin);
    $user    = $this->createPlainUser();

    $this->withToken($this->tokenFor($user))
         ->getJson("/api/v1/companies/{$company->id}")
         ->assertStatus(403);
});

// ── Update ───────────────────────────────────────────────────────────

it('allows company owner to update company', function (): void {
    $owner   = $this->createPlainUser();
    $company = $this->createCompany($owner);

    $this->withToken($this->tokenFor($owner))
         ->putJson("/api/v1/companies/{$company->id}", [
             'name' => 'Updated Name',
         ])
         ->assertStatus(200)
         ->assertJsonPath('data.name', 'Updated Name');
});

it('blocks non-owner from updating company', function (): void {
    $owner   = $this->createPlainUser('owner@test.com');
    $company = $this->createCompany($owner);
    $user    = $this->createPlainUser();
    $this->addUserToCompany($user, $company);

    $this->withToken($this->tokenFor($user))
         ->putJson("/api/v1/companies/{$company->id}", [
             'name' => 'Hacked Name',
         ])
         ->assertStatus(403);
});

// ── Destroy ──────────────────────────────────────────────────────────

it('allows super admin to delete company', function (): void {
    $admin   = $this->createSuperAdmin();
    $company = $this->createCompany($admin);

    $this->withToken($this->tokenFor($admin))
         ->deleteJson("/api/v1/companies/{$company->id}")
         ->assertStatus(204);

    $this->assertSoftDeleted('companies', ['id' => $company->id]);
});

it('blocks plain user from deleting company', function (): void {
    $admin   = $this->createSuperAdmin();
    $company = $this->createCompany($admin);
    $user    = $this->createPlainUser();

    $this->withToken($this->tokenFor($user))
         ->deleteJson("/api/v1/companies/{$company->id}")
         ->assertStatus(403);
});
