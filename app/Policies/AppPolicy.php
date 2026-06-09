<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\App;
use App\Models\User;

class AppPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can see available apps
    }

    public function view(User $user, App $app): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->platformPermissions()
            ->where('permission', 'platform.apps.register')
            ->exists();
    }

    public function update(User $user, App $app): bool
    {
        return $user->platformPermissions()
            ->where('permission', 'platform.apps.manage')
            ->exists();
    }

    public function delete(User $user, App $app): bool
    {
        return $user->platformPermissions()
            ->where('permission', 'platform.apps.manage')
            ->exists();
    }

    public function activate(User $user, App $app): bool
    {
        // Company owner or platform admin can activate apps
        return app(\App\Services\TenantContext::class)->isSet();
    }
}
