<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }
        // A "Sign in" link on a record page says where it came from, so the reader lands back
        // there afterwards. Only a path on this site is accepted, never another host.
        $return = (string) $request->query('return', '');
        if (self::isLocalPath($return)) {
            $request->session()->put('url.intended', url($return));
        }

        return view('auth.login');
    }

    /** A path on this site ("/policies/eu-ai-act"), not a protocol-relative or absolute URL. */
    public static function isLocalPath(string $path): bool
    {
        return $path !== '' && str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\') && ! preg_match('/[\x00-\x1f]/', $path);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        if ($user->isAdmin()) {
            return to_route('dashboard');
        }

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
