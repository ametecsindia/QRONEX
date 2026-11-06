@extends('layouts.app', ['title' => 'Platform settings', 'crumb' => 'Platform'])
@section('content')
<form method="post" action="{{ route('admin.settings.update') }}" class="space-y-4 max-w-5xl">
  <fieldset class="contents" @cannot('manage-platform') disabled @endcannot>
  @csrf
  <div class="grid lg:grid-cols-3 gap-4 items-start">
    <div class="card space-y-4"><div><div class="card-t flex items-center gap-2"><x-icon name="qr" class="w-5 h-5 text-teal" /> Rotating QR</div><p class="text-xs text-slate-500 mt-1">Bounds every company's gate interval must stay within.</p></div>
      <x-field name="qr_min_s" label="Interval minimum (seconds)" type="number" :value="$s['qr_min_s']" required />
      <x-field name="qr_max_s" label="Interval maximum (seconds)" type="number" :value="$s['qr_max_s']" required />
      <x-field name="qr_default_s" label="Default for new gates (seconds)" type="number" :value="$s['qr_default_s']" required />
      <x-field name="grace_max_s" label="Max grace after a code expires (seconds)" type="number" :value="$s['grace_max_s']" required />
    </div>
    <div class="card space-y-4"><div><div class="card-t flex items-center gap-2"><x-icon name="phone" class="w-5 h-5 text-teal" /> Phones</div><p class="text-xs text-slate-500 mt-1">Upper limit a company can allow.</p></div>
      <x-field name="device_limit_max" label="Max phones per employee" type="number" :value="$s['device_limit_max']" required />
    </div>
    <div class="card space-y-4"><div><div class="card-t flex items-center gap-2"><x-icon name="cash" class="w-5 h-5 text-teal" /> Billing</div><p class="text-xs text-slate-500 mt-1">Applied to new invoices and sign-ups.</p></div>
      <x-field name="gst_pct" label="GST %" type="number" step="0.01" :value="$s['gst_pct']" required />
      <x-field name="trial_days" label="Default trial (days)" type="number" :value="$s['trial_days']" required />
      <div class="text-sm">Currency <b>{{ $s['currency'] }}</b></div>
    </div>
  </div>
  @can('manage-platform')<div class="flex items-center gap-3"><button class="btn">Save settings</button><span class="text-xs text-slate-500">Changes are saved to the audit log.</span></div>@endcan
  </fieldset>
</form>
@endsection
