<?php

namespace App\Services\Security;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * DNS-backed resolver, with two safeguards that matter more than the lookup.
 *
 * Caching: a result is kept per domain, so a form submitted five times over a
 * password rule costs one round trip, not five.
 *
 * Failing open: if a domain appears to have no mail route, a control domain is
 * resolved before that is believed. A resolver outage would otherwise refuse
 * every sign-up on the site at once, which is a worse failure than admitting a
 * throwaway address.
 */
class SystemMailDomainResolver implements MailDomainResolver
{
    public function __construct(private CacheRepository $cache) {}

    /** @return list<string>|null */
    public function mailHosts(string $domain): ?array
    {
        $ttl = max(60, (int) config('email.cache_ttl', 86400));

        $cached = $this->cache->remember('email-domain:mx:'.$domain, $ttl, function () use ($domain) {
            $hosts = $this->lookup($domain);

            // Encoded rather than returned directly: the cache must be able to
            // tell "no mail route" (an empty list) from "not known" (null), and
            // a cache miss also reads as null.
            return $hosts === null ? ['unknown' => true] : ['hosts' => $hosts];
        });

        return isset($cached['unknown']) ? null : $cached['hosts'];
    }

    /** @return list<string>|null */
    private function lookup(string $domain): ?array
    {
        $records = @dns_get_record($domain, DNS_MX);

        if (is_array($records) && $records !== []) {
            $hosts = [];
            foreach ($records as $record) {
                $target = rtrim(strtolower((string) ($record['target'] ?? '')), '.');
                if ($target !== '') {
                    $hosts[] = $target;
                }
            }

            return array_values(array_unique($hosts));
        }

        // No MX is not the same as no mail. RFC 5321 says a host with an address
        // record accepts mail for itself, and small domains still rely on that.
        if ($this->hasAddressRecord($domain)) {
            return [$domain];
        }

        // Nothing found. Before refusing the address, check that the resolver is
        // answering at all.
        return $this->resolverIsHealthy() ? [] : null;
    }

    private function hasAddressRecord(string $domain): bool
    {
        $records = @dns_get_record($domain, DNS_A | DNS_AAAA);

        return is_array($records) && $records !== [];
    }

    private function resolverIsHealthy(): bool
    {
        $canary = (string) config('email.canary_domain', 'gmail.com');

        return (bool) $this->cache->remember('email-domain:canary:'.$canary, 300, function () use ($canary) {
            $records = @dns_get_record($canary, DNS_MX);

            return is_array($records) && $records !== [];
        });
    }
}
