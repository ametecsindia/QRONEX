<?php

namespace App\Support;

use App\Models\TenantSetting;
use App\Models\User;

/**
 * Single registry of abilities. Platform abilities are fixed; company abilities have defaults per role
 * that a Company Owner can change per company (Users & roles → Role permissions). The owner always has all.
 */
class Permissions
{
    public const PLATFORM = [
        'manage-platform' => ['super_admin'],
        'view-platform' => ['super_admin', 'support_admin'],
    ];

    /** ability => [label, help, default roles] */
    public const COMPANY = [
        'approve-devices' => ['Approvals & devices', 'Approve phones, remote work and punch corrections', ['company_admin', 'office_manager', 'attendance_approver']],
        'manage-employees' => ['Employees', 'Add, edit, import employees and send app activation codes', ['company_admin', 'office_manager']],
        'manage-offices' => ['Offices & gates', 'Offices, geofences, gates and QR display links', ['company_admin']],
        'manage-users' => ['Users & roles', 'Create portal users and change their roles', ['company_admin']],
        'manage-company' => ['Company settings', 'Attendance policy, API & webhooks, company profile', ['company_admin']],
        'manage-billing' => ['Billing', 'Plan, invoices and payments', []],
    ];

    /** Roles whose company permissions can be changed (the owner is always full). */
    public const EDITABLE_ROLES = ['company_admin', 'office_manager', 'attendance_approver'];

    public static function defaults(): array
    {
        $m = [];
        foreach (self::EDITABLE_ROLES as $r) {
            $m[$r] = array_keys(array_filter(self::COMPANY, fn ($a) => in_array($r, $a[2], true)));
        }

        return $m;
    }

    /** role => abilities for one company (defaults merged with the owner's overrides). */
    public static function matrix(?int $tenantId): array
    {
        $saved = $tenantId ? (TenantSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->value('role_permissions') ?? null) : null;
        $saved = is_string($saved) ? json_decode($saved, true) : $saved;

        return array_replace(self::defaults(), array_intersect_key((array) $saved, array_flip(self::EDITABLE_ROLES)));
    }

    public static function allows(User $u, string $ability): bool
    {
        if (isset(self::PLATFORM[$ability])) {
            return $u->hasRole(...self::PLATFORM[$ability]);
        }
        if (! isset(self::COMPANY[$ability]) || ! $u->tenant_id) {
            return false;
        }
        if ($u->hasRole('company_owner')) {
            return true;
        }
        // one settings read per request (memo lives on the request, so a saved change applies on the next page load)
        $attrs = request()->attributes;
        if (! $attrs->has('qx_perm_matrix')) {
            $attrs->set('qx_perm_matrix', self::matrix($u->tenant_id));
        }
        $m = $attrs->get('qx_perm_matrix');

        return in_array($ability, $m[$u->role] ?? [], true);
    }
}
