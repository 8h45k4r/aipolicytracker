<?php

namespace App\Providers;

use App\Enums\AdminCapability;
use App\Models\User;
use App\Services\Admin\RolePermissions;
use App\Services\Billing\BillingConfig;
use App\Services\Billing\Contracts\BillingGateway;
use App\Services\Billing\DodoGateway;
use App\Services\Billing\Entitlements;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\Security\EmailDomainPolicy;
use App\Services\Security\MailDomainResolver;
use App\Services\Security\SystemMailDomainResolver;
use App\Services\Subscribers\AccountDigest;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Requests a minute one signed-in administrator may make to /backend. An owner working
     * through a queue opens, filters and saves quickly; ten pages a second for a full minute
     * is still far beyond any person and well inside what the host serves.
     */
    public const ADMIN_REQUESTS_PER_MINUTE = 600;

    /** The read-only API's public rate, per address; free on every plan and unchanged by selling. */
    public const PUBLIC_API_REQUESTS_PER_MINUTE = 120;

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
        // A suspended account's API tokens stop working at once, whether or not they were
        // revoked when it was suspended.
        Sanctum::authenticateAccessTokensUsing(fn ($token, bool $isValid) => $isValid && ! ($token->tokenable?->isSuspended() ?? false));

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
        // Page links are built through the URL generator, so they follow the forced root
        // too. Built from the raw request they kept whatever host a caller sent, and the
        // cached API responses then served that host to everyone.
        Paginator::currentPathResolver(fn () => url()->current());

        // One ability per administrative capability, so a route group says what it needs
        // ("can:users.manage") instead of naming the roles it trusts. Adding a page means
        // naming its capability once, here and on the route, rather than editing role
        // conditions in several places and missing one.
        foreach (AdminCapability::cases() as $capability) {
            Gate::define($capability->value, fn (User $user) => $user->hasCapability($capability));
        }

        // The admin's own limiter, keyed on the account rather than the address, so an owner
        // clicking quickly is not counted with anything else and the public limits stay as
        // they are. Named, so its bucket is its own: plain `throttle:N,1` limits all share a
        // single per-account counter. A request with no account (it cannot pass `auth`, but
        // the limiter runs only after it) falls back to the public rate per address.
        RateLimiter::for('admin', fn (Request $request) => $request->user()
            ? Limit::perMinute(self::ADMIN_REQUESTS_PER_MINUTE)->by('user:'.$request->user()->getAuthIdentifier())
            : Limit::perMinute(120)->by('ip:'.$request->ip()));

        // The read-only API. Without a token the public rate applies per address, as it always
        // has. With an account token the count is per account, at the plan's rate once selling
        // is on. While selling is off the token gets the public rate: granting the paid rate to
        // everyone then (as other capabilities are) would take it away when selling starts.
        RateLimiter::for('public-api', function (Request $request) {
            $user = $request->bearerToken() ? auth('sanctum')->user() : null;
            if (! $user instanceof User) {
                return Limit::perMinute(self::PUBLIC_API_REQUESTS_PER_MINUTE)->by('ip:'.$request->ip());
            }
            $rate = app(BillingConfig::class)->enabled() ? (int) app(Entitlements::class)->value($user, 'api.rate_per_minute') : 0;

            return Limit::perMinute(max(self::PUBLIC_API_REQUESTS_PER_MINUTE, $rate))->by('user:'.$user->getAuthIdentifier());
        });

        // A choice made at sign-up to receive the digest takes effect once the address is proven.
        Event::listen(Verified::class, function (Verified $event) {
            if ($event->user instanceof User) {
                app(AccountDigest::class)->afterVerification($event->user);
            }
        });

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
