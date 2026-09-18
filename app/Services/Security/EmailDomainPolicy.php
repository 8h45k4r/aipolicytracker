<?php

namespace App\Services\Security;

/**
 * Decides whether an address belongs to somebody who can be reached later.
 *
 * The product's promise is that a reader hears when a deadline moves. An address
 * that expires in ten minutes makes that promise unkeepable, and it also inflates
 * the subscriber and download figures the site publishes about itself.
 *
 * Four checks, in order, each doing work the others cannot:
 *
 *  1. Trusted list — freemail and consumer providers people keep for years.
 *     Checked first and final, so no later heuristic can refuse a mailbox
 *     somebody has had since 2009. Work addresses are welcome; so are personal
 *     ones. What is refused is a mailbox nobody owns.
 *  2. Reserved names — RFC 2606 and RFC 6761. Nobody is reachable there.
 *  3. Domain lists — the curated file plus the operator's overlay. Short by
 *     design; a static list of throwaway domains is out of date the day it
 *     ships, so it is the weakest of the four and is never relied on alone.
 *  4. Mail route — where the domain's MX records actually point. This is the
 *     check that earns its keep: a throwaway service rotates thousands of
 *     domains through a handful of its own exchangers, so blocking the
 *     exchanger stops the domains nobody has catalogued yet. The same lookup
 *     refuses a domain with no mail route at all.
 *
 * Every uncertain answer allows the address. A person with a real mailbox who
 * cannot sign up is a worse outcome than a throwaway account, and a DNS outage
 * must never become a sign-up outage.
 */
class EmailDomainPolicy
{
    /** @var array<string,list<string>> parsed list files, per instance so a test can swap a path */
    private array $loaded = [];

    public function __construct(private MailDomainResolver $resolver) {}

    public function inspect(string $email): EmailVerdict
    {
        $domain = $this->domainOf($email);

        // Not parseable as an address. Format is the `email` rule's job, and
        // reporting it twice in different words helps nobody.
        if ($domain === null) {
            return EmailVerdict::allow();
        }

        if (! config('email.enforce', true)) {
            return EmailVerdict::allow($domain);
        }

        if ($match = $this->matchIn($domain, $this->list('trusted'))) {
            return EmailVerdict::allow($domain, $match);
        }

        if ($match = $this->matchIn($domain, $this->reserved())) {
            return EmailVerdict::refuse($domain, 'reserved_domain', $match);
        }

        if ($match = $this->matchIn($domain, $this->blockedDomains())) {
            return EmailVerdict::refuse($domain, 'disposable_domain', $match);
        }

        if (! config('email.check_deliverability', true)) {
            return EmailVerdict::allow($domain);
        }

        $hosts = $this->resolver->mailHosts($domain);

        // Unknown. The resolver could not answer, so nothing has been learned.
        if ($hosts === null) {
            return EmailVerdict::allow($domain);
        }

        if ($hosts === []) {
            return EmailVerdict::refuse($domain, 'undeliverable_domain');
        }

        $exchangers = $this->list('mail_hosts');
        foreach ($hosts as $host) {
            if ($match = $this->matchIn($host, $exchangers)) {
                return EmailVerdict::refuse($domain, 'disposable_mail_host', $host.' → '.$match);
            }
        }

        return EmailVerdict::allow($domain);
    }

    /**
     * The trusted-list entry covering this domain, if any.
     *
     * Public because an operator loading somebody else's blocklist needs to be
     * told which of its lines will never apply, and answering that must not
     * cost a DNS lookup per domain.
     */
    public function trustedEntryFor(string $domain): ?string
    {
        return $this->matchIn(strtolower(trim($domain)), $this->list('trusted'));
    }

    /** The part after the last @, lowercased, without a trailing dot. */
    public function domainOf(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }

        $domain = rtrim(strtolower(trim(substr($email, $at + 1))), '.');

        // A bracketed literal (user@[192.0.2.1]) is valid under RFC 5321 and has
        // no domain to judge. Nothing here can say anything useful about it.
        return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain) ? $domain : null;
    }

    /**
     * Suffix match on label boundaries: "mailinator.com" matches itself and
     * "team.mailinator.com", but never "notmailinator.com".
     *
     * @param  list<string>  $entries
     */
    private function matchIn(string $host, array $entries): ?string
    {
        foreach ($entries as $entry) {
            if ($host === $entry || str_ends_with($host, '.'.$entry)) {
                return $entry;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function blockedDomains(): array
    {
        return array_values(array_unique(array_merge(
            $this->list('disposable'),
            $this->readFile((string) config('email.overlay')),
        )));
    }

    /** @return list<string> */
    private function reserved(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => strtolower(trim((string) $value)),
            (array) config('email.reserved', []),
        )));
    }

    /**
     * Curated list files, parsed once per instance.
     *
     * @return list<string>
     */
    private function list(string $key): array
    {
        return $this->loaded[$key] ??= $this->readFile((string) config("email.lists.{$key}"));
    }

    /** @return list<string> */
    private function readFile(string $path): array
    {
        if ($path === '' || ! is_file($path)) {
            return [];
        }

        $entries = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = strtolower(trim($line));
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $entries[] = ltrim($line, '.@');
        }

        return array_values(array_unique($entries));
    }
}
