<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CountFunnelViews;
use App\Http\Middleware\EnsureAdminSecondFactor;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\KeepEmailAddressesReadable;
use App\Http\Middleware\NegotiateMarkdown;
use App\Http\Middleware\RecordAdminAction;
use App\Http\Middleware\RecordAlertClick;
use App\Http\Middleware\RedirectToCanonicalPath;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Admin\AdminErrorPage;
use App\Support\ErrorAlert;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
        // First in the stack, so the id is on every log line the request writes.
        $middleware->prepend(AssignRequestId::class);

        $middleware->web(append: [
            AddLinkHeadersForPreloadedAssets::class,
            CountFunnelViews::class,
            RecordAlertClick::class,
        ]);
        // Global, not web-only: the API, unmatched-route 404s and every other response
        // get the same headers. On the web group alone, JSON and error pages went out
        // with no CSP and no nosniff.
        $middleware->append(SecurityHeaders::class);
        // One email_off region around every HTML body, so the edge never turns an address into
        // "[email protected]" (the policy above refuses the script that would decode it).
        $middleware->append(KeepEmailAddressesReadable::class);
        // Inside the security headers, so a redirect and a negotiated Markdown file carry them too.
        $middleware->append(RedirectToCanonicalPath::class);
        $middleware->append(NegotiateMarkdown::class);
        $middleware->alias([
            'isAdmin' => CheckAdmin::class,
            'admin.2fa' => EnsureAdminSecondFactor::class,
            'admin.audit' => RecordAdminAction::class,
            'subscribed' => EnsureSubscribed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // An unhandled error in production emails the administrators (throttled), since
        // there is no error-tracking service. Logging still happens as before.
        $exceptions->report(function (Throwable $e) {
            ErrorAlert::report($e);
        });

        // Inside /backend, a signed-in administrator's 403, 404, 419, 429 and 500 render in
        // the admin layout with the reason and a way back; everything else, and anything
        // that fails while drawing that page, falls through to the public error pages.
        $exceptions->render(fn (Throwable $e, Request $request) => AdminErrorPage::render($e, $request));

        // The handler builds the error response outside the middleware stack, so the
        // header is set here as well: the reference the page shows is the one on the
        // wire and the one in the log.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $response->headers->set(AssignRequestId::HEADER, AssignRequestId::assign($request));

            return $response;
        });
    })->create();
