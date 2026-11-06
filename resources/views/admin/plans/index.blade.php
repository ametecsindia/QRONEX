@extends('layouts.app', ['title' => 'Plans & pricing'])
@section('content')
<p class="text-sm text-slate-600">Editing a plan creates a new immutable price version. Existing subscriptions stay on the version they bought. <b>Prices shown are demonstration values pending founder approval.</b></p>
<div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
@foreach($plans as $p)
  @php($v = $p->currentVersion)
  @php($q = $v ? $calc->quote($v, max($p->min_employees, min(40, $p->max_employees))) : null)
  <div class="card space-y-2 {{ $p->is_popular ? 'ring-2 ring-teal' : '' }}">
    <div class="flex items-center justify-between"><h2 class="font-bold text-navy text-lg">{{ $p->name }}</h2>@unless($p->is_active)<span class="chip bg-slate-200">inactive</span>@endunless</div>
    <div class="text-xs text-slate-500">{{ $p->description }} · {{ $p->min_employees }}–{{ $p->max_employees >= 100000 ? '∞' : $p->max_employees }} employees</div>
    <div class="text-sm">
      @if($v->pricing_model === 'per_employee')₹{{ +$v->per_employee_price }}/employee/month · min ₹{{ +$v->min_billing }}
      @elseif($v->pricing_model === 'slab')₹{{ number_format($v->slab_price) }}/month slab
      @else Custom contract @endif
    </div>
    <div class="text-xs text-slate-600">Onboarding ₹{{ number_format($v->onboarding_fixed) }}@if($v->onboarding_per_employee > 0) + ₹{{ +$v->onboarding_per_employee }}/employee @endif · Q −{{ +$v->quarterly_discount_pct }}% · A −{{ +$v->annual_discount_pct }}% · {{ $v->trial_days }}-day trial · {{ number_format($v->sms_allowance) }} SMS · API {{ $v->api_included ? 'included' : 'add-on' }}</div>
    @if($q)<div class="text-xs bg-slate-50 rounded p-2">Example {{ max($p->min_employees, min(40, $p->max_employees)) }} employees monthly: ₹{{ number_format($q['subscription']) }} + onboarding ₹{{ number_format($q['onboarding']) }} + GST ₹{{ number_format($q['gst']) }} = <b>₹{{ number_format($q['total']) }}</b></div>@endif
    <div class="flex items-center justify-between pt-1"><span class="text-xs text-slate-500">v{{ $v->version }} · {{ $p->versions_count }} version(s)</span>
      @can('manage-platform')<a class="btn-ghost btn-sm" href="{{ route('admin.plans.edit', $p) }}">Edit</a>@endcan</div>
  </div>
@endforeach
</div>
@endsection
