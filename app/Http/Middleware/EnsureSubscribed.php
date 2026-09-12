<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for paid capabilities: `subscribed` requires any covering plan,
 * `subscribed:alerts.daily` requires that specific entitlement. Guests are sent
 * to sign in; signed-in users without the entitlement land on the pricing page.
 */
class EnsureSubscribed
{
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('login'));
        }
        $ok = $capability ? $user->entitled($capability) : $user->activeSubscription() !== null;
        if (! $ok) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This feature requires a Pro plan.'], 402);
            }

            return redirect()->route('pricing')->with('error', 'This feature requires a Pro plan.');
        }

        return $next($request);
    }
}
