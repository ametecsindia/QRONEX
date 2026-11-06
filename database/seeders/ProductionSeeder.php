<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Production: reference data + ONE super admin. No demo companies, no shared passwords.
 *   QRONEX_ADMIN_EMAIL=you@ametecsindia.com php artisan db:seed --class=ProductionSeeder --force
 * Prints a one-time random password; sign in, change it, and set up two-factor sign-in (required for admins).
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformSeeder::class);

        $email = env('QRONEX_ADMIN_EMAIL');
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command?->warn('QRONEX_ADMIN_EMAIL not set — no admin created. Set it and run again.');

            return;
        }
        if (User::where('email', $email)->exists()) {
            $this->command?->info("Admin $email already exists — unchanged.");

            return;
        }
        $password = Str::password(20);
        User::create(['name' => 'QRONEX Super Admin', 'email' => $email, 'password' => $password, 'role' => 'super_admin']);
        $this->command?->warn("Super admin created: $email  /  one-time password: $password");
        $this->command?->warn('Sign in, change it under Account & security, and turn on two-factor sign-in.');
    }
}
