<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\RemotePermission;
use App\Services\Approvals;
use App\Services\AttendanceVerifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RemoteController extends Controller
{
    private function scopeQuery($q, Request $r)
    {
        $scope = $r->user()->officeScope();

        return $q->when($scope !== null, fn ($x) => $x->whereHas('employee', fn ($e) => $e->whereIn('office_id', $scope)));
    }

    public function index(Request $request)
    {
        $scope = $request->user()->officeScope();

        $all = fn () => $this->scopeQuery(RemotePermission::query(), $request);
        $today = today('Asia/Kolkata')->toDateString();

        return view('portal.remote.index', [
            'counts' => [
                'today' => $all()->where('status', 'approved')->whereDate('from_date', '<=', $today)->whereDate('to_date', '>=', $today)->count(),
                'pending' => $all()->where('status', 'pending')->count(),
                'waived' => $all()->where('status', 'approved')->where('location_waived', true)->whereDate('to_date', '>=', $today)->count(),
                'punches' => $this->scopeQuery(AttendanceEvent::whereNotNull('remote_permission_id'), $request)->where('recorded_at', '>=', now()->subDays(30))->count(),
            ],
            'perms' => $this->scopeQuery(RemotePermission::with('employee', 'decider'), $request)->latest()->paginate(50),
            'events' => $this->scopeQuery(AttendanceEvent::with('employee', 'remotePermission')->whereNotNull('remote_permission_id'), $request)->latest('recorded_at')->take(15)->get(),
            'employees' => Employee::where('status', 'active')->when($scope !== null, fn ($q) => $q->whereIn('office_id', $scope))->orderBy('name')->get(['id', 'name', 'employee_code']),
        ]);
    }

    /** Granted by an approver = approved immediately (they are the approver). */
    public function store(Request $request)
    {
        $scope = $request->user()->officeScope();
        if ($request->boolean('location_waived')) {
            $request->merge(['lat' => null, 'lng' => null, 'radius_m' => null, 'location_label' => null]); // waived = no location rule
        }
        $d = $request->validate([
            'employee_id' => ['required', Rule::in(Employee::when($scope !== null, fn ($q) => $q->whereIn('office_id', $scope))->pluck('id')->all())],
            'type' => ['required', Rule::in(array_keys(RemotePermission::TYPES))],
            'from_date' => 'required|date', 'to_date' => 'required|date|after_or_equal:from_date',
            'location_label' => 'nullable|string|max:120',
            'lat' => 'nullable|required_without:location_waived|numeric|between:-90,90', 'lng' => 'nullable|required_with:lat|numeric|between:-180,180',
            'radius_m' => 'nullable|integer|between:50,5000', 'reason' => 'required|string|max:255',
        ], ['lat.required_without' => 'Set the approved location, or tick “Waive location check”.']);
        $waived = $request->boolean('location_waived');
        $p = RemotePermission::create(array_merge($d, [
            'ref' => AttendanceVerifier::ref('RP'), 'location_waived' => $waived, 'status' => 'approved', 'requested_via' => 'portal',
            'decided_by' => $request->user()->id, 'decided_at' => now(), 'radius_m' => $waived ? null : ($d['radius_m'] ?? 250),
        ], $waived ? ['lat' => null, 'lng' => null] : []));
        AuditLog::record('remote.granted', $p->ref, ['employee_id' => $p->employee_id, 'waived' => $waived]);
        Approvals::remoteApproved($p->load('employee'));

        return back()->with('ok', "Permission {$p->ref} granted to {$p->employee->name}.");
    }

    public function revoke(RemotePermission $permission)
    {
        abort_unless($permission->status === 'approved', 422);
        $permission->update(['status' => 'revoked', 'decided_by' => auth()->id(), 'decided_at' => now()]);
        AuditLog::record('remote.revoked', $permission->ref);

        return back()->with('ok', "Permission {$permission->ref} revoked.");
    }
}
