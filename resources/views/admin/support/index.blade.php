@extends('layouts.app', ['title' => 'Support & enquiries', 'crumb' => 'Overview'])
@section('content')
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <x-stat label="Open" :value="$counts['open'] ?? 0" icon="inbox" :tone="($counts['open'] ?? 0) ? 'red' : 'navy'" sub="Waiting for QRONEX" :href="route('admin.support.index', ['status' => 'open'])" />
  <x-stat label="Answered" :value="$counts['answered'] ?? 0" icon="chat" tone="sky" sub="Waiting for the customer" :href="route('admin.support.index', ['status' => 'answered'])" />
  <x-stat label="Urgent / high" :value="$urgent" icon="alert" :tone="$urgent ? 'amber' : 'navy'" sub="Not closed" />
  <x-stat label="Website enquiries" :value="$enquiries" icon="mail" tone="teal" sub="Open sales leads" :href="route('admin.support.index', ['area' => 'sales'])" />
</div>

<form class="card flex flex-wrap items-end gap-3">
  <div class="flex flex-wrap gap-1.5">@foreach(['' => 'Open & answered', 'open' => 'Open', 'answered' => 'Answered', 'closed' => 'Closed'] as $k => $l)
    <a href="{{ route('admin.support.index', array_filter(['status' => $k, 'area' => $area, 'q' => request('q')])) }}" class="{{ ($status ?? '') === $k ? 'btn' : 'btn-ghost' }} btn-sm">{{ $l }}@if($k && ($counts[$k] ?? 0))<span class="ml-1 opacity-70">{{ $counts[$k] }}</span>@endif</a>@endforeach</div>
  <input type="hidden" name="status" value="{{ $status }}">
  <div><label class="label">Area</label><select name="area" class="input" onchange="this.form.submit()"><option value="">All areas</option>@foreach(\App\Models\SupportTicket::AREAS as $k => $l)<option value="{{ $k }}" @selected($area === $k)>{{ $l }}</option>@endforeach</select></div>
  <div class="flex-1 min-w-[200px]"><label class="label">Search</label><input name="q" value="{{ request('q') }}" class="input" placeholder="Ref, subject, name or email"></div>
  <button class="btn-ghost">Filter</button>
</form>

<div class="card overflow-x-auto">
  <table class="tbl"><tr><th>Ref</th><th>From</th><th>Subject</th><th>Area</th><th>Priority</th><th>Status</th><th class="text-right">Updated</th></tr>
  @forelse($tickets as $t)<tr class="hover:bg-slate-50">
    <td class="text-xs font-mono"><a class="text-teal font-semibold" href="{{ route('admin.support.show', $t) }}">{{ $t->ref }}</a></td>
    <td><div class="font-medium text-navy">{{ $t->tenant?->name ?? 'Website enquiry' }}</div><div class="text-xs text-slate-500">{{ $t->name }} · {{ $t->email }}</div></td>
    <td><a href="{{ route('admin.support.show', $t) }}" class="hover:text-teal">{{ $t->subject }}</a><div class="text-xs text-slate-400">{{ $t->messages_count }} message{{ $t->messages_count === 1 ? '' : 's' }}</div></td>
    <td class="text-xs">{{ \App\Models\SupportTicket::AREAS[$t->area] ?? $t->area }}</td>
    <td><span class="chip {{ ['urgent' => 'bg-red-100 text-red-800', 'high' => 'bg-amber-100 text-amber-800'][$t->priority] ?? 'bg-slate-100' }}">{{ $t->priority }}</span></td>
    <td><span class="chip {{ ['open' => 'bg-red-50 text-red-700', 'answered' => 'bg-sky-100 text-sky-800', 'closed' => 'bg-slate-100 text-slate-600'][$t->status] ?? 'bg-slate-100' }}">{{ $t->status }}</span></td>
    <td class="text-xs text-right whitespace-nowrap">{{ $t->updated_at->diffForHumans() }}</td></tr>
  @empty<tr><td colspan="7"><x-empty icon="chat" title="No tickets here" text="Nothing matches these filters." class="py-6" /></td></tr>@endforelse</table>
  <div class="mt-3">{{ $tickets->links() }}</div></div>
@endsection
