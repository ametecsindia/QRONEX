@extends('layouts.app', ['title' => $ticket->subject, 'crumb' => '<a class="hover:text-teal" href="'.route('portal.help.index').'">Help &amp; support</a> · '.e($ticket->ref)])
@section('content')
<div class="grid lg:grid-cols-3 gap-4 items-start">
  <div class="lg:col-span-2"><x-ticket-thread :ticket="$ticket" :action="route('portal.help.reply', $ticket)" /></div>
  <div class="card space-y-3 text-sm">
    <div class="flex flex-wrap gap-1.5">
      <span class="chip {{ ['open' => 'bg-amber-100 text-amber-800', 'answered' => 'bg-emerald-100 text-emerald-800', 'closed' => 'bg-slate-100 text-slate-600'][$ticket->status] ?? 'bg-slate-100' }}">{{ $ticket->status === 'answered' ? 'QRONEX replied' : $ticket->status }}</span>
      <span class="chip bg-navy/5 text-navy">{{ \App\Models\SupportTicket::AREAS[$ticket->area] ?? $ticket->area }}</span>
      <span class="chip bg-slate-100">{{ $ticket->priority }}</span>
    </div>
    <dl class="space-y-2"><div><dt class="text-xs text-slate-500">Raised by</dt><dd>{{ $ticket->name }}</dd></div>
      <div><dt class="text-xs text-slate-500">Opened</dt><dd>{{ $ticket->created_at->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</dd></div></dl>
    <p class="text-xs text-slate-500">We reply here and by email, usually within one working day.</p>
  </div>
</div>
@endsection
