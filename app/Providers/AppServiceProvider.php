<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\PricingService;
use App\Support\Livewire\SubdirectoryHandleRequests;
use App\Support\Settings;
use App\Support\Theme;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

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

        // Live server serves the app from a sub-folder (/mobileWrap): Livewire's
        // update endpoint must include that base path (see SubdirectoryHandleRequests).
        $handler = new SubdirectoryHandleRequests;
        $this->app->instance(HandleRequests::class, $handler);
        $this->app->instance(SubdirectoryHandleRequests::class, $handler);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // ...and so must Livewire's script tag. Its route exists once every provider has booted.
        if (SubdirectoryHandleRequests::basePath() !== '') {
            $this->app->booted(function () {
                if ($route = app(FrontendAssets::class)->javaScriptRoute) {
                    config(['livewire.asset_url' => SubdirectoryHandleRequests::prefix($route->uri())]);
                }
            });
        }

        // Admins can do everything; other roles are limited by permissions.
        Gate::before(fn ($user) => method_exists($user, 'hasRole') && $user->hasRole('Admin') ? true : null);
    }
}
