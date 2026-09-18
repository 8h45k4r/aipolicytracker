<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin request must come from a session that has passed a second factor.
 *
 * Two states are sent elsewhere rather than refused: an admin who has not yet
 * enrolled an authenticator is taken to enrolment, and one who has enrolled but
 * has not proved a code in this session is taken to the challenge. The check is
 * per session on purpose: "remember me" can keep a browser signed in for weeks,
 * and a cookie should not be enough to publish records or read API keys.
 */
class EnsureAdminSecondFactor
{
    public const SESSION_KEY = 'auth.two_factor_passed';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.two-factor.enrol');
        }

        // Bound to the user id so a session that passed for one account cannot be
        // reused for another after a re-login in the same browser.
        if ((int) $request->session()->get(self::SESSION_KEY) !== (int) $user->getKey()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('admin.two-factor.challenge');
        }

        return $next($request);
    }

    public static function pass(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, (int) $request->user()->getKey());
    }
}
