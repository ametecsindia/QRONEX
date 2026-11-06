@extends('layouts.site')
@section('content')
@php($cms = \App\Http\Controllers\SiteController::cms())

{{-- HERO: the product's own artefact — a live entrance display that rotates every 3 s --}}
<section class="relative overflow-hidden bg-ink text-white">
  <div aria-hidden="true" class="absolute inset-0 opacity-[.07]" style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:44px 44px"></div>
  <div class="site-wrap relative grid lg:grid-cols-[1.05fr_1fr] gap-14 items-center py-16 sm:py-24">
    <div>
      <p class="text-teal-glow font-semibold">For offices, branches and field teams across India</p>
      <h1 class="mt-4 text-[2.6rem] sm:text-6xl lg:text-[4.4rem] leading-[.98] font-extrabold tracking-tightest !text-white">{{ $cms['hero_title'] }}</h1>
      <p class="mt-6 text-lg sm:text-xl text-slate-300 max-w-[34rem] leading-relaxed">{{ $cms['hero_sub'] }}</p>
      <div class="mt-9 flex flex-wrap gap-3">
        <a class="site-btn" href="{{ route('register') }}">Start your free trial</a>
        <a class="site-btn-line text-white" href="#app">See how a punch works</a>
      </div>
      <dl class="mt-12 grid grid-cols-3 max-w-md gap-6 border-t border-white/15 pt-6">
        <div><dt class="text-sm text-slate-400">New code every</dt><dd class="num text-3xl font-extrabold">3 s</dd></div>
        <div><dt class="text-sm text-slate-400">Server checks</dt><dd class="num text-3xl font-extrabold">11</dd></div>
        <div><dt class="text-sm text-slate-400">Hardware needed</dt><dd class="text-3xl font-extrabold">None</dd></div>
      </dl>
    </div>

    <div class="relative mx-auto w-full max-w-[460px] mb-20" x-data="liveQr()" x-init="start()">
      <div class="rounded-[28px] bg-white text-navy p-7 shadow-[0_40px_80px_-30px_rgba(0,0,0,.6)]">
        <div class="flex items-center justify-between text-sm">
          <div><div class="font-bold">Hyderabad HQ</div><div class="text-slate-500">Main entrance</div></div>
          <span class="inline-flex items-center gap-1.5 text-teal font-semibold"><span class="h-2 w-2 rounded-full bg-teal-2 animate-pulse"></span>Live</span>
        </div>
        <div class="relative mx-auto mt-6 w-[248px] h-[248px]">
          <svg viewBox="0 0 100 100" class="absolute inset-0 w-full h-full" aria-hidden="true">
            <circle cx="50" cy="50" r="48" fill="none" stroke="#E2E8F0" stroke-width="1.6"/>
            <circle x-ref="ring" class="ring-tick" cx="50" cy="50" r="48" fill="none" stroke="#F2B33D" stroke-width="1.6" stroke-dasharray="302" stroke-linecap="round"/>
          </svg>
          <div class="absolute inset-[34px] rounded-lg overflow-hidden bg-white">
            <div x-ref="qr" class="w-full h-full [&_img]:w-full [&_img]:h-full [&_canvas]:w-full [&_canvas]:h-full" role="img" aria-label="Rotating attendance QR code (demo)"></div>
            <div class="scanline absolute left-2 right-2 h-0.5 bg-teal-2/70 shadow-[0_0_12px_#14B8A6]"></div>
          </div>
        </div>
        <div class="mt-5 flex items-center justify-between text-sm text-slate-500"><span>Scan with the QRONEX app</span><span class="num">Code <b class="text-navy" x-text="serial"></b></span></div>
      </div>
      <div class="punch-in absolute left-4 sm:-left-10 -bottom-16 w-[250px] rounded-2xl bg-navy text-white p-4 shadow-2xl ring-1 ring-white/10">
        <div class="flex items-center gap-3">
          <span class="h-9 w-9 rounded-full bg-teal-2 text-ink flex items-center justify-center"><x-icon name="check" class="w-5 h-5" /></span>
          <div><div class="font-bold">IN recorded</div><div class="num text-sm text-slate-300">09:02:14 · Priya Reddy</div></div>
        </div>
        <div class="mt-3 text-xs text-teal-glow">All 11 checks passed, 14 m from the door</div>
      </div>
    </div>
  </div>
</section>

{{-- WHY --}}
<section class="py-20 sm:py-28">
  <div class="site-wrap grid lg:grid-cols-[1fr_1.4fr] gap-12">
    <h2 class="site-h2">Biometric machines break. Buddy punching costs money. QRONEX removes both.</h2>
    <div class="grid sm:grid-cols-3 gap-8">
      @foreach([['qr', 'No machine to buy', 'Any tablet, TV or laptop at the door shows the code. Nothing to install, nothing to repair.'], ['phone', 'One phone per person', 'Each employee punches only from the phone you approved. A copied code or login does nothing.'], ['building', 'Every branch, one view', 'Each office has its own gates and geofence. Head office sees everyone; managers see their branch.']] as [$ic, $t, $d])
        <div><span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-teal-soft text-teal"><x-icon :name="$ic" class="w-6 h-6" /></span><h3 class="mt-4 text-lg font-bold">{{ $t }}</h3><p class="mt-2 text-slate-600 leading-relaxed">{{ $d }}</p></div>
      @endforeach
    </div>
  </div>
</section>

{{-- THE PIPELINE: what actually happens after a scan (a real sequence) --}}
<section id="security" class="bg-paper py-20 sm:py-28">
  <div class="site-wrap grid lg:grid-cols-[1fr_1.2fr] gap-14 items-start">
    <div class="lg:sticky lg:top-28">
      <h2 class="site-h2">Every punch passes 11 checks before it counts.</h2>
      <p class="site-lede mt-5">The phone sends the scanned code, its location and a signature from a key locked inside the phone. The server decides in under half a second, and the employee sees exactly which check failed.</p>
      <p class="mt-6 text-sm text-slate-500 max-w-md">These checks make proxy attendance much harder and record every suspicious attempt with a reason. They cannot prove with certainty that a person stood at the door, so every rejection is kept for review.</p>
    </div>
    <ol class="relative border-l-2 border-slate-200 ml-3 space-y-6">
      @foreach([['Employee account active', 'Inactive employees are refused straight away.'], ['Approved phone', 'Only a phone an administrator approved can punch.'], ['Phone signature', 'Each request is signed by a key that never leaves that phone.'], ['QR signature', 'The code was issued for this company’s gate.'], ['QR still fresh', 'Older than the 3-second window plus grace means a screenshot.'], ['Office and gate open', 'Disabled gates and closed offices accept nothing.'], ['Right office', 'Employees punch only at offices they belong to.'], ['Location accuracy', 'A blurry location fix is rejected rather than guessed.'], ['Inside the geofence', 'Distance from the office pin, within your radius.'], ['Fake-GPS check', 'Mock-location apps are detected and flagged.'], ['No replay', 'The same code cannot be used twice, and repeat scans within minutes are ignored.']] as $i => [$t, $d])
        <li class="ml-7 relative">
          <span class="absolute -left-[45px] top-0 num h-7 w-7 rounded-full bg-white border-2 border-teal text-teal text-xs font-bold flex items-center justify-center">{{ $i + 1 }}</span>
          <div class="font-bold text-navy">{{ $t }}</div><div class="text-slate-600">{{ $d }}</div>
        </li>
      @endforeach
    </ol>
  </div>
</section>

{{-- HOW IT WORKS (sequence) --}}
<section id="how" class="py-20 sm:py-28">
  <div class="site-wrap">
    <h2 class="site-h2">From sign-up to the first punch in one afternoon.</h2>
    <p class="site-lede mt-4">Everything happens in your browser. The only thing you put at the door is a screen.</p>
    <ol class="mt-14 grid sm:grid-cols-2 lg:grid-cols-4 gap-x-8 gap-y-12">
      @foreach(\App\Http\Controllers\SiteController::HOW as $i => [$t, $d])
        <li class="border-t-2 {{ $i < 4 ? 'border-navy' : 'border-teal' }} pt-5">
          <div class="num text-sm font-bold {{ $i < 4 ? 'text-navy' : 'text-teal' }}">Step {{ $i + 1 }}</div>
          <div class="mt-1 text-lg font-bold text-navy">{{ $t }}</div><p class="mt-1.5 text-slate-600 leading-relaxed">{{ $d }}</p>
        </li>
      @endforeach
    </ol>
  </div>
</section>

{{-- ON THE PHONE: a live run-through of one punch, in the app's own screens --}}
<section id="app" class="bg-paper py-20 sm:py-28" x-data="phoneDemo()" x-init="start()">
  <div class="site-wrap grid lg:grid-cols-[1fr_auto] gap-14 items-center">
    <div>
      <p class="text-teal font-semibold">What your employee sees</p>
      <h2 class="site-h2 mt-2">One tap at the door. Verified in under two seconds.</h2>
      <p class="site-lede mt-4">This is the QRONEX app, running a real punch. Tap any step to see it.</p>
      <ol class="mt-10 space-y-2" role="tablist" aria-label="Punch steps">
        <template x-for="(st, k) in steps" :key="k">
          <li>
            <button type="button" role="tab" :aria-selected="s === k" @click="go(k)"
              class="w-full text-left rounded-2xl px-5 py-4 transition flex gap-4 items-start"
              :class="s === k ? 'bg-white shadow-[0_10px_30px_-18px_rgba(14,39,71,.45)] ring-1 ring-slate-200' : 'hover:bg-white/60'">
              <span class="num mt-0.5 h-7 w-7 shrink-0 rounded-full text-sm font-bold flex items-center justify-center transition"
                :class="s === k ? 'bg-teal text-white' : (s > k ? 'bg-teal-soft text-teal' : 'bg-slate-200 text-slate-500')" x-text="k + 1"></span>
              <span class="min-w-0 flex-1">
                <span class="block font-bold text-navy" x-text="st.t"></span>
                <span class="block mt-0.5 text-slate-600 leading-relaxed" x-text="st.d"></span>
                <span x-show="s === k" class="mt-3 block h-0.5 rounded bg-slate-200 overflow-hidden"><span class="block h-full bg-teal" :style="`width:${p}%`"></span></span>
              </span>
            </button>
          </li>
        </template>
      </ol>
    </div>

    {{-- Phone --}}
    <div class="mx-auto order-first lg:order-none" aria-hidden="true">
      <div class="relative w-[300px] h-[610px] rounded-[46px] bg-ink p-3 shadow-[0_50px_90px_-35px_rgba(8,26,51,.65)] ring-1 ring-black/10">
        <div class="absolute left-1/2 top-5 -translate-x-1/2 h-5 w-24 rounded-full bg-black z-20"></div>
        <div class="relative h-full w-full overflow-hidden rounded-[36px] bg-[#F3F6F9] text-navy">
          {{-- status bar --}}
          <div class="relative z-10 flex justify-between px-6 pt-3 text-[11px] font-semibold" :class="s === 1 ? 'text-white' : 'text-white'">
            <span class="num" x-text="clock.slice(0,5)"></span><span>5G ▮▮▮</span>
          </div>

          {{-- 0 · HOME --}}
          <div x-show="s === 0" x-transition.opacity.duration.300ms class="absolute inset-0 flex flex-col">
            <div class="bg-navy text-white px-5 pt-10 pb-6">
              <div class="text-[11px] text-slate-300">Good morning</div>
              <div class="text-lg font-bold">Priya Reddy</div>
              <div class="mt-3 num text-3xl font-extrabold" x-text="clock"></div>
              <span class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px]"><span class="h-1.5 w-1.5 rounded-full bg-scan"></span>Not checked in yet</span>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center">
              <div class="relative">
                <span class="absolute inset-0 rounded-full bg-teal-2/30 animate-ping"></span>
                <span class="relative flex h-32 w-32 flex-col items-center justify-center rounded-full bg-teal text-white shadow-xl transition" :class="tap ? 'scale-90' : ''">
                  <x-icon name="qr" class="w-10 h-10" /><span class="mt-1 text-sm font-bold">Scan to punch</span>
                </span>
              </div>
              <div class="mt-5 text-xs text-slate-500">Hyderabad HQ · shift 09:30</div>
              <span class="mt-4 h-9 w-9 rounded-full bg-white/80 ring-8 ring-teal-2/20 transition-all duration-500" :class="tap ? 'opacity-100 scale-75' : 'opacity-0 scale-125'" style="margin-top:-170px"></span>
            </div>
            @include('site._phone-tabs', ['on' => 0])
          </div>

          {{-- 1 · SCAN --}}
          <div x-show="s === 1" x-transition.opacity.duration.300ms class="absolute inset-0 bg-[#0b1220] flex flex-col items-center">
            <div class="mt-12 text-white/80 text-xs">Point at the screen by the door</div>
            <div class="relative mt-6 h-56 w-56">
              <div class="absolute inset-0 rounded-2xl bg-white p-4 transition duration-700" :class="locked ? 'scale-95' : 'scale-100 rotate-1'">
                <div x-ref="pqr" class="w-full h-full [&_img]:w-full [&_img]:h-full [&_canvas]:w-full [&_canvas]:h-full"></div>
              </div>
              @foreach(['top-0 left-0 border-t-4 border-l-4 rounded-tl-xl', 'top-0 right-0 border-t-4 border-r-4 rounded-tr-xl', 'bottom-0 left-0 border-b-4 border-l-4 rounded-bl-xl', 'bottom-0 right-0 border-b-4 border-r-4 rounded-br-xl'] as $c)
                <span class="absolute h-9 w-9 transition-colors {{ $c }}" :class="locked ? 'border-teal-2' : 'border-white'" style="margin:-10px"></span>
              @endforeach
              <div x-show="!locked" class="scanline absolute left-3 right-3 h-0.5 bg-teal-2 shadow-[0_0_14px_#14B8A6]"></div>
            </div>
            <div class="mt-8 rounded-full px-4 py-2 text-xs font-semibold transition" :class="locked ? 'bg-teal-2 text-ink' : 'bg-white/10 text-white'" x-text="locked ? 'Code read · Main entrance' : 'Looking for the QRONEX code…'"></div>
            <div class="mt-auto mb-8 flex items-center gap-2 text-[11px] text-white/60"><span class="h-1.5 w-1.5 rounded-full bg-teal-2 animate-pulse"></span>GPS ±12 m · fresh fix</div>
          </div>

          {{-- 2 · VERIFY --}}
          <div x-show="s === 2" x-transition.opacity.duration.300ms class="absolute inset-0 flex flex-col">
            <div class="bg-navy text-white px-5 pt-10 pb-4"><div class="text-[11px] text-slate-300">Sending signed request</div><div class="font-bold">Server is checking your punch</div></div>
            <ul class="flex-1 px-4 py-3 space-y-1.5 text-[12px]">
              <template x-for="(c, k) in checks" :key="k">
                <li class="flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 shadow-sm transition duration-300" :class="k < done ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-3'">
                  <span class="h-4 w-4 shrink-0 rounded-full bg-teal-2 text-ink flex items-center justify-center text-[10px] font-bold">✓</span>
                  <span class="flex-1 truncate" x-text="c[0]"></span><span class="num text-slate-400" x-text="c[1]"></span>
                </li>
              </template>
            </ul>
            <div class="px-5 pb-6 text-[11px] text-slate-500 num" x-text="`${done} of 11 checks passed`"></div>
          </div>

          {{-- 3 · RESULT --}}
          <div x-show="s === 3" x-transition.opacity.duration.300ms class="absolute inset-0 flex flex-col items-center justify-center bg-teal text-white px-6 text-center">
            <span class="qx-pop flex h-24 w-24 items-center justify-center rounded-full bg-white text-teal"><x-icon name="check" class="w-12 h-12" /></span>
            <div class="mt-6 text-2xl font-extrabold">IN recorded</div>
            <div class="mt-1 num text-4xl font-extrabold">09:02:14</div>
            <div class="mt-3 text-sm text-teal-soft">Hyderabad HQ · Main entrance</div>
            <div class="mt-6 rounded-2xl bg-white/15 px-4 py-3 text-xs leading-relaxed">On time · 14 m from the door<br>All 11 checks passed</div>
          </div>

          {{-- 4 · HISTORY --}}
          <div x-show="s === 4" x-transition.opacity.duration.300ms class="absolute inset-0 flex flex-col">
            <div class="bg-navy text-white px-5 pt-10 pb-5"><div class="text-lg font-bold">Attendance</div><div class="text-[11px] text-slate-300">Your punches, as HR sees them</div></div>
            <div class="px-4 py-3 space-y-3 flex-1 text-[12px]">
              <div class="grid grid-cols-3 gap-2 text-center">
                @foreach([['First IN', '09:02'], ['Last OUT', '—'], ['Hours', '0:00']] as [$l, $v])
                  <div class="rounded-xl bg-white py-2.5 shadow-sm"><div class="text-[10px] text-slate-500">{{ $l }}</div><div class="num font-bold text-navy">{{ $v }}</div></div>
                @endforeach
              </div>
              @foreach([['Today', [['IN', '09:02', true]]], ['Yesterday', [['IN', '09:24', false], ['OUT', '18:41', false]]], ['Wed 4 Nov', [['IN', '09:11', false], ['OUT', '18:05', false]]]] as [$day, $rows])
                <div><div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">{{ $day }}</div>
                  @foreach($rows as [$io, $t, $new])
                    <div class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 mb-1 shadow-sm {{ $new ? 'qx-pop ring-2 ring-teal-2' : '' }}">
                      <span class="rounded px-1.5 py-0.5 text-[10px] font-bold {{ $io === 'IN' ? 'bg-teal-soft text-teal' : 'bg-slate-100 text-slate-600' }}">{{ $io }}</span>
                      <span class="flex-1">Main entrance</span><span class="num font-semibold">{{ $t }}</span>
                    </div>
                  @endforeach
                </div>
              @endforeach
            </div>
            @include('site._phone-tabs', ['on' => 1])
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- WHO IT'S FOR: one interactive selector instead of 8 identical cards --}}
<section class="bg-navy text-white py-20 sm:py-28" x-data="{i:0}">
  <div class="site-wrap grid lg:grid-cols-[1fr_1.3fr] gap-12 items-center">
    <div>
      <h2 class="site-h2 !text-white">Built for teams that clock in at a door.</h2>
      <div class="mt-8 flex flex-wrap gap-2" role="tablist">
        @foreach(\App\Http\Controllers\SiteController::INDUSTRIES as $k => [$t])
          <button role="tab" :aria-selected="i === {{ $k }}" @click="i={{ $k }}" class="rounded-full px-4 py-2 text-sm font-semibold transition" :class="i === {{ $k }} ? 'bg-white text-navy' : 'bg-white/10 text-white hover:bg-white/20'">{{ $t }}</button>
        @endforeach
      </div>
    </div>
    <div class="rounded-3xl bg-white/5 ring-1 ring-white/10 p-8 sm:p-10 min-h-[220px]">
      @foreach(\App\Http\Controllers\SiteController::INDUSTRIES as $k => [$t, $d, $tag])
        <div x-show="i === {{ $k }}" @if($k) x-cloak @endif>
          <div class="text-teal-glow font-semibold">{{ $tag }}</div>
          <div class="mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight">{{ $t }}</div>
          <p class="mt-4 text-lg text-slate-300 leading-relaxed">{{ $d }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- FEATURES grouped by job --}}
<section id="features" class="py-20 sm:py-28">
  <div class="site-wrap">
    <h2 class="site-h2">Capture, verify, deliver. Nothing else to buy.</h2>
    <p class="site-lede mt-4">QRONEX records attendance and hands it to the software you already run. It does not do payroll, leave or productivity tracking.</p>
    @php($f = collect(\App\Http\Controllers\SiteController::FEATURES))
    <div class="mt-14 grid lg:grid-cols-3 gap-10">
      @foreach([['Capture', 'qr', [0, 1, 5, 6, 7]], ['Verify', 'shield', [2, 3, 4, 14]], ['Deliver', 'code', [8, 9, 10, 11, 12, 13]]] as [$g, $ic, $idx])
        <div>
          <div class="flex items-center gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-navy text-white"><x-icon :name="$ic" /></span><h3 class="text-xl font-extrabold">{{ $g }}</h3></div>
          <ul class="mt-6 space-y-5">
            @foreach($idx as $n)<li><div class="font-semibold text-navy">{{ $f[$n][0] }}</div><div class="text-slate-600">{{ $f[$n][1] }}</div></li>@endforeach
          </ul>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- INTEGRATIONS --}}
