@extends('layouts.app', ['title' => 'API usage', 'crumb' => 'Platform · last 24 hours'])
@section('content')
@php($total = $byTenant->sum('total'))
@php($errors24 = $byTenant->sum('errors'))
@php($avg = $total ? round($byTenant->sum(fn ($r) => $r->avg_ms * $r->total) / $total) : null)
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <x-stat label="Requests · 24 h" :value="number_format($total)" icon="code" tone="teal" :sub="$byTenant->count().' companies calling'" />
  <x-stat label="Error rate" :value="$total ? round(100 * $errors24 / $total, 1).'%' : '—'" icon="alert" :tone="$total && $errors24 / $total > .05 ? 'red' : 'navy'" :sub="number_format($errors24).' responses ≥ 400 · '.number_format($byTenant->sum('limited')).' rate-limited'" />
  <x-stat label="Average time" :value="$avg === null ? '—' : $avg.' ms'" icon="clock" tone="sky" sub="Server time per request" />
  <x-stat label="Webhooks" :value="$pending" icon="globe" :tone="$failed->count() ? 'amber' : 'navy'" :sub="'pending · '.$failed->count().' recent failures'" />
</div>

<div class="card overflow-x-auto"><div class="card-h"><div class="card-t">By company</div><span class="text-xs text-slate-500">{{ $keys->sum() }} active API keys in total</span></div>
  <table class="tbl"><tr><th>Company</th><th class="text-right">Requests</th><th class="w-1/4">Share</th><th class="text-right">Errors</th><th class="text-right">Rate-limited</th><th class="text-right">Avg time</th><th class="text-right">Active keys</th></tr>
  @forelse($byTenant as $r)<tr><td>@if(isset($tenants[$r->tenant_id]))<a class="font-medium text-navy hover:text-teal" href="{{ route('admin.companies.show', $r->tenant_id) }}">{{ $tenants[$r->tenant_id] }}</a>@else — @endif</td>
    <td class="text-right">{{ number_format($r->total) }}</td>
    <td><div class="h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-teal" style="width: {{ round(100 * $r->total / max(1, $total)) }}%"></div></div></td>
    <td class="text-right {{ $r->errors ? 'text-red-700' : '' }}">{{ number_format($r->errors) }}</td>
    <td class="text-right">{{ number_format($r->limited) }}</td><td class="text-right">{{ round($r->avg_ms) }} ms</td><td class="text-right">{{ $keys[$r->tenant_id] ?? 0 }}</td></tr>
  @empty<tr><td colspan="7"><x-empty icon="code" title="No API traffic" text="No company called the API in the last 24 hours." class="py-6" /></td></tr>@endforelse</table></div>

<div class="card overflow-x-auto"><div class="card-h"><div><div class="card-t">Failed webhook deliveries</div><div class="text-xs text-slate-500">{{ $pending }} pending across all companies — delivered by <code>php artisan queue:work</code>.</div></div></div>
  <table class="tbl"><tr><th>When</th><th>Company</th><th>Event</th><th>Endpoint</th><th>Error</th></tr>
  @forelse($failed as $d)<tr><td class="text-xs whitespace-nowrap">{{ $d->created_at->timezone('Asia/Kolkata')->format('d M H:i') }}</td><td>{{ $tenants[$d->tenant_id] ?? '—' }}</td><td class="text-xs font-mono">{{ $d->event }}</td>
    <td class="text-xs break-all">{{ $d->endpoint?->url }}</td><td class="text-xs text-red-700">{{ $d->last_error }}</td></tr>
  @empty<tr><td colspan="5"><x-empty icon="check" title="No failed deliveries" text="Every webhook reached its endpoint." class="py-4" /></td></tr>@endforelse</table></div>
@endsection
