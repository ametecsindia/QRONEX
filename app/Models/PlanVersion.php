<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PlanVersion extends Model
{
    public const UPDATED_AT = null;

    public const PRICE_FIELDS = ['pricing_model', 'per_employee_price', 'slab_price', 'min_billing', 'quarterly_discount_pct', 'annual_discount_pct', 'onboarding_fixed', 'onboarding_per_employee', 'trial_days', 'api_included', 'sms_allowance'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['api_included' => 'boolean', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Plan versions are immutable — create a new version.'));
        static::deleting(fn () => throw new LogicException('Plan versions are immutable.'));
    }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}
