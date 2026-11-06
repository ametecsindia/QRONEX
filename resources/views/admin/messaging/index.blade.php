@extends('layouts.app', ['title' => 'Email & SMS', 'crumb' => 'Platform · last 7 days'])
@section('content')
@php($e = $week['email'] ?? collect())
@php($m = $week['sms'] ?? collect())
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <x-stat label="Emails sent" :value="number_format($e['sent'] ?? 0)" icon="mail" tone="teal" :sub="($e['skipped'] ?? 0).' skipped'" :href="route('admin.messaging.index', ['channel' => 'email', 'status' => 'sent'])" />
  <x-stat label="Emails failed" :value="number_format($e['failed'] ?? 0)" icon="alert" :tone="($e['failed'] ?? 0) ? 'red' : 'navy'" sub="Check SMTP settings" :href="route('admin.messaging.index', ['channel' => 'email', 'status' => 'failed'])" />
  <x-stat label="SMS sent" :value="number_format($m['sent'] ?? 0)" icon="phone" tone="sky" :sub="($m['skipped'] ?? 0).' skipped'" :href="route('admin.messaging.index', ['channel' => 'sms', 'status' => 'sent'])" />
  <x-stat label="SMS failed" :value="number_format($m['failed'] ?? 0)" icon="alert" :tone="($m['failed'] ?? 0) ? 'red' : 'navy'" sub="Gateway errors" :href="route('admin.messaging.index', ['channel' => 'sms', 'status' => 'failed'])" />
</div>
<div class="grid lg:grid-cols-2 gap-4">
  <form method="post" action="{{ route('admin.messaging.mail') }}" class="card lg:row-span-2" x-data="{ m: '{{ old('mailer', $mail['mailer']) }}' }">
    @csrf
    <fieldset class="space-y-4 min-w-0" @cannot('manage-platform') disabled @endcannot>
    <div class="flex items-start justify-between gap-3">
      <div><div class="card-t flex items-center gap-2"><x-icon name="mail" class="w-5 h-5 text-teal" /> Email settings</div>
        <p class="text-xs text-slate-500 mt-1">Used for activation codes, password resets, invoices and alerts.@unless($mail['saved']) Currently from the server's .env file.@endunless</p></div>
      <span class="chip whitespace-nowrap {{ $mail['mailer'] === 'smtp' ? 'bg-teal-soft text-teal' : 'bg-amber-100 text-amber-800' }}">{{ $mail['mailer'] === 'smtp' ? 'Sending' : 'Log only' }}</span>
    </div>
    <div class="grid sm:grid-cols-2 gap-3">
      @foreach(['smtp' => ['Send with SMTP', 'Real emails through your mail server'], 'log' => ['Log only', 'Nothing is sent; written to the server log']] as $k => [$t, $sub])
      <label class="flex gap-3 items-start rounded-xl border p-3 cursor-pointer" :class="m === '{{ $k }}' ? 'border-teal bg-teal-soft/40' : 'border-slate-200'">
        <input type="radio" name="mailer" value="{{ $k }}" x-model="m" class="mt-1"><span><b class="text-sm text-navy">{{ $t }}</b><span class="block text-xs text-slate-500">{{ $sub }}</span></span>
      </label>
      @endforeach
    </div>
    <div x-show="m === 'smtp'" class="space-y-3">
      <div class="grid sm:grid-cols-3 gap-3">
        <x-field name="host" label="SMTP server" :value="$mail['host']" placeholder="smtp.gmail.com" class="sm:col-span-2" />
        <x-field name="port" label="Port" type="number" :value="$mail['port']" placeholder="587" />
      </div>
      <div><label class="label" for="f-security">Security</label>
        <select id="f-security" name="security" class="input">@foreach(\App\Support\MailSettings::SECURITY as $k => $l)<option value="{{ $k }}" @selected(old('security', $mail['security']) === $k)>{{ $l }}</option>@endforeach</select></div>
      <div class="grid sm:grid-cols-2 gap-3">
        <x-field name="username" label="Username" :value="$mail['username']" autocomplete="off" placeholder="you@yourdomain.com" />
        <x-field name="password" label="Password" type="password" autocomplete="new-password" :hint="$mail['has_password'] ? 'Saved (encrypted). Leave blank to keep it.' : 'For Gmail / Google Workspace use an App Password.'" />
      </div>
      @if($mail['has_password'])<label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="clear_password" value="1"> Remove the saved password</label>@endif
    </div>
    <div class="grid sm:grid-cols-2 gap-3">
      <x-field name="from_address" label="From email" type="email" :value="$mail['from_address']" required placeholder="no-reply@qronex.app" />
      <x-field name="from_name" label="From name" :value="$mail['from_name']" required placeholder="QRONEX" />
    </div>
    @can('manage-platform')<div class="flex items-center gap-3"><button class="btn">Save email settings</button><span class="text-xs text-slate-500">Then send a test below.</span></div>@endcan
    </fieldset>
  </form>
  <div class="card text-sm space-y-1"><h2 class="font-semibold text-navy">SMS</h2>
    <div>Driver: <b>{{ $sms['driver'] }}</b> · sender {{ $sms['sender'] }}@if($sms['url']) · {{ parse_url($sms['url'], PHP_URL_HOST) }}@endif</div>
    @if($sms['driver'] !== 'http')<div class="text-amber-700">SMS_DRIVER=log: SMS are logged below, not sent. A DLT-registered gateway is needed (open founder decision).</div>@endif</div>
