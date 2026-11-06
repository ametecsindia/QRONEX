@extends('layouts.app', ['title' => $tenant->name, 'crumb' => '<a class="hover:text-teal" href="'.route('admin.companies.index').'">Companies</a> · qronex.app/'.e($tenant->slug)])
@section('actions')
  <x-status :s="$tenant->status" />
  @if($tenant->customer_type !== 'independent')<span class="chip bg-navy text-white uppercase">{{ $tenant->customer_type }}</span>@endif
@endsection
@section('content')
@php($sub = $tenant->subscription)
@if($tenant->isBlocked())<div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm flex gap-2"><x-icon name="alert" class="w-5 h-5 shrink-0" /> This company is {{ $tenant->status }} — its users and phones cannot sign in or punch.</div>@endif
@if($tenant->commercial_note)<div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm flex gap-2"><x-icon name="tag" class="w-5 h-5 shrink-0" /> {{ $tenant->commercial_note }}</div>@endif

<div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
  <x-stat label="Present today" :value="$a['present'].' / '.$a['active']" icon="users" tone="teal" :sub="$a['on_time_rate'] === null ? 'No one in yet' : $a['on_time_rate'].'% on time'" />
  <x-stat label="Punches · 30 days" :value="number_format($usage['punches30'])" icon="qr" tone="sky" :sub="$usage['last'] ? 'Last '.\Illuminate\Support\Carbon::parse($usage['last'])->diffForHumans() : 'Never punched'" />
  <x-stat label="Rejected · 30 days" :value="number_format($usage['rejected30'])" icon="shield" :tone="$usage['rejected30'] ? 'amber' : 'navy'" sub="Failed a security check" />
  <x-stat label="Phones" :value="$tenant->devices_count" icon="phone" :sub="$usage['pending_devices'].' waiting for approval'" />
  <x-stat label="Paid to date" :value="'₹'.number_format($usage['paid'])" icon="cash" :tone="$usage['due'] ? 'red' : 'teal'" :sub="$usage['due'] ? '₹'.number_format($usage['due']).' outstanding' : 'Nothing outstanding'" class="col-span-2 xl:col-span-1" />
</div>

<div class="grid lg:grid-cols-3 gap-4">
  <div class="card lg:col-span-2">
    <div class="card-h"><div><div class="card-t">Attendance — last 14 days</div><div class="text-xs text-slate-500">Employees with a verified first IN · day starts {{ $a['shift'] }}</div></div></div>
    <x-bars :labels="$a['trend']['labels']" :every="2" :series="[['name' => 'On time', 'color' => '#0A8278', 'values' => $a['trend']['on_time']], ['name' => 'Late', 'color' => '#F59E0B', 'values' => $a['trend']['late']]]" />
  </div>
  <div class="card text-sm">
    <div class="card-h"><div class="card-t">Profile</div></div>
    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
      <div><dt class="text-xs text-slate-500">Industry</dt><dd class="font-medium text-navy">{{ $tenant->industry ?: '—' }}</dd></div>
      <div><dt class="text-xs text-slate-500">Location</dt><dd class="font-medium text-navy">{{ collect([$tenant->city, $tenant->state])->filter()->join(', ') ?: '—' }}</dd></div>
      <div><dt class="text-xs text-slate-500">GSTIN</dt><dd class="font-medium text-navy font-mono text-xs">{{ $tenant->gstin ?: '—' }}</dd></div>
      <div><dt class="text-xs text-slate-500">Owner</dt><dd class="font-medium text-navy truncate">{{ $tenant->owner?->name ?? '—' }}<div class="text-xs text-slate-500 truncate">{{ $tenant->owner?->email }}</div></dd></div>
      <div><dt class="text-xs text-slate-500">Employees</dt><dd class="font-medium text-navy">{{ $tenant->employees_count }}</dd></div>
      <div><dt class="text-xs text-slate-500">Offices</dt><dd class="font-medium text-navy">{{ $tenant->offices_count }} · {{ $tenant->offices->sum(fn ($o) => $o->gates->count()) }} gates</dd></div>
      <div><dt class="text-xs text-slate-500">Joined</dt><dd class="font-medium text-navy">{{ $tenant->created_at->timezone('Asia/Kolkata')->format('d M Y') }}</dd></div>
      <div><dt class="text-xs text-slate-500">API</dt><dd class="font-medium text-navy">{{ $tenant->apiAllowed() ? 'Allowed' : 'Off' }}</dd></div>
    </dl>
  </div>
