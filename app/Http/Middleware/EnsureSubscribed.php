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

            // Selling is retired, so there is no page to send anyone to and no
            // plan to buy. With billing disabled every signed-in account already
            // holds these capabilities, so reaching this line means the feature is
            // genuinely unavailable rather than merely unpaid.
            return redirect()->route('home')->with('error', 'That feature is not available on this account.');
        }

        return $next($request);
    }
}
