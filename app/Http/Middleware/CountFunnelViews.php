<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts successful GET views of the guides library and tool pages by day and path.
 * Anonymous by design: nothing about the visitor is stored. Skips bots that identify
 * themselves and never throws.
 */
class CountFunnelViews
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        try {
            $path = '/'.ltrim($request->path(), '/');
            if ($request->isMethod('GET') && $response->getStatusCode() === 200 && ! $request->ajax()
                && ($path === '/guides' || str_starts_with($path, '/guides/'))
                && ! preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview/i', (string) $request->userAgent())) {
                PageView::hit($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }
}
