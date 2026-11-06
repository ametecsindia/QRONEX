<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AttendanceEvent;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $t;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->t = Tenant::where('slug', 'stormfinancial')->first(); // Growth plan → API included
    }

    private function key(array $scopes = ['offices:read', 'employees:read', 'employees:write', 'attendance:read', 'attendance:export', 'webhooks:manage']): string
    {
        return ApiKey::issue($this->t->id, 'test', $scopes)[1];
    }

    public function test_auth_scopes_and_plan_gating(): void
    {
        $this->getJson('/api/v1/offices')->assertUnauthorized()->assertJsonPath('error.code', 'invalid_key');
        $this->withToken('qrx_live_nope')->getJson('/api/v1/offices')->assertUnauthorized();

        $readOnly = $this->key(['offices:read']);
        $this->withToken($readOnly)->getJson('/api/v1/offices')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.gates.0.gate_code', 'gate1');
        $this->withToken($readOnly)->getJson('/api/v1/employees')->assertForbidden()->assertJsonPath('error.code', 'insufficient_scope');

        $this->t->update(['api_enabled' => false]);
        $this->withToken($readOnly)->getJson('/api/v1/offices')->assertForbidden()->assertJsonPath('error.code', 'api_not_in_plan');

        $this->t->update(['api_enabled' => null]);
        ApiKey::withoutGlobalScopes()->update(['revoked_at' => now()]);
        $this->withToken($readOnly)->getJson('/api/v1/offices')->assertUnauthorized();
    }

    public function test_rate_limit(): void
    {
        $k = $this->key(['offices:read']);
        ApiKey::withoutGlobalScopes()->latest('id')->first()->update(['rate_limit_per_min' => 2]);
        $this->withToken($k)->getJson('/api/v1/offices')->assertOk()->assertHeader('X-RateLimit-Limit', '2');
        $this->withToken($k)->getJson('/api/v1/offices')->assertOk();
        $this->withToken($k)->getJson('/api/v1/offices')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_employees_crud_idempotency_and_cursor(): void
    {
        $k = $this->key();
        $body = ['employee_code' => 'EMP0101', 'name' => 'Ravi Kumar', 'office_code' => 'hyd01', 'hrms_ref' => 'HR-9001'];

        $first = $this->withToken($k)->withHeader('Idempotency-Key', 'abc')->postJson('/api/v1/employees', $body)->assertCreated()->assertJsonPath('data.office_code', 'HYD01');
        $this->withToken($k)->withHeader('Idempotency-Key', 'abc')->postJson('/api/v1/employees', $body)->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJson($first->json());
        $this->withToken($k)->withHeader('Idempotency-Key', 'abc')->postJson('/api/v1/employees', ['name' => 'x'] + $body)->assertStatus(422)->assertJsonPath('error.code', 'idempotency_conflict');
        $this->flushHeaders(); // test client keeps headers between requests
        $this->withToken($k)->postJson('/api/v1/employees', $body)->assertStatus(422); // duplicate code

        $this->withToken($k)->patchJson('/api/v1/employees/EMP0101', ['office_code' => 'BLR01'])->assertOk()->assertJsonPath('data.office_code', 'BLR01');
        $this->withToken($k)->getJson('/api/v1/employees/NOPE')->assertNotFound()->assertJsonPath('error.code', 'not_found');

        $page1 = $this->withToken($k)->getJson('/api/v1/employees?limit=10')->assertOk()->assertJsonCount(10, 'data');
        $page2 = $this->withToken($k)->getJson('/api/v1/employees?limit=10&cursor='.$page1->json('next_cursor'))->assertOk();
        $this->assertNotSame($page1->json('data.0.employee_code'), $page2->json('data.0.employee_code'));
    }

    public function test_events_and_export_are_tenant_scoped(): void
    {
        $k = $this->key();
        $e = AttendanceEvent::withoutGlobalScopes()->oldest('recorded_at')->first();
        $day = $e->recorded_at->timezone('Asia/Kolkata')->toDateString();

        $r = $this->withToken($k)->getJson("/api/v1/attendance/events?from=$day&type=IN&limit=500")->assertOk();
        $this->assertNotEmpty($r->json('data'));
        $this->assertSame(['IN'], array_values(array_unique(array_column($r->json('data'), 'type'))));
        $this->withToken($k)->getJson("/api/v1/attendance/events/{$e->ref}")->assertOk()->assertJsonPath('data.id', $e->ref);
        $this->withToken($k)->get("/api/v1/attendance/export?from=$day")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->withToken($k)->getJson('/api/v1/attendance/events?from=2026-01-01&to=2026-12-31')->assertStatus(422);

        // Another company's key sees nothing of Storm's
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'api_enabled' => true]);
        $ok = ApiKey::issue($other->id, 'o', ['attendance:read'])[1];
        $this->withToken($ok)->getJson("/api/v1/attendance/events/{$e->ref}")->assertNotFound();
    }

    public function test_deactivate_revokes_devices_and_sends_signed_webhook(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);
        $k = $this->key();
        $hook = $this->withToken($k)->postJson('/api/v1/webhooks', ['url' => 'https://hooks.example.com/q', 'events' => ['employee.deactivated']])->assertCreated();
        $secret = $hook->json('data.secret');

        $emp = Employee::withoutGlobalScopes()->where('employee_code', 'EMP0001')->first();
        $this->withToken($k)->postJson('/api/v1/employees/EMP0001/deactivate')->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->assertSame(0, Device::withoutGlobalScopes()->where('employee_id', $emp->id)->where('status', '!=', 'revoked')->count());

        Http::assertSent(function (HttpRequest $req) use ($secret) {
            [$t, $v1] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $req->header('X-Qronex-Signature')[0]));

            return $req['event'] === 'employee.deactivated' && $req['data']['employee_code'] === 'EMP0001'
                && hash_equals(hash_hmac('sha256', $t.'.'.$req->body(), $secret), $v1);
        });
        $this->assertSame('delivered', WebhookDelivery::withoutGlobalScopes()->first()->status);
        $this->withToken($k)->getJson('/api/v1/webhooks')->assertOk()->assertJsonMissingPath('data.0.secret');
    }

    public function test_failed_webhook_does_not_break_the_action_and_signature_helper(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $w = WebhookEndpoint::create(['tenant_id' => $this->t->id, 'url' => 'https://down.example.com', 'secret' => 's', 'events' => ['employee.deactivated']]);
        Employee::withoutGlobalScopes()->where('employee_code', 'EMP0002')->first()->deactivate();
        $this->assertSame(503, WebhookDelivery::withoutGlobalScopes()->where('webhook_endpoint_id', $w->id)->first()->response_code);

        $this->assertSame('t=1,v1='.hash_hmac('sha256', '1.{}', 'k'), Webhooks::sign('k', 1, '{}'));
    }

    public function test_openapi_and_docs(): void
    {
        $this->getJson('/api/v1/openapi.json')->assertOk()->assertJsonPath('openapi', '3.1.0')->assertJsonStructure(['paths' => ['/attendance/events', '/employees/{code}'], 'webhooks' => ['attendance.created']]);
        $this->get('/docs/api')->assertOk()->assertSee('swagger-ui', false);
    }
}
