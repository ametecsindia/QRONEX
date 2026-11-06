<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function index(PriceCalculator $calc)
    {
        $plans = Plan::with('currentVersion')->withCount('versions')->orderBy('sort')->get();

        return view('admin.plans.index', compact('plans', 'calc'));
    }

    public function edit(Plan $plan)
    {
        $plan->load(['versions' => fn ($q) => $q->orderByDesc('version')]);

        return view('admin.plans.edit', ['plan' => $plan, 'v' => $plan->versions->first()]);
    }

    /** Never edits a version in place: writes plan identity + a new immutable version. */
    public function update(Request $request, Plan $plan)
    {
        $d = $request->validate([
            'name' => 'required|string|max:60',
            'description' => 'nullable|string|max:190',
            'min_employees' => 'required|integer|min:1',
            'max_employees' => 'required|integer|gte:min_employees',
            'pricing_model' => 'required|in:per_employee,slab,custom',
            'per_employee_price' => 'required|numeric|min:0',
            'slab_price' => 'required|numeric|min:0',
            'min_billing' => 'required|numeric|min:0',
            'quarterly_discount_pct' => 'required|numeric|min:0|max:100',
            'annual_discount_pct' => 'required|numeric|min:0|max:100',
            'onboarding_fixed' => 'required|numeric|min:0',
            'onboarding_per_employee' => 'required|numeric|min:0',
            'trial_days' => 'required|integer|min:0|max:90',
            'sms_allowance' => 'required|integer|min:0',
            'note' => 'required|string|max:190',
        ]);
        $d['api_included'] = $request->boolean('api_included');

        DB::transaction(function () use ($plan, $d, $request) {
            $plan->update([
                'name' => $d['name'], 'description' => $d['description'], 'min_employees' => $d['min_employees'],
                'max_employees' => $d['max_employees'], 'is_active' => $request->boolean('is_active'), 'is_popular' => $request->boolean('is_popular'),
            ]);
            $next = (int) \App\Models\PlanVersion::where('plan_id', $plan->id)->lockForUpdate()->max('version') + 1;
            $plan->versions()->create(collect($d)->only([...\App\Models\PlanVersion::PRICE_FIELDS, 'note'])->all() + ['version' => $next, 'created_by' => auth()->id()]);
            AuditLog::record('plan.version_created', "{$plan->code} v{$next}", ['note' => $d['note']]);
        });

        return redirect()->route('admin.plans.index')->with('ok', "{$plan->name}: new price version saved. Existing subscriptions keep their locked version.");
    }
}
