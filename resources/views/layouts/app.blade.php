<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0E2747">
<title>{{ $title ?? 'QRONEX' }} · QRONEX</title>
<link rel="stylesheet" href="{{ asset('css/qronex.css') }}?v={{ @filemtime(public_path('css/qronex.css')) }}">
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
<script defer src="{{ asset('js/qronex.js') }}?v={{ @filemtime(public_path('js/qronex.js')) }}"></script>
@stack('head')
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen antialiased">
<div class="loading-bar" aria-hidden="true"></div>
@auth
@php
  $u = auth()->user();
  $waiting = 0;
  if (! $u->isPlatform() && $u->can('approve-devices')) {
      $waiting = collect(\App\Http\Controllers\Portal\ApprovalsController::pending(request()))->sum(fn ($c) => $c->count());
  }
  // [label, route, active pattern, icon, ability|null, badge]
  $nav = $u->isPlatform() ? [
      'Overview' => [['Dashboard', 'admin.dashboard', 'admin.dashboard', 'home'], ['Companies', 'admin.companies.index', 'admin.companies.*', 'building'], ['Support', 'admin.support.index', 'admin.support.*', 'chat']],
      'Revenue' => [['Plans & pricing', 'admin.plans.index', 'admin.plans.*', 'tag'], ['Payments', 'admin.payments', 'admin.payments', 'cash']],
      'Platform' => [['QR displays', 'admin.displays', 'admin.displays', 'qr'], ['API usage', 'admin.api', 'admin.api', 'code'], ['Email & SMS', 'admin.messaging.index', 'admin.messaging.*', 'mail'], ['Website', 'admin.website.edit', 'admin.website.*', 'window'], ['System', 'admin.system', 'admin.system', 'server'], ['Platform settings', 'admin.settings.edit', 'admin.settings.*', 'cog']],
      'Security' => [['Admin users', 'admin.users.index', 'admin.users.*', 'shield'], ['Audit log', 'admin.audit', 'admin.audit', 'list']],
  ] : [
      'Overview' => [['Dashboard', 'portal.dashboard', 'portal.dashboard', 'home'], ['Attendance ledger', 'portal.attendance.index', 'portal.attendance.*', 'list'], ['Reports', 'portal.reports', 'portal.reports', 'chart']],
      'Approvals' => [['Approvals', 'portal.approvals', 'portal.approvals', 'check', 'approve-devices', $waiting], ['Devices', 'portal.devices.index', 'portal.devices.*', 'phone', 'approve-devices'], ['Remote attendance', 'portal.remote.index', 'portal.remote.*', 'globe', 'approve-devices']],
      'Organisation' => [['Employees', 'portal.employees.index', 'portal.employees.*', 'users', 'manage-employees'], ['Offices & gates', 'portal.offices.index', 'portal.offices.*', 'building', 'manage-offices'], ['Users & roles', 'portal.users.index', 'portal.users.*', 'shield', 'manage-users']],
      'Settings' => [['Attendance policy', 'portal.policy.edit', 'portal.policy.*', 'sliders', 'manage-company'], ['API & webhooks', 'portal.api.index', 'portal.api.*', 'code', 'manage-company'], ['Company profile', 'portal.company.edit', 'portal.company.*', 'briefcase', 'manage-company'], ['Subscription & billing', 'portal.billing.index', 'portal.billing.*', 'card', 'manage-billing'], ['Help & support', 'portal.help.index', 'portal.help.*', 'help']],
  ];
  $initials = collect(explode(' ', $u->name))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');
