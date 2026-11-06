@extends('layouts.app', ['title' => 'Edit plan · '.$plan->name])
@section('content')
<form method="post" action="{{ route('admin.plans.update', $plan) }}" class="card grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  @csrf
  <x-field name="name" label="Name" :value="$plan->name" required />
  <x-field class="sm:col-span-2 lg:col-span-3" name="description" label="Description" :value="$plan->description" />
  <x-field name="min_employees" label="Min employees" type="number" :value="$plan->min_employees" required />
  <x-field name="max_employees" label="Max employees" type="number" :value="$plan->max_employees" required />
  <div><label class="label">Pricing model</label><select name="pricing_model" class="input">@foreach(['per_employee' => 'Per employee', 'slab' => 'Fixed monthly slab', 'custom' => 'Custom / negotiated'] as $k => $l)<option value="{{ $k }}" @selected(old('pricing_model', $v->pricing_model) === $k)>{{ $l }}</option>@endforeach</select></div>
  <x-field name="per_employee_price" label="₹ per employee / month" type="number" step="0.01" :value="+$v->per_employee_price" required />
  <x-field name="slab_price" label="₹ slab / month" type="number" step="0.01" :value="+$v->slab_price" required />
  <x-field name="min_billing" label="Minimum billing ₹ / month" type="number" step="0.01" :value="+$v->min_billing" required />
  <x-field name="quarterly_discount_pct" label="Quarterly discount %" type="number" step="0.01" :value="+$v->quarterly_discount_pct" required />
  <x-field name="annual_discount_pct" label="Annual discount %" type="number" step="0.01" :value="+$v->annual_discount_pct" required />
  <x-field name="onboarding_fixed" label="Onboarding fixed ₹" type="number" step="0.01" :value="+$v->onboarding_fixed" required />
  <x-field name="onboarding_per_employee" label="Onboarding ₹ / employee" type="number" step="0.01" :value="+$v->onboarding_per_employee" required />
  <x-field name="trial_days" label="Trial days" type="number" :value="$v->trial_days" required />
  <x-field name="sms_allowance" label="SMS / month" type="number" :value="$v->sms_allowance" required />
  <div class="flex flex-col gap-2 text-sm pt-5">
    <label><input type="checkbox" name="api_included" value="1" @checked(old('api_included', $v->api_included))> API included</label>
    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))> Active (shown publicly)</label>
    <label><input type="checkbox" name="is_popular" value="1" @checked(old('is_popular', $plan->is_popular))> Mark as popular</label>
  </div>
  <x-field class="sm:col-span-2 lg:col-span-4" name="note" label="Change note (required, audited)" required placeholder="e.g. Founder-approved launch pricing" />
  <div class="col-span-full flex gap-2"><button class="btn">Save as v{{ $v->version + 1 }}</button><a class="btn-ghost" href="{{ route('admin.plans.index') }}">Cancel</a></div>
</form>
<div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Version history</h2>
  <table class="tbl"><tr><th>Version</th><th>Model</th><th class="text-right">₹/emp</th><th class="text-right">Slab</th><th class="text-right">Min</th><th class="text-right">Onboarding</th><th>Note</th><th>Created</th></tr>
  @foreach($plan->versions as $h)<tr><td>v{{ $h->version }}</td><td>{{ $h->pricing_model }}</td><td class="text-right">{{ +$h->per_employee_price }}</td><td class="text-right">{{ number_format($h->slab_price) }}</td><td class="text-right">{{ +$h->min_billing }}</td><td class="text-right">{{ number_format($h->onboarding_fixed) }} + {{ +$h->onboarding_per_employee }}/emp</td><td>{{ $h->note }}</td><td class="text-xs">{{ $h->created_at->format('d M Y H:i') }}</td></tr>@endforeach
  </table></div>
@endsection
