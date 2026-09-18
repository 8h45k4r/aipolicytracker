<?php

namespace App\Http\Controllers\Backend\Security;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminSecondFactor;
use App\Services\Security\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Authenticator enrolment and the per-session challenge for admins.
 *
 * Enrolment is mandatory: an admin who has not enrolled cannot reach any other
 * backend route. The secret is shown once as text and as an otpauth link, never
 * sent to a third-party image service to be drawn as a QR code, because that
 * would hand the second factor to whoever runs the service. Recovery codes are
 * shown once, stored hashed, and each works once.
 */
class TwoFactorController extends Controller
{
    private const RECOVERY_CODES = 8;

    private const PENDING_KEY = 'auth.two_factor_pending_secret';

    public function enrol(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.two-factor.challenge');
        }

        // The pending secret lives in the session until one code from it is proved,
        // so an abandoned enrolment leaves nothing on the account.
        $secret = $request->session()->get(self::PENDING_KEY);
        if (! $secret) {
            $secret = Totp::generateSecret();
            $request->session()->put(self::PENDING_KEY, $secret);
        }

        return view('backend.security.enrol', [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri' => Totp::otpauthUri($secret, $user->email, config('aipolicytracker.site_name').' admin'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $secret = $request->session()->get(self::PENDING_KEY);
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        if (! $secret || ! Totp::verify($secret, $data['code'])) {
            return back()->withErrors(['code' => 'That code did not match. Check the clock on your phone and try the next code.']);
        }

        $plain = $this->freshRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plain),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget(self::PENDING_KEY);
        EnsureAdminSecondFactor::pass($request);

        return redirect()->route('admin.two-factor.recovery')->with('recovery_codes', $plain);
    }

    /** Shown once after enrolment or regeneration; the codes are not retrievable afterwards. */
    public function recovery(Request $request): View
    {
        return view('backend.security.recovery', [
            'codes' => $request->session()->get('recovery_codes', []),
            'remaining' => count($request->user()->two_factor_recovery_codes ?? []),
        ]);
    }

    /** Behind password confirmation: new codes invalidate every old one. */
    public function regenerate(Request $request): RedirectResponse
    {
        $plain = $this->freshRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plain)])->save();

        return redirect()->route('admin.two-factor.recovery')->with('recovery_codes', $plain);
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasTwoFactorEnabled()) {
            return redirect()->route('admin.two-factor.enrol');
        }

        return view('backend.security.challenge');
    }

    /** Throttled at the route: six digits must not be guessable by volume. */
    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $code = trim($data['code']);

        if (Totp::verify($user->two_factor_secret, $code) || $this->consumeRecoveryCode($user, $code)) {
            $request->session()->regenerate();
            EnsureAdminSecondFactor::pass($request);

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['code' => 'That code did not match.']);
    }

    /** @return list<string> */
    private function freshRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $codes[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        return $codes;
    }

    /** A recovery code matches at most once: the hash is removed the moment it is used. */
    private function consumeRecoveryCode($user, string $code): bool
    {
        $hashes = $user->two_factor_recovery_codes ?? [];
        foreach ($hashes as $i => $hash) {
            if (Hash::check(Str::lower($code), $hash)) {
                unset($hashes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }
}
