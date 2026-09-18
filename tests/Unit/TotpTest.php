<?php

namespace Tests\Unit;

use App\Services\Security\Totp;
use PHPUnit\Framework\TestCase;

/**
 * RFC 6238 Appendix B vectors for HMAC-SHA1 with the ASCII secret
 * "12345678901234567890". The RFC lists 8-digit codes; the last six digits are
 * the 6-digit code every authenticator app shows.
 */
class TotpTest extends TestCase
{
    /** "12345678901234567890" in base32. */
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_the_rfc_6238_vectors_are_reproduced(): void
    {
        $vectors = [
            59 => '287082',            // 94287082
            1111111109 => '081804',    // 07081804
            1111111111 => '050471',    // 14050471
            1234567890 => '005924',    // 89005924
            2000000000 => '279037',    // 69279037
            20000000000 => '353130',   // 65353130
        ];
        foreach ($vectors as $time => $expected) {
            $this->assertSame($expected, Totp::code(self::SECRET, $time), "T={$time}");
        }
    }

    public function test_base32_round_trips_the_rfc_secret(): void
    {
        $this->assertSame(self::SECRET, Totp::base32Encode('12345678901234567890'));
        $this->assertSame('12345678901234567890', Totp::base32Decode(self::SECRET));
        $this->assertSame('12345678901234567890', Totp::base32Decode('gezd gnbv gy3t qojq gezd gnbv gy3t qojq'), 'spaces and case are tolerated on entry');
    }

    public function test_verification_allows_one_period_of_clock_drift_and_no_more(): void
    {
        $now = 1234567890;
        $code = Totp::code(self::SECRET, $now);

        $this->assertTrue(Totp::verify(self::SECRET, $code, $now));
        $this->assertTrue(Totp::verify(self::SECRET, $code, $now + 30), 'one period late');
        $this->assertTrue(Totp::verify(self::SECRET, $code, $now - 30), 'one period early');
        $this->assertFalse(Totp::verify(self::SECRET, $code, $now + 60), 'two periods late');
        $this->assertFalse(Totp::verify(self::SECRET, $code, $now - 60), 'two periods early');
    }

    public function test_malformed_codes_are_rejected_without_being_compared(): void
    {
        foreach (['', '12345', '1234567', 'abcdef', '12 34 5', '000000; drop'] as $bad) {
            $this->assertFalse(Totp::verify(self::SECRET, $bad, 59), "'{$bad}'");
        }
        $this->assertTrue(Totp::verify(self::SECRET, '287 082', 59), 'a space inside a valid code is tolerated');
    }

    public function test_generated_secrets_are_160_bits_of_base32(): void
    {
        $a = Totp::generateSecret();
        $b = Totp::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $a);
        $this->assertNotSame($a, $b);
        $this->assertSame(20, strlen(Totp::base32Decode($a)));
    }

    public function test_the_otpauth_uri_carries_issuer_account_and_parameters(): void
    {
        $uri = Totp::otpauthUri(self::SECRET, 'editor@example.org', 'AIPolicyTracker admin');
        $this->assertStringStartsWith('otpauth://totp/AIPolicyTracker%20admin:editor%40example.org?', $uri);
        $this->assertStringContainsString('secret='.self::SECRET, $uri);
        $this->assertStringContainsString('issuer=AIPolicyTracker%20admin', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
