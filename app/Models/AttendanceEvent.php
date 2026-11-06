<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'created_at' => 'datetime', 'lat' => 'float', 'lng' => 'float', 'adjusted' => 'boolean'];
    }

    /** Shape used by the public API and webhooks. */
    public function toApi(): array
    {
        return [
            'id' => $this->ref, 'type' => $this->type, 'mode' => $this->mode, 'recorded_at' => $this->recorded_at->toIso8601ZuluString(),
            'employee_code' => $this->employee?->employee_code, 'employee_name' => $this->employee?->name, 'hrms_ref' => $this->employee?->hrms_ref,
            'office_code' => $this->office?->code, 'gate_code' => $this->gate?->code,
            'location' => ['distance_m' => $this->distance_m, 'accuracy_m' => $this->accuracy_m],
            'adjusted' => (bool) $this->adjusted, 'remote_permission_id' => $this->remotePermission?->ref, 'correction_id' => $this->correction?->ref,
        ];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
    public function gate(): BelongsTo { return $this->belongsTo(Gate::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function remotePermission(): BelongsTo { return $this->belongsTo(RemotePermission::class); }
    public function correction(): BelongsTo { return $this->belongsTo(PunchCorrection::class, 'punch_correction_id'); }
}
