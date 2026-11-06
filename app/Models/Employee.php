<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $hidden = ['activation_code_hash'];

    protected function casts(): array
    {
        return ['activation_expires_at' => 'datetime'];
    }

    public function office(): BelongsTo { return $this->belongsTo(Office::class); }

    /** Inactive + every device revoked (the app is signed out) + webhook. Same path for portal and API. Returns devices revoked. */
    public function deactivate(): int
    {
        $this->update(['status' => 'inactive']);
        $devices = Device::withoutGlobalScopes()->where('employee_id', $this->id)->where('status', '!=', 'revoked')->get();
        foreach ($devices as $d) {
            $d->update(['status' => 'revoked', 'token_hash' => null, 'approval_code' => null]);
            $d->log('revoked', auth()->user(), 'Employee deactivated');
        }
        $revoked = $devices->count();
        \App\Services\Webhooks::dispatch($this->tenant_id, 'employee.deactivated', $this->toApi());

        return $revoked;
    }

    public function toApi(): array
    {
        return [
            'employee_code' => $this->employee_code, 'name' => $this->name, 'email' => $this->email, 'mobile' => $this->mobile,
            'department' => $this->department, 'designation' => $this->designation, 'hrms_ref' => $this->hrms_ref,
            'office_code' => $this->office?->code, 'status' => $this->status, 'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
    public function devices(): HasMany { return $this->hasMany(Device::class); }
    public function events(): HasMany { return $this->hasMany(AttendanceEvent::class); }
}
