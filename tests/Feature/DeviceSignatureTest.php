<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Employee;
use App\Support\DeviceSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The phone holds an EC P-256 key (Android Keystore); every API call is signed. Same scheme as ROVEXY. */
class DeviceSignatureTest extends TestCase
{
    use RefreshDatabase;

    private $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    }

    private function spkiB64(): string
    {
        return preg_replace('/-----[^-]+-----|\s+/', '', openssl_pkey_get_details($this->key)['key']);
    }

    private function signed(string $method, string $uri, string $token, array $body = [], ?int $ts = null, ?string $nonce = null): \Illuminate\Testing\TestResponse
    {
        $json = $body ? json_encode($body) : '';
        $ts = (string) ($ts ?? time());
        $nonce ??= bin2hex(random_bytes(12));
        openssl_sign(DeviceSignature::canonical($method, $uri, $ts, $nonce, $json), $sig, $this->key, OPENSSL_ALGO_SHA256);
        $server = ['HTTP_AUTHORIZATION' => "Bearer $token", 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TIMESTAMP' => $ts, 'HTTP_X_NONCE' => $nonce, 'HTTP_X_SIGNATURE' => base64_encode($sig)];

        return $this->call($method, $uri, [], [], [], $server, $json);
    }

    private function activate(array $extra = []): string
    {
        return $this->postJson('/api/v1/activate', ['company' => 'demo', 'employee_code' => 'DEMO001', 'activation_code' => '12345678', 'platform' => 'android',
            'model' => 'Keystore Phone', 'os_version' => 'Android 15', 'install_ref' => 'keystore-install-0001'] + $extra)->assertCreated()->json('token');
    }

    public function test_signed_phone_works_and_stolen_token_does_not(): void
    {
        $token = $this->activate(['public_key' => $this->spkiB64()]);
        $this->assertNotNull(Device::withoutGlobalScopes()->where('install_ref', 'keystore-install-0001')->value('public_key'));

        $this->signed('GET', '/api/v1/me', $token)->assertOk()->assertJsonPath('employee.code', 'DEMO001');
        $this->signed('GET', '/api/v1/history?days=7', $token)->assertOk();

        // token alone (copied to another phone) is refused
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])->assertUnauthorized()->assertJsonPath('reason_code', 'signature_missing');

        // a different key cannot sign for this phone
        $real = $this->key;
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->signed('GET', '/api/v1/me', $token)->assertUnauthorized()->assertJsonPath('reason_code', 'signature_invalid');
        $this->key = $real;

        // replay + wrong clock
        $this->signed('GET', '/api/v1/me', $token, [], null, 'fixed-nonce-0123456789')->assertOk();
        $this->signed('GET', '/api/v1/me', $token, [], null, 'fixed-nonce-0123456789')->assertStatus(409)->assertJsonPath('reason_code', 'signature_replayed');
        $this->signed('GET', '/api/v1/me', $token, [], time() - 3600)->assertStatus(409)->assertJsonPath('reason_code', 'signature_stale');

        // a signed body can't be altered in transit
        $nonce = bin2hex(random_bytes(12));
        $ts = (string) time();
        openssl_sign(DeviceSignature::canonical('POST', '/api/v1/corrections', $ts, $nonce, '{"type":"IN"}'), $sig, $this->key, OPENSSL_ALGO_SHA256);
        $this->call('POST', '/api/v1/corrections', [], [], [], ['HTTP_AUTHORIZATION' => "Bearer $token", 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TIMESTAMP' => $ts, 'HTTP_X_NONCE' => $nonce, 'HTTP_X_SIGNATURE' => base64_encode($sig)], '{"type":"OUT"}')->assertUnauthorized();
    }

    public function test_invalid_key_is_rejected_and_old_app_still_works(): void
    {
        $this->postJson('/api/v1/activate', ['company' => 'demo', 'employee_code' => 'DEMO002', 'activation_code' => '12345678', 'platform' => 'android',
            'install_ref' => 'bad-key-install-0002', 'public_key' => base64_encode(random_bytes(91))])->assertStatus(422)->assertJsonValidationErrors('public_key');

        $token = $this->postJson('/api/v1/activate', ['company' => 'demo', 'employee_code' => 'DEMO003', 'activation_code' => '12345678', 'platform' => 'android',
            'install_ref' => 'old-app-install-0003'])->assertCreated()->json('token');
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer $token"])->assertOk(); // backward compatible
        $this->assertTrue(Employee::withoutGlobalScopes()->where('employee_code', 'DEMO003')->exists());
    }
}
