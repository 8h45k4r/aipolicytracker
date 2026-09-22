<?php

namespace App\Providers;

use App\Services\Billing\Contracts\BillingGateway;
use App\Services\Billing\DodoGateway;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\Security\EmailDomainPolicy;
use App\Services\Security\MailDomainResolver;
use App\Services\Security\SystemMailDomainResolver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BillingGateway::class, DodoGateway::class);
        // The canonical data/ directory. Bound so services that only read it (the reviewer
        // roster) can be injected; the import and validate commands still pass an explicit
        // path when --path is given.
        $this->app->singleton(PolicyDataRepository::class, fn () => PolicyDataRepository::default());
        // Which mail route a domain has. Bound to the interface so a test of the
        // address policy can decide the answer instead of asking the network.
        $this->app->bind(MailDomainResolver::class, SystemMailDomainResolver::class);
        $this->app->singleton(EmailDomainPolicy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One password rule for sign-up, reset and change. Sign-up used to ask
        // for mixed case and a symbol while a reset accepted any eight
        // characters, so the weakest path set the real minimum.
        Password::defaults(fn () => Password::min(8)->mixedCase()->symbols());

        // Behind a proxy that rewrites the Host header (e.g. Cloudflare in front
        // of an App Service default hostname) generated URLs must use APP_URL.
        if ($this->app->environment('production') && config('app.url')) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }
    }
}
