@extends('layouts.app', ['title' => 'System'])
@section('content')
@php($fails = collect($checks)->where('status', 'fail')->count())
@php($warns = collect($checks)->where('status', 'warn')->count())
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <x-stat label="Go-live checks passing" :value="collect($checks)->where('status', 'ok')->count().' / '.count($checks)" icon="check" tone="teal" sub="php artisan qronex:preflight" />
  <x-stat label="Failing" :value="$fails" icon="alert" :tone="$fails ? 'red' : 'navy'" :sub="$fails ? 'Not ready to go live' : 'Nothing blocking'" />
  <x-stat label="Warnings" :value="$warns" icon="alert" :tone="$warns ? 'amber' : 'navy'" sub="Review before launch" />
  <x-stat label="Failed jobs" :value="$failed->count()" icon="server" :tone="$failed->count() ? 'red' : 'navy'" sub="Background queue" />
</div>
<div class="card grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
  @foreach($versions as $k => $v)<div><div class="text-xs text-slate-500">{{ $k }}</div><div class="font-semibold text-navy truncate">{{ $v }}</div></div>@endforeach
  <div><div class="text-xs text-slate-500">Health check</div><a class="font-semibold text-teal" href="{{ route('health') }}" target="_blank">/health ↗</a></div>
</div>
<div class="card overflow-x-auto">
  <h2 class="font-semibold text-navy mb-1">Go-live checklist</h2>
  <p class="text-sm mb-2 {{ $fails ? 'text-red-700' : 'text-emerald-700' }}">{{ $fails ? "$fails failing — not ready to go live." : 'No failures.' }} Same as <code>php artisan qronex:preflight</code>.</p>
  <table class="tbl"><tr><th>Area</th><th>Check</th><th>Status</th><th>Detail</th></tr>
  @foreach($checks as $c)<tr><td class="text-xs">{{ $c['area'] }}</td><td>{{ $c['check'] }}</td>
    <td><span class="chip {{ ['ok' => 'bg-emerald-100 text-emerald-800', 'warn' => 'bg-amber-100 text-amber-800', 'fail' => 'bg-red-100 text-red-800'][$c['status']] }}">{{ strtoupper($c['status']) }}</span></td>
    <td class="text-xs text-slate-600">{{ $c['detail'] }}</td></tr>@endforeach
  </table>
</div>
<div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Failed background jobs</h2>
  <table class="tbl"><tr><th>Failed at</th><th>Job</th><th>Error</th><th></th></tr>
  @forelse($failed as $j)@php($p = json_decode($j->payload, true))
    <tr><td class="text-xs whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($j->failed_at)->timezone('Asia/Kolkata')->format('d M H:i') }}</td><td class="text-xs font-mono">{{ class_basename($p['displayName'] ?? '?') }}</td>
      <td class="text-xs max-w-md truncate" title="{{ $j->exception }}">{{ \Illuminate\Support\Str::before($j->exception, "\n") }}</td>
      <td class="text-right whitespace-nowrap">@can('manage-platform')<form method="post" action="{{ route('admin.system.retry', $j->uuid) }}" class="inline">@csrf<button class="btn-ghost btn-sm">Retry</button></form>
        <form method="post" action="{{ route('admin.system.forget', $j->uuid) }}" class="inline">@csrf<button class="btn-ghost btn-sm">Delete</button></form>@endcan</td></tr>
  @empty<tr><td colspan="4"><x-empty icon="server" title="No failed jobs" text="The background queue is clean." class="py-4" /></td></tr>@endforelse</table></div>
@endsection
