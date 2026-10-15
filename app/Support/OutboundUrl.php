<?php

namespace App\Support;

/**
 * Whether the server may send a request to a URL a user supplied (an alert webhook).
 *
 * A user-supplied address is refused when it names, or resolves to, a loopback,
 * private, link-local, shared (CGNAT) or reserved address: otherwise any account could
 * make this server call its own database port, the cloud metadata service or another
 * host on the private network, and read the result from the error it is shown.
 *
 * A name that does not resolve is not refused here (the request will fail on its own);
 * a name that does is pinned to the addresses checked, so a DNS answer cannot change
 * between the check and the connection.
 */
final class OutboundUrl
{
    /** Why the URL may not be called from the server, or null when it may. */
    public static function refusal(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return 'not an https address';
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|home\.arpa)$/', $host)) {
            return 'a local address';
        }
        foreach (self::addresses($host) as $ip) {
            if (! self::isPublic($ip)) {
                return 'a private or reserved address';
            }
        }

        return null;
    }

    /**
     * The addresses to connect to: the host itself when it is an IP, else what it resolves to.
     *
     * @return list<string>
     */
    public static function addresses(string $host): array
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = @gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    public static function isPublic(string $ip): bool
    {
        // An IPv4 address written as IPv6 (::ffff:127.0.0.1) is judged as the IPv4 address.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            $ip = $m[1];
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // Shared address space (100.64.0.0/10), which the flags above let through.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);

            return ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'));
        }

        return true;
    }

    /**
     * cURL options that pin the connection to the checked addresses and refuse redirects,
     * for Laravel's HTTP client (`withOptions`).
     *
     * @return array<string, mixed>
     */
    public static function pinnedOptions(string $url): array
    {
        $parts = parse_url($url);
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = (int) ($parts['port'] ?? 443);
        $options = ['allow_redirects' => false];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [] : self::addresses($host);
        if ($ips !== []) {
            $options['curl'] = [CURLOPT_RESOLVE => array_map(fn ($ip) => $host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip), $ips)];
        }

        return $options;
    }
}
