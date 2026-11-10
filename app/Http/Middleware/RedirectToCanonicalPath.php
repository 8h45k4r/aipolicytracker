<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One URL per page. `/policies/` and `/index.php/policies` both reached the
 * same page as `/policies`; the canonical link named the right one, but a
 * permanent redirect is the stronger signal and keeps links to the variants
 * from being counted apart.
 *
 * Only an HTML page is redirected. The API root is published and probed as
 * `/api/v1/` (deploy/remote-deploy.sh rolls a release back when it is not 200),
 * and a client of a feed, a file or an endpoint may not follow a redirect.
 */
class RedirectToCanonicalPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $uri = (string) $request->server('REQUEST_URI', '/');
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true) || ! str_starts_with($uri, '/')) {
            return $next($request);
        }

        [$path, $query] = array_pad(explode('?', $uri, 2), 2, null);
        $clean = $path;
        if ($clean === '/index.php' || str_starts_with($clean, '/index.php/')) {
            $clean = substr($clean, strlen('/index.php'));
        }
        // Leading slashes are collapsed too, so the target is always a path on this
        // host and never a protocol-relative address.
        $clean = '/'.trim($clean, '/');

        $response = $next($request);
        if ($clean === $path || $response->getStatusCode() !== 200 || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        // Built from the host alone: the URL generator would put `/index.php` back in front.
        return redirect()->to($request->getSchemeAndHttpHost().$clean.($query !== null && $query !== '' ? '?'.$query : ''), 301);
    }
}
