<?php

namespace Tests\Unit;

use App\Support\Security\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** RFC 6238 appendix B seed for SHA1 ("12345678901234567890"). */
    private function rfcSecret(): string
    {
        return Totp::base32Encode('12345678901234567890');
    }

    public function test_it_matches_the_rfc_6238_test_vectors(): void
    {
        $secret = $this->rfcSecret();
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);

        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $expected) {
            $this->assertSame($expected, Totp::code($secret, Totp::step($time), 8), "T={$time}");
            $this->assertSame(substr($expected, -6), Totp::code($secret, Totp::step($time)));
        }
    }

    public function test_it_accepts_one_step_of_clock_drift_either_way(): void
    {
        $secret = Totp::generateSecret();
        $now = 1_800_000_000;
        $step = Totp::step($now);

        $this->assertSame($step, Totp::verify($secret, Totp::code($secret, $step), $now));
        $this->assertSame($step - 1, Totp::verify($secret, Totp::code($secret, $step - 1), $now));
        $this->assertSame($step + 1, Totp::verify($secret, Totp::code($secret, $step + 1), $now));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step - 2), $now));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step + 2), $now));
    }

    public function test_a_used_step_is_refused_and_junk_is_rejected(): void
    {
        $secret = Totp::generateSecret();
        $now = 1_800_000_000;
        $code = Totp::code($secret, Totp::step($now));

        $this->assertNull(Totp::verify($secret, $code, $now, afterStep: Totp::step($now)));
        $this->assertNull(Totp::verify($secret, 'abcdef', $now));
        $this->assertNull(Totp::verify($secret, '12345', $now));
        $this->assertNotNull(Totp::verify($secret, substr($code, 0, 3).' '.substr($code, 3), $now));
    }

    public function test_base32_round_trips_and_builds_an_otpauth_url(): void
    {
        $bytes = random_bytes(20);
        $this->assertSame($bytes, Totp::base32Decode(Totp::base32Encode($bytes)));
        $this->assertSame(32, strlen(Totp::generateSecret()));

        $uri = Totp::provisioningUri('JBSWY3DPEHPK3PXP', '+201000000000', 'محاسبي');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('digits=6', $uri);
    }
}
