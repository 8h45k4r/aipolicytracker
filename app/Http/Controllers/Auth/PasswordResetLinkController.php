<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public const SENT = 'If an account uses that address, we have emailed it a link to reset the password. The link works for 60 minutes.';

    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // The same answer whether or not an account uses the address, so the form cannot be
        // used to find out who has an account. The broker's own per-address throttle still
        // applies; a throttled address gets the same answer too.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', self::SENT);
    }
}
