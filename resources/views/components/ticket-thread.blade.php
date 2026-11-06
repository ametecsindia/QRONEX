@props(['ticket', 'action'])
<div class="card space-y-3">
  @foreach($ticket->messages as $m)
    <div class="rounded-lg p-3 {{ $m->from_staff ? 'bg-teal-soft' : 'bg-slate-50' }}">
      <div class="text-xs text-slate-500">{{ $m->from_staff ? 'QRONEX support' : ($m->user?->name ?? $ticket->name) }} · {{ $m->created_at->timezone('Asia/Kolkata')->format('d M Y H:i') }}</div>
      <div class="text-sm whitespace-pre-line mt-1">{{ $m->body }}</div>
    </div>
  @endforeach
  @if($ticket->status !== 'closed' || $action)
  <form method="post" action="{{ $action }}" class="space-y-2">@csrf
    <textarea name="body" rows="3" class="input" required placeholder="Write a reply…"></textarea>
    <div class="flex gap-2 items-center"><button class="btn btn-sm">Send reply</button>{{ $slot }}</div>
  </form>
  @endif
</div>