<section class="bg-paper py-20">
  <div class="site-wrap grid lg:grid-cols-2 gap-12 items-center">
    <div>
      <h2 class="site-h2">Attendance flows straight into your HRMS.</h2>
      <p class="site-lede mt-4">Read verified punches over the REST API, or receive a signed webhook the moment one is accepted. Integration keys can read attendance but can never create a punch.</p>
      <a class="site-btn mt-8" href="{{ route('docs.api') }}">Read the API reference</a>
    </div>
    <div class="rounded-2xl bg-ink text-slate-300 p-6 font-mono text-[13px] leading-6 overflow-x-auto shadow-xl">
<pre><span class="text-slate-500"># a signed webhook, sent within a second of the scan</span>
POST /your-hrms/qronex
X-Qronex-Signature: t=1762313534,v1=9f2c…

{ <span class="text-teal-glow">"event"</span>: "attendance.created",
  <span class="text-teal-glow">"data"</span>: { "employee_code": "EMP0002",
            "type": "IN", "office_code": "HYD01",
            "recorded_at": "2026-11-05T03:32:14Z",
            "location": { "distance_m": 14 } } }</pre>
    </div>
  </div>
</section>

{{-- PRICING (live from Super Admin → Plans) --}}
<section class="py-20 sm:py-28">
  <div class="site-wrap">
    <div class="flex flex-wrap items-end justify-between gap-6">
      <div><h2 class="site-h2">Pay per employee. Monthly, quarterly or yearly.</h2><p class="site-lede mt-4">Every plan starts with a free trial. Prices exclude GST.</p></div>
      <a class="site-btn-line text-navy" href="{{ route('pricing') }}">Work out your price</a>
    </div>
    <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-5 items-start">
      @foreach($plans as $p)@php($v = $p->currentVersion)
        <div class="relative rounded-3xl p-7 {{ $p->is_popular ? 'bg-navy text-white shadow-2xl lg:-translate-y-3' : 'bg-white border border-slate-200' }}">
          @if($p->is_popular)<span class="absolute top-6 right-6 rounded-full bg-scan text-ink text-xs font-bold px-2.5 py-1">Most chosen</span>@endif
          <div class="text-xl font-extrabold {{ $p->is_popular ? 'text-white' : 'text-navy' }}">{{ $p->name }}</div>
          <div class="mt-1 text-sm {{ $p->is_popular ? 'text-slate-300' : 'text-slate-500' }}">{{ $p->description }}</div>
          <div class="mt-6 {{ $p->is_popular ? 'text-white' : 'text-navy' }}">
            @if($v->pricing_model === 'per_employee')<span class="text-4xl font-extrabold">₹{{ +$v->per_employee_price }}</span><span class="text-sm"> per employee / month</span>
            @elseif($v->pricing_model === 'slab')<span class="text-4xl font-extrabold">₹{{ number_format($v->slab_price) }}</span><span class="text-sm"> / month</span>
            @else<span class="text-4xl font-extrabold">Custom</span>@endif
          </div>
          <div class="mt-4 text-sm {{ $p->is_popular ? 'text-slate-300' : 'text-slate-600' }}">{{ $p->min_employees }}–{{ $p->max_employees >= 100000 ? 'unlimited' : $p->max_employees }} employees, {{ $v->trial_days }}-day free trial{{ $v->api_included ? ', API included' : '' }}</div>
          <a class="mt-7 w-full {{ $p->is_popular ? 'site-btn' : 'site-btn-line text-navy' }}" href="{{ $v->pricing_model === 'custom' ? route('contact') : route('register') }}">{{ $v->pricing_model === 'custom' ? 'Talk to sales' : 'Start free trial' }}</a>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- FAQ --}}
