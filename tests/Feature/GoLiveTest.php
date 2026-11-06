<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\PlatformSetting;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Totp;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_sign_in_and_recovery_code(): void
    {
        $this->seed();
        $u = User::where('email', 'admin@qronex.test')->first();
        $u->forceFill(['two_factor_secret' => $secret = Totp::secret(), 'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [hash('sha256', 'AAAAABBBBB')]])->save();

        $this->post('/login', ['email' => $u->email, 'password' => '12345678'])->assertRedirect('/two-factor');
        $this->assertGuest();
        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/two-factor', ['code' => Totp::code($secret)])->assertRedirect('/super');
        $this->assertAuthenticatedAs($u);

        auth()->logout();
        $this->post('/login', ['email' => $u->email, 'password' => '12345678']);
        $this->post('/two-factor', ['code' => 'aaaaa-bbbbb'])->assertRedirect('/super');
        $this->assertSame([], $u->fresh()->two_factor_recovery_codes, 'recovery code is single-use');
    }

    public function test_admins_are_forced_to_set_up_2fa_when_required(): void
    {
        $this->seed();
        config(['qronex.require_admin_2fa' => true]);
        $this->actingAs(User::where('email', 'admin@qronex.test')->first())->get('/super')->assertRedirect('/account/security');
        $this->get('/account/security')->assertOk()->assertSee('Required for platform administrators');
        $this->post('/account/2fa/enable')->assertRedirect();
        $this->get('/account/security')->assertSee('6-digit code')->assertSee('otpauth:', false);
        $secret = auth()->user()->fresh()->two_factor_secret;
        $this->from('/account/security')->post('/account/2fa/confirm', ['code' => Totp::code($secret)])->assertSessionHas('recovery');
        $this->get('/super')->assertOk();
        $this->post('/account/2fa/disable', ['current_password' => '12345678'])->assertForbidden();
    }

    public function test_security_headers_health_and_password_change(): void
    {
        $this->seed();
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Content-Security-Policy');

        $this->getJson('/health')->assertStatus(503)->assertJsonPath('checks.scheduler', 'stale');
        PlatformSetting::put('scheduler_heartbeat', now()->toIso8601String());
        $this->getJson('/health')->assertOk()->assertJsonPath('status', 'ok');

        $this->actingAs(User::where('role', 'company_owner')->first());
        $this->post('/account/password', ['current_password' => 'wrong', 'password' => 'NewPass1234X', 'password_confirmation' => 'NewPass1234X'])->assertSessionHasErrors('current_password');
        $this->post('/account/password', ['current_password' => '12345678', 'password' => 'NewPass1234X', 'password_confirmation' => 'NewPass1234X'])->assertSessionHas('ok');
    }

    public function test_production_seeding_is_safe(): void
    {
        $this->app['env'] = 'production';
        $this->expectExceptionMessage('ProductionSeeder');
        try {
            (new DatabaseSeeder)->setContainer($this->app)->run();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_production_seeder_creates_one_admin_and_reference_data(): void
    {
        putenv('QRONEX_ADMIN_EMAIL=owner@ametecs.test');
        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class); // re-run is harmless
        putenv('QRONEX_ADMIN_EMAIL');

        $this->assertSame(1, User::count());
        $this->assertSame('super_admin', User::first()->role);
        $this->assertSame(4, \App\Models\Plan::count());
        $this->assertSame(25, \App\Models\MessageTemplate::count());
    }

    public function test_retention_and_preflight_commands(): void
    {
        $this->seed();
        TenantSetting::withoutGlobalScopes()->update(['retention_months' => 6]);
        $e = AttendanceEvent::withoutGlobalScopes()->first();
        $e->forceFill(['recorded_at' => now()->subMonths(7)])->save();
        $before = AttendanceEvent::withoutGlobalScopes()->count();

        $this->artisan('qronex:retention')->assertSuccessful();
        $this->assertSame($before - 1, AttendanceEvent::withoutGlobalScopes()->count());

        $this->artisan('qronex:preflight')->assertSuccessful(); // outside production, problems are warnings
        $this->actingAs(User::where('email', 'admin@qronex.test')->first())->get('/super/system')->assertOk()->assertSee('Go-live checklist');
    }
}
