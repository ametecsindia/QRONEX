<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRejection;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Analytics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::query()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('slug', 'like', "%$s%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->withCount(['employees', 'offices'])
            ->with('subscription.planVersion.plan')
            ->orderBy(in_array($request->sort, ['name', 'created_at', 'status'], true) ? $request->sort : 'name', $request->dir === 'desc' ? 'desc' : 'asc')
            ->paginate(25)->withQueryString();

        $byStatus = Tenant::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $trialEnding = \App\Models\Subscription::where('status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->count();

        return view('admin.companies.index', compact('tenants', 'byStatus', 'trialEnding'));
    }

    public function create()
    {
        return view('admin.companies.create', ['plans' => Plan::where('is_active', true)->with('currentVersion')->orderBy('min_employees')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company' => 'required|string|max:150',
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Tenant::RESERVED_SLUGS), Rule::unique('tenants', 'slug')],
            'industry' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:80',
            'state' => 'nullable|string|max:80',
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'customer_type' => 'required|in:independent,smartprs,smartept,smartdcm',
            'employees' => 'required|integer|min:1|max:100000',
            'billing_cycle' => 'required|in:monthly,quarterly,annual',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', Password::min(8)],
        ], ['slug.not_in' => 'That address is reserved. Choose another.', 'gstin.regex' => 'Enter a valid 15-character GSTIN.']);

        $plan = Plan::forEmployees((int) $data['employees']);
        if (! $plan) {
            return back()->withInput()->withErrors(['employees' => 'No plan is available for that team size.']);
        }
        $owner = Tenant::provision($data, $plan, $request->user()->id);
        $owner->forceFill(['email_verified_at' => now()])->save(); // created by us, no code to confirm

        return redirect()->route('admin.companies.show', $owner->tenant_id)->with('ok', "{$data['company']} created on a {$plan->name} trial. Owner signs in as {$data['email']}.");
    }

    public function show(Tenant $tenant)
    {
        $tenant->load(['subscription.planVersion.plan', 'settings', 'offices.gates', 'users', 'invoices' => fn ($q) => $q->latest('issued_on')]);
        $tenant->loadCount(['employees', 'devices', 'offices', 'invoices']);
        $audit = AuditLog::where('tenant_id', $tenant->id)->with('user')->latest('created_at')->take(20)->get();
        $a = Analytics::company($tenant->id, null);
        $usage = [
            'punches30' => AttendanceEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('recorded_at', '>=', now()->subDays(30))->count(),
            'rejected30' => AttendanceRejection::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('created_at', '>=', now()->subDays(30))->count(),
            'last' => AttendanceEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->max('recorded_at'),
            'pending_devices' => \App\Models\Device::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
            'paid' => (float) $tenant->invoices->where('status', 'paid')->sum('total'),
            'due' => (float) $tenant->invoices->whereIn('status', ['issued', 'failed'])->sum('total'),
        ];

        return view('admin.companies.show', compact('tenant', 'audit', 'a', 'usage'));
    }

    public function status(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['status' => 'required|in:trial,active,grace,suspended,cancelled', 'reason' => 'required|string|max:255']);
        $old = $tenant->status;
        $tenant->update(['status' => $data['status']]);
        $tenant->subscription?->update(['status' => $data['status']]);
        AuditLog::record('tenant.status', $tenant->slug, ['from' => $old, 'to' => $data['status'], 'reason' => $data['reason']], $tenant->id);

        return back()->with('ok', "Status changed to {$data['status']}.");
    }

    public function terms(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'customer_type' => 'required|in:independent,smartprs,smartept,smartdcm',
            'arrangement' => 'required|in:published,discount,complimentary,custom',
            'discount_pct' => 'nullable|numeric|min:0|max:100|required_if:arrangement,discount',
            'custom_monthly' => 'nullable|numeric|min:0|required_if:arrangement,custom',
            'commercial_note' => 'nullable|string|max:500',
            'api_enabled' => 'nullable|in:0,1',
        ]);

        $tenant->update(['customer_type' => $data['customer_type'], 'commercial_note' => $data['commercial_note'],
            'api_enabled' => ($data['api_enabled'] ?? null) === null ? null : (bool) $data['api_enabled']]);
        $tenant->subscription?->update([
            'arrangement' => $data['arrangement'],
            'discount_pct' => $data['arrangement'] === 'discount' ? $data['discount_pct'] : 0,
            'custom_monthly' => $data['arrangement'] === 'custom' ? $data['custom_monthly'] : null,
        ]);
        AuditLog::record('tenant.terms', $tenant->slug, $data, $tenant->id);

        return back()->with('ok', 'Commercial terms saved.');
    }
}
