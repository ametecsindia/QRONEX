<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'current_period_start' => 'date', 'current_period_end' => 'date'];
    }

    public function planVersion(): BelongsTo { return $this->belongsTo(PlanVersion::class); }
}
