<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Super-admin bypass is handled in Gate::before() — no need to repeat here.
     */

    public function viewAny(User $user): bool
    {
        return $user->platformPermissions()
            ->whereIn('permission', [
                'platform.companies.create',
                'platform.companies.manage_all',
            ])
            ->exists();
    }

    public function view(User $user, Company $company): bool
    {
        // Can view if platform permission OR is member of this company
        return $user->companyUsers()
            ->where('company_id', $company->id)
            ->whereNull('next_id')
            ->where('status', 'active')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->platformPermissions()
            ->where('permission', 'platform.companies.create')
            ->exists();
    }

    public function update(User $user, Company $company): bool
    {
        // Platform admin OR company owner
        $hasPlatformPerm = $user->platformPermissions()
            ->where('permission', 'platform.companies.manage_all')
            ->exists();

        if ($hasPlatformPerm) {
            return true;
        }

        return $user->companyUsers()
            ->where('company_id', $company->id)
            ->whereNull('next_id')
            ->where('status', 'active')
            ->where('is_owner', true)
            ->exists();
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->platformPermissions()
            ->where('permission', 'platform.companies.manage_all')
            ->exists();
    }
}
