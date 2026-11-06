<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log is append-only.'));
        static::deleting(fn () => throw new LogicException('Audit log is append-only.'));
    }

    public static function record(string $action, ?string $subject = null, array $meta = [], ?int $tenantId = null): void
    {
        $user = auth()->user();
        static::create([
            'tenant_id' => $tenantId ?? $user?->tenant_id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject' => $subject,
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
