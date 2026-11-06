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
