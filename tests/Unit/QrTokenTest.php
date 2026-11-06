<?php

namespace Tests\Unit;

use App\Models\Gate;
use App\Services\QrToken;
use Tests\TestCase;

class QrTokenTest extends TestCase
{
    private function gate(string $secret = 'secret-one'): Gate
    {
        return (new Gate)->forceFill(['id' => 7, 'display_secret' => $secret]);
    }

    public function test_round_trip_signature_and_age(): void
    {
        $t = QrToken::make($this->gate(), 3, 1_800_000_001);
        $p = QrToken::parse($t['qr']);

        $this->assertSame(['gate_id' => 7, 'interval' => 3, 'window' => 600_000_000], array_slice($p, 0, 3));
        $this->assertTrue(QrToken::validSignature($this->gate(), $p));
        $this->assertSame(1, QrToken::age($p, 1_800_000_001));
        $this->assertSame(2, $t['expires_in']);
    }

    public function test_reset_secret_or_tampering_invalidates(): void
    {
        $p = QrToken::parse(QrToken::make($this->gate(), 3)['qr']);

        $this->assertFalse(QrToken::validSignature($this->gate('secret-two'), $p), 'old screens stop working after reset');
        $p['window']++;
        $this->assertFalse(QrToken::validSignature($this->gate(), $p), 'window is signed');
        $this->assertNull(QrToken::parse('https://example.com'));
        $this->assertNull(QrToken::parse('QRX1.7.0.1.aaaaaaaaaaaaaaaaaaaa'), 'zero interval rejected');
    }
}
