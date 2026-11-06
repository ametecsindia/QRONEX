<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\TenantSetting;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $scope = $request->user()->officeScope();
        $devices = Device::with('employee.office', 'approver', 'replaces')
            ->when($scope !== null, fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereIn('office_id', $scope)))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('model', 'like', "%$s%")->orWhere('install_ref', 'like', "%$s%")
                ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', "%$s%")->orWhere('employee_code', 'like', "%$s%"))))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")->latest()
            ->paginate(50)->withQueryString();

        $counts = Device::when($scope !== null, fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereIn('office_id', $scope)))
            ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('portal.devices.index', ['devices' => $devices, 'counts' => $counts, 'limit' => TenantSetting::first()?->device_limit ?? 1]);
    }

    /** Device detail + full approval history + the employee's other phones (ROVEXY device history). */
    public function show(Request $request, Device $device)
    {
        $this->authorizeScope($request, $device);

        return view('portal.devices.show', [
            'd' => $device->load('employee.office', 'approver', 'replaces', 'events.actor'),
            'others' => Device::where('employee_id', $device->employee_id)->whereKeyNot($device->id)->latest()->get(),
            'punches' => \App\Models\AttendanceEvent::where('device_id', $device->id)->with('office', 'gate')->latest('recorded_at')->take(10)->get(),
        ]);
    }

    private function authorizeScope(Request $request, Device $device): void
    {
        $scope = $request->user()->officeScope();
        abort_if($scope !== null && ! in_array((int) $device->employee->office_id, array_map('intval', $scope), true), 403);
    }

    public static function deviceApi(Device $d): array
    {
        return ['employee_code' => $d->employee->employee_code, 'install_ref' => $d->install_ref, 'platform' => $d->platform, 'model' => $d->model, 'status' => $d->status, 'approved_at' => $d->approved_at?->toIso8601ZuluString()];
    }

    public function act(Request $request, Device $device, string $action)
    {
        $this->authorizeScope($request, $device);

        $allowed = ['approve' => ['pending', 'rejected'], 'reject' => ['pending'], 'revoke' => ['approved']];
        if (! in_array($device->status, $allowed[$action], true)) {
            return back()->withErrors(['device' => "Cannot $action a device that is {$device->status}."]);
        }

        // ROVEXY rules: approving needs the match code shown on the phone; rejecting/revoking needs a reason.
        if ($action === 'approve' && $device->approval_code && ! Device::codeMatches($device->approval_code, $request->input('code'))) {
            return back()->withErrors(['code' => "The code doesn't match the one on {$device->employee->name}'s phone. Ask them to open QRONEX and read it out."]);
        }
        $reason = null;
        if ($action !== 'approve') {
            $reason = $request->validate(['reason' => 'required|string|min:3|max:255'], ['reason.required' => 'Enter a reason — it is kept in the device history.'])['reason'];
        }

        if ($action === 'approve') {
            try {
                \App\Services\Approvals::approveDevice($device, $request->user());
            } catch (\RuntimeException $e) {
                return back()->withErrors(['device' => $e->getMessage()]);
            }
        } else {
            $device->update(['status' => $action === 'reject' ? 'rejected' : 'revoked'] + ($action === 'revoke' ? ['token_hash' => null, 'approval_code' => null] : []));
            $device->log($action === 'reject' ? 'rejected' : 'revoked', $request->user(), $reason);
        }
        AuditLog::record("device.$action", $device->install_ref, ['employee' => $device->employee->employee_code, 'model' => $device->model, 'reason' => $reason]);
        if ($action !== 'revoke') {
            \App\Services\Notifier::email($action === 'approve' ? 'dev_ok' : 'dev_no', $device->employee->email, [
                'employee_name' => $device->employee->name, 'device_model' => $device->model, 'company_name' => $request->user()->tenant->name,
            ], $device->tenant_id);
        }

        return back()->with('ok', "Device {$action}d.");
    }
}
