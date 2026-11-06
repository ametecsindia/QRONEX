<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Office;
use App\Services\Analytics;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $scope = $user->officeScope();
        $tenant = $user->tenant->load('subscription.planVersion.plan');
        $pending = collect(ApprovalsController::pending(request()))->map->count();

        return view('portal.dashboard', [
            'tenant' => $tenant,
            'offices' => Office::when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->withCount(['gates', 'employees'])->orderBy('name')->get(),
            'a' => Analytics::company($tenant->id, $scope),
            'pending' => $pending,
            'audit' => AuditLog::where('tenant_id', $tenant->id)->with('user')->latest('created_at')->take(8)->get(),
        ]);
    }
}
