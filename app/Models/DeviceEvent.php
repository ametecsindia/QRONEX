<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const LABELS = [
        'registered' => 'Phone activated — waiting for approval', 're_registered' => 'Phone re-activated',
        'replacement_requested' => 'Replacement phone requested', 'approved' => 'Approved', 'rejected' => 'Rejected',
        'revoked' => 'Revoked', 'replaced' => 'Revoked — replaced by a new phone', 'auto_approved' => 'Activated (approval not required)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
}
