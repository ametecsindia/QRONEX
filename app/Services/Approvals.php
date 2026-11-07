<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\PunchCorrection;
use App\Models\RemotePermission;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** One place for every approve/reject decision (portal, API) so audit, notifications and webhooks never diverge. */
class Approvals
{
    /** Approve a device; if it is a replacement and the limit is reached, the old phone is revoked first. */
    public static function approveDevice(Device $d, ?User $by): void
    {
        DB::transaction(function () use ($d, $by) {
            $limit = $d->employee->deviceLimit();
            $active = Device::withoutGlobalScopes()->where('employee_id', $d->employee_id)->where('status', 'approved')->where('id', '!=', $d->id);
            if ($active->count() >= $limit) {
                if (! $d->replaces_device_id) {
                    throw new RuntimeException("{$d->employee->name} already has {$active->count()} approved device(s); the limit is {$limit}. Revoke one first, or a company admin can allow more phones on the employee's page.");
                }
                $old = Device::withoutGlobalScopes()->find($d->replaces_device_id);
                $old?->update(['status' => 'revoked', 'token_hash' => null]);
                $old?->log('replaced', $by, null, ['new_device_id' => $d->id]);
                AuditLog::create(['tenant_id' => $d->tenant_id, 'user_id' => $by?->id, 'action' => 'device.replaced', 'subject' => $d->install_ref, 'meta' => ['old_device_id' => $d->replaces_device_id]]);
            }
            $d->update(['status' => 'approved', 'approved_by' => $by?->id, 'approved_at' => now(), 'approval_code' => null]);
            $d->log('approved', $by);
        });
        Webhooks::dispatch($d->tenant_id, 'device.approved', \App\Http\Controllers\Portal\DeviceController::deviceApi($d));
    }

    public static function decideRemote(RemotePermission $p, bool $approve, ?User $by): void
    {
        if ($p->status !== 'pending') {
            throw new RuntimeException("Permission is already {$p->status}.");
        }
        if ($approve && ! $p->location_waived && $p->lat === null) {
            throw new RuntimeException('Set an approved location (or waive the location check) before approving.');
        }
        $p->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by?->id, 'decided_at' => now()]);
        AuditLog::create(['tenant_id' => $p->tenant_id, 'user_id' => $by?->id, 'action' => $approve ? 'remote.approved' : 'remote.rejected', 'subject' => $p->ref,
            'meta' => ['employee' => $p->employee->employee_code, 'waived' => $p->location_waived]]);
        if ($approve) {
            self::remoteApproved($p);
        } else {
            Notifier::email('remote_no', $p->employee->email, ['employee_name' => $p->employee->name, 'permission_type' => $p->typeLabel()], $p->tenant_id);
        }
    }

    /** Called for permissions created already-approved (portal grant / API) and on approval. */
    public static function remoteApproved(RemotePermission $p): void
    {
        Notifier::email('remote_ok', $p->employee->email, ['employee_name' => $p->employee->name, 'permission_type' => $p->typeLabel(),
            'from_date' => $p->from_date->format('d M Y'), 'to_date' => $p->to_date->format('d M Y')], $p->tenant_id);
        Webhooks::dispatch($p->tenant_id, 'remote_permission.approved', $p->toApi());
    }

    /** Approving never edits an existing punch: it adds a MANUAL_EXCEPTION event marked adjusted. */
    public static function decideCorrection(PunchCorrection $c, bool $approve, ?User $by): ?AttendanceEvent
    {
        return DB::transaction(function () use ($c, $approve, $by) {
            $status = PunchCorrection::withoutGlobalScopes()->whereKey($c->id)->lockForUpdate()->value('status'); // double click = one punch
            if ($status !== 'pending') {
                throw new RuntimeException("Correction is already {$status}.");
            }
            $c->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by?->id, 'decided_at' => now()]);
            AuditLog::create(['tenant_id' => $c->tenant_id, 'user_id' => $by?->id, 'action' => $approve ? 'correction.approved' : 'correction.rejected', 'subject' => $c->ref,
                'meta' => ['employee' => $c->employee->employee_code, 'type' => $c->type, 'at' => $c->punch_at->toIso8601ZuluString()]]);
            if (! $approve) {
                return null;
            }
            $e = AttendanceEvent::create([
                'ref' => AttendanceVerifier::ref('QRX'), 'tenant_id' => $c->tenant_id, 'employee_id' => $c->employee_id, 'office_id' => $c->employee->office_id,
                'type' => $c->type, 'mode' => 'MANUAL_EXCEPTION', 'adjusted' => true, 'punch_correction_id' => $c->id, 'recorded_at' => $c->punch_at,
            ]);
            $e->setRelation('employee', $c->employee)->setRelation('correction', $c);
            DB::afterCommit(fn () => Webhooks::dispatch($c->tenant_id, 'attendance.adjusted', $e->toApi()));

            return $e;
        });
    }

    /** Email company owners/admins (and scoped managers of that office) when something waits for them. */
    public static function notifyApprovers(int $tenantId, ?int $officeId, string $template, array $vars, string $settingFlag): void
    {
        if (! TenantSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->value($settingFlag)) {
            return;
        }
        User::where('tenant_id', $tenantId)->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('role', ['company_owner', 'company_admin'])
                ->orWhere(fn ($m) => $m->whereIn('role', ['office_manager', 'attendance_approver'])->whereHas('offices', fn ($o) => $o->withoutGlobalScopes()->where('offices.id', $officeId))))
            ->get()->each(fn ($u) => Notifier::email($template, $u->email, $vars + ['admin_name' => $u->name, 'approvals_url' => route('portal.approvals', ['company' => $u->tenant->slug])], $tenantId));
    }
}
