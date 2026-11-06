<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Go-live checklist. Used by `php artisan qronex:preflight` and Super Admin → System. */
class Preflight
{
    /** @return list<array{area:string, check:string, status:'ok'|'warn'|'fail', detail:string}> */
    public static function run(): array
    {
        $prod = app()->isProduction();
        $r = [];
        $add = function (string $area, string $check, bool $ok, string $detail, bool $failInProd = true) use (&$r, $prod) {
            $r[] = ['area' => $area, 'check' => $check, 'status' => $ok ? 'ok' : ($prod && $failInProd ? 'fail' : 'warn'), 'detail' => $detail];
        };

        // App
        $add('App', 'Environment is production', $prod, 'APP_ENV='.app()->environment(), false);
        $add('App', 'Debug mode off', ! config('app.debug'), 'APP_DEBUG must be false in production (it leaks secrets in error pages)');
        $add('App', 'App key set', filled(config('app.key')), 'php artisan key:generate');
        $add('App', 'HTTPS URL', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL='.config('app.url'));
        $add('App', 'Secure session cookie', (bool) config('session.secure') || ! str_starts_with((string) config('app.url'), 'https://'), 'SESSION_SECURE_COOKIE=true');
        $add('App', 'Config cached', app()->configurationIsCached(), 'php artisan config:cache (after every .env change)', false);

        // Database
        try {
            DB::select('select 1');
            $driver = DB::getDriverName();
            $add('Database', 'Connection works', true, $driver);
            $add('Database', 'Not SQLite', $driver !== 'sqlite', 'Use MySQL 8 in production');
            $add('Database', 'Not the MySQL root user', config('database.connections.mysql.username') !== 'root' || $driver !== 'mysql', 'Create a dedicated user with access to the qronex database only', false);
            $m = app('migrator');
            $pending = $m->repositoryExists() ? array_diff(array_keys($m->getMigrationFiles(database_path('migrations'))), $m->getRepository()->getRan()) : ['(migrations table missing)'];
            $add('Database', 'Migrations up to date', ! $pending, $pending ? 'Pending: '.implode(', ', $pending) : 'All migrations ran');
            $add('Security', 'No demo accounts', ! User::where('email', 'like', '%@qronex.test')->orWhere('email', 'like', '%.example')->exists(), 'Demo users exist — production must be seeded with ProductionSeeder only');
            $weak = User::whereIn('role', ['super_admin', 'support_admin'])->whereNull('two_factor_confirmed_at')->count();
            $add('Security', 'Admins use two-factor sign-in', $weak === 0, $weak ? "$weak platform admin(s) without 2FA" : 'All platform admins have 2FA');
            $beat = PlatformSetting::get('scheduler_heartbeat');
            $age = $beat ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($beat)) : null;
            $add('Workers', 'Scheduler running', $age !== null && $age < 600, $age === null ? 'Never ran — add the cron entry (see deploy/DEPLOY.md)' : "Last run {$age}s ago");
            $oldest = DB::table('jobs')->min('created_at');
            $lag = $oldest ? time() - (int) $oldest : 0;
            $add('Workers', 'Queue worker keeping up', $lag < 300, $oldest ? "Oldest queued job waiting {$lag}s — is `queue:work` running?" : 'Queue empty');
            $failed = DB::table('failed_jobs')->count();
            $add('Workers', 'No failed jobs', $failed === 0, "$failed failed job(s) — see Super Admin → System", false);
        } catch (Throwable $e) {
            $add('Database', 'Connection works', false, $e->getMessage());
        }
        $add('Workers', 'Queue is asynchronous', config('queue.default') !== 'sync', 'QUEUE_CONNECTION=database (or redis)');

        // Integrations
        $add('Email', 'Real mailer', ! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER='.config('mail.default'));
        $add('Email', 'From address set', ! str_contains((string) config('mail.from.address'), 'example.com'), 'MAIL_FROM_ADDRESS='.config('mail.from.address'));
        $add('Payments', 'Razorpay keys', Razorpay::configured(), 'RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET');
        $add('Payments', 'Razorpay live mode', Razorpay::mode() === 'Live', 'Mode: '.Razorpay::mode(), false);
        $add('Payments', 'Razorpay webhook secret', filled(config('qronex.razorpay.webhook_secret')), 'RAZORPAY_WEBHOOK_SECRET; webhook URL '.url('/api/razorpay/webhook'));
        $add('SMS', 'DLT gateway', config('qronex.sms.driver') === 'http' && filled(config('qronex.sms.url')), 'SMS_DRIVER=http + SMS_URL / SMS_API_KEY / SMS_DLT_PE_ID', false);
        $add('Invoices', 'Supplier GSTIN', ! str_contains((string) config('qronex.supplier.gstin'), 'X'), 'QRONEX_SUPPLIER_GSTIN='.config('qronex.supplier.gstin'));

        // Files
        $add('Files', 'Logo uploaded', is_file(public_path('images/qronex-logo.png')), 'public/images/qronex-logo.png (white background)', false);
        $add('Files', 'Storage link', is_dir(public_path('storage')), 'php artisan storage:link');
        $add('Files', 'Compiled CSS', is_file(public_path('css/qronex.css')), 'public/css/qronex.css');

        return $r;
    }
}
