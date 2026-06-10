<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Company;

use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;

    // ── Index ────────────────────────────────────────────────────────

    public function test_super_admin_can_list_companies(): void
    {
        $admin = $this->createSuperAdmin();
        $this->createCompany($admin, 'Company One');
        $this->createCompany($admin, 'Company Two');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
             ->getJson('/api/v1/companies')
             ->assertStatus(200)
             ->assertJsonCount(2, 'data');
    }

    public function test_plain_user_cannot_list_companies(): void
    {
        $user  = $this->createPlainUser();
        $token = $this->tokenFor($user);

        $this->withToken($token)
             ->getJson('/api/v1/companies')
             ->assertStatus(403);
    }

    public function test_guest_cannot_list_companies(): void
    {
        $this->getJson('/api/v1/companies')
             ->assertStatus(401);
    }

    // ── Store ────────────────────────────────────────────────────────

    public function test_super_admin_can_create_company(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $this->tokenFor($admin);

        $this->withToken($token)
             ->postJson('/api/v1/companies', [
                 'name'    => 'New Company',
                 'country' => 'RO',
             ])
             ->assertStatus(201)
             ->assertJsonPath('data.name', 'New Company');
    }

    public function test_company_creation_requires_name(): void
    {
        $admin = $this->createSuperAdmin();
        $token = $this->tokenFor($admin);

        $this->withToken($token)
             ->postJson('/api/v1/companies', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['name']);
    }

    public function test_plain_user_cannot_create_company(): void
    {
        $user  = $this->createPlainUser();
        $token = $this->tokenFor($user);

        $this->withToken($token)
             ->postJson('/api/v1/companies', [
                 'name' => 'New Company',
             ])
             ->assertStatus(403);
    }

    // ── Show ─────────────────────────────────────────────────────────

    public function test_company_member_can_view_their_company(): void
    {
        $admin   = $this->createSuperAdmin();
        $company = $this->createCompany($admin);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($user);

        $this->withToken($token)
             ->getJson("/api/v1/companies/{$company->id}")
             ->assertStatus(200)
             ->assertJsonPath('data.id', $company->id);
    }

    public function test_user_cannot_view_company_they_dont_belong_to(): void
    {
        $admin   = $this->createSuperAdmin();
        $company = $this->createCompany($admin);
        $user    = $this->createPlainUser();
        $token   = $this->tokenFor($user);

        $this->withToken($token)
             ->getJson("/api/v1/companies/{$company->id}")
             ->assertStatus(403);
    }

    // ── Update ───────────────────────────────────────────────────────

    public function test_company_owner_can_update_company(): void
    {
        $owner   = $this->createPlainUser();
        $company = $this->createCompany($owner);
        $token   = $this->tokenFor($owner);

        $this->withToken($token)
             ->putJson("/api/v1/companies/{$company->id}", [
                 'name' => 'Updated Name',
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_non_owner_cannot_update_company(): void
    {
        $owner   = $this->createPlainUser('owner@test.com');
        $company = $this->createCompany($owner);
        $user    = $this->createPlainUser();
        $this->addUserToCompany($user, $company);
        $token = $this->tokenFor($user);

        $this->withToken($token)
             ->putJson("/api/v1/companies/{$company->id}", [
                 'name' => 'Hacked Name',
             ])
             ->assertStatus(403);
    }

    // ── Destroy ──────────────────────────────────────────────────────

    public function test_super_admin_can_delete_company(): void
    {
        $admin   = $this->createSuperAdmin();
        $company = $this->createCompany($admin);
        $token   = $this->tokenFor($admin);

        $this->withToken($token)
             ->deleteJson("/api/v1/companies/{$company->id}")
             ->assertStatus(204);

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_plain_user_cannot_delete_company(): void
    {
        $admin   = $this->createSuperAdmin();
        $company = $this->createCompany($admin);
        $user    = $this->createPlainUser();
        $token   = $this->tokenFor($user);

        $this->withToken($token)
             ->deleteJson("/api/v1/companies/{$company->id}")
             ->assertStatus(403);
    }
}
