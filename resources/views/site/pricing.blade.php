@extends('layouts.site', ['title' => 'Pricing'])
@section('content')
<section class="max-w-6xl mx-auto px-4 py-12 space-y-8">
  <div><h1 class="text-3xl font-bold text-navy">Pricing</h1><p class="text-slate-600 mt-1">Per employee, billed monthly, quarterly or yearly. Prices exclude GST.</p></div>
  <div class="grid lg:grid-cols-3 gap-6 items-start">
    <form class="card space-y-4 lg:col-span-1">
      <div><label class="label" for="emp">Employees</label>
        <input id="emp" type="number" name="employees" min="1" value="{{ $employees }}" class="input"></div>
      <div><label class="label">Billing cycle</label><select name="cycle" class="input">@foreach(['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'] as $k => $l)<option value="{{ $k }}" @selected($cycle === $k)>{{ $l }}</option>@endforeach</select></div>
      <button class="btn w-full justify-center">Calculate</button>
    </form>
    <div class="card lg:col-span-2">
      @if($quote)
        <div class="text-sm text-slate-500">{{ $employees }} employees → <b class="text-navy">{{ $plan->name }}</b> plan, {{ $cycle }}</div>
        <table class="tbl mt-3 max-w-lg">
          <tr><td>Subscription ({{ $quote['months'] }} month{{ $quote['months'] > 1 ? 's' : '' }}@if($quote['discount_pct']) · −{{ +$quote['discount_pct'] }}%@endif)</td><td class="text-right">₹{{ number_format($quote['subscription']) }}</td></tr>
          <tr><td>One-time onboarding</td><td class="text-right">₹{{ number_format($quote['onboarding']) }}</td></tr>
          <tr><td>GST @ {{ +$quote['gst_pct'] }}%</td><td class="text-right">₹{{ number_format($quote['gst']) }}</td></tr>
          <tr class="font-bold"><td>Total first payment</td><td class="text-right">₹{{ number_format($quote['total']) }}</td></tr>
        </table>
        <a class="btn mt-4" href="{{ route('register') }}">Start {{ $plan->currentVersion->trial_days }}-day free trial</a>
      @else
        <p>Above 500 employees we prepare a custom contract with volume pricing and dedicated onboarding.</p><a class="btn mt-3" href="{{ route('contact') }}">Talk to sales</a>
      @endif
    </div>
  </div>
  <div class="card overflow-x-auto"><table class="tbl"><tr><th>Plan</th><th>Team size</th><th>Price</th><th>Min / month</th><th>Onboarding</th><th>Quarterly / annual</th><th>API</th><th>SMS / month</th></tr>
  @foreach($plans as $p)@php($v = $p->currentVersion)<tr><td class="font-semibold text-navy">{{ $p->name }}</td><td>{{ $p->min_employees }}–{{ $p->max_employees >= 100000 ? '∞' : $p->max_employees }}</td>
    <td>@if($v->pricing_model === 'per_employee')₹{{ +$v->per_employee_price }}/employee @elseif($v->pricing_model === 'slab')₹{{ number_format($v->slab_price) }} slab @else Custom @endif</td>
    <td>{{ $v->min_billing > 0 ? '₹'.number_format($v->min_billing) : '—' }}</td><td>₹{{ number_format($v->onboarding_fixed) }}@if($v->onboarding_per_employee > 0) + ₹{{ +$v->onboarding_per_employee }}/emp @endif</td>
    <td>−{{ +$v->quarterly_discount_pct }}% / −{{ +$v->annual_discount_pct }}%</td><td>{{ $v->api_included ? 'Included' : 'Add-on' }}</td><td>{{ number_format($v->sms_allowance) }}</td></tr>@endforeach</table></div>
</section>
@endsection
