<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Which proxies may set X-Forwarded-* headers, read from `app.trusted_proxies`
 * at request time. The list is a deployment fact rather than a property of
 * the code, and bootstrap/app.php runs before the environment is loaded, so a
 * value set there could only ever be a constant.
 */
class TrustProxies extends Middleware
{
    /**
     * Only the client address and the scheme are taken from a proxy. Host, port and
     * prefix are not: every shipped proxy config passes a visitor's own
     * X-Forwarded-Port and X-Forwarded-Prefix straight through, and a trusted one
     * rewrote the URLs the app generates, including the pagination links cached in
     * public API responses for everyone else.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO;

    /** @return array<int, string>|string|null */
    protected function proxies(): array|string|null
    {
        $value = trim((string) config('app.trusted_proxies', '*'));
        if ($value === '' || $value === '*' || $value === '**') {
            return $value === '' ? null : $value;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
