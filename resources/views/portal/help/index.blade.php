@extends('layouts.app', ['title' => 'Help & support', 'crumb' => 'Settings'])
@section('content')
<div class="grid grid-cols-2 xl:grid-cols-3 gap-4">
  <x-stat label="Waiting for QRONEX" :value="$tickets->where('status', 'open')->count()" icon="inbox" tone="amber" sub="Open tickets" />
  <x-stat label="Replied" :value="$tickets->where('status', 'answered')->count()" icon="chat" tone="teal" sub="Your turn to reply or close" />
  <x-stat label="Closed" :value="$tickets->where('status', 'closed')->count()" icon="check" sub="All time" class="col-span-2 xl:col-span-1" />
</div>
<div class="grid lg:grid-cols-2 gap-4 items-start">
  <div class="space-y-2"><div class="card-t px-1">Common questions</div>@foreach(\App\Http\Controllers\SiteController::faqs() as [$q, $a])<details class="card py-3 group"><summary class="font-semibold text-navy text-sm cursor-pointer list-none flex justify-between gap-2"><span>{{ $q }}</span><span class="text-teal group-open:rotate-45 transition">+</span></summary><p class="text-sm text-slate-600 mt-2">{{ $a }}</p></details>@endforeach</div>
  <div class="space-y-4">
    <form method="post" action="{{ route('portal.help.store') }}" class="card grid sm:grid-cols-2 gap-3">
      @csrf <h2 class="font-semibold text-navy sm:col-span-2">Raise a support ticket</h2>
      <x-field class="sm:col-span-2" name="subject" label="Subject" required />
      <div><label class="label">Area</label><select name="area" class="input">@foreach(\App\Models\SupportTicket::AREAS as $k => $l)@continue($k === 'sales')<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
      <div><label class="label">Priority</label><select name="priority" class="input"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
      <div class="sm:col-span-2"><label class="label" for="b">Details</label><textarea id="b" name="body" rows="4" class="input" required>{{ old('body') }}</textarea></div>
      <div><button class="btn">Submit ticket</button></div>
    </form>
    <div class="card overflow-x-auto"><h2 class="font-semibold text-navy mb-2">Your tickets</h2>
      <table class="tbl"><tr><th>Ref</th><th>Subject</th><th>Status</th><th>Updated</th></tr>
      @forelse($tickets as $t)<tr><td class="text-xs font-mono"><a class="text-teal" href="{{ route('portal.help.show', $t) }}">{{ $t->ref }}</a></td><td>{{ $t->subject }}</td>
        <td><span class="chip {{ ['open' => 'bg-amber-100 text-amber-800', 'answered' => 'bg-emerald-100 text-emerald-800'][$t->status] ?? 'bg-slate-200' }}">{{ $t->status }}</span></td><td class="text-xs">{{ $t->updated_at->diffForHumans() }}</td></tr>
      @empty<tr><td colspan="4"><x-empty icon="chat" title="No tickets yet" text="Raise one above — we reply here and by email." class="py-4" /></td></tr>@endforelse</table></div>
  </div>
</div>
@endsection
