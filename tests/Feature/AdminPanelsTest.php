<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->first();
    }

    public function test_activation_gives_match_code_and_approval_needs_it(): void
    {
        $t = Tenant::where('slug', 'stormfinancial')->first();
        $e = Employee::withoutGlobalScopes()->where(['tenant_id' => $t->id, 'employee_code' => 'EMP0016'])->first(); // no phone yet
        $e->forceFill(['activation_code_hash' => bcrypt('ABCD1234'), 'activation_expires_at' => now()->addDay()])->save();

        $r = $this->postJson('/api/v1/activate', ['company' => 'stormfinancial', 'employee_code' => 'EMP0016', 'activation_code' => 'ABCD1234',
            'platform' => 'android', 'model' => 'Test Phone', 'os_version' => 'Android 15', 'install_ref' => 'test-install-ref-0016'])->assertCreated();
        $code = $r->json('device.approval_code');
        $this->assertSame('pending', $r->json('device.status'));
        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-TV-Z]{4}-[2-9A-HJKMNP-TV-Z]{4}$/', $code);
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$r->json('token')])->assertJsonPath('device.approval_code', $code);

        $d = Device::withoutGlobalScopes()->where('install_ref', 'test-install-ref-0016')->first();
        $this->actingAs($this->user('anil.mathur@stormfinancial.example'));
        $this->post("/stormfinancial/devices/{$d->id}/approve")->assertSessionHasErrors('code');
        $this->post("/stormfinancial/devices/{$d->id}/approve", ['code' => str_replace('-', '', $code)])->assertSessionHas('ok');
        $this->assertSame('approved', $d->fresh()->status);
        $this->assertNull($d->fresh()->approval_code);

        $this->post("/stormfinancial/devices/{$d->id}/revoke")->assertSessionHasErrors('reason');
        $this->post("/stormfinancial/devices/{$d->id}/revoke", ['reason' => 'Phone lost'])->assertSessionHas('ok');
        $this->assertSame(['registered', 'approved', 'revoked'], DeviceEvent::withoutGlobalScopes()->where('device_id', $d->id)->orderBy('id')->pluck('action')->all());
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$r->json('token')])->assertUnauthorized(); // revoked phone is signed out

        $this->get("/stormfinancial/devices/{$d->id}")->assertOk()->assertSee('Phone lost')->assertSee('Revoked');
    }

    public function test_other_tenant_cannot_open_device(): void
    {
        $d = Device::withoutGlobalScopes()->whereHas('tenant', fn ($q) => $q->where('slug', 'stormfinancial'))->first();
        $this->actingAs($this->user('demo@qronex.test'))->get("/demo/devices/{$d->id}")->assertNotFound();
    }

    public function test_owner_changes_role_permissions(): void
    {
        $approver = $this->user('nandini.r@stormfinancial.example'); // attendance_approver
        $this->actingAs($approver)->get('/stormfinancial/employees')->assertForbidden();
        $this->put('/stormfinancial/roles', ['perm' => []])->assertForbidden(); // can't even reach (manage-users)

        $owner = $this->user('anil.mathur@stormfinancial.example');
        $matrix = Permissions::defaults();
        $perm = [];
        foreach ($matrix as $role => $abilities) {
            foreach ($abilities as $a) {
                $perm[$role][$a] = 1;
            }
        }
        $perm['attendance_approver']['manage-employees'] = 1;
        $this->actingAs($owner)->put('/stormfinancial/roles', ['perm' => $perm])->assertSessionHas('ok');

        $this->actingAs($approver)->get('/stormfinancial/employees')->assertOk();
        $this->actingAs($this->user('rekha.s@stormfinancial.example'))->put('/stormfinancial/roles', ['perm' => []])->assertForbidden(); // admin is not owner
    }

    public function test_dashboards_render_with_analytics(): void
    {
        $this->actingAs($this->user('anil.mathur@stormfinancial.example'))->get('/stormfinancial')
            ->assertOk()->assertSee('Present today')->assertSee('Attendance — last 14 days')->assertSee('Arrival times');
        $this->actingAs($this->user('demo@qronex.test'))->get('/demo')->assertOk()->assertSee('QRONEX Demo Company');
        $this->actingAs($this->user('admin@qronex.test'))->get('/super')
            ->assertOk()->assertSee('Collected · 30 days')->assertSee('Plan mix')->assertSee('Verified punches — last 14 days');
    }

    public function test_late_uses_policy_shift_start(): void
    {
        $t = Tenant::where('slug', 'demo')->first();
        $a = \App\Services\Analytics::company($t->id, null);
        $this->assertSame('09:30', $a['shift']);
        $this->assertCount(14, $a['trend']['labels']);
        $this->assertSame($a['present'], $a['on_time'] + $a['late']);
    }

    public function test_super_admin_manages_platform_staff(): void
    {
        $admin = $this->user('admin@qronex.test');
        $this->actingAs($admin)->get('/super/users')->assertOk()->assertSee('support@qronex.test');
        $this->post('/super/users', ['name' => 'Ops Person', 'email' => 'ops@qronex.test', 'password' => 'Secret12345', 'role' => 'support_admin'])->assertSessionHas('ok');
        $ops = $this->user('ops@qronex.test');
        $this->assertNull($ops->tenant_id);
        $this->put("/super/users/{$ops->id}", ['role' => 'support_admin'])->assertSessionHas('ok'); // unchecked = deactivate
        $this->assertFalse($ops->fresh()->is_active);
        $this->put("/super/users/{$admin->id}", ['role' => 'support_admin', 'is_active' => 1])->assertSessionHasErrors('user'); // not yourself

        $support = $this->user('support@qronex.test');
        $this->actingAs($support)->get('/super/users')->assertOk()->assertDontSee('Add platform admin');
        $this->post('/super/users', ['name' => 'X', 'email' => 'x@qronex.test', 'password' => 'Secret12345', 'role' => 'super_admin'])->assertForbidden();
        $owner = $this->user('anil.mathur@stormfinancial.example');
        $this->actingAs($owner)->get('/super/users')->assertForbidden();
    }

    public function test_audit_log_filters_and_exports(): void
    {
        $this->actingAs($this->user('admin@qronex.test'));
        $this->post('/super/users', ['name' => 'Ops Person', 'email' => 'ops2@qronex.test', 'password' => 'Secret12345', 'role' => 'support_admin']);
        $this->get('/super/audit?action=admin_user')->assertOk()->assertSee('admin_user.created')->assertDontSee('Demo data loaded');
        $this->get('/super/audit?company=platform&q=ops2')->assertOk()->assertSee('ops2@qronex.test');
        $csv = $this->get('/super/audit?format=csv&action=admin_user');
        $csv->assertOk();
        $this->assertStringContainsString('admin_user.created', $csv->streamedContent());
        $this->get('/super/companies?status=active&sort=created_at&dir=desc')->assertOk()->assertSee('On trial');
        $this->get('/super/payments')->assertOk()->assertSee('Collected this FY');
    }

    public function test_super_admin_adds_company_and_every_console_page_renders(): void
    {
        $this->actingAs($this->user('admin@qronex.test'));
        foreach (['/super', '/super/companies', '/super/companies/create', '/super/support', '/super/support?status=closed&area=sales&q=x', '/super/payments', '/super/plans',
            '/super/displays', '/super/displays?state=live', '/super/api', '/super/messaging?channel=sms&status=failed', '/super/website', '/super/system', '/super/settings', '/super/users', '/super/audit'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/super/support')->assertSee('Website enquiries');
        $this->get('/super/displays')->assertSee('Live now');
        $this->get('/super/system')->assertSee('Go-live checks passing');

        $this->post('/super/companies', ['company' => 'Acme Tools', 'slug' => 'admin', 'customer_type' => 'smartprs', 'employees' => 30, 'billing_cycle' => 'monthly',
            'name' => 'Ravi Acme', 'email' => 'ravi@acme.test', 'password' => '12345678'])->assertSessionHasErrors('slug'); // reserved
        $this->post('/super/companies', ['company' => 'Acme Tools', 'slug' => 'acme-tools', 'customer_type' => 'smartprs', 'employees' => 30, 'billing_cycle' => 'monthly',
            'name' => 'Ravi Acme', 'email' => 'ravi@acme.test', 'password' => '12345678'])->assertRedirect()->assertSessionHas('ok');
        $t = Tenant::where('slug', 'acme-tools')->first();
        $this->assertSame(['trial', 'smartprs'], [$t->status, $t->customer_type]);
        $this->assertSame('company_owner', $t->owner->role);
        $this->assertFalse($t->owner->emailPending());
        $this->get("/super/companies/{$t->id}")->assertOk()->assertSee('Acme Tools')->assertSee('Trial ends');
        $this->get('/super/companies/'.Tenant::where('slug', 'demo')->value('id'))->assertOk()->assertSee('Present today')->assertSee('Punches · 30 days');

        $this->post('/logout');
        $this->post('/login', ['email' => 'ravi@acme.test', 'password' => '12345678']);
        $this->assertAuthenticatedAs($t->owner);

        $this->actingAs($this->user('support@qronex.test'))->get('/super/companies/create')->assertForbidden();
        $this->post('/super/companies', [])->assertForbidden();
    }

    public function test_client_admin_pages_render_and_employee_profile_is_scoped(): void
    {
        $this->actingAs($this->user('demo@qronex.test'));
        foreach (['/demo', '/demo/attendance', '/demo/reports', '/demo/approvals', '/demo/devices', '/demo/remote', '/demo/employees', '/demo/employees/create', '/demo/employees/import',
            '/demo/offices', '/demo/users', '/demo/policy', '/demo/api', '/demo/company', '/demo/billing', '/demo/help'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/demo/employees')->assertSee('App not set up')->assertSee('Phones waiting');
        $this->get('/demo/remote')->assertSee('Remote today');
        $this->get('/demo/offices')->assertSee('Screens live now');

        $e = Employee::where('employee_code', 'DEMO001')->first();
        $this->get("/demo/employees/{$e->id}")->assertOk()->assertSee($e->name)->assertSee('Days present · 30 days')->assertSee('Last 30 days');

        $other = Employee::withoutGlobalScopes()->whereHas('tenant', fn ($q) => $q->where('slug', 'stormfinancial'))->first();
        $this->get("/demo/employees/{$other->id}")->assertNotFound();
    }

    public function test_company_and_super_admin_addresses(): void
    {
        $this->get('/demo')->assertRedirect('/login');
        $this->post('/login', ['email' => 'demo@qronex.test', 'password' => '12345678'])->assertRedirect('/demo');
        $this->get('/app/employees?q=x')->assertRedirect('/demo/employees');
        $this->get('/stormfinancial/employees?q=x')->assertRedirect('/demo/employees?q=x'); // another company's address → your own
        $this->post('/stormfinancial/remote', [])->assertNotFound();
        $this->get('/super')->assertForbidden();
        $this->post('/logout');

        $this->post('/login', ['email' => 'support@qronex.test', 'password' => '12345678'])->assertRedirect('/super');
        $this->get('/admin/companies')->assertRedirect('/super/companies');
        $this->get('/demo')->assertForbidden();
        $this->get('/pricing')->assertOk(); // fixed pages are not company addresses
    }
}
