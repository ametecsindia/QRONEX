@extends('layouts.app', ['title' => 'Dashboard', 'crumb' => now('Asia/Kolkata')->format('l, d F Y')])
@section('actions')
  <a class="btn-ghost btn-sm" href="{{ route('portal.reports') }}"><x-icon name="chart" class="w-4 h-4" /> Reports</a>
  <a class="btn btn-sm" href="{{ route('portal.attendance.index') }}"><x-icon name="list" class="w-4 h-4" /> Today's ledger</a>
@endsection
@section('content')
@php($sub = $tenant->subscription)
@if($sub && $sub->status === 'trial')
  <div class="rounded-xl bg-sky-50 border border-sky-200 text-sky-900 px-4 py-3 text-sm flex flex-wrap items-center gap-2"><x-icon name="clock" class="w-5 h-5" />
    Trial of the {{ $sub->planVersion->plan->name }} plan ends {{ $sub->trial_ends_at?->timezone('Asia/Kolkata')->format('d M Y') }} — {{ max(0, (int) now()->diffInDays($sub->trial_ends_at, false)) }} days left.
    @can('manage-billing')<a class="ml-auto btn btn-sm" href="{{ route('portal.billing.index') }}">Choose a plan</a>@endcan</div>
@endif

@if($offices->isEmpty())
  <div class="card"><x-empty icon="building" title="Set up your first office" text="Add an office with its location and a gate. QRONEX then gives you a QR display link for the entrance.">
    @can('manage-offices')<a class="btn" href="{{ route('portal.offices.create') }}">Add office</a>@endcan</x-empty></div>
@endif

@php($waiting = $pending->sum())
<div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
  <x-stat label="Present today" :value="$a['present'].' / '.$a['active']" icon="users" tone="teal"
    :sub="$a['active'] ? round(100 * $a['present'] / max(1, $a['active'])).'% of active employees' : 'No active employees'" :href="route('portal.attendance.index')" />
  <x-stat label="On time" :value="$a['on_time_rate'] === null ? '—' : $a['on_time_rate'].'%'" icon="clock" tone="sky" :sub="$a['on_time'].' on time · day starts '.$a['shift']" />
  <x-stat label="Late" :value="$a['late']" icon="alert" :tone="$a['late'] ? 'amber' : 'navy'" :sub="'After '.$a['shift'].' + '.$a['late_after'].' min'" />
  <x-stat :label="$a['workday'] ? 'Not yet in' : 'Sunday'" :value="$a['workday'] ? $a['absent'] : '—'" icon="inbox" :sub="$a['remote'].' on approved remote / field duty'" />
  <x-stat label="Waiting for you" :value="$waiting" icon="check" :tone="$waiting ? 'red' : 'navy'" class="col-span-2 xl:col-span-1"
    :sub="$pending['devices'].' phones · '.$pending['remote'].' remote · '.$pending['corrections'].' corrections'" :href="auth()->user()->can('approve-devices') ? route('portal.approvals') : null" />
</div>

<div class="grid lg:grid-cols-3 gap-4">
  <div class="card lg:col-span-2">
    <div class="card-h"><div><div class="card-t">Attendance — last 14 days</div><div class="text-xs text-slate-500">Employees with a verified first IN · average {{ $a['avg_present'] }} a working day</div></div></div>
    <x-bars :labels="$a['trend']['labels']" :every="2" :series="[['name' => 'On time', 'color' => '#0A8278', 'values' => $a['trend']['on_time']], ['name' => 'Late', 'color' => '#F59E0B', 'values' => $a['trend']['late']]]" />
  </div>
  <div class="card">
    <div class="card-h"><div class="card-t">Offices today</div>@can('manage-offices')<a class="text-xs font-semibold text-teal" href="{{ route('portal.offices.index') }}">Manage →</a>@endcan</div>
    <div class="space-y-4">
      @forelse($offices as $o)
        @php($b = $a['by_office'][$o->id] ?? ['active' => 0, 'present' => 0])
        <x-meter :label="$o->name" :value="$b['present']" :max="$b['active']" :color="$b['active'] && $b['present'] / $b['active'] < .6 ? 'bg-amber-500' : 'bg-teal'" />
      @empty
        <p class="text-sm text-slate-500">No offices.</p>
      @endforelse
    </div>
  </div>
