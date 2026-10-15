<?php

namespace App\Http\Middleware;

use App\Support\Reliance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Marks the week when a signed-in account opens a record from an alert (src=alert). */
class RecordAlertClick
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->query('src') === 'alert' && $response->getStatusCode() < 400) {
            try {
                Reliance::recordAlertClick($request);
            } catch (\Throwable $e) {
                report($e); // a measurement must never break the page
            }
        }

        return $response;
    }
}
