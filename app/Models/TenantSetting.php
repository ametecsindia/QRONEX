<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['require_device_approval' => 'boolean', 'notify_device_requests' => 'boolean', 'notify_remote_requests' => 'boolean', 'role_permissions' => 'array'];
    }

    public static function defaults(): array
    {
        return [
            'qr_interval_s' => PlatformSetting::get('qr_default_s', 3),
            'grace_s' => 5,
            'device_limit' => 1,
            'require_device_approval' => true,
            'duplicate_window_min' => 2,
            'gps_accuracy_m' => 50,
            'retention_months' => 24,
            'shift_start' => '09:30',
            'late_after_min' => 10,
            'location_notice' => 'QRONEX checks your phone location only at the moment you mark attendance. Location is not tracked at any other time.',
        ];
    }
}