</div>
@can('manage-platform')
<form method="post" action="{{ route('admin.messaging.test') }}" class="card flex flex-wrap gap-2 items-end">
  @csrf <div><label class="label">Send a test</label><select name="channel" class="input"><option value="email">Email</option><option value="sms">SMS</option></select></div>
  <div><label class="label">To</label><input name="to" class="input" placeholder="email or +91 mobile" required></div>
  <button class="btn-ghost">Send test</button>
</form>
@endcan
<div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Templates</h2>
  <table class="tbl"><tr><th>Channel</th><th>Template</th><th>Subject / DLT id</th><th>Enabled</th><th></th></tr>
  @foreach($templates as $t)<tr><td class="uppercase text-xs">{{ $t->channel }}</td><td>{{ $t->name }} <span class="text-xs text-slate-500">{{ $t->key }}</span></td>
    <td class="text-xs">{{ $t->channel === 'email' ? $t->subject : ($t->dlt_template_id ?: '—') }}</td><td>{{ $t->enabled ? 'Yes' : 'No' }}</td>
    <td class="text-right">@can('manage-platform')<a class="btn-ghost btn-sm" href="{{ route('admin.messaging.edit', $t) }}">Edit</a>@endcan</td></tr>@endforeach
  </table></div>
<div class="card overflow-x-auto"><div class="card-h"><div class="card-t">Message log <span class="text-xs font-normal text-slate-500">latest 50</span></div>
  <div class="flex flex-wrap gap-1.5">@foreach(['' => 'All', 'email' => 'Email', 'sms' => 'SMS'] as $k => $l)<a class="{{ (string) request('channel') === $k ? 'btn' : 'btn-ghost' }} btn-sm" href="{{ route('admin.messaging.index', array_filter(['channel' => $k, 'status' => request('status')])) }}">{{ $l }}</a>@endforeach
  <span class="w-px bg-slate-200 mx-1"></span>@foreach(['' => 'Any status', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped'] as $k => $l)<a class="{{ (string) request('status') === $k ? 'btn' : 'btn-ghost' }} btn-sm" href="{{ route('admin.messaging.index', array_filter(['status' => $k, 'channel' => request('channel')])) }}">{{ $l }}</a>@endforeach</div></div>
  <table class="tbl"><tr><th>When</th><th>Company</th><th>Channel</th><th>Template</th><th>To</th><th>Status</th></tr>
  @forelse($logs as $l)<tr><td class="text-xs whitespace-nowrap">{{ $l->created_at->timezone('Asia/Kolkata')->format('d M H:i:s') }}</td><td class="text-xs">{{ $l->tenant?->name ?? 'Platform' }}</td>
    <td class="uppercase text-xs">{{ $l->channel }}</td><td class="text-xs">{{ $l->template_key }}</td><td class="text-xs">{{ $l->to }}</td>
    <td><span class="chip {{ ['sent' => 'bg-emerald-100 text-emerald-800', 'failed' => 'bg-red-100 text-red-800'][$l->status] ?? 'bg-slate-100' }}">{{ $l->status }}</span>@if($l->error)<div class="text-xs text-slate-500">{{ $l->error }}</div>@endif</td></tr>
  @empty<tr><td colspan="6"><x-empty icon="mail" title="No messages" text="Nothing matches these filters." class="py-4" /></td></tr>@endforelse</table></div>
@endsection
