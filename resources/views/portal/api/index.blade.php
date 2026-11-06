@extends('layouts.app', ['title' => 'API & webhooks'])
@section('actions')<a class="btn-ghost" href="{{ route('docs.api') }}" target="_blank">API reference</a>@endsection
@section('content')
<div class="card text-sm flex flex-wrap gap-x-6 gap-y-1">
  <span>API access: @if($allowed)<span class="chip bg-emerald-100 text-emerald-800">Included</span>@else<span class="chip bg-amber-100 text-amber-800">Not in your plan</span> <a class="text-teal font-semibold" href="{{ route('portal.billing.index') }}">Upgrade</a>@endif</span>
  <span>Base URL <code class="text-xs">{{ url('/api/v1') }}</code></span>
  <span>Integrations can read and export attendance, never create punches.</span>
</div>
@foreach(['newKey' => 'New API key — copy it now, it is shown only once:', 'newSecret' => 'Webhook signing secret — copy it now:'] as $k => $label)
  @if(session($k))<div class="card border-teal" x-data="{c:false}"><div class="text-sm font-semibold text-navy mb-1">{{ $label }}</div>
    <div class="flex gap-2 items-center"><code class="text-sm bg-slate-100 rounded px-2 py-1 break-all">{{ session($k) }}</code>
    <button type="button" class="btn-ghost btn-sm" @click="navigator.clipboard?.writeText(@js(session($k))); c=true" x-text="c?'Copied':'Copy'">Copy</button></div></div>@endif
@endforeach
<div class="flex gap-2">
  @foreach(['keys' => 'Keys & scopes', 'webhooks' => 'Webhooks', 'usage' => 'Usage'] as $k => $l)<a href="{{ route('portal.api.index', ['tab' => $k]) }}" class="{{ $tab === $k ? 'btn' : 'btn-ghost' }} btn-sm">{{ $l }}</a>@endforeach
</div>

@if($tab === 'keys')
<div class="card overflow-x-auto">
  <table class="tbl"><tr><th>Name</th><th>Scopes</th><th>Created</th><th>Last used</th><th>Status</th><th></th></tr>
  @forelse($keys as $k)<tr class="{{ $k->revoked_at ? 'opacity-50' : '' }}">
    <td><b>{{ $k->name }}</b><div class="text-xs font-mono text-slate-500">{{ $k->prefix }}••••</div></td>
    <td class="text-xs">@foreach($k->scopes as $s)<span class="chip bg-teal-soft text-teal mr-1 mb-1">{{ $s }}</span>@endforeach</td>
    <td class="text-xs">{{ $k->created_at->timezone('Asia/Kolkata')->format('d M Y') }}</td>
    <td class="text-xs">{{ $k->last_used_at?->diffForHumans() ?? 'never' }}</td>
    <td>@if($k->revoked_at)<x-status s="revoked" />@else<x-status s="active" />@endif</td>
    <td class="text-right whitespace-nowrap">@unless($k->revoked_at)
      <form method="post" action="{{ route('portal.api.keys.rotate', $k) }}" class="inline" onsubmit="return confirm('Rotate? The current key stops working immediately.')">@csrf<button class="btn-ghost btn-sm">Rotate</button></form>
      <form method="post" action="{{ route('portal.api.keys.revoke', $k) }}" class="inline" onsubmit="return confirm('Revoke this key?')">@csrf<button class="btn-ghost btn-sm">Revoke</button></form>@endunless</td></tr>
  @empty<tr><td colspan="6" class="text-slate-500 text-center py-6">No API keys yet.</td></tr>@endforelse
  </table>
</div>
<form method="post" action="{{ route('portal.api.keys.store') }}" class="card space-y-3 max-w-3xl">
  @csrf <h2 class="font-semibold text-navy">Create API key</h2>
  <x-field name="name" label="Key name" placeholder="SmartPRS payroll sync" required />
  <div class="grid sm:grid-cols-2 gap-2 text-sm">@foreach(\App\Models\ApiKey::SCOPES as $s => $d)
    <label class="flex items-start gap-2"><input type="checkbox" name="scopes[]" value="{{ $s }}" class="mt-1" @checked(str_ends_with($s, ':read'))><span><code class="text-xs">{{ $s }}</code><br><span class="text-xs text-slate-500">{{ $d }}</span></span></label>@endforeach</div>
  <button class="btn" @disabled(! $allowed)>Create key</button>
</form>
@endif

@if($tab === 'webhooks')
<div class="card overflow-x-auto">
  <table class="tbl"><tr><th>Endpoint</th><th>Events</th><th>Status</th><th></th></tr>
  @forelse($endpoints as $w)<tr>
    <td class="break-all"><b>{{ $w->description ?: 'Endpoint' }}</b><div class="text-xs font-mono">{{ $w->url }}</div>@if($w->failed_count)<div class="text-xs text-red-700">{{ $w->failed_count }} failed deliveries</div>@endif</td>
    <td class="text-xs">@foreach($w->events as $e)<span class="chip bg-teal-soft text-teal mr-1">{{ $e }}</span>@endforeach</td>
    <td>@if($w->is_active)<x-status s="active" />@else<x-status s="inactive" />@endif</td>
    <td class="text-right whitespace-nowrap">@foreach(['ping' => 'Send test', 'toggle' => $w->is_active ? 'Disable' : 'Enable', 'roll' => 'Roll secret', 'delete' => 'Delete'] as $a => $l)
      <form method="post" action="{{ route('portal.api.endpoints.update', $w) }}" class="inline" @if(in_array($a, ['roll', 'delete'])) onsubmit="return confirm('{{ $l }}?')" @endif>@csrf<input type="hidden" name="action" value="{{ $a }}"><button class="btn-ghost btn-sm">{{ $l }}</button></form>@endforeach</td></tr>
  @empty<tr><td colspan="4" class="text-slate-500 text-center py-6">No webhook endpoints.</td></tr>@endforelse
  </table>
