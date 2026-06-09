<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->platformPermissions()
            ->whereIn('permission', [
                'platform.users.manage',
                'platform.users.create',
            ])
            ->exists();
    }

    public function view(User $user, User $target): bool
    {
        // Can view self always
        if ($user->id === $target->id) {
            return true;
        }

        return $user->platformPermissions()
            ->whereIn('permission', [
                'platform.users.manage',
                'platform.users.create',
            ])
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->platformPermissions()
            ->where('permission', 'platform.users.create')
            ->exists();
    }

    public function update(User $user, User $target): bool
    {
        // Can update self always
        if ($user->id === $target->id) {
            return true;
        }

        return $user->platformPermissions()
            ->where('permission', 'platform.users.manage')
            ->exists();
    }

    public function delete(User $user, User $target): bool
    {
        // Cannot delete self
        if ($user->id === $target->id) {
            return false;
        }

        return $user->platformPermissions()
            ->where('permission', 'platform.users.delete')
            ->exists();
    }

    public function updateStatus(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        return $user->platformPermissions()
            ->whereIn('permission', [
                'platform.users.manage',
                'platform.users.suspend',
            ])
            ->exists();
    }
}
