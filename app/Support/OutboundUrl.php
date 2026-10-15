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
    /** The refusal for a name with no address: possibly a passing DNS fault, so retried. */
    public const UNRESOLVED = 'an address that does not resolve';

    /**
     * Why the URL may not be called from the server, or null when it may. At save time a
     * name that does not resolve yet is accepted (it may be about to); at send time
     * prepare() refuses it.
     */
    public static function refusal(string $url): ?string
    {
        $reason = self::prepare($url)['refusal'];

        return $reason === self::UNRESOLVED ? null : $reason;
    }

    /**
     * The addresses to connect to: the host itself when it is an IP, else what it resolves to.
     *
     * @return list<string>
     */
    /** @var (callable(string): ?list<string>)|null */
    private static $resolver = null;

    /**
     * Answer name lookups from a callable instead of DNS; it returns null to fall back to
     * DNS. For tests, so a suite never depends on the network to resolve a name.
     */
    public static function resolveUsing(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function addresses(string $host): array
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        if (self::$resolver && ($stubbed = (self::$resolver)($host)) !== null) {
            return array_values($stubbed);
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
        $packed = @inet_pton(trim($ip, '[]'));
        if ($packed === false) {
            return false;
        }
        // An IPv4 address carried inside IPv6 is judged as the IPv4 address, in every
        // spelling: mapped (::ffff:7f00:1), compatible (::127.0.0.1), NAT64 (64:ff9b::/96 and
        // the local-use 64:ff9b:1::/48) and 6to4 (2002::/16, the IPv4 in bits 16-47).
        if (strlen($packed) === 16) {
            $prefix = substr($packed, 0, 12);
            if ($prefix === str_repeat("\0", 10)."\xff\xff" || $prefix === str_repeat("\0", 12) || $prefix === "\x00\x64\xff\x9b".str_repeat("\0", 8)
                || substr($packed, 0, 6) === "\x00\x64\xff\x9b\x00\x01") {
                $packed = substr($packed, 12);
            } elseif (substr($packed, 0, 2) === "\x20\x02") {
                $packed = substr($packed, 2, 4);
            } elseif (substr($packed, 0, 4) === "\x20\x01\x00\x00" // Teredo (2001::/32): tunnels to an address it hides
                || (ord($packed[0]) === 0xFE && (ord($packed[1]) & 0xC0) === 0xC0)) { // deprecated site-local (fec0::/10)
                return false;
            }
        }
        $ip = inet_ntop($packed);
        if (strlen($packed) === 16) {
            return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                && ! str_starts_with(strtolower($ip), 'ff'); // multicast
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // Special-purpose IPv4 blocks the flags above let through (RFC 6890 and RFC 5737).
        foreach (['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if ((ip2long($ip) & $mask) === (ip2long($net) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve once, check what was resolved, and return cURL options that pin the
     * connection to exactly those addresses with redirects refused. Resolving again for the
     * connection would let a DNS answer change between the check and the request.
     *
     * @return array{refusal: ?string, options: array<string, mixed>}
     */
    public static function prepare(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return ['refusal' => 'not an https address', 'options' => []];
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|home\.arpa)$/', $host)) {
            return ['refusal' => 'a local address', 'options' => []];
        }
        $ips = self::addresses($host);
        if ($ips === []) {
            return ['refusal' => self::UNRESOLVED, 'options' => []];
        }
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                return ['refusal' => 'a private or reserved address', 'options' => []];
            }
        }
        $options = ['allow_redirects' => false];
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            $port = (int) ($parts['port'] ?? 443);
            $options['curl'] = [CURLOPT_RESOLVE => array_map(fn ($ip) => $host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip), $ips)];
        }

        return ['refusal' => null, 'options' => $options];
    }
}
