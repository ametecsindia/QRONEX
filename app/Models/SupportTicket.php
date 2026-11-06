<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Not tenant-scoped by trait: public enquiries have no tenant. Controllers filter explicitly. */
class SupportTicket extends Model
{
    public const AREAS = ['display' => 'QR display', 'app' => 'Mobile app', 'billing' => 'Billing', 'api' => 'API', 'sales' => 'Sales enquiry', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (SupportTicket $t) => $t->ref ??= 'T-'.strtoupper(Str::random(6)));
    }

    public function messages(): HasMany { return $this->hasMany(SupportMessage::class)->orderBy('id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
