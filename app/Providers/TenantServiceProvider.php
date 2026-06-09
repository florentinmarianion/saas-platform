<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton — one instance per request lifecycle
        $this->app->singleton(TenantContext::class, fn() => new TenantContext());

        // Alias for convenience: app('tenant')
        $this->app->alias(TenantContext::class, 'tenant');

        // AuditService depends on TenantContext — singleton per request
        $this->app->singleton(
            \App\Services\AuditService::class,
            fn($app) => new \App\Services\AuditService(
                $app->make(TenantContext::class),
            ),
        );
    }

    public function boot(): void
    {
        //
    }
}
