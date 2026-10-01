<?php

namespace App\Providers;

use App\Enums\AdminCapability;
use App\Models\User;
use App\Services\Admin\RolePermissions;
use App\Services\Billing\Contracts\BillingGateway;
use App\Services\Billing\DodoGateway;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\Security\EmailDomainPolicy;
use App\Services\Security\MailDomainResolver;
use App\Services\Security\SystemMailDomainResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        // Role permissions are read once per request (or queued job), then reused by every
        // gate check; scoped so an edit is never served stale to the next request.
        $this->app->scoped(RolePermissions::class);
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

        // One ability per administrative capability, so a route group says what it needs
        // ("can:users.manage") instead of naming the roles it trusts. Adding a page means
        // naming its capability once, here and on the route, rather than editing role
        // conditions in several places and missing one.
        foreach (AdminCapability::cases() as $capability) {
            Gate::define($capability->value, fn (User $user) => $user->hasCapability($capability));
        }

        // When an account last signed in, for access reviews. Written without touching
        // updated_at, so "last changed" on the account still means a change to it.
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $now = now();
                User::whereKey($event->user->getKey())->toBase()->update(['last_login_at' => $now]);
                $event->user->setAttribute('last_login_at', $now)->syncOriginalAttribute('last_login_at');
            }
        });
    }
}
