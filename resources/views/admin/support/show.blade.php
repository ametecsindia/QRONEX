@extends('layouts.app', ['title' => $ticket->subject, 'crumb' => '<a class="hover:text-teal" href="'.route('admin.support.index').'">Support</a> · '.e($ticket->ref)])
@section('actions')
  @if($ticket->status !== 'closed')<form method="post" action="{{ route('admin.support.close', $ticket) }}">@csrf<button class="btn-ghost btn-sm"><x-icon name="check" class="w-4 h-4" /> Close ticket</button></form>@endif
@endsection
@section('content')
<div class="grid lg:grid-cols-3 gap-4 items-start">
  <div class="lg:col-span-2">
    <x-ticket-thread :ticket="$ticket" :action="route('admin.support.reply', $ticket)"><label class="text-sm flex items-center gap-1.5 text-slate-600"><input type="checkbox" name="close" value="1"> Close after replying</label></x-ticket-thread>
  </div>
  <div class="card space-y-3 text-sm">
    <div class="flex flex-wrap gap-1.5">
      <span class="chip {{ ['open' => 'bg-red-50 text-red-700', 'answered' => 'bg-sky-100 text-sky-800', 'closed' => 'bg-slate-100 text-slate-600'][$ticket->status] ?? 'bg-slate-100' }}">{{ $ticket->status }}</span>
      <span class="chip {{ ['urgent' => 'bg-red-100 text-red-800', 'high' => 'bg-amber-100 text-amber-800'][$ticket->priority] ?? 'bg-slate-100' }}">{{ $ticket->priority }} priority</span>
      <span class="chip bg-navy/5 text-navy">{{ \App\Models\SupportTicket::AREAS[$ticket->area] ?? $ticket->area }}</span>
    </div>
    <dl class="space-y-2">
      <div><dt class="text-xs text-slate-500">Company</dt><dd class="font-medium text-navy">@if($ticket->tenant)<a class="hover:text-teal" href="{{ route('admin.companies.show', $ticket->tenant) }}">{{ $ticket->tenant->name }}</a>@else Website enquiry @endif</dd></div>
      <div><dt class="text-xs text-slate-500">Contact</dt><dd>{{ $ticket->name }}<div><a class="text-teal" href="mailto:{{ $ticket->email }}">{{ $ticket->email }}</a></div>@if($ticket->phone)<div>{{ $ticket->phone }}</div>@endif</dd></div>
      <div><dt class="text-xs text-slate-500">Opened</dt><dd>{{ $ticket->created_at->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</dd></div>
      <div><dt class="text-xs text-slate-500">Last update</dt><dd>{{ $ticket->updated_at->diffForHumans() }}</dd></div>
    </dl>
  </div>
</div>
@endsection
