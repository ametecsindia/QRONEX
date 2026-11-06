@extends('layouts.site')
@section('content')
@php($cms = \App\Http\Controllers\SiteController::cms())

{{-- HERO: the product's own artefact — a live entrance display that rotates every 3 s --}}
<section class="relative overflow-hidden bg-ink text-white">
  <div aria-hidden="true" class="absolute inset-0 opacity-[.06]" style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:44px 44px;mask-image:radial-gradient(ellipse at 70% 40%,#000,transparent 70%)"></div>
  <div aria-hidden="true" class="absolute -right-40 -top-40 h-[620px] w-[620px] rounded-full bg-teal-2/20 blur-[120px]"></div>
  <div aria-hidden="true" class="absolute -left-40 bottom-0 h-[420px] w-[420px] rounded-full bg-navy-3/40 blur-[110px]"></div>
  <div class="site-wrap relative grid lg:grid-cols-[1fr_1.05fr] gap-10 lg:gap-14 items-center py-12 sm:py-20">
    <div>
      <p class="text-teal-glow font-semibold">For offices, branches and field teams across India</p>
      <h1 class="mt-4 text-[2.6rem] sm:text-6xl lg:text-[4.4rem] leading-[.98] font-extrabold tracking-tightest !text-white">{{ $cms['hero_title'] }}</h1>
      <p class="mt-6 text-lg sm:text-xl text-slate-300 max-w-[34rem] leading-relaxed">{{ $cms['hero_sub'] }}</p>
      <div class="mt-9 flex flex-wrap gap-3">
        <a class="site-btn" href="{{ route('register') }}">Start your free trial</a>
        <a class="site-btn-line text-white" href="#app">See how a punch works</a>
      </div>
      <dl class="mt-12 hidden sm:grid grid-cols-3 max-w-md gap-6 border-t border-white/15 pt-6">
        <div><dt class="text-sm text-slate-400">New code every</dt><dd class="num text-3xl font-extrabold">3 s</dd></div>
        <div><dt class="text-sm text-slate-400">Server checks</dt><dd class="num text-3xl font-extrabold">11</dd></div>
        <div><dt class="text-sm text-slate-400">Hardware needed</dt><dd class="text-3xl font-extrabold">None</dd></div>
      </dl>
    </div>

    @include('site._hero-scene')
  </div>
</section>

{{-- WHY --}}
<section class="py-20 sm:py-28">
  <div class="site-wrap grid lg:grid-cols-[1fr_1.4fr] gap-12">
    <h2 class="reveal site-h2">Biometric machines break. Buddy punching costs money. QRONEX removes both.</h2>
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
      <h2 class="reveal site-h2">Every punch passes 11 checks before it counts.</h2>
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

{{-- HOW IT WORKS: real QRONEX screens (captured from the app with demo data), played as a guided tour --}}
@php($tour = [
  ['/register', 'Create workspace', 50, 85.9],
  ['/stormfinancial/offices', 'Add office', 94.3, 4.6],
  ['/stormfinancial/offices/1/edit', 'Radius 150 m', 30, 64.6],
  ['/stormfinancial/employees', 'Import CSV', 84.7, 4.6],
  ['/stormfinancial/approvals', 'Approve with match code', 88.8, 39.1],
  ['/display/stormfinancial/hyderabad/gate1', 'New code every 3 s', 50, 50.9],
  ['/stormfinancial/attendance', 'Every punch, verified', 58.9, 31.4],
  ['/stormfinancial/reports', 'Download Excel', 25.4, 33.4],
])
<section id="how" class="relative overflow-hidden bg-ink text-white py-20 sm:py-28" x-data="howDemo(@js(array_map(fn ($t) => [$t[2], $t[3]], $tour)))" x-init="start()">
  <div aria-hidden="true" class="absolute inset-0 opacity-[.05]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:22px 22px"></div>
  <div class="site-wrap relative">
    <div class="max-w-3xl">
      <p class="text-teal-glow font-semibold">The real product, not a mock-up</p>
      <h2 class="reveal site-h2 !text-white mt-2">From sign-up to the first punch in one afternoon.</h2>
      <p class="mt-4 text-lg text-slate-300 leading-relaxed">These are live QRONEX screens with demo data. Everything happens in the browser; the only thing at the door is a screen.</p>
    </div>

    <div class="mt-12 grid lg:grid-cols-[320px_1fr] gap-10 lg:gap-14 items-start">
      {{-- Step rail --}}
      <ol class="order-2 lg:order-1 relative" role="tablist" aria-label="Setup steps">
        <span aria-hidden="true" class="absolute left-[15px] top-4 bottom-4 w-px bg-white/15"></span>
        @foreach(\App\Http\Controllers\SiteController::HOW as $i => [$t, $d])
          <li class="relative">
            <button type="button" role="tab" :aria-selected="s === {{ $i }}" @click="go({{ $i }})" class="group flex w-full gap-4 rounded-xl py-2.5 pr-3 text-left">
              <span class="num relative z-10 h-8 w-8 shrink-0 rounded-full text-sm font-bold grid place-items-center ring-4 ring-ink transition"
                :class="s === {{ $i }} ? 'bg-teal-2 text-ink' : (s > {{ $i }} ? 'bg-teal text-white' : 'bg-navy-2 text-slate-300')">{{ $i + 1 }}</span>
              <span class="min-w-0 flex-1 pt-1">
                <span class="block font-bold transition" :class="s === {{ $i }} ? 'text-white' : 'text-slate-400 group-hover:text-white'">{{ $t }}</span>
                <span x-show="s === {{ $i }}" class="block">
                  <span class="mt-1 block text-sm text-slate-300 leading-relaxed">{{ $d }}</span>
                  <span class="mt-3 block h-0.5 rounded bg-white/10 overflow-hidden"><span class="block h-full bg-teal-2" :style="`width:${p}%`"></span></span>
                </span>
              </span>
            </button>
          </li>
        @endforeach
      </ol>

      {{-- Laptop with the real screens --}}
      <div class="order-1 lg:order-2" aria-hidden="true">
        <div class="rounded-t-[18px] bg-[#1c2433] p-2 sm:p-3 pb-0 shadow-[0_60px_120px_-40px_rgba(0,0,0,.8)] ring-1 ring-white/10">
          <div class="flex items-center gap-2 rounded-t-lg bg-[#2a3344] px-3 py-1.5">
            <span class="flex gap-1.5"><span class="h-2 w-2 rounded-full bg-[#F87171]"></span><span class="h-2 w-2 rounded-full bg-scan"></span><span class="h-2 w-2 rounded-full bg-teal-2"></span></span>
            <span class="flex-1 truncate rounded bg-black/30 px-2.5 py-0.5 text-[11px] text-slate-400">🔒 qronex.app<span class="text-white" x-text="urls[s]"></span></span>
          </div>
          <div class="relative aspect-[16/10] overflow-hidden bg-white">
            @foreach($tour as $i => [$url, $call, $x, $y])
              <img src="{{ asset('images/demo/step'.($i + 1).'.webp') }}" alt="" width="1200" height="750" @if($i) loading="lazy" @endif
                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-500 will-change-transform"
                :class="s === {{ $i }} ? 'opacity-100' : 'opacity-0'"
                :style="`transform-origin:{{ $x }}% {{ $y }}%;transform:scale(${s === {{ $i }} && zoom ? 1.16 : 1});transition:opacity .5s, transform ${s === {{ $i }} && zoom ? 2.2 : 0}s cubic-bezier(.3,.7,.3,1)`">
            @endforeach
            {{-- click ripple + callout at the target --}}
            <span class="pointer-events-none absolute -translate-x-1/2 -translate-y-1/2" :style="`left:${pt[0]}%;top:${pt[1]}%`">
              <span x-show="clicked" class="absolute left-1/2 top-1/2 h-14 w-14 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-teal-2 animate-ping"></span>
            </span>
            <span x-show="zoom" x-transition.opacity.duration.400ms class="pointer-events-none absolute z-10 whitespace-nowrap rounded-full bg-ink/90 px-3 py-1.5 text-xs font-semibold text-white shadow-lg ring-1 ring-teal-2/60"
              :style="`${pt[0] > 60 ? 'right:' + Math.max(2, 100 - pt[0] - 4) : 'left:' + Math.max(2, pt[0] - 8)}%;top:${pt[1] > 70 ? pt[1] - 13 : pt[1] + 6}%`" x-text="calls[s]"></span>
            {{-- cursor --}}
            <svg viewBox="0 0 24 24" class="pointer-events-none absolute z-20 h-6 w-6 drop-shadow-[0_2px_3px_rgba(0,0,0,.45)] transition-[left,top] duration-1000 ease-in-out" :class="clicked ? 'scale-90' : ''" :style="`left:${cur[0]}%;top:${cur[1]}%`">
              <path d="M4 2l15 9.5-6.6 1.4L9.6 20z" fill="#fff" stroke="#081A33" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>
        <div class="mx-auto h-3 w-[104%] -ml-[2%] rounded-b-2xl bg-gradient-to-b from-[#3b4557] to-[#202836]"></div>
        <div class="mx-auto h-1 w-1/4 rounded-b-lg bg-[#151b26]"></div>
      </div>
    </div>
  </div>
</section>

{{-- ON THE PHONE: a live run-through of one punch, in the app's own screens --}}
<section id="app" class="bg-paper py-20 sm:py-28" x-data="phoneDemo()" x-init="start()">
  <div class="site-wrap grid lg:grid-cols-[1fr_auto] gap-14 items-center">
    <div>
      <p class="text-teal font-semibold">What your employee sees</p>
      <h2 class="reveal site-h2 mt-2">One tap at the door. Verified in under two seconds.</h2>
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

{{-- WHAT HR SEES: real Client Admin screens (demo data) --}}
@php($consoleTabs = [
  ['Live dashboard', 'admin-dashboard', '/stormfinancial', ['Who is in, on time, late or not in yet, office by office', '14-day attendance trend and arrival times', 'Rejected scans grouped by reason, so problems surface early']],
  ['Phones & approvals', 'admin-device', '/stormfinancial/devices/1', ['One approved phone per employee; approve with the match code shown on it', 'Full history: registered, approved, replaced, revoked, with who and when', 'Every punch made from that phone, and whether its requests are signed']],
  ['Employee record', 'admin-employee', '/stormfinancial/employees/1', ['30-day summary: days present, late days, average arrival and hours', 'Day-by-day first IN, last OUT and hours worked', 'Their phones, remote permissions and any rejected scans in one place']],
])
<section id="admin" class="py-20 sm:py-28" x-data="{t:0}">
  <div class="site-wrap">
    <div class="reveal grid lg:grid-cols-[1fr_auto] gap-6 items-end">
      <div><p class="text-teal font-semibold">What HR and admins see</p><h2 class="reveal site-h2 mt-2">One console for every office, phone and punch.</h2></div>
      <div class="flex flex-wrap gap-2" role="tablist" aria-label="Admin console screens">
        @foreach($consoleTabs as $k => [$label])
          <button type="button" role="tab" :aria-selected="t === {{ $k }}" @click="t = {{ $k }}" class="rounded-full px-4 py-2 text-sm font-semibold transition" :class="t === {{ $k }} ? 'bg-navy text-white' : 'bg-paper text-navy hover:bg-slate-200'">{{ $label }}</button>
        @endforeach
      </div>
    </div>
    <div class="reveal mt-10 grid lg:grid-cols-[1fr_300px] gap-8 items-start">
      <div class="rounded-2xl bg-white ring-1 ring-slate-200 shadow-[0_40px_80px_-40px_rgba(14,39,71,.45)] overflow-hidden">
        <div class="flex items-center gap-3 border-b border-slate-200 bg-paper px-4 py-2.5">
          <span class="flex gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span></span>
          <span class="flex-1 truncate rounded-md bg-white px-3 py-1 text-xs text-slate-500 ring-1 ring-slate-200">🔒 qronex.app<span class="text-navy">@foreach($consoleTabs as $k => $c)<span x-show="t === {{ $k }}">{{ $c[2] }}</span>@endforeach</span></span>
        </div>
        <div class="relative aspect-[16/10] bg-paper">
          @foreach($consoleTabs as $k => [$label, $img])
            <img src="{{ asset('images/demo/'.$img.'.webp') }}" alt="QRONEX Client Admin — {{ $label }} (demo data)" width="1200" height="750" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition-opacity duration-500" :class="t === {{ $k }} ? 'opacity-100' : 'opacity-0'">
          @endforeach
        </div>
      </div>
      <div>
        @foreach($consoleTabs as $k => [$label, $img, $url, $points])
          <ul x-show="t === {{ $k }}" @if($k) x-cloak @endif class="space-y-5">
            @foreach($points as $pt)<li class="flex gap-3"><span class="mt-1 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-teal-soft text-teal"><x-icon name="check" class="w-3.5 h-3.5" /></span><span class="text-slate-700 leading-relaxed">{{ $pt }}</span></li>@endforeach
          </ul>
        @endforeach
        <p class="mt-8 text-sm text-slate-500">Real screens from the QRONEX Client Admin with demo data. Roles decide who sees what: owners, administrators, office managers and approvers.</p>
      </div>
    </div>
  </div>
</section>

{{-- WHO IT'S FOR: one interactive selector instead of 8 identical cards --}}
<section class="bg-navy text-white py-20 sm:py-28" x-data="{i:0}">
  <div class="site-wrap grid lg:grid-cols-[1fr_1.3fr] gap-12 items-center">
    <div>
      <h2 class="reveal site-h2 !text-white">Built for teams that clock in at a door.</h2>
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

{{-- FEATURES: the three jobs QRONEX does, as one pipeline. Each stage has a small live visual. --}}
<section id="features" class="relative py-20 sm:py-28 overflow-hidden">
  <div aria-hidden="true" class="absolute inset-x-0 top-0 h-[420px] bg-gradient-to-b from-paper to-white"></div>
  <div class="site-wrap relative">
    <div class="reveal max-w-3xl">
      <p class="text-teal font-semibold">Everything in one platform</p>
      <h2 class="site-h2 mt-2">Capture, verify, deliver. Nothing else to buy.</h2>
      <p class="site-lede mt-4">QRONEX records attendance and hands it to the software you already run. It does not do payroll, leave or productivity tracking.</p>
    </div>
    @php($f = collect(\App\Http\Controllers\SiteController::FEATURES))
    @php($stages = [
      ['01', 'Capture', 'At the door', 'qr', [[0, 'qr'], [1, 'clock'], [5, 'building'], [6, 'window'], [7, 'home']]],
      ['02', 'Verify', 'On the server', 'shield', [[2, 'globe'], [3, 'phone'], [4, 'users'], [14, 'list']]],
      ['03', 'Deliver', 'To your team and systems', 'code', [[8, 'chart'], [9, 'inbox'], [10, 'code'], [11, 'server'], [12, 'cog'], [13, 'card']]],
    ])
    <div class="relative mt-14">
      {{-- connector with a travelling punch (desktop) --}}
      <div aria-hidden="true" class="hidden lg:block absolute left-[16%] right-[16%] top-[118px] h-px bg-gradient-to-r from-teal/0 via-teal/40 to-teal/0">
        <span class="feat-dot absolute -top-[5px] h-[11px] w-[11px] rounded-full bg-teal-2 shadow-[0_0_14px_#14B8A6]"></span>
      </div>
      <div class="grid lg:grid-cols-3 gap-6">
        @foreach($stages as $si => [$no, $name, $where, $ic, $items])
          <article class="reveal group relative flex flex-col rounded-3xl bg-white ring-1 ring-slate-200 p-6 sm:p-7 transition duration-300 hover:-translate-y-1 hover:ring-teal/40 hover:shadow-[0_40px_70px_-40px_rgba(14,39,71,.45)]" style="transition-delay:{{ $si * 90 }}ms">
            <div class="flex items-center gap-3">
              <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-navy text-white"><x-icon :name="$ic" /></span>
              <div><div class="num text-xs font-bold text-teal">{{ $no }} · {{ $where }}</div><h3 class="text-xl font-extrabold text-navy">{{ $name }}</h3></div>
            </div>

            {{-- live visual --}}
            <div class="relative mt-6 h-36 overflow-hidden rounded-2xl bg-ink" aria-hidden="true">
              <div class="absolute inset-0 opacity-[.07]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:16px 16px"></div>
              @if($si === 0)
                <div class="absolute inset-0 flex items-center justify-center gap-5" x-data="{ s: '' }" x-init="const d = () => { $refs.q.innerHTML = ''; new QRCode($refs.q, { text: 'QRX1.demo.' + Date.now(), width: 84, height: 84, colorDark: '#0E2747', colorLight: '#fff', correctLevel: QRCode.CorrectLevel.L }); s = String(Math.floor(Date.now() / 3000)).slice(-4) }; d(); setInterval(d, 3000)">
                  <div class="relative rounded-xl bg-white p-2 shadow-lg"><div x-ref="q" class="h-[84px] w-[84px] [&_img]:w-full [&_canvas]:w-full"></div><span class="scanline absolute left-2 right-2 h-0.5 bg-teal-2/80 shadow-[0_0_10px_#14B8A6]"></span></div>
                  <div class="text-white"><div class="text-[11px] text-slate-400">Main entrance</div><div class="num text-2xl font-extrabold" x-text="s"></div><div class="mt-1 h-1 w-20 overflow-hidden rounded bg-white/15"><div class="feat-bar h-full bg-scan"></div></div><div class="mt-1 text-[11px] text-slate-400">new code every 3 s</div></div>
                </div>
              @elseif($si === 1)
                <ul class="absolute inset-0 flex flex-col justify-center gap-2 px-5 text-[13px] text-white">
                  @foreach(['Signed by the approved phone', 'Code fresh for this gate', 'Inside the geofence · 14 m'] as $k => $t)
                    <li class="feat-check flex items-center gap-2 whitespace-nowrap rounded-lg bg-white/5 px-3 py-1.5 ring-1 ring-white/10" style="animation-delay:{{ $k * .5 }}s"><span class="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-teal-2 text-[10px] font-bold text-ink">✓</span>{{ $t }}</li>
                  @endforeach
                </ul>
              @else
                <div class="absolute inset-0 flex items-center gap-3 px-5">
                  <div class="feat-event shrink-0 whitespace-nowrap rounded-lg bg-teal-2 px-2.5 py-1.5 font-mono text-[11px] font-bold text-ink shadow-[0_0_18px_rgba(20,184,166,.5)]">attendance.created</div>
                  <div class="h-px flex-1 bg-gradient-to-r from-teal-2/70 to-white/10"></div>
                  <div class="flex flex-col gap-1.5 text-[11px] font-semibold">
                    @foreach(['HRMS webhook', 'Payroll API', 'Excel / CSV'] as $k => $t)<span class="feat-target whitespace-nowrap rounded-md bg-white/10 px-2.5 py-1 text-white ring-1 ring-white/10" style="animation-delay:{{ .6 + $k * .35 }}s">{{ $t }}</span>@endforeach
                  </div>
                </div>
              @endif
            </div>

            <ul class="mt-6 space-y-4">
              @foreach($items as [$n, $fic])
                <li class="flex gap-3">
                  <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-soft text-teal"><x-icon :name="$fic" class="w-4 h-4" /></span>
                  <div><div class="font-semibold text-navy">{{ $f[$n][0] }}</div><div class="text-sm text-slate-600 leading-relaxed">{{ $f[$n][1] }}</div></div>
                </li>
              @endforeach
            </ul>
          </article>
        @endforeach
      </div>
    </div>
  </div>
</section>

{{-- INTEGRATIONS --}}
<section class="bg-paper py-20">
  <div class="site-wrap grid lg:grid-cols-2 gap-12 items-center">
    <div>
      <h2 class="reveal site-h2">Attendance flows straight into your HRMS.</h2>
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
      <div><h2 class="reveal site-h2">Pay per employee. Monthly, quarterly or yearly.</h2><p class="site-lede mt-4">Every plan starts with a free trial. Prices exclude GST.</p></div>
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
          <a class="mt-7 w-full {{ $p->is_popular ? 'site-btn' : 'site-btn-line text-navy' }}" href="{{ $v->pricing_model === 'custom' ? route('contact', ['topic' => 'pricing']) : route('register') }}">{{ $v->pricing_model === 'custom' ? 'Talk to sales' : 'Start free trial' }}</a>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- FAQ --}}
<section id="faq" class="bg-paper py-20 sm:py-28">
  <div class="site-wrap grid lg:grid-cols-[1fr_1.6fr] gap-12">
    <div><h2 class="reveal site-h2">Questions operations teams ask us.</h2><p class="site-lede mt-4">Something else? <a class="text-teal font-semibold underline underline-offset-4" href="{{ route('contact') }}">Ask us directly</a>.</p></div>
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

{{-- SUPPORT & CONTACT --}}
<section class="py-20 sm:py-24">
  <div class="site-wrap">
    <h2 class="reveal site-h2">Talk to the team that builds it.</h2>
    <div class="reveal mt-10 grid md:grid-cols-3 gap-5">
      @foreach([
        ['chat', 'Sales and demos', 'Pricing for many sites, a walkthrough with your own offices, or a pilot for one branch.', route('contact'), 'Contact sales'],
        ['help', 'Customer support', 'Signed-in customers raise tickets from Help & support in the console and follow every reply there.', route('login'), 'Sign in for support'],
        ['code', 'Integration help', 'REST API, webhooks and the OpenAPI file, with guides for payroll and HRMS sync.', route('docs.api'), 'Open the API reference'],
      ] as [$ic, $t, $d, $href, $cta])
        <a href="{{ $href }}" class="group rounded-3xl border border-slate-200 p-7 transition hover:-translate-y-1 hover:border-teal/40 hover:shadow-[0_30px_60px_-30px_rgba(14,39,71,.35)]">
          <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-teal-soft text-teal"><x-icon :name="$ic" class="w-6 h-6" /></span>
          <h3 class="mt-5 text-lg font-bold text-navy">{{ $t }}</h3><p class="mt-2 text-slate-600 leading-relaxed">{{ $d }}</p>
          <span class="mt-5 inline-flex items-center gap-1 font-semibold text-teal">{{ $cta }} <span class="transition group-hover:translate-x-1">→</span></span>
        </a>
      @endforeach
    </div>
    <p class="reveal mt-8 text-slate-500">Email <a class="font-semibold text-navy underline underline-offset-4" href="mailto:{{ config('qronex.supplier.email') }}">{{ config('qronex.supplier.email') }}</a> · Ametecs India Private Limited, Kondapur, Hyderabad</p>
  </div>
</section>

{{-- CTA --}}
<section class="bg-teal text-white">
  <div class="site-wrap py-16 sm:py-20 flex flex-col lg:flex-row lg:items-center justify-between gap-8">
    <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tightest !text-white max-w-[18ch] leading-[1.02]">Put a QR at your door this week.</h2>
    <div class="flex flex-wrap gap-3"><a class="site-btn !bg-white !text-navy hover:!bg-slate-100" href="{{ route('register') }}">Start free trial</a><a class="site-btn-line text-white" href="{{ route('contact', ['topic' => 'demo']) }}">Book a demo</a></div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('vendor/qrcode.min.js') }}"></script>
<script>
  // Hero scene. Demo only: random codes shaped like the real rotating token (the real display gets signed codes from the server).
  function heroScene() {
    return {
      phase: 'scan', serial: '',
      draw() {
        const rnd = Array.from(crypto.getRandomValues(new Uint8Array(10)), b => b.toString(16).padStart(2, '0')).join('');
        const win = Math.floor(Date.now() / 3000), text = 'QRX1.demo.3.' + win + '.' + rnd;
        this.serial = String(win).slice(-4);
        for (const [ref, px] of [['kq', 120], ['pq', 90]]) { this.$refs[ref].innerHTML = ''; new QRCode(this.$refs[ref], { text, width: px, height: px, colorDark: '#0E2747', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M }); }
      },
      start() {
        this.draw(); setInterval(() => this.phase !== 'ok' && this.draw(), 3000);
        const loop = () => { this.phase = 'scan'; setTimeout(() => this.phase = 'lock', 2600); setTimeout(() => this.phase = 'ok', 3400); setTimeout(loop, 7600); };
        loop();
      },
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
  // Guided tour over real screenshots: cursor glides to the step's target, clicks, then the screen zooms in there.
  function howDemo(pts) {
    const ms = 4200, calls = ['Create workspace', 'Add office', 'Radius 150 m', 'Import CSV', 'Approve with match code', 'New code every 3 s', 'Every punch, verified', 'Download Excel'];
    return {
      s: 0, el: 0, p: 0, t0: 0, cur: [50, 60], pt: pts[0], zoom: false, clicked: false, calls,
      urls: ['/register', '/stormfinancial/offices', '/stormfinancial/offices/1/edit', '/stormfinancial/employees', '/stormfinancial/approvals', '/display/stormfinancial/hyderabad/gate1', '/stormfinancial/attendance', '/stormfinancial/reports'],
      go(k) { this.s = k; this.pt = pts[k]; this.t0 = performance.now(); this.el = 0; this.p = 0; this.zoom = false; this.clicked = false; },
      start() {
        this.go(0);
        setInterval(() => {
          this.el = performance.now() - this.t0; this.p = Math.min(100, this.el / ms * 100);
          if (this.el > 300) this.cur = this.pt;                       // glide (CSS transition, 1 s)
          this.clicked = this.el > 1400 && this.el < 2000;
          this.zoom = this.el > 1700;
          if (this.el >= ms) this.go((this.s + 1) % pts.length);
        }, 50);
      },
    };
  }
</script>
@endpush
