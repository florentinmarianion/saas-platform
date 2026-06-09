<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\App;
use App\Models\User;
use App\Models\UserAppPermission;
use App\Services\TenantContext;

class PermissionPolicy
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function view(User $user): bool
    {
        // Must have company context
        if (!$this->context->isSet()) {
            return false;
        }

        // Company owner or platform admin
        return $this->isCompanyOwnerOrAdmin($user);
    }

    public function grant(User $user): bool
    {
        if (!$this->context->isSet()) {
            return false;
        }

        return $this->isCompanyOwnerOrAdmin($user);
    }

    public function revoke(User $user): bool
    {
        if (!$this->context->isSet()) {
            return false;
        }

        return $this->isCompanyOwnerOrAdmin($user);
    }

    private function isCompanyOwnerOrAdmin(User $user): bool
    {
        // Platform admin
        $hasPlatformPerm = $user->platformPermissions()
            ->where('permission', 'platform.permissions.manage')
            ->exists();

        if ($hasPlatformPerm) {
            return true;
        }

        // Company owner
        return $user->companyUsers()
            ->where('company_id', $this->context->companyId())
            ->whereNull('next_id')
            ->where('status', 'active')
            ->where('is_owner', true)
            ->exists();
    }
}
