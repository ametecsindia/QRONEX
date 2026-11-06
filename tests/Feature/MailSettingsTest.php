<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_saves_smtp_settings_and_they_override_env(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $mk = fn ($role, $email) => User::create(['name' => $role, 'email' => $email, 'password' => '12345678', 'role' => $role, 'is_active' => true, 'email_verified_at' => now()]);
        $super = $mk('super_admin', 'sa@x.example');
        $support = $mk('support_admin', 'sup@x.example');

        $this->actingAs($super)->get('/super/messaging')->assertOk()->assertSee('Email settings')->assertSee('Save email settings');

        $form = ['mailer' => 'smtp', 'host' => 'smtp.example.com', 'port' => 465, 'security' => 'ssl', 'username' => 'no-reply@example.com',
            'password' => 'app-pass-123', 'from_address' => 'no-reply@example.com', 'from_name' => 'QRONEX'];
        $this->actingAs($super)->from('/super/messaging')->post('/super/messaging/mail', $form)->assertRedirect('/super/messaging')->assertSessionHasNoErrors();

        $saved = PlatformSetting::find('mail')->value;
        $this->assertNotSame('app-pass-123', $saved['password'], 'password is stored encrypted');
        $this->assertSame('app-pass-123', Crypt::decryptString($saved['password']));

        // blank password keeps the saved one
        $this->actingAs($super)->post('/super/messaging/mail', ['password' => ''] + $form)->assertSessionHasNoErrors();
        $this->assertSame('app-pass-123', Crypt::decryptString(PlatformSetting::find('mail')->value['password']));

        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('app-pass-123', config('mail.mailers.smtp.password'));
        $this->assertSame('no-reply@example.com', config('mail.from.address'));

        // the password never appears on the page
        $this->actingAs($super)->get('/super/messaging')->assertDontSee('app-pass-123')->assertSee('Leave blank to keep it');

        // SMTP needs a server
        $this->actingAs($super)->post('/super/messaging/mail', ['host' => ''] + $form)->assertSessionHasErrors('host');

        // support admin can look but not change
        $this->actingAs($support)->get('/super/messaging')->assertOk()->assertDontSee('Save email settings');
        $this->actingAs($support)->post('/super/messaging/mail', $form)->assertForbidden();
    }
}
