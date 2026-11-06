<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query to the logged-in user's tenant and fills tenant_id on create.
 * Platform staff (tenant_id = null) and guests/console are unscoped.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $q) {
            if ($tid = auth()->user()?->tenant_id) {
                $q->where($q->getModel()->getTable().'.tenant_id', $tid);
            }
        });

        static::creating(function ($model) {
            if (! $model->tenant_id && ($tid = auth()->user()?->tenant_id)) {
                $model->tenant_id = $tid;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