<section id="faq" class="bg-paper py-20 sm:py-28">
  <div class="site-wrap grid lg:grid-cols-[1fr_1.6fr] gap-12">
    <div><h2 class="site-h2">Questions operations teams ask us.</h2><p class="site-lede mt-4">Something else? <a class="text-teal font-semibold underline underline-offset-4" href="{{ route('contact') }}">Ask us directly</a>.</p></div>
    <div class="divide-y divide-slate-200 border-y border-slate-200">
      @foreach(\App\Http\Controllers\SiteController::faqs() as [$q, $a])
        <details class="group py-5">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-6 text-lg font-semibold text-navy">{{ $q }}<span class="shrink-0 h-8 w-8 rounded-full border border-slate-300 flex items-center justify-center transition group-open:rotate-45">+</span></summary>
          <p class="mt-3 text-slate-600 leading-relaxed max-w-[65ch]">{{ $a }}</p>
        </details>
      @endforeach
    </div>
  </div>
</section>

{{-- CTA --}}
<section class="bg-teal text-white">
  <div class="site-wrap py-16 sm:py-20 flex flex-col lg:flex-row lg:items-center justify-between gap-8">
    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tightest !text-white max-w-[18ch] leading-[1.02]">Put a QR at your door this week.</h2>
    <div class="flex flex-wrap gap-3"><a class="site-btn !bg-white !text-navy hover:!bg-slate-100" href="{{ route('register') }}">Start free trial</a><a class="site-btn-line text-white" href="{{ route('contact') }}">Book a demo</a></div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('vendor/qrcode.min.js') }}"></script>
