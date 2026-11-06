<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gate extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $hidden = ['display_secret'];

    protected static function booted(): void
    {
        static::creating(fn (Gate $g) => $g->display_secret ??= \Illuminate\Support\Str::random(40));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'display_last_seen_at' => 'datetime'];
    }

    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
}
