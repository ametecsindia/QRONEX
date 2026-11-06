<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRejection;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Analytics;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $since30 = now()->subDays(30);
        $paid = fn ($from, $to = null) => (float) Invoice::where('status', 'paid')->where('issued_on', '>=', $from->toDateString())
            ->when($to, fn ($q) => $q->where('issued_on', '<', $to->toDateString()))->sum('total');

        return view('admin.dashboard', [
            'byStatus' => Tenant::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'tenants' => Tenant::count(),
            'newTenants30' => Tenant::where('created_at', '>=', $since30)->count(),
            'employees' => Employee::where('status', 'active')->count(),
            'pendingDevices' => Device::where('status', 'pending')->count(),
            'revenue30' => $paid($since30),
            'revenuePrev30' => $paid(now()->subDays(60), $since30),
            'revenue' => (float) Invoice::where('status', 'paid')->sum('total'),
            'planMix' => Subscription::whereIn('subscriptions.status', ['trial', 'active', 'grace'])->join('plan_versions', 'plan_versions.id', '=', 'subscriptions.plan_version_id')
                ->join('plans', 'plans.id', '=', 'plan_versions.plan_id')->selectRaw('plans.name n, count(*) c')->groupBy('plans.name')->pluck('c', 'n'),
            'punches' => Analytics::dailyCounts(AttendanceEvent::class, 'recorded_at'),
            'rejectedSeries' => Analytics::dailyCounts(AttendanceRejection::class, 'created_at'),
            'punchesToday' => AttendanceEvent::where('recorded_at', '>=', now(Analytics::TZ)->startOfDay()->utc())->count(),
            'rejectedWeek' => AttendanceRejection::where('created_at', '>=', now()->subDays(7))
                ->selectRaw('reason_code, count(*) c')->groupBy('reason_code')->orderByDesc('c')->pluck('c', 'reason_code'),
            'openTickets' => \App\Models\SupportTicket::where('status', 'open')->count(),
            'recent' => Tenant::latest()->with('subscription.planVersion.plan')->withCount('employees')->take(6)->get(),
            'audit' => AuditLog::with('user', 'tenant')->latest('created_at')->take(10)->get(),
        ]);
    }
}
