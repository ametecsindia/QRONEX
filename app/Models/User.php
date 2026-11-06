<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = [
        'super_admin' => 'Super Admin',
        'support_admin' => 'Support Admin',
        'company_owner' => 'Company Owner',
        'company_admin' => 'Company Administrator',
        'office_manager' => 'Office Manager',
        'attendance_approver' => 'Attendance Approver',
    ];

    public const TENANT_ROLES = ['company_owner', 'company_admin', 'office_manager', 'attendance_approver'];

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'email_code_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'email_code_expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class);
    }

    public function isPlatform(): bool
    {
        return $this->tenant_id === null;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Office ids this user is limited to, or null when they see every office of the tenant. */
    public function officeScope(): ?array
    {
        return $this->hasRole('office_manager', 'attendance_approver')
            ? $this->offices()->pluck('offices.id')->all()
            : null;
    }

    /** Password reset mail goes through the editable 'reset' template. */
    public function sendPasswordResetNotification($token): void
    {
        \App\Services\Notifier::email('reset', $this->email, [
            'reset_link' => route('password.reset', ['token' => $token, 'email' => $this->email]),
        ], $this->tenant_id);
    }

    /** Only sign-ups get a code; seeded / invited users are not blocked. */
    public function emailPending(): bool
    {
        return $this->email_code_hash !== null && $this->email_verified_at === null;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret;
    }

    /** Platform staff see every company's data, so they must use 2FA wherever config says so (default: production). */
    public function mustUseTwoFactor(): bool
    {
        return $this->isPlatform() && config('qronex.require_admin_2fa');
    }

    /** Uses up a recovery code (each works once). */
    public function useRecoveryCode(string $code): bool
    {
        $hash = hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)));
        $codes = $this->two_factor_recovery_codes ?? [];
        if (! in_array($hash, $codes, true)) {
            return false;
        }
        $this->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$hash]))])->save();

        return true;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
