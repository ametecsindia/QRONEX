<?php

use Illuminate\Support\Facades\Schedule;

// Dev: run `php artisan schedule:work` in a terminal. Production: one cron entry for `php artisan schedule:run` every minute.
Schedule::command('qronex:billing-sweep')->dailyAt('02:00')->timezone('Asia/Kolkata');

// Housekeeping: API request log 90 days, idempotency keys 48 h.
Schedule::call(function () {
    \App\Models\ApiRequest::withoutGlobalScopes()->where('created_at', '<', now()->subDays(90))->delete();
    \App\Models\IdempotencyKey::where('created_at', '<', now()->subHours(48))->delete();
    \App\Models\WebhookDelivery::withoutGlobalScopes()->where('created_at', '<', now()->subDays(30))->delete();
})->dailyAt('03:00')->timezone('Asia/Kolkata')->name('qronex-prune');

Schedule::command('qronex:retention')->dailyAt('03:30')->timezone('Asia/Kolkata');

// Heartbeat read by /health and the go-live checklist.
Schedule::call(fn () => \App\Models\PlatformSetting::put('scheduler_heartbeat', now()->toIso8601String()))->everyFiveMinutes()->name('qronex-heartbeat');

Schedule::command('qronex:daily-report')->dailyAt('07:00')->timezone('Asia/Kolkata');

// Starter logins for a fresh server. The password is passed on the command line, never stored in the repo:
//   php artisan qronex:starter-users --password=…
// Re-running resets these accounts to that password.
\Illuminate\Support\Facades\Artisan::command('qronex:starter-users {--password=}', function () {
    $pw = (string) $this->option('password');
    if (strlen($pw) < 8) {
        $this->error('Pass --password= with at least 8 characters.');

        return 1;
    }
    $put = fn (array $where, array $attrs) => \App\Models\User::updateOrCreate($where, $attrs + ['password' => $pw, 'is_active' => true, 'email_verified_at' => now()]);

    $put(['email' => 'admin@qronex.app'], ['name' => 'QRONEX Super Admin', 'role' => 'super_admin', 'tenant_id' => null]);
    $put(['email' => 'support@qronex.app'], ['name' => 'QRONEX Support', 'role' => 'support_admin', 'tenant_id' => null]);

    $tenant = \App\Models\Tenant::where('slug', 'demo')->first();
    if (! $tenant) {
        \App\Models\Tenant::provision(['company' => 'QRONEX Demo Company', 'slug' => 'demo', 'state' => 'Telangana', 'city' => 'Hyderabad',
            'employees' => 10, 'billing_cycle' => 'monthly', 'name' => 'Demo Owner', 'email' => 'demo@qronex.app', 'password' => $pw],
            \App\Models\Plan::forEmployees(10));
        $tenant = \App\Models\Tenant::where('slug', 'demo')->first();
    }
    $put(['email' => 'demo@qronex.app'], ['name' => 'Demo Owner', 'role' => 'company_owner', 'tenant_id' => $tenant->id]);
    $put(['email' => 'demoadmin@qronex.app'], ['name' => 'Demo Admin', 'role' => 'company_admin', 'tenant_id' => $tenant->id]);

    $this->table(['Role', 'Sign in at', 'Email'], [
        ['Super Admin', '/super', 'admin@qronex.app'],
        ['Support Admin', '/super', 'support@qronex.app'],
        ['Company Owner', '/demo', 'demo@qronex.app'],
        ['Company Admin', '/demo', 'demoadmin@qronex.app'],
    ]);
})->purpose('Create or reset the starter super admin, support and company admin logins');
