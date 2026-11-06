<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    public const DEFAULTS = [
        'qr_min_s' => 2, 'qr_max_s' => 30, 'qr_default_s' => 3,
        'grace_max_s' => 10, 'device_limit_max' => 3,
        'gst_pct' => 18, 'trial_days' => 14, 'currency' => 'INR',
    ];

    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // ponytail: one query per call; cache with once()/Cache if a page reads many keys.
        return static::find($key)?->value ?? self::DEFAULTS[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