</div>

<div class="grid lg:grid-cols-3 gap-4 items-start">
  <div class="card lg:col-span-2 text-sm">
    <div class="card-h"><div class="card-t">Subscription</div></div>
    @if($sub)
      <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
        <div><div class="text-xs text-slate-500">Plan</div><div class="text-lg font-bold text-navy">{{ $sub->planVersion->plan->name }} <span class="text-xs font-normal text-slate-500">v{{ $sub->planVersion->version }}</span></div></div>
        <div><div class="text-xs text-slate-500">Billing</div><div class="font-semibold text-navy capitalize">{{ $sub->billing_cycle }}</div></div>
        <div><div class="text-xs text-slate-500">Seats</div><div class="font-semibold text-navy">{{ $sub->employee_count }}</div></div>
        <div><div class="text-xs text-slate-500">Arrangement</div><div class="font-semibold text-navy capitalize">{{ $sub->arrangement }}@if($sub->arrangement === 'discount') −{{ +$sub->discount_pct }}%@elseif($sub->arrangement === 'custom') ₹{{ number_format($sub->custom_monthly) }}/mo @endif</div></div>
        <div><div class="text-xs text-slate-500">Status</div><x-status :s="$sub->status" /></div>
      </div>
      @if($sub->trial_ends_at && $sub->status === 'trial')
        @php($left = max(0, (int) now()->diffInDays($sub->trial_ends_at, false)))
        <div class="mt-4"><x-meter :label="'Trial ends '.$sub->trial_ends_at->timezone('Asia/Kolkata')->format('d M Y')" :value="max(0, $sub->planVersion->trial_days - $left)" :max="max(1, $sub->planVersion->trial_days)" :note="$left.' days left'" :color="$left <= 3 ? 'bg-red-400' : 'bg-teal'" /></div>
      @endif
    @else
      <x-empty icon="card" title="No subscription" text="This company has no plan yet." class="py-4" />
    @endif
  </div>
  @can('manage-platform')
  <form method="post" action="{{ route('admin.companies.status', $tenant) }}" class="card space-y-3">
    @csrf <div class="card-t">Change status</div>
    <select name="status" class="input">@foreach(['trial', 'active', 'grace', 'suspended', 'cancelled'] as $s)<option @selected($tenant->status === $s)>{{ $s }}</option>@endforeach</select>
    <x-field name="reason" label="Reason (saved to the audit log)" required />
    <button class="btn w-full" data-confirm="Change this company's status?">Apply</button>
    <p class="text-xs text-slate-500">Suspended or cancelled stops sign-in and punching at once.</p>
  </form>
  @endcan
</div>

@can('manage-platform')
<form method="post" action="{{ route('admin.companies.terms', $tenant) }}" class="card grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end" x-data="{a:@js(old('arrangement', $sub?->arrangement ?? 'published'))}">
  @csrf <div class="card-t col-span-full">Commercial terms</div>
  <div><label class="label">Customer type</label><select name="customer_type" class="input">@foreach(['independent', 'smartprs', 'smartept', 'smartdcm'] as $c)<option @selected($tenant->customer_type === $c)>{{ $c }}</option>@endforeach</select></div>
  <div><label class="label">Arrangement</label><select name="arrangement" class="input" x-model="a">@foreach(['published', 'discount', 'complimentary', 'custom'] as $c)<option>{{ $c }}</option>@endforeach</select></div>
  <div x-show="a==='discount'"><x-field name="discount_pct" label="Discount %" type="number" step="0.01" :value="$sub?->discount_pct" /></div>
  <div x-show="a==='custom'"><x-field name="custom_monthly" label="Custom ₹/month" type="number" step="0.01" :value="$sub?->custom_monthly" /></div>
  <div><label class="label">API access</label><select name="api_enabled" class="input">@foreach(['' => 'Follow plan', '1' => 'Enabled (add-on)', '0' => 'Disabled'] as $v => $l)<option value="{{ $v }}" @selected((string) ($tenant->api_enabled === null ? '' : (int) $tenant->api_enabled) === (string) $v)>{{ $l }}</option>@endforeach</select></div>
  <x-field name="commercial_note" label="Note" :value="$tenant->commercial_note" />
  <div><button class="btn w-full">Save terms</button></div>
