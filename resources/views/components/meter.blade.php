@props(['label', 'value', 'max', 'color' => 'bg-teal', 'note' => null])
@php($pct = $max ? min(100, (int) round(100 * $value / $max)) : 0)
<div {{ $attributes }}>
  <div class="flex justify-between text-sm"><span class="text-slate-700 truncate">{{ $label }}</span><span class="tabular-nums text-slate-500">{{ $note ?? "$value / $max" }}</span></div>
  <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full {{ $color }} transition-all duration-700" style="width: {{ $pct }}%"></div></div>
</div>
