<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Behind Cloudflare the origin may get plain http; links must still be https when APP_URL is.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // ponytail: one rule for every password form — 8+ characters, digits-only allowed (founder's call, 6 Nov 2026)
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(8));

        // Super Admin's saved email settings win over MAIL_* in .env (applied lazily, only when mail is used)
        $this->app->afterResolving('mail.manager', fn () => \App\Support\MailSettings::apply());

        foreach (array_keys(\App\Support\Permissions::PLATFORM + \App\Support\Permissions::COMPANY) as $ability) {
            Gate::define($ability, fn (User $u) => \App\Support\Permissions::allows($u, $ability));
        }
    }
}
