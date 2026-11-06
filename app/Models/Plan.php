<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Plan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_popular' => 'boolean'];
    }

    public function versions(): HasMany { return $this->hasMany(PlanVersion::class); }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(PlanVersion::class)->ofMany('version', 'max');
    }

    /** Active published plan whose employee range covers $n; Enterprise (custom) above the ranges. */
    public static function forEmployees(int $n): ?self
    {
        return static::where('is_active', true)->with('currentVersion')->orderBy('sort')->get()
            ->first(fn ($p) => $n >= $p->min_employees && $n <= $p->max_employees);
    }
}
