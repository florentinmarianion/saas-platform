<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\App;
use App\Models\Company;
use App\Models\User;
use App\Policies\AppPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\UserPolicy;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected array $policies = [
        Company::class => CompanyPolicy::class,
        User::class    => UserPolicy::class,
        App::class     => AppPolicy::class,
    ];

    public function boot(): void
    {
        // ── Super-admin bypass ───────────────────────────────────────
        // Users with platform.* permission bypass ALL gate checks.
        // This runs before any policy is evaluated.
        Gate::before(function (User $user, string $ability): ?bool {
            $isSuperAdmin = $user->platformPermissions()
                ->where('permission', 'platform.*')
                ->exists();

            return $isSuperAdmin ? true : null;
        });

        // ── Platform Gates ───────────────────────────────────────────
        // Gates for platform-level actions without a specific model.

        Gate::define('platform.companies.create', function (User $user): bool {
            return $user->platformPermissions()
                ->where('permission', 'platform.companies.create')
                ->exists();
        });

        Gate::define('platform.users.manage', function (User $user): bool {
            return $user->platformPermissions()
                ->whereIn('permission', [
                    'platform.users.manage',
                    'platform.users.create',
                ])
                ->exists();
        });

        Gate::define('platform.apps.manage', function (User $user): bool {
            return $user->platformPermissions()
                ->where('permission', 'platform.apps.manage')
                ->exists();
        });

        Gate::define('platform.audit.read', function (User $user): bool {
            return $user->platformPermissions()
                ->whereIn('permission', [
                    'platform.audit.read',
                    'platform.audit.export',
                ])
                ->exists();
        });

        Gate::define('platform.approvals.review', function (User $user): bool {
            return $user->platformPermissions()
                ->whereIn('permission', [
                    'platform.approvals.review',
                    'platform.approvals.override',
                ])
                ->exists();
        });

        Gate::define('platform.permissions.manage', function (User $user): bool {
            return $user->platformPermissions()
                ->where('permission', 'platform.permissions.manage')
                ->exists();
        });

        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Register permission policy separately (no model)
        Gate::define('permission.grant', [PermissionPolicy::class, 'grant']);
        Gate::define('permission.revoke', [PermissionPolicy::class, 'revoke']);
        Gate::define('permission.view', [PermissionPolicy::class, 'view']);
    }
}
