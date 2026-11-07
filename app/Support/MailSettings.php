<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Outgoing email set by the Super Admin (Email & SMS page). Saved values override MAIL_* in .env;
 * nothing saved = .env as before. The SMTP password is stored encrypted with APP_KEY.
 */
class MailSettings
{
    public const KEY = 'mail';

    public const SECURITY = ['tls' => 'TLS / STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (port 25)'];

    public static function saved(): ?array
    {
        try {
            return PlatformSetting::find(self::KEY)?->value;
        } catch (\Throwable) {
            return null; // no database yet (install, migrate)
        }
    }

    /** Where tickets and enquiries are emailed: the Support inbox, else the From email set here (else .env). */
    public static function supportAddress(): ?string
    {
        $m = self::current();

        return ($m['support_address'] ?? null) ?: ($m['from_address'] ?? null);
    }

    /** What the form shows: saved values, else the current .env config. Never the password. */
    public static function current(): array
    {
        return (self::saved() ?? []) + [
            'mailer' => config('mail.default') === 'smtp' ? 'smtp' : 'log',
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'security' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
            'username' => config('mail.mailers.smtp.username'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    /** Runs once, right before Laravel builds its first mailer. */
    public static function apply(): void
    {
        $m = self::saved();
        if (! $m) {
            return;
        }
        $password = null;
        if (! empty($m['password'])) {
            try {
                $password = Crypt::decryptString($m['password']);
            } catch (\Throwable) {
                // APP_KEY changed: the admin has to enter the password again
            }
        }
        config([
            'mail.default' => $m['mailer'] === 'smtp' ? 'smtp' : 'log',
            'mail.mailers.smtp.scheme' => ($m['security'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $m['host'] ?? null,
            'mail.mailers.smtp.port' => (int) ($m['port'] ?? 587),
            'mail.mailers.smtp.username' => $m['username'] ?? null,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $m['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $m['from_name'] ?? config('mail.from.name'),
        ]);
    }
}
