<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Employee;
use App\Models\Gate;
use App\Models\Office;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\QrToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** End to end: activation code → device token → approve → scan at the gate (sqlite in-memory). */
class MobileAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Gate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $t = Tenant::create(['name' => 'Storm Financial', 'slug' => 'storm', 'status' => 'active', 'state' => 'Telangana']);
        TenantSetting::create(['tenant_id' => $t->id] + TenantSetting::defaults());
        $o = Office::create(['tenant_id' => $t->id, 'code' => 'HYD01', 'slug' => 'hyderabad', 'name' => 'Hyderabad HQ', 'lat' => 17.44862, 'lng' => 78.39081, 'geofence_radius_m' => 150]);
        $this->gate = $o->gates()->create(['tenant_id' => $t->id, 'code' => 'gate1', 'name' => 'Main Entrance']);
        Employee::create(['tenant_id' => $t->id, 'office_id' => $o->id, 'employee_code' => 'EMP0001', 'name' => 'Aarav Sharma', 'status' => 'active',
            'activation_code_hash' => Hash::make('ABCD2345'), 'activation_expires_at' => now()->addHour()]);
    }

    private function activate(string $code = 'abcd-2345')
    {
        return $this->postJson('/api/v1/activate', ['company' => 'storm', 'employee_code' => 'EMP0001', 'activation_code' => $code,
            'platform' => 'android', 'model' => 'Pixel 8', 'os_version' => 'Android 15', 'install_ref' => str_repeat('a1', 12)]);
    }

    private function scan(string $token, array $over = [])
    {
        return $this->withToken($token)->postJson('/api/v1/scan', $over + [
            'qr' => QrToken::make($this->gate, 3)['qr'], 'lat' => 17.44880, 'lng' => 78.39090, 'accuracy' => 12, 'mocked' => false,
        ]);
    }

    public function test_full_flow(): void
    {
        $this->activate('WRONG000')->assertStatus(422);

        $token = $this->activate()->assertCreated()->assertJsonPath('device.status', 'pending')->json('token');
        $this->activate()->assertStatus(422); // code is single-use

        $this->scan($token)->assertStatus(422)->assertJsonPath('reason_code', 'DEVICE_NOT_APPROVED');

        Device::first()->update(['status' => 'approved']);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('next_type', 'IN');

        $this->scan($token)->assertOk()->assertJsonPath('ok', true)->assertJsonPath('event.type', 'IN')->assertJsonCount(11, 'checks');
        $this->scan($token, ['type' => 'IN'])->assertStatus(422)->assertJsonPath('reason_code', 'DUPLICATE');
        $this->withToken($token)->getJson('/api/v1/history')->assertOk()->assertJsonCount(1, 'events');
    }

    public function test_rejections(): void
    {
        $token = $this->activate()->json('token');
        Device::first()->update(['status' => 'approved']);

        $this->scan($token, ['lat' => 17.46502])->assertStatus(422)->assertJsonPath('reason_code', 'OUTSIDE_GEOFENCE');
        $this->scan($token, ['accuracy' => 180])->assertStatus(422)->assertJsonPath('reason_code', 'LOW_GPS_ACCURACY');
        $this->scan($token, ['mocked' => true])->assertStatus(422)->assertJsonPath('reason_code', 'MOCK_LOCATION');
        $old = QrToken::make($this->gate, 3, time() - 60)['qr'];
        $this->scan($token, ['qr' => $old])->assertStatus(422)->assertJsonPath('reason_code', 'QR_EXPIRED');
        $this->scan($token, ['qr' => 'QRX1.'.$this->gate->id.'.3.1.aaaaaaaaaaaaaaaaaaaa'])->assertStatus(422)->assertJsonPath('reason_code', 'QR_INVALID');

        Device::first()->update(['status' => 'revoked']);
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_display_requires_key(): void
    {
        $this->get('/display/storm/hyderabad/gate1?key=nope')->assertForbidden();
        $this->getJson('/display/storm/hyderabad/gate1/token?key='.$this->gate->display_secret)->assertOk()->assertJsonStructure(['qr', 'interval', 'expires_in']);
    }
}
