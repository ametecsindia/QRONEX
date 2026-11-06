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
  <div class="card text-sm space-y-1"><h2 class="font-semibold text-navy">Email</h2>
    <div>Mailer: <b>{{ $mail['mailer'] }}</b>@if($mail['mailer'] === 'smtp') · {{ $mail['host'] }}@endif · from {{ $mail['from'] }}</div>
    @if($mail['mailer'] === 'log')<div class="text-amber-700">MAIL_MAILER=log: emails are written to storage/logs/laravel.log, not sent. Set MAIL_* in .env for real SMTP.</div>@endif</div>
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
