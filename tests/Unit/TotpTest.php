<?php

namespace Tests\Unit;

use App\Services\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    private const RFC = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // base32("12345678901234567890"), RFC 6238 test key

    public function test_rfc_6238_vectors(): void
    {
        $this->assertSame('287082', Totp::code(self::RFC, 59));          // 94287082
        $this->assertSame('081804', Totp::code(self::RFC, 1111111109));  // 07081804
        $this->assertSame('005924', Totp::code(self::RFC, 1234567890));  // 89005924
    }

    public function test_verify_allows_one_step_drift_only(): void
    {
        $now = 1_800_000_000;
        $this->assertTrue(Totp::verify(self::RFC, Totp::code(self::RFC, $now - 30), $now));
        $this->assertTrue(Totp::verify(self::RFC, substr_replace(Totp::code(self::RFC, $now), ' ', 3, 0), $now), 'spaces ignored');
        $this->assertFalse(Totp::verify(self::RFC, Totp::code(self::RFC, $now - 90), $now));
        $this->assertFalse(Totp::verify(self::RFC, '12345', $now));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', Totp::secret());
    }
}
