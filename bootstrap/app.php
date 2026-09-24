<?php

use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CountFunnelViews;
use App\Http\Middleware\EnsureAdminSecondFactor;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordAdminAction;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The app runs behind a reverse proxy in production. Which proxies may
        // speak for the visitor is a deployment fact, so it comes from the
        // environment: a comma-separated list of addresses or CIDR ranges, or `*`
        // for a host where the proxy already resolved the real address and
        // rewrote X-Forwarded-For itself (deploy/nginx-aip.conf does exactly
        // that). Trusting `*` while a proxy passes the visitor's own
        // X-Forwarded-For through lets anyone pick the address every rate limit
        // and audit row is keyed on.
        // Read at request time by App\Http\Middleware\TrustProxies, because this
        // file runs before .env and the configuration are loaded.
        $middleware->replace(TrustProxies::class, App\Http\Middleware\TrustProxies::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CountFunnelViews::class,
        ]);
        // Global, not web-only: the API, unmatched-route 404s and every other response
        // get the same headers. On the web group alone, JSON and error pages went out
        // with no CSP and no nosniff.
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'isAdmin' => CheckAdmin::class,
            'admin.2fa' => EnsureAdminSecondFactor::class,
            'admin.audit' => RecordAdminAction::class,
            'subscribed' => EnsureSubscribed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
