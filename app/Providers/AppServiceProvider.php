<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Billing\Contracts\BillingGateway::class, \App\Services\Billing\DodoGateway::class);
        // The canonical data/ directory. Bound so services that only read it (the reviewer
        // roster) can be injected; the import and validate commands still pass an explicit
        // path when --path is given.
        $this->app->singleton(\App\Services\PolicyData\PolicyDataRepository::class, fn () => \App\Services\PolicyData\PolicyDataRepository::default());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind a proxy that rewrites the Host header (e.g. Cloudflare in front
        // of an App Service default hostname) generated URLs must use APP_URL.
        if ($this->app->environment('production') && config('app.url')) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }
    }
}
