<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Gate;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Models\Office;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every screen renders for the roles that can see it, using the demo seed. */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_admin_pages(): void
    {
        $this->actingAs(User::where('email', 'admin@qronex.test')->first());
        $t = Tenant::first();
        foreach (['/super', '/super/companies', "/super/companies/{$t->id}", '/super/plans', '/super/plans/'.Plan::first()->id.'/edit',
            '/super/settings', '/super/payments', '/super/messaging', '/super/messaging/'.MessageTemplate::first()->id.'/edit', '/super/displays', '/super/api', '/super/system', '/super/support', '/super/support/'.\App\Models\SupportTicket::first()->id] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_owner_pages_and_simulated_payment(): void
    {
        $this->actingAs($owner = User::where('role', 'company_owner')->first());
        $office = Office::first();
        $gate = Gate::first();
        foreach (['/stormfinancial', '/stormfinancial/attendance', '/stormfinancial/attendance?tab=rejected', '/stormfinancial/company', '/stormfinancial/policy', '/stormfinancial/offices', '/stormfinancial/offices/create',
            "/stormfinancial/offices/{$office->id}/edit", '/stormfinancial/employees', '/stormfinancial/employees/create', '/stormfinancial/employees/'.Employee::first()->id.'/edit',
            '/stormfinancial/employees/import', '/stormfinancial/devices', '/stormfinancial/users', '/stormfinancial/billing', '/stormfinancial/billing?employees=600', '/stormfinancial/invoices/'.Invoice::first()->id, '/stormfinancial/api', '/stormfinancial/api?tab=webhooks', '/stormfinancial/api?tab=usage', '/stormfinancial/approvals', '/stormfinancial/remote', '/stormfinancial/reports', '/stormfinancial/help', '/account/security'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/stormfinancial/attendance/export.csv')->assertOk();
        $this->get("/display/{$owner->tenant->slug}/{$gate->office->slug}/{$gate->code}?key={$gate->display_secret}")->assertOk()->assertSee('qrcode.min.js', false);

        $this->post('/stormfinancial/employees/'.Employee::first()->id.'/activation')->assertRedirect()->assertSessionHas('ok');
    }

    public function test_scoped_roles_and_guests(): void
    {
        $this->actingAs(User::where('role', 'office_manager')->first());
        $this->get('/stormfinancial')->assertOk();
        $this->get('/stormfinancial/employees')->assertOk();
        $this->get('/stormfinancial/billing')->assertForbidden();
        $this->get('/super')->assertForbidden();

        auth()->logout();
        foreach (['/login', '/register', '/forgot-password', '/reset-password/abc?email=a@b.c'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
