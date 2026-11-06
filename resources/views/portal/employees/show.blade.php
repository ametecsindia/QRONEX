@extends('layouts.app', ['title' => $employee->name, 'crumb' => '<a class="hover:text-teal" href="'.route('portal.employees.index').'">Employees</a> · '.e($employee->employee_code)])
@section('actions')
  @if($employee->status === 'active')<form method="post" action="{{ route('portal.employees.activation', $employee) }}">@csrf<button class="btn-ghost btn-sm" title="Send app activation code by SMS and email"><x-icon name="phone" class="w-4 h-4" /> Activation code</button></form>@endif
  <a class="btn btn-sm" href="{{ route('portal.employees.edit', $employee) }}">Edit</a>
@endsection
@section('content')
@php($ini = collect(explode(' ', $employee->name))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join(''))
@php($phones = $employee->devices->where('status', 'approved'))
<div class="card flex flex-wrap items-center gap-5">
  <span class="h-16 w-16 rounded-2xl bg-gradient-to-br from-navy to-ink text-white text-xl font-bold flex items-center justify-center">{{ $ini }}</span>
  <div class="min-w-0 flex-1">
    <div class="flex flex-wrap items-center gap-2"><span class="text-lg font-bold text-navy">{{ $employee->name }}</span><x-status :s="$employee->status" />
      @if($phones->isEmpty())<span class="chip bg-amber-100 text-amber-800">App not set up</span>@endif</div>
    <div class="text-sm text-slate-600">{{ collect([$employee->designation, $employee->department, $employee->office?->name])->filter()->join(' · ') }}</div>
    <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-4">@if($employee->email)<span>{{ $employee->email }}</span>@endif @if($employee->mobile)<span>{{ $employee->mobile }}</span>@endif @if($employee->hrms_ref)<span>HRMS {{ $employee->hrms_ref }}</span>@endif</div>
  </div>
</div>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <x-stat label="Days present · 30 days" :value="$kpi['present']" icon="check" tone="teal" sub="Days with a verified IN" />
  <x-stat label="Late days" :value="$kpi['late']" icon="alert" :tone="$kpi['late'] ? 'amber' : 'navy'" :sub="'Day starts '.$shift" />
  <x-stat label="Average arrival" :value="$kpi['avg_in'] ?? '—'" icon="clock" tone="sky" sub="First IN, IST" />
  <x-stat label="Average day" :value="$kpi['avg_hours'] ? number_format($kpi['avg_hours'], 1).' h' : '—'" icon="chart" sub="First IN to last OUT" />
</div>

<div class="grid lg:grid-cols-3 gap-4 items-start">
  <div class="card lg:col-span-2 overflow-x-auto">
    <div class="card-h"><div class="card-t">Last 30 days</div><a class="text-xs font-semibold text-teal" href="{{ route('portal.attendance.index', ['q' => $employee->employee_code, 'from' => today('Asia/Kolkata')->subDays(30)->toDateString()]) }}">Full ledger →</a></div>
    <table class="tbl"><tr><th>Date</th><th>First IN</th><th>Last OUT</th><th class="text-right">Hours</th><th>Where</th><th class="text-right">Punches</th></tr>
    @forelse($days as $date => $d)<tr>
      <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($date)->format('D, d M') }}</td>
      <td>@if($d['in']){{ $d['in']->format('H:i') }} @if($d['late'])<span class="chip bg-amber-100 text-amber-800 ml-1">late</span>@endif @else — @endif</td>
      <td>{{ $d['out']?->format('H:i') ?? '—' }}</td><td class="text-right">{{ $d['hours'] !== null ? number_format($d['hours'], 1) : '—' }}</td>
      <td class="text-xs">{{ $d['where'] }}</td><td class="text-right text-xs">{{ $d['count'] }}</td></tr>
    @empty<tr><td colspan="6"><x-empty icon="clock" title="No attendance in 30 days" text="Punches appear here once the employee scans an office QR." class="py-6" /></td></tr>@endforelse
    </table>
  </div>
  <div class="space-y-4">
    <div class="card"><div class="card-h"><div class="card-t">Phones</div>@can('approve-devices')<a class="text-xs font-semibold text-teal" href="{{ route('portal.devices.index', ['q' => $employee->employee_code]) }}">All →</a>@endcan</div>
      <div class="space-y-3">
      @forelse($employee->devices->sortByDesc('created_at') as $dv)
        <a @can('approve-devices') href="{{ route('portal.devices.show', $dv) }}" @endcan class="flex items-center gap-3 rounded-lg p-2 -m-2 hover:bg-slate-50">
          <span class="rounded-lg bg-navy/5 text-navy p-2"><x-icon name="phone" class="w-4 h-4" /></span>
          <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-navy truncate">{{ $dv->model ?: ucfirst($dv->platform) }}</span><span class="block text-xs text-slate-500">{{ $dv->os_version }} · {{ $dv->last_seen_at ? 'seen '.$dv->last_seen_at->diffForHumans() : 'never seen' }}</span></span>
          <x-status :s="$dv->status" /></a>
      @empty
        <p class="text-sm text-slate-500">No phone yet. Send an activation code — the employee enters it in the QRONEX app.</p>
      @endforelse
      </div></div>
    <div class="card"><div class="card-h"><div class="card-t">Remote permissions</div></div>
      <div class="space-y-2 text-sm">
      @forelse($remote as $p)<div class="flex items-start justify-between gap-2"><div><div class="font-medium text-navy">{{ $p->typeLabel() }}</div><div class="text-xs text-slate-500">{{ $p->from_date->format('d M') }} → {{ $p->to_date->format('d M Y') }}</div></div><x-status :s="$p->status" /></div>
      @empty<p class="text-sm text-slate-500">None.</p>@endforelse
      </div></div>
    <div class="card"><div class="card-h"><div class="card-t">Rejected scans</div></div>
      <div class="space-y-2 text-sm">
      @forelse($rejections as $r)<div><div class="font-medium text-red-700">{{ $r->reason }}</div><div class="text-xs text-slate-500">{{ $r->created_at->timezone('Asia/Kolkata')->format('d M, H:i') }}@if($r->accuracy_m) · ±{{ $r->accuracy_m }} m @endif</div></div>
      @empty<p class="text-sm text-slate-500">No rejected scans.</p>@endforelse
      </div></div>
  </div>
</div>
@endsection
