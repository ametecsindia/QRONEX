<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0E2747">
<title>{{ $title ?? 'QR attendance without biometric machines' }} · QRONEX</title>
<meta name="description" content="QRONEX turns any screen into a secure QR attendance point. Employees scan with their phone; the server verifies the phone, the time and the location.">
<meta property="og:title" content="QRONEX · QR attendance without biometric machines">
<meta property="og:description" content="Turn any screen into a secure QR attendance point. Approved phones, live rotating codes, geofencing and 11 server checks on every punch.">
<meta property="og:type" content="website"><meta property="og:url" content="{{ url()->current() }}">
<script>document.documentElement.classList.add('js')</script>
<link rel="preload" href="/fonts/schibsted-grotesk.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('css/qronex.css') }}?v={{ @filemtime(public_path('css/qronex.css')) }}">
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
</head>
<body class="bg-white text-slate-800 antialiased">
@if($ann = \App\Http\Controllers\SiteController::cms()['announcement'])<div class="bg-scan text-ink text-center text-sm font-semibold px-4 py-2">{{ $ann }}</div>@endif
<header class="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-slate-200/70" x-data="{o:false}">
  <div class="site-wrap h-16 flex items-center gap-6">
    <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-extrabold text-navy text-lg tracking-tight" aria-label="QRONEX home">
      <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0E2747"/><rect x="6" y="6" width="8" height="8" rx="1.5" fill="none" stroke="#fff" stroke-width="2"/><rect x="18" y="6" width="8" height="8" rx="1.5" fill="none" stroke="#fff" stroke-width="2"/><rect x="6" y="18" width="8" height="8" rx="1.5" fill="none" stroke="#fff" stroke-width="2"/><circle cx="22" cy="22" r="3.2" fill="#14B8A6"/></svg>
      QRONEX</a>
    <nav class="hidden lg:flex gap-6 whitespace-nowrap text-[15px] text-slate-600">
      <a href="{{ route('home') }}#how" class="hover:text-navy">How it works</a><a href="{{ route('home') }}#app" class="hover:text-navy">The app</a><a href="{{ route('home') }}#admin" class="hover:text-navy">For HR</a><a href="{{ route('home') }}#security" class="hover:text-navy">Security</a>
      <a href="{{ route('pricing') }}" class="hover:text-navy">Pricing</a><a href="{{ route('docs.api') }}" class="hover:text-navy">API</a><a href="{{ route('contact') }}" class="hover:text-navy">Contact</a>
    </nav>
    <div class="ml-auto flex items-center gap-2">
      <a class="hidden sm:inline whitespace-nowrap text-[15px] font-semibold text-navy px-3 py-2 hover:text-teal" href="{{ route('login') }}">Sign in</a>
      <a class="site-btn !py-2.5 !px-5 hidden sm:inline-flex whitespace-nowrap" href="{{ route('register') }}">Start free trial</a>
      <button class="lg:hidden p-2 text-navy" @click="o=!o" :aria-expanded="o" aria-label="Menu"><x-icon name="menu" /></button>
    </div>
  </div>
  <nav x-show="o" x-cloak x-transition @click="o=false" class="lg:hidden site-wrap pb-4 flex flex-col gap-3 text-[15px]">
    <a href="{{ route('home') }}#how">How it works</a><a href="{{ route('home') }}#app">The app</a><a href="{{ route('home') }}#admin">For HR</a><a href="{{ route('home') }}#security">Security</a><a href="{{ route('home') }}#faq">FAQ</a><a href="{{ route('pricing') }}">Pricing</a><a href="{{ route('docs.api') }}">API</a><a href="{{ route('contact') }}">Contact</a><a href="{{ route('login') }}">Sign in</a>
    <a href="{{ route('register') }}" class="site-btn">Start free trial</a>
  </nav>
</header>
{{-- Site pages show field errors inline; only the success toast comes from the flash component. --}}
<main>@if(session('ok'))@include('components.flash')@endif @yield('content')</main>
<footer class="bg-ink text-slate-400">
  <div class="site-wrap py-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr] text-[15px]">
    <div class="space-y-3">
      <div class="font-extrabold text-white text-lg">QRONEX</div>
      <p class="max-w-xs">Secure QR attendance for offices, branches and field teams. Built in Hyderabad by Ametecs India Private Limited.</p>
    </div>
    <div class="flex flex-col gap-2"><span class="text-white font-semibold">Product</span><a class="hover:text-white" href="{{ route('home') }}#how">How it works</a><a class="hover:text-white" href="{{ route('home') }}#app">Employee app</a><a class="hover:text-white" href="{{ route('home') }}#admin">Admin console</a><a class="hover:text-white" href="{{ route('home') }}#security">Security</a><a class="hover:text-white" href="{{ route('pricing') }}">Pricing</a><a class="hover:text-white" href="{{ route('docs.api') }}">API reference</a><a class="hover:text-white" href="{{ route('home') }}#faq">FAQ</a></div>
    <div class="flex flex-col gap-2"><span class="text-white font-semibold">Company</span><a class="hover:text-white" href="{{ route('contact') }}">Contact sales</a><a class="hover:text-white" href="mailto:{{ config('qronex.supplier.email') }}">{{ config('qronex.supplier.email') }}</a><a class="hover:text-white" href="{{ route('login') }}">Customer sign-in</a><a class="hover:text-white" href="{{ route('register') }}">Start free trial</a></div>
    <div class="flex flex-col gap-2"><span class="text-white font-semibold">Policies</span>@foreach(\App\Http\Controllers\SiteController::LEGAL as $k => [$t])<a class="hover:text-white" href="{{ route('legal', $k) }}">{{ $t }}</a>@endforeach</div>
  </div>
  <div class="site-wrap pb-8 text-sm flex flex-wrap gap-x-6 gap-y-2 border-t border-white/10 pt-6"><span>© {{ now()->year }} Ametecs India Private Limited</span><span>Kondapur, Hyderabad</span></div>
</footer>
@stack('scripts')
<script>
  // Fade sections in once as they scroll into view. Without JS (or with reduced motion) everything is simply visible.
  (() => {
    const els = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) return els.forEach(e => e.classList.add('in'));
    const io = new IntersectionObserver((es) => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { rootMargin: '0px 0px -8% 0px' });
    els.forEach(e => io.observe(e));
  })();
</script>
</body>
</html>
