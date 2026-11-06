<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use BelongsToTenant;

    public const EVENTS = [
        'attendance.created' => 'A verified IN/OUT punch was recorded',
        'device.approved' => 'An employee phone was approved',
        'employee.deactivated' => 'An employee was deactivated',
        'attendance.adjusted' => 'An approved correction added a manual punch',
        'remote_permission.approved' => 'A remote / field permission was approved',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['events' => 'array', 'is_active' => 'boolean'];
    }

    public function deliveries(): HasMany { return $this->hasMany(WebhookDelivery::class); }
}
