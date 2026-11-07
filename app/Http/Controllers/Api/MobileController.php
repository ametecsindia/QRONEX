<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\AttendanceVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileController extends Controller
{
    private function settings(int $tenantId): TenantSetting
    {
        return TenantSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first() ?? new TenantSetting(TenantSetting::defaults());
    }

    private function eventJson(AttendanceEvent $e): array
    {
        return ['ref' => $e->ref, 'type' => $e->type, 'at' => $e->recorded_at->toIso8601ZuluString(), 'office' => $e->office?->name ?? ($e->remotePermission ? $e->remotePermission->typeLabel() : null), 'gate' => $e->gate?->name, 'mode' => $e->mode, 'distance_m' => $e->distance_m, 'adjusted' => (bool) $e->adjusted];
    }

    /** Single-use activation code (sent by SMS/email from the portal) links this installation to an employee. */
    public function activate(Request $request)
    {
        $d = $request->validate([
            'company' => 'required|string|max:60',
            'employee_code' => 'required|string|max:40',
            'activation_code' => 'required|string|max:20',
            'platform' => 'required|in:android,ios',
            'model' => 'nullable|string|max:120',
            'os_version' => 'nullable|string|max:60',
            'install_ref' => 'required|string|min:16|max:80',
            'public_key' => 'nullable|string|max:400',
        ]);
        $fail = fn () => throw ValidationException::withMessages(['activation_code' => 'Company, employee code or activation code is wrong, or the code has expired.']);

        $tenant = Tenant::where('slug', strtolower(trim($d['company'])))->first();
        if (! $tenant || $tenant->isBlocked()) {
            $fail();
        }
        $employee = Employee::withoutGlobalScopes()->with('office')->where('tenant_id', $tenant->id)->where('employee_code', trim($d['employee_code']))->first();
        $d += ['model' => null, 'os_version' => null]; // optional fields
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $d['activation_code']));
        $pem = null;
        if (! empty($d['public_key']) && ! ($pem = \App\Support\DeviceSignature::pemFromBase64Der($d['public_key']))) {
            throw ValidationException::withMessages(['public_key' => 'This phone sent an invalid device key. Update the QRONEX app.']);
        }
        if (! $employee || $employee->status !== 'active' || ! $employee->activation_code_hash || $employee->activation_expires_at?->isPast() || ! Hash::check($code, $employee->activation_code_hash)) {
            $fail();
        }

        $existing = Device::withoutGlobalScopes()->where('install_ref', $d['install_ref'])->first();
        if ($existing && (int) $existing->employee_id !== (int) $employee->id) {
            throw ValidationException::withMessages(['install_ref' => 'This phone is registered to another employee. Ask your administrator to revoke it first.']);
        }

        $settings = $this->settings($tenant->id);
        $token = Str::random(48);

        $device = DB::transaction(function () use ($existing, $employee, $tenant, $d, $settings, $token, $pem) {
            $approvedCount = Device::withoutGlobalScopes()->where('employee_id', $employee->id)->where('status', 'approved')
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->count();
            $status = ! $settings->require_device_approval && $approvedCount < $employee->deviceLimit() ? 'approved' : 'pending';
            // A new phone while the employee is at the limit = replacement request: approving it revokes the old phone.
            $replaces = ! $existing && $approvedCount >= $employee->deviceLimit()
                ? Device::withoutGlobalScopes()->where('employee_id', $employee->id)->where('status', 'approved')->latest('approved_at')->value('id') : null;
            if ($existing?->status === 'approved') {
                $status = 'approved'; // re-activating the same approved phone keeps it approved
            }

            $attrs = ['platform' => $d['platform'], 'model' => $d['model'], 'os_version' => $d['os_version'], 'status' => $status, 'token_hash' => hash('sha256', $token), 'last_seen_at' => now()]
                + ($status === 'approved' && $existing?->status !== 'approved' ? ['approved_at' => now()] : []);
            $attrs['approval_code'] = $status === 'pending' ? Device::newApprovalCode() : null;
            $attrs['public_key'] = $pem;
            $device = $existing ? tap($existing)->update($attrs) : Device::create($attrs + ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'install_ref' => $d['install_ref'], 'replaces_device_id' => $replaces]);
            $device->log(match (true) {
                $status === 'approved' && ! $existing => 'auto_approved',
                (bool) $existing => 're_registered',
                (bool) $replaces => 'replacement_requested',
                default => 'registered',
            }, null, null, ['model' => $d['model'], 'os' => $d['os_version'], 'ip' => request()->ip(), 'signed' => (bool) $pem]);

            $employee->forceFill(['activation_code_hash' => null, 'activation_expires_at' => null])->save();
            AuditLog::create(['tenant_id' => $tenant->id, 'action' => 'device.activated', 'subject' => $device->install_ref, 'meta' => ['employee' => $employee->employee_code, 'model' => $d['model'], 'status' => $status], 'ip' => request()->ip()]);

            return $device;
        });
        if ($device->status === 'pending') {
            \App\Services\Approvals::notifyApprovers($tenant->id, $employee->office_id, 'dev_request', [
                'employee_name' => $employee->name, 'device_model' => $device->model, 'company_name' => $tenant->name,
                'request_type' => $device->replaces_device_id ? 'Replacement phone' : 'New phone',
            ], 'notify_device_requests');
        }
        if ($device->status === 'approved' && $existing?->status !== 'approved') {
            \App\Services\Webhooks::dispatch($tenant->id, 'device.approved', \App\Http\Controllers\Portal\DeviceController::deviceApi($device->setRelation('employee', $employee)));
        }

        return response()->json([
            'token' => $token,
            'device' => ['status' => $device->status, 'approval_code' => $device->approval_code],
            'employee' => ['code' => $employee->employee_code, 'name' => $employee->name, 'office' => $employee->office?->name],
            'company' => ['name' => $tenant->name],
            'policy' => ['location_notice' => $settings->location_notice],
        ], 201);
    }

    public function me(Request $request)
    {
        $device = $request->attributes->get('device');
        $e = $device->employee;
        $last = AttendanceEvent::withoutGlobalScopes()->with('office', 'gate')->where('employee_id', $e->id)->latest('recorded_at')->first();
        $s = $this->settings($device->tenant_id);

        return [
            'employee' => ['code' => $e->employee_code, 'name' => $e->name, 'office' => $e->office?->name],
            'company' => ['name' => $device->tenant->name, 'slug' => $device->tenant->slug],
            'device' => ['status' => $device->status, 'approval_code' => $device->status === 'pending' ? $device->approval_code : null, 'model' => $device->model, 'install_ref' => $device->install_ref, 'registered_at' => $device->created_at->toIso8601ZuluString()],
            'policy' => ['location_notice' => $s->location_notice, 'gps_accuracy_m' => $s->gps_accuracy_m],
            'next_type' => AttendanceVerifier::nextType($e->id),
            'remote_permission' => \App\Models\RemotePermission::activeFor($e->id)?->setRelation('employee', $e)->toApi(),
            'last_event' => $last ? $this->eventJson($last) : null,
        ];
    }

    public function scan(Request $request, AttendanceVerifier $verifier)
    {
        $d = $request->validate([
            'qr' => 'required|string|max:200',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0|max:100000',
            'mocked' => 'boolean',
            'type' => 'nullable|in:IN,OUT',
        ]);
        $r = $verifier->scan($request->attributes->get('device'), [
            'qr' => $d['qr'], 'lat' => (float) $d['lat'], 'lng' => (float) $d['lng'], 'accuracy' => (float) $d['accuracy'],
            'mocked' => (bool) ($d['mocked'] ?? false), 'type' => $d['type'] ?? null,
        ], $request->ip());

        return $r['ok']
            ? response()->json(['ok' => true, 'event' => $this->eventJson($r['event']), 'checks' => $r['checks']])
            : response()->json($r, 422);
    }

    public function remoteScan(Request $request, AttendanceVerifier $verifier)
    {
        $d = $request->validate([
            'lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0|max:100000', 'mocked' => 'boolean', 'type' => 'nullable|in:IN,OUT',
        ]);
        $r = $verifier->remoteScan($request->attributes->get('device'), [
            'lat' => (float) $d['lat'], 'lng' => (float) $d['lng'], 'accuracy' => (float) $d['accuracy'], 'mocked' => (bool) ($d['mocked'] ?? false), 'type' => $d['type'] ?? null,
        ], $request->ip());

        return $r['ok']
            ? response()->json(['ok' => true, 'event' => $this->eventJson($r['event']), 'checks' => $r['checks']])
            : response()->json($r, 422);
    }

    /** Employee asks for WFH / field duty. Optional current location becomes the approved location (radius 250 m). */
    public function requestRemote(Request $request)
    {
        $device = $request->attributes->get('device');
        $e = $device->employee;
        $today = now('Asia/Kolkata')->toDateString();
        $d = $request->validate([
            'type' => 'required|in:'.implode(',', array_keys(\App\Models\RemotePermission::TYPES)),
            'from' => "required|date|after_or_equal:$today", 'to' => 'required|date|after_or_equal:from',
            'reason' => 'required|string|max:255', 'location_label' => 'nullable|string|max:120',
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180|required_with:lat',
        ]);
        abort_if($device->status !== 'approved', 422, 'Your phone must be approved first.');
        $p = \App\Models\RemotePermission::create([
            'ref' => AttendanceVerifier::ref('RP'), 'tenant_id' => $device->tenant_id, 'employee_id' => $e->id, 'type' => $d['type'],
            'from_date' => $d['from'], 'to_date' => $d['to'], 'reason' => $d['reason'], 'requested_via' => 'app', 'status' => 'pending',
            'location_label' => $d['location_label'] ?? (isset($d['lat']) ? 'Location sent from the app' : null),
            'lat' => $d['lat'] ?? null, 'lng' => $d['lng'] ?? null, 'radius_m' => isset($d['lat']) ? 250 : null,
        ]);
        \App\Services\Approvals::notifyApprovers($device->tenant_id, $e->office_id, 'remote_request', [
            'employee_name' => $e->name, 'permission_type' => $p->typeLabel(), 'from_date' => $p->from_date->format('d M Y'), 'to_date' => $p->to_date->format('d M Y'), 'reason' => $p->reason,
        ], 'notify_remote_requests');

        return response()->json(['ok' => true, 'request' => $p->setRelation('employee', $e)->toApi()], 201);
    }

    /** "I forgot to punch": becomes a pending correction; approval adds an adjusted MANUAL_EXCEPTION event. */
    public function requestCorrection(Request $request)
    {
        $device = $request->attributes->get('device');
        $d = $request->validate([
            'type' => 'required|in:IN,OUT', 'date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i', 'reason' => 'required|string|max:255',
        ]);
        $at = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', "{$d['date']} {$d['time']}", 'Asia/Kolkata');
        if ($at->isFuture() || $at->lt(now()->subDays(30))) {
            throw ValidationException::withMessages(['date' => 'Corrections can be requested for the last 30 days only.']);
        }
        abort_if($device->status !== 'approved', 422, 'Your phone must be approved first.');
        $c = \App\Models\PunchCorrection::create([
            'ref' => AttendanceVerifier::ref('COR'), 'tenant_id' => $device->tenant_id, 'employee_id' => $device->employee_id,
            'type' => $d['type'], 'punch_at' => $at->utc(), 'reason' => $d['reason'], 'requested_via' => 'app',
        ]);
        \App\Services\Approvals::notifyApprovers($device->tenant_id, $device->employee->office_id, 'correction_request', [
            'employee_name' => $device->employee->name, 'punch' => "{$d['type']} {$at->format('d M Y H:i')}", 'reason' => $d['reason'],
        ], 'notify_remote_requests');

        return response()->json(['ok' => true, 'request' => ['id' => $c->ref, 'status' => $c->status]], 201);
    }

    public function requests(Request $request)
    {
        $eid = $request->attributes->get('device')->employee_id;
        $since = now()->subDays(60);

        return [
            'remote' => \App\Models\RemotePermission::withoutGlobalScopes()->where('employee_id', $eid)->where('created_at', '>=', $since)->latest()->get()
                ->map(fn ($p) => ['id' => $p->ref, 'type_label' => $p->typeLabel(), 'from' => $p->from_date->toDateString(), 'to' => $p->to_date->toDateString(), 'status' => $p->status, 'reason' => $p->reason]),
            'corrections' => \App\Models\PunchCorrection::withoutGlobalScopes()->where('employee_id', $eid)->where('created_at', '>=', $since)->latest()->get()
                ->map(fn ($c) => ['id' => $c->ref, 'type' => $c->type, 'at' => $c->punch_at->toIso8601ZuluString(), 'status' => $c->status, 'reason' => $c->reason]),
        ];
    }

    public function history(Request $request)
    {
        $days = min(max((int) $request->query('days', 30), 1), 90);
        $events = AttendanceEvent::withoutGlobalScopes()->with('office', 'gate', 'remotePermission')
            ->where('employee_id', $request->attributes->get('device')->employee_id)
            ->where('recorded_at', '>=', now()->subDays($days))->latest('recorded_at')->limit(500)->get();

        return ['events' => $events->map(fn ($e) => $this->eventJson($e))->all()];
    }
}
