<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\PunchCorrection;
use App\Models\RemotePermission;
use App\Services\Approvals;
use Illuminate\Http\Request;
use RuntimeException;

/** One inbox for everything waiting on an approver: devices (incl. replacements), remote requests, punch corrections. */
class ApprovalsController extends Controller
{
    public static function pending(Request $r): array
    {
        $scope = $r->user()->officeScope();
        $q = fn ($m) => $m::with('employee.office')->where('status', 'pending')
            ->when($scope !== null, fn ($x) => $x->whereHas('employee', fn ($e) => $e->whereIn('office_id', $scope)));

        return [
            'devices' => $q(Device::class)->with('replaces')->oldest()->get(),
            'remote' => $q(RemotePermission::class)->oldest()->get(),
            'corrections' => $q(PunchCorrection::class)->oldest()->get(),
        ];
    }

    public function index(Request $request)
    {
        return view('portal.approvals', self::pending($request));
    }

    private function inScope(Request $r, $item): void
    {
        $scope = $r->user()->officeScope();
        abort_if($scope !== null && ! in_array((int) $item->employee->office_id, array_map('intval', $scope), true), 403);
    }

    public function remote(Request $request, RemotePermission $permission)
    {
        $this->inScope($request, $permission);
        if ($permission->status !== 'pending') {
            return back()->withErrors(['remote' => "Remote request {$permission->ref} is already {$permission->status}."]);
        }
        if ($request->filled('lat')) { // approver may fix / add the location before approving
            $d = $request->validate(['lat' => 'numeric|between:-90,90', 'lng' => 'required_with:lat|numeric|between:-180,180', 'radius_m' => 'nullable|integer|between:50,5000']);
            $permission->update($d + ['radius_m' => $d['radius_m'] ?? $permission->radius_m ?? 250, 'location_label' => $permission->location_label ?? 'Set by approver']);
        }
        if ($request->boolean('location_waived')) {
            $permission->update(['location_waived' => true]);
        }
        try {
            Approvals::decideRemote($permission, $request->input('decision') === 'approve', $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['remote' => $e->getMessage()]);
        }

        return back()->with('ok', "Remote request {$permission->ref} {$permission->status}.");
    }

    public function correction(Request $request, PunchCorrection $correction)
    {
        $this->inScope($request, $correction);
        try {
            $event = Approvals::decideCorrection($correction, $request->input('decision') === 'approve', $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['correction' => $e->getMessage()]);
        }

        return back()->with('ok', $event ? "Correction approved — adjusted event {$event->ref} added." : 'Correction rejected.');
    }
}
