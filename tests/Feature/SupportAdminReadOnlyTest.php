<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAdminReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_admin_sees_website_and_settings_read_only_and_cannot_save(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $support = User::create(['name' => 'Support', 'email' => 'sup@x.example', 'password' => '12345678', 'role' => 'support_admin', 'is_active' => true, 'email_verified_at' => now()]);

        $this->actingAs($support)->get('/super/website')->assertOk()->assertSee('Read only', false)->assertDontSee('<button class="btn">Save</button>', false);
        $this->actingAs($support)->get('/super/settings')->assertOk()->assertSee('disabled', false)->assertDontSee('Save settings');
        $this->actingAs($support)->post('/super/website', ['hero_title' => 'x', 'hero_sub' => 'y', 'faqs' => "q\na"])->assertForbidden();
    }
}
