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
        foreach (array_keys(\App\Support\Permissions::PLATFORM + \App\Support\Permissions::COMPANY) as $ability) {
            Gate::define($ability, fn (User $u) => \App\Support\Permissions::allows($u, $ability));
        }
    }
}
