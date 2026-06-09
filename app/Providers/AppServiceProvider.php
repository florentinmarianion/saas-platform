<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Company;
use App\Models\User;
use App\Models\UserAppPermission;
use App\Observers\CompanyObserver;
use App\Observers\UserAppPermissionObserver;
use App\Observers\UserObserver;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Builder::defaultStringLength(191);

        // Register observers
        User::observe(UserObserver::class);
        Company::observe(CompanyObserver::class);
        UserAppPermission::observe(UserAppPermissionObserver::class);
    }
}