<script>
  // Demo only: random codes shaped like the real rotating token. The real display gets signed codes from the server.
  function liveQr() {
    return {
      serial: '',
      draw() {
        const rnd = Array.from(crypto.getRandomValues(new Uint8Array(10)), b => b.toString(16).padStart(2, '0')).join('');
        const win = Math.floor(Date.now() / 3000);
        this.serial = String(win).slice(-4);
        this.$refs.qr.innerHTML = '';
        new QRCode(this.$refs.qr, { text: 'QRX1.demo.3.' + win + '.' + rnd, width: 180, height: 180, colorDark: '#0E2747', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
        const r = this.$refs.ring; r.style.animation = 'none'; void r.getBoundingClientRect(); r.style.animation = '';
      },
      start() { this.draw(); setInterval(() => this.draw(), 3000); },
    };
  }
  // Demo only: plays one punch through the app's screens. Timings roughly match a real scan.
  function phoneDemo() {
    const steps = [
      { t: 'Open the app and tap Scan', d: 'Only on the phone HR approved. Shift and office are shown up front.', ms: 2600 },
      { t: 'Point at the door screen', d: 'The camera reads the code that changes every 3 seconds. GPS takes a fresh fix at the same time.', ms: 2800 },
      { t: 'The server checks 11 things', d: 'Signed by the phone\'s hardware key, inside the geofence, code fresh, no mock location, no replay…', ms: 4200 },
      { t: 'Punch recorded', d: 'Green means it is in the ledger. Red explains exactly which check failed.', ms: 2800 },
      { t: 'History updates instantly', d: 'The employee and HR see the same record, in IST, ready for payroll.', ms: 3200 },
    ];
    const checks = [['Employee account active', ''], ['Device registered and approved', ''], ['Request signed by phone key', ''], ['QR cryptographic validity', ''], ['QR time window', '1.4 s'],
      ['Office and gate active', ''], ['Authorised for this office', ''], ['GPS accuracy', '±12 m'], ['Inside geofence', '14 m'], ['No mock location', ''], ['Not a replay', '']];
    return {
      steps, checks, s: 0, p: 0, done: 0, tap: false, locked: false, clock: '09:02:11', timer: null, t0: 0,
      start() {
        // Demo clock: starts at 09:02:03 so it lands on the 09:02:14 punch shown on the result screen.
        let sec = 9 * 3600 + 2 * 60 + 3;
        const tick = () => { const t = sec % 86400; this.clock = [t / 3600, t / 60 % 60, t % 60].map(n => String(Math.floor(n)).padStart(2, '0')).join(':'); };
        tick(); setInterval(() => { sec++; tick(); }, 1000);
        new QRCode(this.$refs.pqr, { text: 'QRX1.demo.3.' + Date.now(), width: 180, height: 180, colorDark: '#0E2747', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
        this.go(0);
        requestAnimationFrame(this.frame.bind(this));
      },
      go(k) {
        this.s = k; this.t0 = performance.now(); this.p = 0; this.tap = false; this.locked = false; this.done = k > 2 ? 11 : 0;
      },
      frame(now) {
        const el = now - this.t0, ms = this.steps[this.s].ms;
        this.p = Math.min(100, el / ms * 100);
        if (this.s === 0) this.tap = el > ms - 700;
        if (this.s === 1) this.locked = el > ms * .55;
        if (this.s === 2) this.done = Math.min(11, Math.floor(el / ((ms - 700) / 11)));
        if (el >= ms) this.go((this.s + 1) % this.steps.length);
        requestAnimationFrame(this.frame.bind(this));
      },
    };
  }
</script>
@endpush
