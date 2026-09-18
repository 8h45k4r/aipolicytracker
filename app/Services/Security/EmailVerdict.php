<?php

namespace App\Services\Security;

/**
 * What the policy decided about one address, and which line decided it.
 *
 * The evidence is carried because support will need it: the first false
 * positive will arrive as "your site says my work address is fake", and the
 * answer has to be a file and a line, not a shrug.
 */
class EmailVerdict
{
    /**
     * @param  string|null  $reason  One of: disposable_domain, disposable_mail_host,
     *                               reserved_domain, undeliverable_domain. Null when allowed.
     * @param  string|null  $evidence  The list entry or exchanger that decided it.
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $domain = null,
        public readonly ?string $reason = null,
        public readonly ?string $evidence = null,
    ) {}

    public static function allow(?string $domain = null, ?string $evidence = null): self
    {
        return new self(true, $domain, null, $evidence);
    }

    public static function refuse(string $domain, string $reason, ?string $evidence = null): self
    {
        return new self(false, $domain, $reason, $evidence);
    }

    /**
     * Wording shown to the person typing. It names what is wrong and what to do
     * instead; "invalid email" would leave a reader with a real address and no
     * idea why it was refused.
     */
    public function message(): string
    {
        return match ($this->reason) {
            'disposable_domain', 'disposable_mail_host' => 'Temporary and disposable mailboxes are not accepted. Use a work address, or any personal address you will still be reading when a deadline changes.',
            'reserved_domain' => 'That domain is reserved for documentation and cannot receive mail. Use a real address.',
            'undeliverable_domain' => 'That domain has no mail server, so nothing sent to it would arrive. Check the spelling of the part after the @.',
            default => 'Enter an address that can receive mail.',
        };
    }
}