</div>
<form method="post" action="{{ route('portal.api.endpoints.store') }}" class="card space-y-3 max-w-3xl">
  @csrf <h2 class="font-semibold text-navy">Add endpoint</h2>
  <x-field name="url" label="HTTPS URL" type="url" placeholder="https://hrms.example.com/hooks/qronex" required />
  <x-field name="description" label="Description" placeholder="HRMS attendance sync" />
  <div class="space-y-1 text-sm">@foreach(\App\Models\WebhookEndpoint::EVENTS as $e => $d)<label class="flex items-center gap-2"><input type="checkbox" name="events[]" value="{{ $e }}" @checked($e === 'attendance.created')><code class="text-xs">{{ $e }}</code><span class="text-xs text-slate-500">{{ $d }}</span></label>@endforeach</div>
  <p class="text-xs text-slate-500">Each request carries <code>X-Qronex-Signature: t=…,v1=HMAC-SHA256(secret, "t.body")</code>. Reply 2xx within 10 s; failures retry with backoff for about 24 hours.</p>
  <button class="btn" @disabled(! $allowed)>Add endpoint</button>
</form>
<div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Delivery log</h2>
  <table class="tbl"><tr><th>When</th><th>Event</th><th>Delivery</th><th>Response</th><th class="text-right">Latency</th><th class="text-right">Attempts</th><th></th></tr>
  @forelse($deliveries as $d)<tr><td class="text-xs whitespace-nowrap">{{ $d->created_at->timezone('Asia/Kolkata')->format('d M H:i:s') }}</td><td class="text-xs font-mono">{{ $d->event }}</td><td class="text-xs font-mono">{{ $d->uuid }}</td>
    <td><span class="chip {{ ['delivered' => 'bg-emerald-100 text-emerald-800', 'failed' => 'bg-red-100 text-red-800'][$d->status] ?? 'bg-amber-100 text-amber-800' }}">{{ $d->status }}</span> <span class="text-xs">{{ $d->response_code }}</span>@if($d->last_error)<div class="text-xs text-slate-500">{{ $d->last_error }}</div>@endif</td>
    <td class="text-right text-xs">{{ $d->latency_ms !== null ? $d->latency_ms.' ms' : '—' }}</td><td class="text-right text-xs">{{ $d->attempts }}</td>
    <td class="text-right">@if($d->status === 'failed')<form method="post" action="{{ route('portal.api.deliveries.retry', $d) }}">@csrf<button class="btn-ghost btn-sm">Retry</button></form>@endif</td></tr>
  @empty<tr><td colspan="7" class="text-slate-500">No deliveries yet.</td></tr>@endforelse</table>
  <p class="text-xs text-slate-500 mt-2">Deliveries are sent by the queue worker (<code>php artisan queue:work</code>). “pending” with 0 attempts means the worker is not running.</p></div>
@endif

@if($tab === 'usage')
@php($max = max(1, $days->max('total')))
<div class="card"><h2 class="font-semibold text-navy mb-3">Requests · last 14 days (UTC)</h2>
  <div class="flex items-end gap-1 h-40" role="img" aria-label="Daily API requests">
  @foreach($days as $d => $v)<div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ $d }}: {{ $v['total'] }} requests, {{ $v['errors'] }} errors">
    <div class="w-full bg-teal rounded-t" style="height: {{ max(2, round($v['total'] / $max * 100)) }}%"></div><div class="text-[10px] text-slate-500 mt-1">{{ \Illuminate\Support\Carbon::parse($d)->format('d') }}</div></div>@endforeach
  </div>
  <div class="text-sm mt-3">Total {{ number_format($days->sum('total')) }} · errors {{ number_format($days->sum('errors')) }} · limit {{ $keys->first()?->rate_limit_per_min ?? 120 }} requests/min per key</div></div>
<div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Recent requests</h2>
  <table class="tbl"><tr><th>When</th><th>Request</th><th>Status</th><th class="text-right">Time</th></tr>
  @forelse($recent as $r)<tr><td class="text-xs whitespace-nowrap">{{ $r->created_at->timezone('Asia/Kolkata')->format('d M H:i:s') }}</td><td class="text-xs font-mono">{{ $r->method }} {{ $r->path }}</td>
    <td><span class="chip {{ $r->status < 400 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">{{ $r->status }}</span></td><td class="text-right text-xs">{{ $r->ms }} ms</td></tr>
  @empty<tr><td colspan="4" class="text-slate-500">No requests yet.</td></tr>@endforelse</table></div>
@endif
@endsection
