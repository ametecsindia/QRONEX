@extends('layouts.app', ['title' => 'Platform dashboard', 'crumb' => now('Asia/Kolkata')->format('l, d F Y')])
@section('actions')
  <a class="btn-ghost btn-sm" href="{{ route('admin.system') }}"><x-icon name="server" class="w-4 h-4" /> System health</a>
  <a class="btn btn-sm" href="{{ route('admin.companies.index') }}"><x-icon name="building" class="w-4 h-4" /> Companies</a>
@endsection
@section('content')
@php($delta = $revenuePrev30 > 0 ? (int) round(100 * ($revenue30 - $revenuePrev30) / $revenuePrev30) : null)
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <x-stat label="Collected · 30 days" :value="'₹'.number_format($revenue30)" icon="cash" tone="teal" :delta="$delta" :sub="'Lifetime ₹'.number_format($revenue)" :href="route('admin.payments')" />
  <x-stat label="Companies" :value="$tenants" icon="building" :sub="'+'.$newTenants30.' in 30 days'" :href="route('admin.companies.index')" />
  <x-stat label="Active employees" :value="number_format($employees)" icon="users" tone="sky" :sub="number_format($punchesToday).' verified punches today'" />
  <x-stat label="Needs attention" :value="$openTickets + $pendingDevices" icon="alert" :tone="$openTickets ? 'amber' : 'navy'" :sub="$openTickets.' open tickets · '.$pendingDevices.' phones pending'" :href="route('admin.support.index')" />
</div>

<div class="grid lg:grid-cols-3 gap-4">
  <div class="card lg:col-span-2">
    <div class="card-h"><div><div class="card-t">Verified punches — last 14 days</div><div class="text-xs text-slate-500">All companies · rejected scans shown on top</div></div></div>
    <x-bars :labels="$punches['labels']" :every="2" :series="[['name' => 'Verified', 'color' => '#0A8278', 'values' => $punches['values']], ['name' => 'Rejected', 'color' => '#F87171', 'values' => $rejectedSeries['values']]]" />
  </div>
  <div class="card space-y-5">
    <div>
      <div class="card-t mb-3">Companies by status</div>
      @php($tmax = max(1, $tenants))
      <div class="space-y-3">
        @foreach(['active' => 'bg-teal', 'trial' => 'bg-sky-500', 'grace' => 'bg-amber-500', 'suspended' => 'bg-red-500', 'cancelled' => 'bg-slate-400'] as $st => $col)
          @if($byStatus[$st] ?? 0)<x-meter :label="ucfirst($st)" :value="$byStatus[$st]" :max="$tmax" :note="(string) $byStatus[$st]" :color="$col" />@endif
        @endforeach
      </div>
    </div>
    <div>
      <div class="card-t mb-3">Plan mix</div>
      @php($pmax = max(1, $planMix->sum()))
      <div class="space-y-3">
        @forelse($planMix as $plan => $c)<x-meter :label="$plan" :value="$c" :max="$pmax" :note="$c.' · '.round(100 * $c / $pmax).'%'" color="bg-navy-3" />
        @empty<p class="text-sm text-slate-500">No subscriptions yet.</p>@endforelse
      </div>
    </div>
  </div>
</div>

<div class="grid lg:grid-cols-3 gap-4">
  <div class="card">
    <div class="card-h"><div><div class="card-t">Rejected scans</div><div class="text-xs text-slate-500">Last 7 days, by reason</div></div></div>
    @php($rmax = max([1, ...$rejectedWeek->values()->all()]))
    <div class="space-y-3">
      @forelse($rejectedWeek as $code => $c)<x-meter :label="ucwords(strtolower(str_replace('_', ' ', $code)))" :value="$c" :max="$rmax" :note="(string) $c" color="bg-red-400" />
      @empty<x-empty icon="shield" title="No rejected scans" class="py-4" />@endforelse
    </div>
  </div>
  <div class="card lg:col-span-2 overflow-x-auto">
    <div class="card-h"><div class="card-t">Newest companies</div><a class="text-xs font-semibold text-teal" href="{{ route('admin.companies.index') }}">All →</a></div>
    <table class="tbl"><tr><th>Company</th><th>Plan</th><th class="text-right">Employees</th><th>Status</th><th>Joined</th></tr>
    @forelse($recent as $t)<tr><td><a class="font-semibold text-navy hover:text-teal" href="{{ route('admin.companies.show', $t) }}">{{ $t->name }}</a><div class="text-xs text-slate-500">{{ $t->slug }}</div></td>
      <td>{{ $t->subscription?->planVersion?->plan?->name ?? '—' }}</td><td class="text-right tabular-nums">{{ $t->employees_count }}</td><td><x-status :s="$t->status" /></td><td class="text-xs text-slate-500">{{ $t->created_at->diffForHumans() }}</td></tr>
    @empty<tr><td colspan="5"><x-empty icon="building" title="No companies yet" /></td></tr>@endforelse
    </table>
  </div>
</div>

<div class="card overflow-x-auto">
  <div class="card-h"><div class="card-t">Audit log</div></div>
  <table class="tbl"><tr><th>When</th><th>Who</th><th>Company</th><th>What</th></tr>
  @foreach($audit as $x)<tr><td class="whitespace-nowrap text-xs text-slate-500">{{ $x->created_at->timezone('Asia/Kolkata')->format('d M H:i') }}</td><td>{{ $x->user?->name ?? 'System' }}</td><td class="text-xs">{{ $x->tenant?->name ?? 'Platform' }}</td><td><code class="text-xs bg-slate-100 rounded px-1">{{ $x->action }}</code> <span class="text-slate-600">{{ $x->subject }}</span></td></tr>@endforeach
  </table>
</div>
@endsection
