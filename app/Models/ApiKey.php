<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use BelongsToTenant;

    /** No scope can create punches — attendance only comes from the app after server checks. */
    public const SCOPES = [
        'offices:read' => 'Read offices and gates',
        'employees:read' => 'Read employees',
        'employees:write' => 'Create, update and deactivate employees',
        'attendance:read' => 'Read verified attendance events',
        'attendance:export' => 'Export attendance as CSV',
        'remote:write' => 'Grant remote / field permissions',
        'webhooks:manage' => 'Manage webhook endpoints',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return array{0: ApiKey, 1: string} the model and the plain key (shown once) */
    public static function issue(int $tenantId, string $name, array $scopes, ?int $userId = null): array
    {
        $plain = 'qrx_live_'.Str::random(40);
        $key = static::create([
            'tenant_id' => $tenantId, 'name' => $name, 'prefix' => substr($plain, 0, 17), 'key_hash' => hash('sha256', $plain),
            'scopes' => array_values(array_intersect(array_keys(self::SCOPES), $scopes)), 'created_by' => $userId,
        ]);

        return [$key, $plain];
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
