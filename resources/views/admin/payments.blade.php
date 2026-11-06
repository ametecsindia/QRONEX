@extends('layouts.app', ['title' => 'Payments'])
@section('content')
<div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
  <x-stat label="Collected this month" :value="'₹'.number_format($stats['month'])" icon="cash" tone="teal" />
  <x-stat label="Collected this FY" :value="'₹'.number_format($stats['fy'])" icon="chart" :sub="'From 1 April · GST ₹'.number_format((float) $stats['gst_fy'])" />
  <x-stat label="Failed payments · 30 days" :value="$stats['failed30']" icon="alert" :tone="$stats['failed30'] ? 'red' : 'navy'" />
  <x-stat label="Companies overdue" :value="$stats['due']" icon="clock" :tone="$stats['due'] ? 'amber' : 'navy'" sub="In grace period" :href="route('admin.companies.index', ['status' => 'grace'])" />
  <x-stat label="Razorpay" :value="ucfirst($mode)" icon="card" :tone="$webhook ? 'teal' : 'amber'" :sub="$webhook ? 'Webhook secret set' : 'Webhook secret not set'" class="col-span-2 xl:col-span-1" />
</div>
<div class="card text-sm">Razorpay: <b>{{ $mode }}</b> · webhook secret {{ $webhook ? 'set' : 'NOT set' }} · webhook URL <code class="text-xs">{{ url('/api/razorpay/webhook') }}</code> (events: payment.captured, payment.failed, order.paid). Keys live in <code>.env</code>, never in the database.</div>
<div class="card overflow-x-auto">
  <table class="tbl"><tr><th>When</th><th>Company</th><th>Order / payment</th><th class="text-right">Amount</th><th>Status</th><th>Invoice</th></tr>
  @forelse($payments as $p)<tr>
    <td class="text-xs whitespace-nowrap">{{ $p->created_at->timezone('Asia/Kolkata')->format('d M H:i') }}</td>
    <td>{{ $tenants[$p->tenant_id] ?? '—' }}</td>
    <td class="text-xs font-mono">{{ $p->order_id }}<br>{{ $p->payment_id }}</td>
    <td class="text-right">₹{{ number_format($p->rupees(), 2) }}</td>
    <td><x-status :s="$p->status === 'created' ? 'pending' : $p->status" />@if($p->failure_reason)<div class="text-xs text-slate-500">{{ $p->failure_reason }}</div>@endif</td>
    <td class="text-xs">{{ $p->invoice?->number }}</td></tr>
  @empty<tr><td colspan="6" class="text-center text-slate-500 py-6">No payments yet.</td></tr>@endforelse
  </table>
  <div class="mt-3">{{ $payments->links() }}</div>
</div>
<div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Recent webhook events</h2>
  <table class="tbl"><tr><th>When</th><th>Event</th><th>Event id</th></tr>
  @forelse($events as $e)<tr><td class="text-xs">{{ $e->created_at->timezone('Asia/Kolkata')->format('d M H:i:s') }}</td><td>{{ $e->event }}</td><td class="text-xs font-mono">{{ $e->event_id }}</td></tr>
  @empty<tr><td colspan="3" class="text-slate-500">None received.</td></tr>@endforelse</table></div>
@endsection
