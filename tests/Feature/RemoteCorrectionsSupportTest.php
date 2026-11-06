<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AttendanceEvent;
use App\Models\Device;
use App\Models\Employee;
use App\Models\PunchCorrection;
use App\Models\RemotePermission;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemoteCorrectionsSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** Give an employee's approved device a known bearer token. */
    private function token(string $code): string
    {
        $e = Employee::withoutGlobalScopes()->where('employee_code', $code)->first();
        Device::withoutGlobalScopes()->where('employee_id', $e->id)->where('status', 'approved')->first()->forceFill(['token_hash' => hash('sha256', "tok-$code")])->save();

        return "tok-$code";
    }

    private function owner(): User
    {
        return User::where('role', 'company_owner')->first();
    }

    public function test_remote_request_approval_and_remote_scan(): void
    {
        $tok = $this->token('EMP0002');
        $this->withToken($tok)->postJson('/api/v1/remote-scan', ['lat' => 17.4, 'lng' => 78.4, 'accuracy' => 10])
            ->assertStatus(422)->assertJsonPath('reason_code', 'NO_REMOTE_PERMISSION');

        $today = today('Asia/Kolkata')->toDateString();
        $ref = $this->withToken($tok)->postJson('/api/v1/remote-requests', ['type' => 'wfh', 'from' => $today, 'to' => $today, 'reason' => 'Plumber visit', 'lat' => 17.43, 'lng' => 78.41])
            ->assertCreated()->assertJsonPath('request.status', 'pending')->json('request.id');
        $this->withToken($tok)->getJson('/api/v1/me')->assertJsonPath('remote_permission', null);

        $p = RemotePermission::withoutGlobalScopes()->where('ref', $ref)->first();
        $this->actingAs($this->owner())->get('/stormfinancial/approvals')->assertOk()->assertSee($ref);
        $this->post("/stormfinancial/approvals/remote/{$p->id}", ['decision' => 'approve'])->assertSessionHas('ok');
        auth()->logout();

        $this->withToken($tok)->getJson('/api/v1/me')->assertJsonPath('remote_permission.id', $ref);
        $this->withToken($tok)->postJson('/api/v1/remote-scan', ['lat' => 17.46, 'lng' => 78.41, 'accuracy' => 10])
            ->assertStatus(422)->assertJsonPath('reason_code', 'OUTSIDE_APPROVED_LOCATION');
        $this->withToken($tok)->postJson('/api/v1/remote-scan', ['lat' => 17.4301, 'lng' => 78.4101, 'accuracy' => 10, 'type' => 'IN'])
            ->assertOk()->assertJsonPath('event.mode', 'WFH_APPROVED');
        $this->withToken($tok)->getJson('/api/v1/requests')->assertOk()->assertJsonPath('remote.0.status', 'approved');
    }

    public function test_waived_field_duty_scan_and_portal_grant(): void
    {
        $tok = $this->token('EMP0015'); // RP-1002: field duty, location waived, covers today
        $this->withToken($tok)->postJson('/api/v1/remote-scan', ['lat' => 16.3, 'lng' => 80.4, 'accuracy' => 500])->assertOk()->assertJsonPath('event.mode', 'FIELD_APPROVED');

        $emp = Employee::withoutGlobalScopes()->where('employee_code', 'EMP0006')->first();
        $this->actingAs($this->owner())->post('/stormfinancial/remote', ['employee_id' => $emp->id, 'type' => 'client_site', 'from_date' => today()->toDateString(), 'to_date' => today()->toDateString(), 'reason' => 'x'])
            ->assertSessionHasErrors('lat'); // must give a location or waive
        $this->post('/stormfinancial/remote', ['employee_id' => $emp->id, 'type' => 'client_site', 'from_date' => today()->toDateString(), 'to_date' => today()->toDateString(), 'reason' => 'Audit', 'location_waived' => '1', 'lat' => 1])
            ->assertSessionHas('ok');
        $p = RemotePermission::withoutGlobalScopes()->latest('id')->first();
        $this->assertTrue($p->location_waived);
        $this->assertNull($p->lat);
        $this->assertSame('approved', $p->status);
    }

    public function test_correction_creates_adjusted_event_and_webhook(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $t = Tenant::where('slug', 'stormfinancial')->first();
        WebhookEndpoint::create(['tenant_id' => $t->id, 'url' => 'https://h.example.com', 'secret' => 's', 'events' => ['attendance.adjusted']]);

        $tok = $this->token('EMP0005');
        $this->withToken($tok)->postJson('/api/v1/corrections', ['type' => 'OUT', 'date' => now('Asia/Kolkata')->addDay()->toDateString(), 'time' => '18:30', 'reason' => 'x'])->assertStatus(422);
        $this->withToken($tok)->postJson('/api/v1/corrections', ['type' => 'OUT', 'date' => now('Asia/Kolkata')->subDay()->toDateString(), 'time' => '18:30', 'reason' => 'Forgot'])->assertCreated();

        $c = PunchCorrection::withoutGlobalScopes()->latest('id')->first();
        $before = AttendanceEvent::withoutGlobalScopes()->count();
        $this->actingAs($this->owner())->post("/stormfinancial/approvals/corrections/{$c->id}", ['decision' => 'approve'])->assertSessionHas('ok');
        $this->assertSame($before + 1, AttendanceEvent::withoutGlobalScopes()->count());
        $e = AttendanceEvent::withoutGlobalScopes()->latest('id')->first();
        $this->assertTrue($e->adjusted);
        $this->assertSame('MANUAL_EXCEPTION', $e->mode);
        $this->assertSame('18:30', $e->recorded_at->timezone('Asia/Kolkata')->format('H:i'));
        Http::assertSent(fn ($r) => $r['event'] === 'attendance.adjusted' && $r['data']['adjusted'] === true);
        $this->post("/stormfinancial/approvals/corrections/{$c->id}", ['decision' => 'approve'])->assertSessionHasErrors('correction'); // not twice
    }

    public function test_replacement_phone_revokes_old_one(): void
    {
        $e = Employee::withoutGlobalScopes()->where('employee_code', 'EMP0003')->first();
        $new = Device::withoutGlobalScopes()->where('employee_id', $e->id)->where('status', 'pending')->first();
        $this->actingAs($this->owner())->post("/stormfinancial/devices/{$new->id}/approve", ['code' => 'WRON-GCOD'])->assertSessionHasErrors('code');
        $this->post("/stormfinancial/devices/{$new->id}/approve", ['code' => strtolower($new->approval_code)])->assertSessionHas('ok');
        $this->assertSame(['replaced'], \App\Models\DeviceEvent::withoutGlobalScopes()->where('device_id', $new->replaces_device_id)->latest('id')->limit(1)->pluck('action')->all());
        $this->assertSame('revoked', Device::withoutGlobalScopes()->find($new->replaces_device_id)->status);
        $this->assertSame('approved', $new->fresh()->status);
    }

    public function test_reports_and_daily_email(): void
    {
        $this->actingAs($this->owner());
        foreach (['register', 'monthly', 'remote', 'adjustments'] as $r) {
            $this->get("/stormfinancial/reports/$r.csv")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }
        $csv = $this->get('/stormfinancial/reports/monthly.csv')->streamedContent();
        $this->assertStringContainsString('first_in', $csv);
        $this->assertStringContainsString('EMP0001', $csv);

        \App\Models\TenantSetting::withoutGlobalScopes()->update(['daily_report_email' => 'hr@storm.example']);
        $this->artisan('qronex:daily-report')->assertSuccessful();
        $this->assertDatabaseHas('message_logs', ['template_key' => 'daily_report', 'to' => 'hr@storm.example', 'status' => 'sent']);
    }

    public function test_support_tickets_and_public_website(): void
    {
        foreach (['/', '/pricing?employees=40', '/pricing?employees=900', '/contact', '/legal/privacy'] as $u) {
            $this->get($u)->assertOk();
        }
        $this->get('/pricing?employees=40')->assertSee('₹8,920'); // same maths as the prototype
        $this->post('/contact', ['name' => 'Ravi', 'email' => 'r@x.example', 'message' => 'Demo please', 'employees' => 300])->assertSessionHas('ok');
        $this->post('/contact', ['name' => 'Bot', 'email' => 'b@x.example', 'message' => 'spam', 'website' => 'http://spam'])->assertSessionHas('ok');
        $this->assertSame(1, SupportTicket::where('email', 'r@x.example')->count());
        $this->assertSame(0, SupportTicket::where('email', 'b@x.example')->count());

        $this->actingAs($this->owner())->post('/stormfinancial/help', ['subject' => 'Help', 'area' => 'app', 'priority' => 'high', 'body' => 'App crashes'])->assertRedirect();
        $t = SupportTicket::where('subject', 'Help')->first();
        $this->actingAs(User::where('email', 'admin@qronex.test')->first())->post("/super/support/{$t->id}", ['body' => 'Fixed in 0.2', 'close' => '1'])->assertSessionHas('ok');
        $this->assertSame('closed', $t->fresh()->status);
        $this->actingAs($this->owner())->get("/stormfinancial/help/{$t->id}")->assertOk()->assertSee('Fixed in 0.2');
    }

    public function test_api_grant_remote_and_email_verification(): void
    {
        $t = Tenant::where('slug', 'stormfinancial')->first();
        $key = ApiKey::issue($t->id, 'hrms', ['remote:write'])[1];
        $this->withToken($key)->postJson('/api/v1/remote-permissions', ['employee_code' => 'EMP0001', 'type' => 'field_duty', 'from' => today()->toDateString(), 'to' => today()->toDateString(), 'reason' => 'Visit', 'location_waived' => true])
            ->assertCreated()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.location_waived', true);

        $u = $this->owner();
        $u->forceFill(['email_code_hash' => Hash::make('123456'), 'email_code_expires_at' => now()->addMinutes(30), 'email_verified_at' => null])->save();
        $this->actingAs($u)->post('/stormfinancial/billing/checkout', ['employees' => 24, 'cycle' => 'monthly'])->assertRedirect('/account/verify-email');
        $this->post('/account/verify-email', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/account/verify-email', ['code' => '123456'])->assertRedirect('/stormfinancial');
        $this->assertFalse($u->fresh()->emailPending());
    }

    public function test_excel_export_and_website_editor(): void
    {
        $this->actingAs($this->owner());
        $x = $this->get('/stormfinancial/reports/monthly.csv?format=xlsx')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $x->getContent()); // zip container
        $this->get('/stormfinancial/attendance/export.csv?format=xlsx')->assertOk();

        $this->actingAs(User::where('email', 'admin@qronex.test')->first());
        $this->get('/super/website')->assertOk()->assertSee('Do we need biometric hardware?');
        $this->post('/super/website', ['hero_title' => 'Punch in with your phone.', 'hero_sub' => 'Sub', 'announcement' => 'Maintenance at 23:00',
            'faqs' => "Is it free?\nFor 14 days.\n\nDoes it need Wi-Fi?\nNo."])->assertSessionHas('ok');
        auth()->logout();
        $this->get('/')->assertSee('Punch in with your phone.')->assertSee('Maintenance at 23:00')->assertSee('Does it need Wi-Fi?')->assertDontSee('Do we need biometric hardware?');
    }
}