</form>
@endcan

<div class="grid lg:grid-cols-2 gap-4 items-start">
  <div class="card overflow-x-auto"><div class="card-h"><div class="card-t">Offices &amp; gates</div></div>
    <table class="tbl"><tr><th>Office</th><th>Gates</th><th class="text-right">Geofence</th></tr>
    @forelse($tenant->offices as $o)<tr><td><div class="font-medium text-navy">{{ $o->name }}</div><div class="text-xs text-slate-500">{{ $o->code }}</div></td>
      <td class="text-xs">@foreach($o->gates as $g)@php($live = $g->display_last_seen_at?->gt(now()->subMinute()))<span class="chip {{ $live ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100' }} mr-1 mb-1">{{ $g->name }}</span>@endforeach</td>
      <td class="text-right">{{ $o->geofence_radius_m >= 1000 ? number_format($o->geofence_radius_m / 1000).' km' : $o->geofence_radius_m.' m' }}</td></tr>
    @empty<tr><td colspan="3"><x-empty icon="building" title="No offices yet" class="py-4" /></td></tr>@endforelse
    </table></div>
  <div class="card overflow-x-auto"><div class="card-h"><div class="card-t">Portal users</div></div>
    <table class="tbl"><tr><th>Name</th><th>Role</th><th class="text-right">Last sign-in</th></tr>
    @foreach($tenant->users as $u)<tr><td><div class="font-medium text-navy">{{ $u->name }}</div><div class="text-xs text-slate-500">{{ $u->email }}</div></td><td class="text-xs">{{ $u->roleLabel() }}@unless($u->is_active) <span class="chip bg-slate-100">inactive</span>@endunless</td><td class="text-xs text-right">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td></tr>@endforeach
    </table></div>
  <div class="card overflow-x-auto"><div class="card-h"><div class="card-t">Invoices</div><span class="text-xs text-slate-500">{{ $tenant->invoices_count }} total</span></div>
    <table class="tbl"><tr><th>Number</th><th>Date</th><th class="text-right">Total</th><th>Status</th></tr>
    @forelse($tenant->invoices->take(12) as $i)<tr><td class="font-mono text-xs">{{ $i->number }}</td><td class="text-xs">{{ $i->issued_on->format('d M Y') }}</td><td class="text-right">₹{{ number_format($i->total, 2) }}</td><td><x-status :s="$i->status" /></td></tr>
    @empty<tr><td colspan="4"><x-empty icon="card" title="No invoices" class="py-4" /></td></tr>@endforelse
    </table></div>
  <div class="card"><div class="card-h"><div class="card-t">Recent activity</div><a class="text-xs font-semibold text-teal" href="{{ route('admin.audit', ['company' => $tenant->id]) }}">Full audit log →</a></div>
    <ol class="relative border-l border-slate-200 ml-2 space-y-3">
      @forelse($audit as $x)
        <li class="ml-4"><span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-scan ring-4 ring-white"></span>
          <div class="text-sm"><span class="font-medium text-navy">{{ $x->user?->name ?? 'System' }}</span> <code class="text-xs text-slate-600">{{ $x->action }}</code> <span class="text-slate-500">{{ $x->subject }}</span></div>
          <div class="text-[11px] text-slate-400">{{ $x->created_at->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</div></li>
      @empty<li class="ml-4 text-sm text-slate-500">Nothing yet.</li>@endforelse
    </ol></div>
</div>
@endsection
