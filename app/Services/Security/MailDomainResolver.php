<?php

namespace App\Services\Security;

/**
 * Where a domain's mail goes, if anywhere.
 *
 * Kept behind an interface for one reason: the rule that uses it decides whether
 * somebody can create an account, and a test of that decision must not depend on
 * the network. The real implementation talks to DNS; tests supply their own.
 */
interface MailDomainResolver
{
    /**
     * Exchanger hostnames for a domain, lowercased and without the trailing dot.
     *
     * @return list<string>|null An empty list means the domain has no mail route
     *                           at all. Null means the lookup could not be made —
     *                           the caller must treat that as "unknown" and allow
     *                           the address, never as a refusal.
     */
    public function mailHosts(string $domain): ?array;
}
