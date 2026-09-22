<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every web response, including a nonce-based
 * Content-Security-Policy. Inline scripts must carry the Vite CSP nonce
 * (`nonce="{{ Vite::cspNonce() }}"`); third-party sources are listed explicitly.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', self::policy($nonce));

        // Server-rendered pages must always reflect the latest import; only endpoints
        // that set their own Cache-Control (API, exports, feeds, sitemaps) are cached.
        // `no-cache` already forces revalidation on every use. `no-store` on top of
        // it added nothing to freshness and cost the back/forward cache, so a reader
        // pressing Back re-rendered the page they had just left.
        if (! $response->headers->has('Cache-Control') || str_contains((string) $response->headers->get('Cache-Control'), 'no-cache, private')) {
            $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate, max-age=0');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    public static function policy(string $nonce): string
    {
        $dev = app()->environment('local') ? ' http://localhost:5173 ws://localhost:5173' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://www.googletagmanager.com https://static.cloudflareinsights.com https://cdn.jsdelivr.net/npm/flowbite@2.4.1/".$dev,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://cdnjs.cloudflare.com".$dev,
            "font-src 'self' data: https://fonts.bunny.net https://cdnjs.cloudflare.com",
            "img-src 'self' data: https:",
            "connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://cloudflareinsights.com".$dev,
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);
    }
}
