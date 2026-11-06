<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class StarterUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_rule_is_eight_characters_and_digits_only_is_allowed(): void
    {
        $this->assertTrue(Validator::make(['p' => '12345678'], ['p' => Password::defaults()])->passes());
        $this->assertTrue(Validator::make(['p' => 'abcdefgh'], ['p' => Password::defaults()])->passes());
        $this->assertFalse(Validator::make(['p' => '1234567'], ['p' => Password::defaults()])->passes());
    }

    public function test_starter_users_are_created_and_rerun_is_safe(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->artisan('qronex:starter-users', ['--password' => '12345678'])->assertSuccessful();
        $this->artisan('qronex:starter-users', ['--password' => '12345678'])->assertSuccessful();

        foreach (['admin@qronex.app' => 'super_admin', 'support@qronex.app' => 'support_admin', 'demo@qronex.app' => 'company_owner', 'demoadmin@qronex.app' => 'company_admin'] as $email => $role) {
            $u = User::where('email', $email)->sole();
            $this->assertSame($role, $u->role);
            $this->assertTrue(Hash::check('12345678', $u->password));
        }
        $this->assertSame(1, \App\Models\Tenant::where('slug', 'demo')->count());
        $this->artisan('qronex:starter-users')->assertFailed();
    }
}
