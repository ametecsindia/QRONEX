{{-- Approve (needs the code on the phone) / reject / revoke (need a reason). Same rules as ROVEXY. --}}
@php($can = ['approve' => ['pending', 'rejected'], 'reject' => ['pending'], 'revoke' => ['approved']])
<div x-data="{m:null}" class="inline-flex flex-wrap gap-1 justify-end">
  @foreach($can as $act => $from)
    @if(in_array($d->status, $from))<button type="button" class="{{ $act === 'approve' ? 'btn' : 'btn-ghost' }} btn-sm" @click="m='{{ $act }}'">{{ ucfirst($act) }}</button>@endif
  @endforeach
  <template x-teleport="body">
    <div x-show="m" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-navy/50 flex items-end sm:items-center justify-center p-4" @keydown.escape.window="m=null" @click.self="m=null">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-left space-y-4" x-transition>
        <div>
          <div class="text-lg font-bold text-navy" x-text="{approve:'Approve phone', reject:'Reject phone', revoke:'Revoke phone'}[m]"></div>
          <div class="text-sm text-slate-600">{{ $d->employee->name }} · {{ $d->model }} <span class="text-slate-400">{{ $d->install_ref }}</span></div>
        </div>
        <form method="post" :action="'{{ url('app/devices/'.$d->id) }}/' + m" class="space-y-4">
          @csrf
          <template x-if="m === 'approve'"><div>
            @if($d->approval_code)
              <label class="label" for="code{{ $d->id }}">Code shown on the employee's phone</label>
              <input id="code{{ $d->id }}" name="code" class="input code uppercase text-center text-lg" placeholder="XXXX-XXXX" maxlength="9" autocomplete="off" required>
              <p class="text-xs text-slate-500 mt-1">Ask {{ strtok($d->employee->name, ' ') }} to open QRONEX — the code is on the waiting screen. It proves this is the right phone.</p>
            @else
              <p class="text-sm text-slate-600">This phone was registered before match codes existed — approve only if you are sure it belongs to {{ $d->employee->name }}.</p>
            @endif
            @if($d->replaces)<p class="mt-2 text-sm rounded-lg bg-amber-50 text-amber-800 px-3 py-2">Approving revokes the old phone ({{ $d->replaces->model }}).</p>@endif
          </div></template>
          <template x-if="m !== 'approve'"><div>
            <label class="label" for="reason{{ $d->id }}">Reason (kept in device history)</label>
            <input id="reason{{ $d->id }}" name="reason" class="input" minlength="3" maxlength="255" required :placeholder="m === 'revoke' ? 'e.g. Phone lost / employee left' : 'e.g. Not a company-approved phone'">
          </div></template>
          <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="m=null">Cancel</button><button class="btn" :class="m !== 'approve' && 'btn-danger'" x-text="{approve:'Approve',reject:'Reject',revoke:'Revoke'}[m]"></button></div>
        </form>
      </div>
    </div>
  </template>
</div>
