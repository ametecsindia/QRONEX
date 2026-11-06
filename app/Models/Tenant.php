<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    public const RESERVED_SLUGS = ['admin', 'api', 'app', 'www', 'login', 'logout', 'signup', 'register', 'pricing', 'docs', 'help', 'support', 'status', 'billing', 'display', 'static', 'assets', 'mail', 'blog', 'about', 'legal', 'terms', 'privacy', 'qronex', 'ametecs', 'dashboard', 'settings', 'super', 'client', 'account', 'two-factor', 'forgot-password', 'reset-password', 'contact', 'up', 'health'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['api_enabled' => 'boolean'];
    }

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function offices(): HasMany { return $this->hasMany(Office::class); }
    public function employees(): HasMany { return $this->hasMany(Employee::class); }
    public function devices(): HasMany { return $this->hasMany(Device::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
    public function settings(): HasOne { return $this->hasOne(TenantSetting::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', 'company_owner')->oldestOfMany();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function apiAllowed(): bool
    {
        return $this->api_enabled ?? (bool) $this->subscription?->planVersion?->api_included;
    }

    /** Creates company + settings + trial subscription + owner. Used by self sign-up and by Super Admin "Add company". */
    public static function provision(array $d, Plan $plan, ?int $actorId = null): User
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($d, $plan, $actorId) {
            $tenant = static::create([
                'name' => $d['company'], 'slug' => $d['slug'], 'industry' => $d['industry'] ?? null,
                'state' => $d['state'] ?? null, 'city' => $d['city'] ?? null, 'gstin' => $d['gstin'] ?? null,
                'status' => 'trial', 'customer_type' => $d['customer_type'] ?? 'independent',
            ]);
            $tenant->settings()->create(TenantSetting::defaults());
            $tenant->subscriptions()->create([
                'plan_version_id' => $plan->currentVersion->id,
                'billing_cycle' => $d['billing_cycle'],
                'employee_count' => $d['employees'],
                'status' => 'trial',
                'trial_ends_at' => now()->addDays($plan->currentVersion->trial_days),
            ]);
            $user = $tenant->users()->create(['name' => $d['name'], 'email' => $d['email'], 'password' => $d['password'], 'role' => 'company_owner']);
            AuditLog::create(['tenant_id' => $tenant->id, 'user_id' => $actorId ?? $user->id, 'action' => $actorId ? 'tenant.created_by_admin' : 'tenant.registered',
                'subject' => $tenant->slug, 'meta' => ['plan' => $plan->code, 'employees' => (int) $d['employees']], 'ip' => request()->ip()]);

            return $user;
        });
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, ['suspended', 'cancelled'], true);
    }
}
