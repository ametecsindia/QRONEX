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
        // ponytail: one rule for every password form — 8+ characters, digits-only allowed (founder's call, 6 Nov 2026)
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(8));

        foreach (array_keys(\App\Support\Permissions::PLATFORM + \App\Support\Permissions::COMPANY) as $ability) {
            Gate::define($ability, fn (User $u) => \App\Support\Permissions::allows($u, $ability));
        }
    }
}
