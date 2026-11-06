<div class="grid grid-cols-4 border-t border-slate-200 bg-white pb-4 pt-2 text-[10px] text-center">
  @foreach(['Home', 'Attendance', 'Requests', 'Profile'] as $k => $t)
    <div class="{{ $k === $on ? 'text-teal font-bold' : 'text-slate-400' }}"><span class="mx-auto mb-1 block h-1.5 w-6 rounded-full {{ $k === $on ? 'bg-teal' : 'bg-slate-200' }}"></span>{{ $t }}</div>
  @endforeach
</div>
