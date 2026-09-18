<?php

namespace App\Services\Security;

/**
 * Time-based one-time passwords (RFC 6238 over RFC 4226 HMAC-SHA1), the scheme
 * every authenticator app implements.
 *
 * Written in place rather than pulled from a package because the whole of it is
 * a base32 codec, one HMAC and a truncation, and a dependency that small is
 * still a dependency somebody has to patch. The RFC's own test vectors run in
 * the suite, so a mistake here fails the build rather than locking an admin out.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const PERIOD = 30;

    public const DIGITS = 6;

    /** A fresh 160-bit secret, base32 encoded, which is what authenticator apps expect to be typed or scanned. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** The code valid for the given moment. */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);
        $hash = hash_hmac('sha1', pack('J', $counter), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Accepts the current code and one period either side, so a phone whose clock
     * drifts by a few seconds still works. Compared in constant time.
     */
    public static function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return false;
        }
        $now = $timestamp ?? time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $now + ($i * self::PERIOD)), $code)) {
                return true;
            }
        }

        return false;
    }

    /** The otpauth:// link an authenticator app opens directly on a phone. */
    public static function otpauthUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => self::DIGITS, 'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $encoded) ?? '');
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                throw new \InvalidArgumentException('Invalid base32 character.');
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
