@extends('layouts.app', ['title' => 'QR displays', 'crumb' => 'Platform'])
@section('content')
@php($q = fn ($s) => route('admin.displays', array_filter(['state' => $s, 'company' => request('company')])))
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <x-stat label="Live now" :value="$counts['live']" icon="qr" tone="teal" sub="Heartbeat in the last minute" :href="$q('live')" />
  <x-stat label="Offline" :value="$counts['offline']" icon="alert" :tone="$counts['offline'] ? 'amber' : 'navy'" sub="Was set up, now silent" :href="$q('offline')" />
  <x-stat label="Never opened" :value="$counts['never']" icon="window" sub="Gate exists, screen not set up" :href="$q('never')" />
  <x-stat label="Inactive gates" :value="$counts['inactive']" icon="x" sub="Turned off by the company" :href="$q('inactive')" />
</div>

<form class="card flex flex-wrap items-end gap-3">
  <div><label class="label">Company</label><select name="company" class="input" onchange="this.form.submit()"><option value="">All companies</option>@foreach($companies as $id => $n)<option value="{{ $id }}" @selected((string) request('company') === (string) $id)>{{ $n }}</option>@endforeach</select></div>
  <div><label class="label">State</label><select name="state" class="input" onchange="this.form.submit()"><option value="">All</option>@foreach(['live' => 'Live', 'offline' => 'Offline', 'never' => 'Never opened', 'inactive' => 'Inactive'] as $k => $l)<option value="{{ $k }}" @selected(request('state') === $k)>{{ $l }}</option>@endforeach</select></div>
  @if(request('state') || request('company'))<a class="btn-ghost btn-sm" href="{{ route('admin.displays') }}">Clear</a>@endif
</form>

<div class="card overflow-x-auto">
  <table class="tbl"><tr><th>Status</th><th>Company</th><th>Office · gate</th><th>Last heartbeat</th><th>Screen</th></tr>
  @forelse($gates as $g)
    @php($live = $g->display_last_seen_at?->gt(now()->subMinute()))
    <tr><td>@if(! $g->is_active)<span class="chip bg-slate-100 text-slate-600">Inactive</span>@elseif($live)<span class="chip bg-emerald-100 text-emerald-800"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live</span>@elseif($g->display_last_seen_at)<span class="chip bg-amber-100 text-amber-800">Offline</span>@else<span class="chip bg-slate-100">Not set up</span>@endif</td>
      <td>@if($g->tenant)<a class="font-medium text-navy hover:text-teal" href="{{ route('admin.companies.show', $g->tenant) }}">{{ $g->tenant->name }}</a>@endif</td>
      <td>{{ $g->office?->name }} · <span class="text-slate-600">{{ $g->name }}</span></td>
      <td class="text-xs whitespace-nowrap">{{ $g->display_last_seen_at?->diffForHumans() ?? 'never' }}</td>
      <td class="text-xs text-slate-500 max-w-xs truncate" title="{{ $g->display_agent }}">{{ $g->display_agent ?: '—' }}</td></tr>
  @empty<tr><td colspan="5"><x-empty icon="qr" title="No gates" text="No QR display matches these filters." class="py-6" /></td></tr>
  @endforelse
  </table>
  <div class="mt-3">{{ $gates->links() }}</div>
</div>
@endsection
