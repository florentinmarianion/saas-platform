<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\App;
use App\Models\Company;
use App\Models\CompanyApp;
use App\Models\CompanyUser;
use App\Models\User;
use App\Models\UserPlatformPermission;
use Illuminate\Support\Str;

trait CreatesTestData
{
    /**
     * Creates a super-admin user with platform.* wildcard permission.
     */
    protected function createSuperAdmin(): User
    {
        $user = User::create([
            'id'            => (string) Str::orderedUuid(),
            'email'         => 'superadmin@test.com',
            'name'          => 'Super Admin',
            'password_hash' => 'password',
            'status'        => 'active',
            'version'       => 1,
        ]);

        UserPlatformPermission::insert([
            'id'              => (string) Str::orderedUuid(),
            'user_id'         => $user->id,
            'permission'      => 'platform.*',
            'granted'         => true,
            'granted_by'      => $user->id,
            'approval_status' => 'auto_approved',
            'version'         => 1,
            'created_at'      => now(),
        ]);

        return $user;
    }

    /**
     * Creates a plain user with no permissions.
     */
    protected function createPlainUser(string $email = 'user@test.com'): User
    {
        return User::create([
            'id'            => (string) Str::orderedUuid(),
            'email'         => $email,
            'name'          => 'Plain User',
            'password_hash' => 'password',
            'status'        => 'active',
            'version'       => 1,
        ]);
    }

    /**
     * Creates a company.
     */
    protected function createCompany(User $owner, string $name = 'Test Company'): Company
    {
        $company = Company::create([
            'id'              => (string) Str::orderedUuid(),
            'slug'            => Str::slug($name) . '-' . Str::random(4),
            'name'            => $name,
            'country'         => 'RO',
            'settings'        => [],
            'status'          => 'active',
            'approval_status' => 'approved',
            'approved_by'     => $owner->id,
            'approved_at'     => now(),
            'version'         => 1,
            'created_by'      => $owner->id,
        ]);

        CompanyUser::insert([
            'id'              => (string) Str::orderedUuid(),
            'company_id'      => $company->id,
            'user_id'         => $owner->id,
            'is_owner'        => true,
            'status'          => 'active',
            'approval_status' => 'approved',
            'approved_by'     => $owner->id,
            'approved_at'     => now(),
            'version'         => 1,
            'joined_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $company;
    }

    /**
     * Adds a user to a company as member.
     */
    protected function addUserToCompany(User $user, Company $company): void
    {
        CompanyUser::insert([
            'id'              => (string) Str::orderedUuid(),
            'company_id'      => $company->id,
            'user_id'         => $user->id,
            'is_owner'        => false,
            'status'          => 'active',
            'approval_status' => 'approved',
            'approved_by'     => $user->id,
            'approved_at'     => now(),
            'version'         => 1,
            'joined_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Creates an app and activates it for a company.
     */
    protected function createAppForCompany(Company $company, User $activatedBy): App
    {
        $app = App::create([
            'id'                   => (string) Str::orderedUuid(),
            'slug'                 => 'test-app-' . Str::random(4),
            'module'               => 'TestApp',
            'name'                 => 'Test App',
            'semver'               => '1.0.0',
            'declared_permissions' => ['view', 'edit', 'delete', 'reports.view'],
            'metadata'             => ['sensitive_permissions' => ['delete']],
            'status'               => 'active',
            'approval_status'      => 'approved',
            'version'              => 1,
        ]);

        CompanyApp::insert([
            'id'              => (string) Str::orderedUuid(),
            'company_id'      => $company->id,
            'app_id'          => $app->id,
            'status'          => 'active',
            'config'          => json_encode([]),
            'approval_status' => 'auto_approved',
            'activated_by'    => $activatedBy->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $app;
    }

    /**
     * Returns auth token for a user scoped to a company.
     */
    protected function tokenFor(User $user, ?Company $company = null): string
    {
        $abilities = $company !== null
            ? ["company:{$company->id}", 'api']
            : ['*'];

        return $user->createToken('test-token', $abilities)->plainTextToken;
    }
}
