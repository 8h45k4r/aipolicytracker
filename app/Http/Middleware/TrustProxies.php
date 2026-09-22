<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Which proxies may set X-Forwarded-* headers, read from `app.trusted_proxies`
 * at request time. The list is a deployment fact rather than a property of
 * the code, and bootstrap/app.php runs before the environment is loaded, so a
 * value set there could only ever be a constant.
 */
class TrustProxies extends Middleware
{
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
