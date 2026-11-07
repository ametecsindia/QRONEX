<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Employee;
use App\Models\Gate;
use App\Models\Office;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\QrToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Approval binds office + gate; scan scope decides where the employee may punch; >1 phone is an admin-granted exception. */
class GateBindingTest extends TestCase
{
    use RefreshDatabase;

    private Gate $g1;
    private Gate $g2;
    private Gate $other;
    private Employee $e;

    protected function setUp(): void
    {
        parent::setUp();
        $t = Tenant::create(['name' => 'Storm', 'slug' => 'storm', 'status' => 'active', 'state' => 'Telangana']);
        TenantSetting::create(['tenant_id' => $t->id] + TenantSetting::defaults());
        $o = Office::create(['tenant_id' => $t->id, 'code' => 'HYD', 'slug' => 'hyd', 'name' => 'HQ', 'lat' => 17.44862, 'lng' => 78.39081, 'geofence_radius_m' => 150]);
        $o2 = Office::create(['tenant_id' => $t->id, 'code' => 'BLR', 'slug' => 'blr', 'name' => 'Bengaluru', 'lat' => 17.44862, 'lng' => 78.39081, 'geofence_radius_m' => 150]);
        $this->g1 = $o->gates()->create(['tenant_id' => $t->id, 'code' => 'g1', 'name' => 'Main']);
        $this->g2 = $o->gates()->create(['tenant_id' => $t->id, 'code' => 'g2', 'name' => 'Back']);
        $this->other = $o2->gates()->create(['tenant_id' => $t->id, 'code' => 'b1', 'name' => 'BLR gate']);
        $this->e = Employee::create(['tenant_id' => $t->id, 'office_id' => $o->id, 'employee_code' => 'E1', 'name' => 'Asha', 'status' => 'active',
            'activation_code_hash' => Hash::make('ABCD2345'), 'activation_expires_at' => now()->addHour()]);
        User::create(['tenant_id' => $t->id, 'name' => 'Owner', 'email' => 'o@x.test', 'password' => 'x', 'role' => 'company_owner']);
        User::create(['tenant_id' => $t->id, 'name' => 'Approver', 'email' => 'a@x.test', 'password' => 'x', 'role' => 'attendance_approver']);
    }

    private function scanAt(string $token, Gate $g)
    {
        return $this->withToken($token)->postJson('/api/v1/scan', ['qr' => QrToken::make($g, 3)['qr'], 'lat' => 17.4488, 'lng' => 78.3909, 'accuracy' => 12, 'mocked' => false, 'type' => 'IN']);
    }

    public function test_approval_binds_gate_and_scope_controls_scans(): void
    {
        $r = $this->postJson('/api/v1/activate', ['company' => 'storm', 'employee_code' => 'E1', 'activation_code' => 'ABCD2345',
            'platform' => 'android', 'model' => 'Pixel', 'install_ref' => str_repeat('b2', 12)])->assertCreated();
        $token = $r->json('token');
        $d = Device::withoutGlobalScopes()->first();

        $this->actingAs(User::where('email', 'o@x.test')->first());
        $this->post("/storm/devices/{$d->id}/approve", ['code' => $r->json('device.approval_code'), 'office_id' => $this->g1->office_id, 'scan_scope' => 'gate'])
            ->assertSessionHasErrors('gate_id');
        $this->post("/storm/devices/{$d->id}/approve", ['code' => $r->json('device.approval_code'), 'office_id' => $this->g1->office_id, 'gate_id' => $this->other->id, 'scan_scope' => 'gate'])
            ->assertSessionHasErrors('gate_id'); // gate of another office
        $this->post("/storm/devices/{$d->id}/approve", ['code' => $r->json('device.approval_code'), 'office_id' => $this->g1->office_id, 'gate_id' => $this->g1->id, 'scan_scope' => 'gate'])
            ->assertSessionHas('ok');
        $this->assertSame([$this->g1->id, 'gate'], [$this->e->fresh()->gate_id, $this->e->fresh()->scan_scope]);

        $this->scanAt($token, $this->g2)->assertStatus(422)->assertJsonPath('reason_code', 'OFFICE_NOT_ASSIGNED');
        $this->scanAt($token, $this->g1)->assertOk();

        $this->e->update(['scan_scope' => 'office']);
        $this->travel(5)->minutes();
        $this->scanAt($token, $this->other)->assertStatus(422)->assertJsonPath('reason_code', 'OFFICE_NOT_ASSIGNED');
        $this->scanAt($token, $this->g2)->assertOk();

        $this->e->update(['scan_scope' => 'any']);
        $this->travel(5)->minutes();
        $this->scanAt($token, $this->other)->assertOk();
    }

    public function test_one_phone_unless_admin_grants_exception(): void
    {
        Device::create(['tenant_id' => $this->e->tenant_id, 'employee_id' => $this->e->id, 'platform' => 'android', 'install_ref' => 'old-phone-0000000001', 'status' => 'approved', 'approved_at' => now()]);
        $second = Device::create(['tenant_id' => $this->e->tenant_id, 'employee_id' => $this->e->id, 'platform' => 'android', 'install_ref' => 'new-phone-0000000002', 'status' => 'pending']);

        $this->actingAs(User::where('email', 'o@x.test')->first());
        $this->post("/storm/devices/{$second->id}/approve")->assertSessionHasErrors('device');

        // Only users with manage-company (owner/admin) can raise the limit.
        $form = ['name' => 'Asha', 'employee_code' => 'E1', 'office_id' => $this->e->office_id, 'scan_scope' => 'office', 'status' => 'active', 'max_devices' => 2];
        $this->assertNull($this->e->fresh()->max_devices);
        $this->put("/storm/employees/{$this->e->id}", $form)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->e->fresh()->deviceLimit());
        $this->post("/storm/devices/{$second->id}/approve")->assertSessionHas('ok');
    }
}
