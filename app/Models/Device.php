<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'public_key'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function replaces(): BelongsTo { return $this->belongsTo(Device::class, 'replaces_device_id'); }
    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(DeviceEvent::class)->latest('created_at')->latest('id'); }

    /** ROVEXY ApprovalCode: human-matchable, no 0/O/1/I/L/U. Shown on the phone; the approver types it to prove it's the right phone. */
    public static function newApprovalCode(): string
    {
        $a = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
        $c = '';
        for ($i = 0; $i < 8; $i++) {
            $c .= $a[random_int(0, strlen($a) - 1)];
        }

        return substr($c, 0, 4).'-'.substr($c, 4);
    }

    public static function codeMatches(?string $expected, ?string $typed): bool
    {
        $norm = fn ($v) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));

        return $expected !== null && $typed !== null && hash_equals($norm($expected), $norm($typed));
    }

    /** Append-only device history (device_events). */
    public function log(string $action, ?User $by = null, ?string $reason = null, array $meta = []): void
    {
        DeviceEvent::create(['tenant_id' => $this->tenant_id, 'device_id' => $this->id, 'action' => $action,
            'actor_user_id' => $by?->id, 'reason' => $reason ? mb_substr($reason, 0, 255) : null, 'meta' => $meta ?: null]);
    }
}
