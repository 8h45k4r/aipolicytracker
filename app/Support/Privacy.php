<?php

namespace App\Support;

/**
 * Pseudonymisation helpers for the little personal data the site keeps.
 */
class Privacy
{
    /**
     * A keyed hash of a network address. A plain SHA-256 of an IPv4 address is
     * reversible in seconds by hashing all four billion candidates, so it hides
     * nothing; keying it with the application secret makes the table useless
     * without the key while still letting two rows from one address be matched.
     */
    public static function ipHash(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
