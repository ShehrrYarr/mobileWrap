<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\PricingService;
use App\Support\Settings;
use App\Support\Theme;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Theme::class);
        $this->app->singleton(PricingService::class);
        $this->app->singleton(CartService::class);
        $this->app->singleton(\App\Support\StoreNav::class);
        $this->app->singleton(\App\Services\Storefront\SectionData::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Admins can do everything; other roles are limited by permissions.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('Admin') ? true : null);
    }
}
