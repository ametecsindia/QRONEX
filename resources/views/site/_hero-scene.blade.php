{{-- Hero scene: an office entrance with a QRONEX tablet kiosk; a phone scans the live code and the punch is verified.
     Art is inline SVG; the two screens are HTML so the QR is a real, rotating code. Loop: scan → lock → verified (7 s). --}}
<div class="hero-scene relative mx-auto w-full max-w-[600px] aspect-square select-none rounded-[36px] ring-1 ring-white/10 shadow-[0_60px_120px_-40px_rgba(0,0,0,.75),0_0_0_10px_rgba(255,255,255,.025)]" x-data="heroScene()" x-init="start()" aria-hidden="true">
  {{-- room --}}
  <svg viewBox="0 0 600 600" class="absolute inset-0 h-full w-full">
    <defs>
      <linearGradient id="hs-wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#14325A"/><stop offset="1" stop-color="#0B2140"/></linearGradient>
      <linearGradient id="hs-floor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0C2443"/><stop offset="1" stop-color="#081A33"/></linearGradient>
      <linearGradient id="hs-glass" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9FE7DD" stop-opacity=".28"/><stop offset=".55" stop-color="#5EEAD4" stop-opacity=".08"/><stop offset="1" stop-color="#9FE7DD" stop-opacity=".18"/></linearGradient>
      <linearGradient id="hs-metal" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#3A4A63"/><stop offset=".5" stop-color="#6B7B94"/><stop offset="1" stop-color="#2D3B52"/></linearGradient>
      <radialGradient id="hs-glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#14B8A6" stop-opacity=".45"/><stop offset="1" stop-color="#14B8A6" stop-opacity="0"/></radialGradient>
      <linearGradient id="hs-beam" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#5EEAD4" stop-opacity=".0"/><stop offset=".35" stop-color="#5EEAD4" stop-opacity=".35"/><stop offset="1" stop-color="#5EEAD4" stop-opacity=".05"/></linearGradient>
      <clipPath id="hs-room"><rect width="600" height="600" rx="36"/></clipPath>
    </defs>
    <g clip-path="url(#hs-room)">
      <rect width="600" height="600" fill="url(#hs-wall)"/>
      {{-- wall panels --}}
      @foreach([40, 120, 480, 560] as $x)<rect x="{{ $x }}" y="0" width="1" height="420" fill="#fff" opacity=".05"/>@endforeach
      {{-- floor with perspective lines --}}
      <path d="M0 420H600V600H0z" fill="url(#hs-floor)"/>
      <g stroke="#5EEAD4" stroke-opacity=".08">
        @foreach([-300, -150, 0, 150, 300, 450, 600, 750, 900] as $x)<line x1="300" y1="420" x2="{{ $x }}" y2="600"/>@endforeach
        @foreach([440, 470, 512, 570] as $y)<line x1="0" y1="{{ $y }}" x2="600" y2="{{ $y }}"/>@endforeach
      </g>
      <rect x="0" y="418" width="600" height="3" fill="#5EEAD4" opacity=".12"/>
      {{-- entrance: frame, sign, glass doors --}}
      <rect x="70" y="58" width="300" height="364" rx="6" fill="#0A1A30"/>
      <rect x="62" y="40" width="316" height="30" rx="5" fill="#0E2747" stroke="#ffffff" stroke-opacity=".08"/>
      <text x="220" y="60" text-anchor="middle" font-size="13" font-weight="700" fill="#E2E8F0" letter-spacing="1.5">HYDERABAD HQ · MAIN ENTRANCE</text>
      <rect x="70" y="70" width="300" height="3" class="transition-[fill] duration-500" :fill="phase === 'ok' ? '#14B8A6' : '#F2B33D'"/>
      <rect x="70" y="73" width="300" height="40" :fill="phase === 'ok' ? '#14B8A6' : '#F2B33D'" opacity=".07"/>
      <g>
        <rect x="80" y="80" width="138" height="336" rx="3" fill="url(#hs-glass)" stroke="#9FE7DD" stroke-opacity=".35"/>
        <rect x="222" y="80" width="138" height="336" rx="3" fill="url(#hs-glass)" stroke="#9FE7DD" stroke-opacity=".35"/>
        <path d="M96 96 L150 96 L104 300 L96 300z" fill="#fff" opacity=".08"/><path d="M238 96 L270 96 L246 210 L238 210z" fill="#fff" opacity=".07"/>
        <rect x="205" y="210" width="6" height="64" rx="3" fill="url(#hs-metal)"/><rect x="229" y="210" width="6" height="64" rx="3" fill="url(#hs-metal)"/>
        {{-- the office seen through the glass --}}
        <rect x="100" y="330" width="90" height="10" rx="2" fill="#5EEAD4" opacity=".08"/><rect x="250" y="300" width="80" height="40" rx="3" fill="#5EEAD4" opacity=".06"/>
      </g>
      {{-- glow on the floor in front of the kiosk --}}
      <ellipse cx="470" cy="430" rx="120" ry="26" fill="url(#hs-glow)"/>
      {{-- kiosk: light spill, pedestal, tablet bezel --}}
      <circle cx="470" cy="196" r="150" fill="url(#hs-glow)" opacity=".55"/>
      <rect x="462" y="250" width="16" height="174" rx="5" fill="url(#hs-metal)"/>
      <ellipse cx="470" cy="424" rx="44" ry="9" fill="#2D3B52"/><ellipse cx="470" cy="421" rx="40" ry="7" fill="#4A5A73"/>
      <rect x="392" y="96" width="156" height="200" rx="16" fill="#0A1220" stroke="#5B6B84" stroke-width="2"/>
      <circle cx="470" cy="106" r="2.5" fill="#334155"/>
      {{-- beam from the phone camera to the tablet --}}
      <polygon class="hs-beam" points="248,330 300,352 412,250 404,130" fill="url(#hs-beam)" :class="phase === 'scan' ? 'opacity-100' : 'opacity-0'"/>
    </g>
  </svg>

  {{-- tablet screen (HTML) --}}
  <div class="absolute rounded-[10px] overflow-hidden bg-white text-navy transition-colors duration-500" style="left:66.5%;top:18.4%;width:24.3%;height:30.5%" :class="phase === 'ok' ? '!bg-teal' : ''">
    <div class="absolute inset-0 flex flex-col items-center justify-center p-[6%]" x-show="phase !== 'ok'" x-transition.opacity>
      <div class="text-[clamp(6px,1.5vw,9px)] font-bold tracking-wide">SCAN TO PUNCH</div>
      <div class="relative mt-[5%] w-[78%] aspect-square">
        <svg viewBox="0 0 100 100" class="absolute -inset-[7%] w-[114%] h-[114%]"><circle cx="50" cy="50" r="47" fill="none" stroke="#E2E8F0" stroke-width="2.4"/><circle class="hs-ring" cx="50" cy="50" r="47" fill="none" stroke="#F2B33D" stroke-width="2.4" stroke-dasharray="296" stroke-linecap="round"/></svg>
        <div x-ref="kq" class="absolute inset-[6%] [&_img]:w-full [&_img]:h-full [&_canvas]:w-full [&_canvas]:h-full"></div>
      </div>
      <div class="mt-[5%] text-[clamp(5px,1.3vw,8px)] text-slate-500 num">Code <b class="text-navy" x-text="serial"></b> · new every 3 s</div>
    </div>
    <div class="absolute inset-0 flex flex-col items-center justify-center text-white text-center" x-show="phase === 'ok'" x-transition.opacity x-cloak>
      <span class="qx-pop grid h-[34%] aspect-square place-items-center rounded-full bg-white text-teal"><svg viewBox="0 0 24 24" class="w-1/2 h-1/2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
      <div class="mt-[7%] font-extrabold text-[clamp(8px,2vw,13px)]">Verified</div>
      <div class="text-[clamp(6px,1.4vw,9px)] text-teal-soft num">Priya Reddy · IN 09:02</div>
    </div>
  </div>

  {{-- hand + phone (foreground) --}}
  <div class="absolute hs-phone" style="left:16%;top:46%;width:34%;">
    <div class="relative" style="transform:rotate(-14deg);transform-origin:50% 80%">
      <svg viewBox="0 0 200 330" class="relative w-full">
        <defs><linearGradient id="hs-skin" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#C98E6B"/><stop offset="1" stop-color="#9A6447"/></linearGradient></defs>
        {{-- palm (behind the phone) --}}
        <path d="M40 226 C32 262 44 294 68 306 L150 306 C168 292 174 262 168 228 Z" fill="url(#hs-skin)"/>
        {{-- phone body --}}
        <rect x="38" y="6" width="124" height="250" rx="20" fill="#0B1220" stroke="#64748B" stroke-width="2"/>
        <rect x="160" y="70" width="3" height="34" rx="1.5" fill="#64748B"/>
        {{-- fingers over the right edge, thumb over the left edge --}}
        @foreach([112, 146, 180, 214] as $k => $y)<rect x="155" y="{{ $y }}" width="{{ 30 - $k * 2 }}" height="28" rx="14" fill="#B47555"/><rect x="157" y="{{ $y + 4 }}" width="{{ 10 }}" height="8" rx="4" fill="#D9A283" opacity=".55"/>@endforeach
        <path d="M24 262 C16 238 24 210 42 198 C54 190 64 198 62 212 L56 240 C52 258 36 272 24 262Z" fill="#C2876A"/>
        <path d="M44 204 C50 200 56 202 57 208" stroke="#E0B094" stroke-width="3" stroke-linecap="round" fill="none" opacity=".7"/>
        {{-- sleeve --}}
        <path d="M50 298 H166 L176 330 H40 Z" fill="#173A64"/><rect x="48" y="294" width="120" height="12" rx="4" fill="#21497A"/>
      </svg>
      {{-- phone screen (HTML) --}}
      <div class="absolute overflow-hidden rounded-[11%/6%] transition-colors duration-500" style="left:20.5%;top:6.6%;width:59%;height:68%" :class="phase === 'ok' ? 'bg-teal' : 'bg-[#0b1220]'">
        <div class="absolute inset-0 flex flex-col items-center pt-[22%]" x-show="phase !== 'ok'">
          <div class="text-[clamp(5px,1.2vw,8px)] text-white/70">Point at the screen by the door</div>
          <div class="relative mt-[10%] w-[68%] aspect-square">
            <div class="absolute inset-[10%] rounded bg-white p-[6%] transition duration-500" :class="phase === 'lock' ? 'scale-95' : 'rotate-[3deg] scale-[.92]'">
              <div x-ref="pq" class="w-full h-full [&_img]:w-full [&_img]:h-full [&_canvas]:w-full [&_canvas]:h-full"></div>
            </div>
            @foreach(['top-0 left-0 border-t-[3px] border-l-[3px] rounded-tl-md', 'top-0 right-0 border-t-[3px] border-r-[3px] rounded-tr-md', 'bottom-0 left-0 border-b-[3px] border-l-[3px] rounded-bl-md', 'bottom-0 right-0 border-b-[3px] border-r-[3px] rounded-br-md'] as $c)
              <span class="absolute h-[26%] w-[26%] transition-colors {{ $c }}" :class="phase === 'lock' ? 'border-teal-2' : 'border-white'"></span>
            @endforeach
            <span x-show="phase === 'scan'" class="scanline absolute left-[8%] right-[8%] h-[2px] bg-teal-2 shadow-[0_0_10px_#14B8A6]"></span>
          </div>
          <div class="mt-[10%] rounded-full px-[8%] py-[3%] text-[clamp(5px,1.1vw,8px)] font-semibold transition" :class="phase === 'lock' ? 'bg-teal-2 text-ink' : 'bg-white/10 text-white'" x-text="phase === 'lock' ? 'Checking…' : 'Scanning'"></div>
        </div>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-white text-center px-[8%]" x-show="phase === 'ok'" x-cloak>
          <span class="qx-pop grid h-[24%] aspect-square place-items-center rounded-full bg-white text-teal"><svg viewBox="0 0 24 24" class="w-1/2 h-1/2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
          <div class="mt-[10%] font-extrabold text-[clamp(8px,2vw,13px)]">IN recorded</div>
          <div class="num font-extrabold text-[clamp(10px,2.6vw,17px)]">09:02:14</div>
          <div class="mt-[6%] text-[clamp(5px,1.1vw,8px)] text-teal-soft">All 11 checks passed</div>
        </div>
      </div>
    </div>
  </div>

  {{-- glass cards --}}
  <div class="hs-card absolute left-[2%] top-[17%] rounded-2xl bg-white/10 backdrop-blur-md ring-1 ring-white/20 px-3.5 py-2.5 text-white shadow-xl">
    <div class="flex items-center gap-2 text-[clamp(9px,1.7vw,12px)] font-semibold"><span class="h-2 w-2 rounded-full bg-teal-2 animate-pulse"></span>Gate display live</div>
    <div class="num text-[clamp(8px,1.5vw,11px)] text-slate-300">signed code · rotates every 3 s</div>
  </div>
  <div class="absolute right-[1%] bottom-[5%] w-[46%] rounded-2xl bg-white/10 backdrop-blur-md ring-1 ring-white/20 p-3 text-white shadow-2xl transition duration-500" :class="phase === 'ok' ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-3'">
    <div class="text-[clamp(9px,1.7vw,12px)] font-bold">Server verified in 0.4 s</div>
    <ul class="mt-1.5 space-y-1 text-[clamp(8px,1.5vw,11px)] text-slate-200">
      @foreach(['Approved phone · request signed', 'Fresh code for this gate', 'Inside geofence · 14 m · ±12 m GPS', 'No mock location · no replay'] as $k => $t)
        <li class="flex items-center gap-1.5 transition duration-300" :class="phase === 'ok' ? 'opacity-100' : 'opacity-0'" style="transition-delay:{{ 150 + $k * 120 }}ms"><span class="grid h-3.5 w-3.5 shrink-0 place-items-center rounded-full bg-teal-2 text-ink text-[8px] font-bold">✓</span>{{ $t }}</li>
      @endforeach
    </ul>
  </div>
</div>
