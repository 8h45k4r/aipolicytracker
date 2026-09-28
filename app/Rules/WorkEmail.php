<?php

namespace App\Rules;

use App\Services\Security\EmailDomainPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses consumer mailboxes (gmail.com, outlook.com and the rest of the trusted
 * freemail list) where an organisation's address is asked for. Pair it with
 * NotDisposableEmail, which refuses throwaway domains and domains with no mail route.
 */
class WorkEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! config('templates.gate.require_work_email', true)) {
            return;
        }
        $policy = app(EmailDomainPolicy::class);
        $domain = $policy->domainOf($value);
        if ($domain !== null && $policy->trustedEntryFor($domain) !== null) {
            $fail('Use your work email address. Personal mailboxes such as '.$domain.' are not accepted for template downloads.');
        }
    }
}
