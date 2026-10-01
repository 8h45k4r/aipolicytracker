<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Accepting an invitation sent from Backend → Users: choose a password through the
 * link in the email. It is a password reset on the "invites" broker (a week instead of
 * an hour), and it also verifies the address, because only its owner could have
 * opened the link.
 */
class InvitationController extends Controller
{
    public const BROKER = 'invites';

    public function create(Request $request, string $token): View
    {
        return view('auth.accept-invitation', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker(self::BROKER)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();
                $user->endAllSessions();
                if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
                    event(new Verified($user));
                }

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Your account is ready. Sign in with the password you chose.');
        }

        throw ValidationException::withMessages([
            'email' => [$status === Password::INVALID_TOKEN ? 'This invitation has expired or was already used. Ask the person who invited you to send a new one.' : trans($status)],
        ]);
    }
}
