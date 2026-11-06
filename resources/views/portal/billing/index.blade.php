@extends('layouts.app', ['title' => 'Subscription & billing'])
@section('content')
<div class="grid lg:grid-cols-3 gap-4">
  <div class="card space-y-1 text-sm">
    <h2 class="font-semibold text-navy">Current subscription</h2>
    @if($sub)
      <div class="text-lg font-bold text-navy">{{ $sub->planVersion->plan->name }} <span class="text-xs text-slate-500">v{{ $sub->planVersion->version }}</span></div>
      <div>{{ ucfirst($sub->billing_cycle) }} · {{ $sub->employee_count }} employees · <x-status :s="$sub->status" /></div>
      @if($sub->status === 'trial')<div>Trial ends {{ $sub->trial_ends_at?->timezone('Asia/Kolkata')->format('d M Y') }}</div>@endif
      @if($sub->current_period_end)<div>Paid until {{ $sub->current_period_end->format('d M Y') }}</div>@endif
      @if($sub->arrangement !== 'published')<div class="text-amber-700">Special terms: {{ $sub->arrangement }}</div>@endif
    @else <p class="text-slate-500">No subscription.</p> @endif
    @if(in_array($tenant->status, ['grace', 'suspended']))<div class="rounded bg-amber-50 border border-amber-200 text-amber-800 p-2 mt-2">Your account is in <b>{{ $tenant->status }}</b>. Pay below to restore full access.</div>@endif
  </div>

  <div class="card lg:col-span-2 space-y-3">
    <h2 class="font-semibold text-navy">{{ $sub?->status === 'active' ? 'Renew or change plan' : 'Choose your plan' }}</h2>
    <form method="get" class="flex flex-wrap gap-3 items-end">
      <div><label class="label">Employees</label><input type="number" name="employees" min="1" value="{{ $employees }}" class="input w-32" onchange="this.form.submit()"></div>
      <div><label class="label">Billing cycle</label><select name="cycle" class="input" onchange="this.form.submit()">@foreach(['monthly', 'quarterly', 'annual'] as $c)<option @selected($cycle === $c)>{{ $c }}</option>@endforeach</select></div>
      <noscript><button class="btn-ghost">Update</button></noscript>
    </form>
    @if($quote)
      <table class="tbl max-w-lg">
        <tr><td>{{ $plan->name }} plan · {{ $employees }} employees · {{ $cycle }}@if($quote['discount_pct']) (−{{ +$quote['discount_pct'] }}%)@endif</td><td class="text-right">₹{{ number_format($quote['subscription']) }}</td></tr>
        <tr><td>One-time onboarding</td><td class="text-right">₹{{ number_format($quote['onboarding']) }}</td></tr>
        <tr><td>GST @ {{ +$quote['gst_pct'] }}%</td><td class="text-right">₹{{ number_format($quote['gst']) }}</td></tr>
        <tr class="font-bold"><td>Total payable</td><td class="text-right">₹{{ number_format($quote['total']) }}</td></tr>
      </table>
      <form method="post" action="{{ route('portal.billing.checkout') }}" class="flex items-center gap-3">
        @csrf <input type="hidden" name="employees" value="{{ $employees }}"><input type="hidden" name="cycle" value="{{ $cycle }}">
        <button class="btn" @disabled($quote['total'] <= 0)>Pay ₹{{ number_format($quote['total']) }}</button>
        <span class="text-xs text-slate-500">Payments: {{ $mode }} · UPI, cards, netbanking via Razorpay</span>
      </form>
    @else
      <p class="text-sm">Above 500 employees we prepare a custom contract with volume pricing. <a class="text-teal font-semibold" href="mailto:{{ config('qronex.supplier.email') }}">Contact sales</a></p>
    @endif
  </div>
</div>

<div class="grid lg:grid-cols-2 gap-4">
  <div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Invoices</h2>
    <table class="tbl"><tr><th>Number</th><th>Date</th><th class="text-right">Total</th><th>Status</th><th></th></tr>
    @forelse($invoices as $i)<tr><td>{{ $i->number }}</td><td>{{ $i->issued_on->format('d M Y') }}</td><td class="text-right">₹{{ number_format($i->total, 2) }}</td><td><x-status :s="$i->status" /></td>
      <td class="text-right"><a class="btn-ghost btn-sm" href="{{ route('portal.billing.invoice', $i) }}" target="_blank">View / print</a></td></tr>
    @empty<tr><td colspan="5" class="text-slate-500">No invoices yet.</td></tr>@endforelse</table></div>
  <div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Payment attempts</h2>
    <table class="tbl"><tr><th>When</th><th>Order</th><th class="text-right">Amount</th><th>Status</th></tr>
    @forelse($payments as $p)<tr><td class="text-xs">{{ $p->created_at->timezone('Asia/Kolkata')->format('d M H:i') }}</td><td class="text-xs font-mono">{{ $p->order_id }}</td><td class="text-right">₹{{ number_format($p->rupees(), 2) }}</td>
      <td><x-status :s="$p->status === 'created' ? 'pending' : $p->status" />@if($p->failure_reason)<div class="text-xs text-slate-500">{{ $p->failure_reason }}</div>@endif</td></tr>
    @empty<tr><td colspan="4" class="text-slate-500">None.</td></tr>@endforelse</table></div>
</div>
@endsection
