<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'is_active' => 'boolean'];
    }

    public function gates(): HasMany { return $this->hasMany(Gate::class); }
    public function employees(): HasMany { return $this->hasMany(Employee::class); }
}
