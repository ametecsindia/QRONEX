@extends('layouts.site', ['title' => 'Contact'])
@section('content')
@php($topics = \App\Http\Controllers\SiteController::TOPICS)
@php($topic = old('topic', array_key_exists(request('topic'), $topics) ? request('topic') : 'demo'))
<section class="relative overflow-hidden bg-paper">
  <div aria-hidden="true" class="absolute -right-40 -top-40 h-[520px] w-[520px] rounded-full bg-teal-2/10 blur-[110px]"></div>
  <div class="site-wrap relative grid lg:grid-cols-[1fr_1.15fr] gap-x-16 gap-y-10 py-14 sm:py-20">

    {{-- left (top on phones): heading --}}
    <div>
      <p class="text-teal font-semibold">Contact</p>
      <h1 class="mt-2 text-4xl sm:text-5xl font-extrabold tracking-tightest text-navy leading-[1.02]">Talk to the team that builds QRONEX.</h1>
      <p class="site-lede mt-5">Pricing for many sites, a demo with your own offices, or an integration question. A person replies within one working day.</p>

    </div>

    {{-- right: the form --}}
    <form method="post" action="{{ route('contact') }}" class="lg:col-start-2 lg:row-start-1 lg:row-span-2 self-start rounded-3xl bg-white p-6 sm:p-8 ring-1 ring-slate-200 shadow-[0_40px_80px_-40px_rgba(14,39,71,.35)]" x-data="{ topic: @js($topic) }" novalidate>
      @csrf
      <fieldset>
        <legend class="label">What do you need?</legend>
        <div class="mt-1 flex flex-wrap gap-2">
          @foreach($topics as $k => $label)
            <label class="cursor-pointer">
              <input type="radio" name="topic" value="{{ $k }}" x-model="topic" class="peer sr-only" @checked($k === $topic)>
              <span class="inline-block rounded-full bg-white px-4 py-2 text-sm font-semibold text-navy ring-1 ring-slate-300 transition hover:ring-navy peer-checked:bg-navy peer-checked:text-white peer-checked:ring-navy peer-focus-visible:ring-2 peer-focus-visible:ring-teal">{{ $label }}</span>
            </label>
          @endforeach
        </div>
      </fieldset>

      <div class="mt-6 grid sm:grid-cols-2 gap-4">
        <x-field name="name" label="Your name" required autocomplete="name" maxlength="120" />
        <x-field name="email" label="Work email" type="email" required autocomplete="email" maxlength="190" />
        <x-field name="phone" label="Phone (optional)" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" placeholder="+91 98xxx xxxxx" />
        <x-field name="company" label="Company" autocomplete="organization" maxlength="150" />
        <x-field name="employees" label="Number of employees" type="number" inputmode="numeric" min="1" max="100000" placeholder="e.g. 40" hint="Roughly how many people will punch in." />
        <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="sm:col-span-2">
          <label class="label" for="f-message">Message</label>
          <textarea id="f-message" name="message" rows="5" class="input" required maxlength="3000" @error('message') aria-invalid="true" @enderror
            :placeholder="{ demo: 'How many offices and gates, and when would you like a walkthrough?', pricing: 'Number of offices and employees, billing cycle you prefer…', integration: 'Which HRMS or payroll system should receive attendance?', other: 'How can we help?' }[topic]">{{ old('message') }}</textarea>
          @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
      </div>
      <button class="site-btn mt-6 w-full">Send message</button>
      <p class="mt-3 text-center text-xs text-slate-500">We use these details only to reply to you. See our <a class="underline" href="{{ route('legal', 'privacy') }}">Privacy Policy</a>.</p>
    </form>

    {{-- left (below the form on phones): what happens next + other ways to reach us --}}
    <div class="lg:row-start-2">
      <ol class="space-y-5">
        @foreach([['You send this form', 'It reaches our sales and support team directly, with a reference number.'], ['We reply within one working day', 'By email, or a call if you leave a phone number.'], ['We set you up', 'A walkthrough for your offices, or a free trial you can start yourself.']] as $i => [$t, $d])
          <li class="flex gap-4"><span class="num grid h-8 w-8 shrink-0 place-items-center rounded-full bg-navy text-sm font-bold text-white">{{ $i + 1 }}</span><div><div class="font-bold text-navy">{{ $t }}</div><div class="text-slate-600">{{ $d }}</div></div></li>
        @endforeach
      </ol>

      <div class="mt-10 grid sm:grid-cols-2 gap-3">
        <a href="mailto:{{ config('qronex.supplier.email') }}" class="rounded-2xl bg-white p-4 ring-1 ring-slate-200 transition hover:ring-teal/40">
          <div class="flex items-center gap-2 font-semibold text-navy"><x-icon name="mail" class="w-4 h-4 text-teal" />Email</div>
          <div class="mt-1 text-sm text-slate-600">{{ config('qronex.supplier.email') }}</div></a>
        <a href="{{ route('login') }}" class="rounded-2xl bg-white p-4 ring-1 ring-slate-200 transition hover:ring-teal/40">
          <div class="flex items-center gap-2 font-semibold text-navy"><x-icon name="help" class="w-4 h-4 text-teal" />Already a customer?</div>
          <div class="mt-1 text-sm text-slate-600">Sign in → Help &amp; support for tickets</div></a>
        <a href="{{ route('register') }}" class="rounded-2xl bg-white p-4 ring-1 ring-slate-200 transition hover:ring-teal/40">
          <div class="flex items-center gap-2 font-semibold text-navy"><x-icon name="qr" class="w-4 h-4 text-teal" />Try it yourself</div>
          <div class="mt-1 text-sm text-slate-600">Start a free trial, no card needed</div></a>
        <div class="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
          <div class="flex items-center gap-2 font-semibold text-navy"><x-icon name="building" class="w-4 h-4 text-teal" />Office</div>
          <div class="mt-1 text-sm text-slate-600">Ametecs India Private Limited, Kondapur, Hyderabad</div></div>
      </div>
    </div>

  </div>
</section>
@endsection
