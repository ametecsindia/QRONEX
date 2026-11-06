<?php

namespace App\Services;

/** RFC 6238 TOTP (SHA-1, 6 digits, 30 s) — works with Google Authenticator, Microsoft Authenticator, Authy. */
class Totp
{
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn ($chunk) => self::B32[bindec($chunk)], str_split($bits, 5)));
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $key = '';
        $bits = '';
        foreach (str_split(strtoupper($secret)) as $c) {
            $bits .= str_pad(decbin(strpos(self::B32, $c)), 5, '0', STR_PAD_LEFT);
        }
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }
        $h = hash_hmac('sha1', pack('J', intdiv($time ?? time(), 30)), $key, true);
        $o = ord($h[19]) & 0xF;
        $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);

        return str_pad((string) ($n % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** Accepts the current 30 s step ± 1 (clock drift). */
    public static function verify(string $secret, string $code, ?int $time = null): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $time ??= time();
        foreach ([-30, 0, 30] as $drift) {
            if (hash_equals(self::code($secret, $time + $drift), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function uri(string $secret, string $account, string $issuer = 'QRONEX'): string
    {
        return 'otpauth://totp/'.rawurlencode("$issuer:$account").'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&digits=6&period=30';
    }
}