@endphp
<div x-data="{open:false}" class="lg:flex">
  <div x-show="open" x-transition.opacity class="fixed inset-0 bg-navy/60 z-30 lg:hidden" @click="open=false" x-cloak></div>
  <div class="lg:w-64 lg:shrink-0 {{ $u->isPlatform() ? 'lg:bg-[#0B1220]' : 'lg:bg-ink' }}">
  <aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="sidebar {{ $u->isPlatform() ? 'is-platform bg-[#0B1220]' : 'bg-gradient-to-b from-navy to-ink' }} fixed z-40 inset-y-0 left-0 w-64 text-white flex flex-col -translate-x-full transition-transform duration-200 lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:shrink-0">
    <div class="px-5 pt-5 pb-4 flex items-center gap-3">
      <svg viewBox="0 0 32 32" class="h-9 w-9 shrink-0" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#fff"/><rect x="6" y="6" width="8" height="8" rx="1.5" fill="none" stroke="#0E2747" stroke-width="2"/><rect x="18" y="6" width="8" height="8" rx="1.5" fill="none" stroke="#0E2747" stroke-width="2"/><rect x="6" y="18" width="8" height="8" rx="1.5" fill="none" stroke="#0E2747" stroke-width="2"/><circle cx="22" cy="22" r="3.2" fill="#0A8278"/></svg>
      <div class="min-w-0">
        <div class="font-bold tracking-wide">QRONEX</div>
        @if($u->isPlatform())<div class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-scan/15 text-scan text-[11px] font-semibold px-2 py-0.5">{{ $u->roleLabel() }} console</div>
        @else<div class="text-[11px] text-slate-300 truncate">Client Admin · {{ $u->tenant->name }}</div>@endif
      </div>
      <button class="ml-auto lg:hidden text-slate-300" @click="open=false" aria-label="Close menu"><x-icon name="x" /></button>
    </div>
    <nav class="nav flex-1 overflow-y-auto px-3 pb-4 space-y-5">
      @foreach($nav as $group => $items)
        @php($items = array_filter($items, fn ($i) => empty($i[4]) || $u->can($i[4])))
        @if($items)
        <div>
          <div class="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-400">{{ $group }}</div>
          @foreach($items as $i)
            <a href="{{ route($i[1]) }}" @class(['on' => request()->routeIs($i[2])])><x-icon :name="$i[3]" class="w-[18px] h-[18px] shrink-0 opacity-80" /><span class="truncate">{{ $i[0] }}</span>
              @if(! empty($i[5]))<span class="ml-auto chip bg-amber-400 text-navy">{{ $i[5] }}</span>@endif</a>
          @endforeach
        </div>
        @endif
      @endforeach
    </nav>
    <div class="border-t border-white/10 p-3" x-data="{m:false}">
      <button class="w-full flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-white/5 text-left" @click="m=!m" :aria-expanded="m">
        <span class="h-8 w-8 rounded-full bg-teal flex items-center justify-center text-xs font-bold shrink-0">{{ $initials }}</span>
        <span class="min-w-0"><span class="block text-sm truncate">{{ $u->name }}</span><span class="block text-[11px] text-slate-300 truncate">{{ $u->roleLabel() }}</span></span>
      </button>
      <div x-show="m" x-transition x-cloak class="mt-1 space-y-0.5 text-sm">
        <a href="{{ route('account.security') }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-slate-200 hover:bg-white/5"><x-icon name="shield" class="w-4 h-4" /> Account &amp; security</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="w-full flex items-center gap-2 rounded-md px-3 py-2 text-slate-200 hover:bg-white/5"><x-icon name="logout" class="w-4 h-4" /> Sign out</button></form>
      </div>
    </div>
  </aside>
  </div>

  <main class="flex-1 min-w-0">
    @if($u->isPlatform())<div class="h-1 bg-gradient-to-r from-scan via-amber-300 to-scan" aria-hidden="true"></div>@endif
    <header class="sticky top-0 z-20 bg-white/85 backdrop-blur border-b border-slate-200/80 px-4 md:px-8 py-4 flex flex-wrap items-center gap-3">
      <button class="lg:hidden btn-ghost btn-sm" @click="open=true" aria-label="Open menu"><x-icon name="menu" /></button>
      <div class="min-w-0">
        <div class="text-[11px] text-slate-500 flex items-center gap-2">@if($u->isPlatform())<span class="rounded bg-[#0B1220] text-scan font-semibold px-1.5 py-px">SUPER ADMIN</span>@else<span class="rounded bg-teal-soft text-teal font-semibold px-1.5 py-px">CLIENT ADMIN</span>@endif @isset($crumb)<span>{!! $crumb !!}</span>@endisset</div>
        <h1 class="text-xl md:text-2xl font-extrabold tracking-tight text-navy truncate">{{ $title ?? '' }}</h1>
      </div>
      <div class="ml-auto flex flex-wrap items-center gap-2">@yield('actions')</div>
    </header>
    <div class="p-4 md:p-8 space-y-5 max-w-[1400px] fade-in">
      @if($u->emailPending() && ! request()->routeIs('account.verify-email'))<div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm flex gap-2"><x-icon name="mail" class="w-5 h-5 shrink-0" /><span>Confirm your email address — we sent a 6-digit code to {{ $u->email }}. <a class="font-semibold underline" href="{{ route('account.verify-email') }}">Enter code</a></span></div>@endif
      @include('components.flash')
      @yield('content')
    </div>
  </main>
</div>
@else
<main class="min-h-screen flex items-center justify-center p-4 auth-bg">
  <div class="w-full {{ $wide ?? false ? 'max-w-2xl' : 'max-w-md' }} space-y-4 fade-in">
    <a href="{{ url('/') }}" class="flex items-center justify-center gap-3 text-white">
      <svg viewBox="0 0 32 32" class="h-11 w-11 shrink-0" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#fff"/><rect x="6" y="6" width="8" height="8" rx="1.5" fill="none" stroke="#0E2747" stroke-width="2"/><rect x="18" y="6" width="8" height="8" rx="1.5" fill="none" stroke="#0E2747" stroke-width="2"/><rect x="6" y="18" width="8" height="8" rx="1.5" fill="none" stroke="#0E2747" stroke-width="2"/><circle cx="22" cy="22" r="3.2" fill="#0A8278"/></svg>
      <span class="text-2xl font-bold tracking-wide">QRONEX</span>
    </a>
    @include('components.flash')
    @yield('content')
    <p class="text-center text-xs text-slate-300">Secure QR attendance · by Ametecs India</p>
  </div>
</main>
@endauth
@stack('scripts')
</body>
</html>
