<?php

namespace App\Http\Requests\Auth;

use App\Services\Security\Turnstile;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Failed sign-ins one address may make, across all emails, per window. */
    public const ADDRESS_ATTEMPTS = 20;

    public const ADDRESS_DECAY_SECONDS = 600;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->addressKey(), self::ADDRESS_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Suspension is the stop button: it blocks sign-in outright rather than only removing
        // admin access, so a compromised or departed account can be stopped without deleting
        // the history attached to it. Checked after a correct password so the message cannot
        // be used to enumerate which addresses are suspended.
        if (Auth::user()?->isSuspended()) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->addressKey(), self::ADDRESS_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        // Two counters. Per address and email: five wrong passwords lock that pair. Per
        // address alone: one caller trying a common password against many accounts
        // (spraying) never repeats an email, so only this one stops it. A successful
        // sign-in does not reset it, so an attacker's own account cannot clear it.
        //
        // The address counter does not lock the address out when the automated check is
        // configured: it asks for the check instead. If the client address were ever wrong
        // (a proxy hop reporting one address for everyone), a hard limit would sign every
        // visitor out of reach after 20 failures; the check keeps people in and bots out.
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5) && self::challengeRequired($this)) {
            $turnstile = app(Turnstile::class);
            if ($turnstile->configured()) {
                if ($turnstile->rejects($this)) {
                    throw ValidationException::withMessages(['turnstile' => 'Many sign-in attempts came from your network. Complete the check below and sign in again.']);
                }

                return;
            }
        }
        $key = RateLimiter::tooManyAttempts($this->throttleKey(), 5) ? $this->throttleKey()
            : (self::challengeRequired($this) ? $this->addressKey() : null);
        if ($key === null) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /** Failed sign-ins from one address, whatever email they tried. */
    public function addressKey(): string
    {
        return self::addressKeyFor($this);
    }

    public static function addressKeyFor(Request $request): string
    {
        return 'login-address|'.$request->ip();
    }

    /** Whether sign-ins from this request's address have reached the per-address limit. */
    public static function challengeRequired(Request $request): bool
    {
        return RateLimiter::tooManyAttempts(self::addressKeyFor($request), self::ADDRESS_ATTEMPTS);
    }
}