</div>

<div class="grid lg:grid-cols-3 gap-4">
  <div class="card">
    <div class="card-h"><div><div class="card-t">Arrival times</div><div class="text-xs text-slate-500">First IN, last 14 days{{ $a['before7'] ? ' · '.$a['before7'].' before 07:00' : '' }}</div></div></div>
    <x-bars :labels="array_keys($a['arrivals'])" :every="2" :width="360" :height="200" :series="[['name' => 'Arrivals', 'color' => '#173A64', 'values' => array_values($a['arrivals'])]]" />
  </div>
  <div class="card">
    <div class="card-h"><div><div class="card-t">Rejected scans</div><div class="text-xs text-slate-500">Last 7 days, by reason</div></div><a class="text-xs font-semibold text-teal" href="{{ route('portal.attendance.index', ['tab' => 'rejected']) }}">View →</a></div>
    @php($rmax = max([1, ...array_values($a['rejections'])]))
    <div class="space-y-3">
      @forelse($a['rejections'] as $code => $c)
        <x-meter :label="ucwords(strtolower(str_replace('_', ' ', $code)))" :value="$c" :max="$rmax" :note="(string) $c" color="bg-red-400" />
      @empty
        <x-empty icon="shield" title="No rejected scans" text="Every scan this week passed all checks." class="py-4" />
      @endforelse
    </div>
  </div>
  <div class="card">
    <div class="card-h"><div class="card-t">Recent activity</div></div>
    <ol class="relative border-l border-slate-200 ml-2 space-y-3">
      @forelse($audit as $x)
        <li class="ml-4"><span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-teal/70 ring-4 ring-white"></span>
          <div class="text-sm"><span class="font-medium text-navy">{{ $x->user?->name ?? 'System' }}</span> <span class="text-slate-600">{{ str_replace(['.', '_'], ' ', $x->action) }}</span> <span class="text-slate-500">{{ $x->subject }}</span></div>
          <div class="text-[11px] text-slate-400">{{ $x->created_at->timezone('Asia/Kolkata')->format('d M, H:i') }}</div></li>
      @empty
        <li class="ml-4 text-sm text-slate-500">Nothing yet.</li>
      @endforelse
    </ol>
  </div>
</div>

<div class="grid sm:grid-cols-3 gap-4">
  <div class="card flex items-center gap-3"><span class="rounded-lg bg-navy/5 text-navy p-2"><x-icon name="card" /></span><div><div class="text-xs text-slate-500">Plan</div><div class="font-semibold text-navy">{{ $sub?->planVersion?->plan?->name ?? '—' }} · {{ $sub?->billing_cycle }}</div><div class="text-xs text-slate-500">{{ $sub?->employee_count }} employees · <x-status :s="$tenant->status" /></div></div></div>
  <div class="card flex items-center gap-3"><span class="rounded-lg bg-navy/5 text-navy p-2"><x-icon name="building" /></span><div><div class="text-xs text-slate-500">Offices & gates</div><div class="font-semibold text-navy">{{ $offices->count() }} offices · {{ $offices->sum('gates_count') }} gates</div></div></div>
  <div class="card flex items-center gap-3"><span class="rounded-lg bg-navy/5 text-navy p-2"><x-icon name="users" /></span><div><div class="text-xs text-slate-500">Active employees</div><div class="font-semibold text-navy">{{ $a['active'] }}</div>@can('manage-employees')<a class="text-xs font-semibold text-teal" href="{{ route('portal.employees.index') }}">Manage →</a>@endcan</div></div>
</div>
@endsection
