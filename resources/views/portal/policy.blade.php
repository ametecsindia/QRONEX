@extends('layouts.app', ['title' => 'Attendance policy', 'crumb' => 'Settings'])
@section('content')
<form method="post" action="{{ route('portal.policy.update') }}" class="space-y-4 max-w-6xl">
  @csrf @method('PUT')
  <div class="grid lg:grid-cols-2 gap-4 items-start">
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="col-span-full"><div class="card-t flex items-center gap-2"><x-icon name="clock" class="w-5 h-5 text-teal" /> Working hours</div><p class="text-xs text-slate-500 mt-1">Decides on time and late on the dashboard and in reports.</p></div>
      <x-field name="shift_start" label="Working day starts at" type="time" :value="$s->shift_start ?? '09:30'" required />
      <x-field name="late_after_min" label="Late after (minutes past start)" type="number" min="0" max="240" :value="$s->late_after_min ?? 10" required />
      <x-field name="duplicate_window_min" label="Ignore repeat scans within (minutes)" type="number" min="0" max="60" :value="$s->duplicate_window_min" required />
      <x-field name="retention_months" label="Keep records for (months)" type="number" min="6" max="120" :value="$s->retention_months" required />
    </div>
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="col-span-full"><div class="card-t flex items-center gap-2"><x-icon name="qr" class="w-5 h-5 text-teal" /> Rotating QR</div><p class="text-xs text-slate-500 mt-1">A new code shows on the entrance screen every few seconds, so a photo of it can't be reused.</p></div>
      <x-field name="qr_interval_s" label="New code every (seconds)" type="number" :min="$b['qr_min']" :max="$b['qr_max']" :value="$s->qr_interval_s" :hint="'Allowed '.$b['qr_min'].'–'.$b['qr_max'].' s'" required />
      <x-field name="grace_s" label="Accept a just-expired code for (seconds)" type="number" min="0" :max="$b['grace_max']" :value="$s->grace_s" :hint="'Max '.$b['grace_max'].' s'" required />
    </div>
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="col-span-full"><div class="card-t flex items-center gap-2"><x-icon name="globe" class="w-5 h-5 text-teal" /> Location</div><p class="text-xs text-slate-500 mt-1">The phone must be inside the office geofence with a good GPS fix.</p></div>
      <x-field name="gps_accuracy_m" label="Required GPS accuracy (metres)" type="number" min="10" max="500" :value="$s->gps_accuracy_m" hint="50 m suits most offices; raise it for basements" required />
      <div class="col-span-full"><label class="label" for="ln">Location notice shown to employees</label>
        <textarea id="ln" name="location_notice" rows="3" class="input" required>{{ old('location_notice', $s->location_notice) }}</textarea></div>
    </div>
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="col-span-full"><div class="card-t flex items-center gap-2"><x-icon name="phone" class="w-5 h-5 text-teal" /> Phones</div><p class="text-xs text-slate-500 mt-1">Each phone holds a hardware key; a copied login won't work on another phone.</p></div>
      <p class="text-sm text-slate-600 col-span-full">One phone per employee. A company admin can allow more for a specific employee on the employee's page.</p>
      <label class="flex items-start gap-2 text-sm col-span-full rounded-lg bg-slate-50 p-3"><input class="mt-0.5" type="checkbox" name="require_device_approval" value="1" @checked(old('require_device_approval', $s->require_device_approval))>
        <span><b class="text-navy">New phones need approval</b><br><span class="text-xs text-slate-500">The administrator types the 8-character match code shown on the employee's phone.</span></span></label>
    </div>
  </div>
  <div class="card grid sm:grid-cols-2 gap-4">
    <div class="col-span-full"><div class="card-t flex items-center gap-2"><x-icon name="mail" class="w-5 h-5 text-teal" /> Notifications to administrators</div></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify_device_requests" value="1" @checked(old('notify_device_requests', $s->notify_device_requests ?? true))> Email when a phone waits for approval</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify_remote_requests" value="1" @checked(old('notify_remote_requests', $s->notify_remote_requests ?? true))> Email for remote and correction requests</label>
    <x-field class="sm:col-span-2" name="daily_report_email" label="Email yesterday's punch register (CSV) every morning at 07:00 to" type="email" :value="$s->daily_report_email" placeholder="hr@yourcompany.com" hint="Leave empty to turn off. QRONEX never sends an SMS for each punch." />
  </div>
  <div class="flex items-center gap-3"><button class="btn">Save policy</button><span class="text-xs text-slate-500">Applies to the next scan. Changes are saved to the audit log.</span></div>
</form>
@endsection
