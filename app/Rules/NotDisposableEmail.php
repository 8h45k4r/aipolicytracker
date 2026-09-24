<?php

namespace App\Rules;

use App\Services\Security\EmailDomainPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Refuses an address nobody will be reading later.
 *
 * Applies wherever an address enters the system and is meant to be written to:
 * registration, a change of address on the profile, the newsletter, and the
 * optional contact address on a contribution. It is deliberately absent from
 * sign-in and password reset — an account that already exists must always be
 * able to get back in, whatever its address, and tightening the rule later must
 * not lock out the people who signed up before it.
 *
 * Pair it with `email`, which judges the format. This one judges the domain.
 */
class NotDisposableEmail implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $verdict = app(EmailDomainPolicy::class)->inspect(trim($value));

        if (! $verdict->allowed) {
            $fail($verdict->message());
        }
    }
}
