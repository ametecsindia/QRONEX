{{-- Dependency-free SVG bar chart. series: [['name' => ..., 'color' => '#hex', 'values' => [...]], ...] stacked. --}}
@props(['labels', 'series', 'height' => 180, 'every' => 1, 'width' => 600])
@php
  $n = max(1, count($labels));
  $totals = array_map(fn ($i) => array_sum(array_map(fn ($s) => $s['values'][$i] ?? 0, $series)), range(0, $n - 1));
  $max = max(1, max($totals ?: [0]));
  $nice = $max <= 5 ? 5 : (int) (ceil($max / (10 ** floor(log10($max)) / 2)) * (10 ** floor(log10($max)) / 2));
  $W = $width; $H = $height; $pl = 34; $pb = 22; $pt = 8; $cw = ($W - $pl) / $n; $bw = max(4, $cw * 0.62);
  $y = fn ($v) => $pt + ($H - $pt - $pb) * (1 - $v / $nice);
@endphp
<div {{ $attributes->merge(['class' => 'w-full']) }}>
  <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-auto" role="img" aria-label="Chart">
    @foreach([0, 0.5, 1] as $g)
      <line x1="{{ $pl }}" x2="{{ $W }}" y1="{{ $y($nice * $g) }}" y2="{{ $y($nice * $g) }}" stroke="#E2E8F0" stroke-dasharray="{{ $g ? '3 3' : '' }}" />
      <text x="{{ $pl - 6 }}" y="{{ $y($nice * $g) + 4 }}" text-anchor="end" font-size="11" fill="#94A3B8">{{ (int) round($nice * $g) }}</text>
    @endforeach
    @foreach($labels as $i => $l)
      @php($base = 0)
      @foreach($series as $s)
        @php($v = $s['values'][$i] ?? 0)
        @if($v > 0)
          <rect class="bar" x="{{ $pl + $i * $cw + ($cw - $bw) / 2 }}" y="{{ $y($base + $v) }}" width="{{ $bw }}" height="{{ max(1, $y($base) - $y($base + $v)) }}" rx="3" fill="{{ $s['color'] }}"><title>{{ $l }} · {{ $s['name'] }}: {{ $v }}</title></rect>
        @endif
        @php($base += $v)
      @endforeach
      @if(($n - 1 - $i) % $every === 0)<text x="{{ $pl + $i * $cw + $cw / 2 }}" y="{{ $H - 6 }}" text-anchor="middle" font-size="11" fill="#64748B">{{ $l }}</text>@endif
    @endforeach
  </svg>
  @if(count($series) > 1)
    <div class="flex flex-wrap gap-4 text-xs text-slate-600 mt-1">@foreach($series as $s)<span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}</span>@endforeach</div>
  @endif
</div>
