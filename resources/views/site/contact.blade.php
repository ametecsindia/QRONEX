@extends('layouts.site', ['title' => 'Contact'])
@section('content')
<section class="max-w-3xl mx-auto px-4 py-12">
  <h1 class="text-3xl font-bold text-navy">Talk to us</h1>
  <p class="text-slate-600 mt-1">Pricing for many sites, a demo, or integration questions — we reply within one working day.</p>
  <form method="post" action="{{ route('contact') }}" class="card grid sm:grid-cols-2 gap-4 mt-6">
    @csrf
    <x-field name="name" label="Your name" required />
    <x-field name="email" label="Work email" type="email" required />
    <x-field name="phone" label="Phone (optional)" />
    <x-field name="company" label="Company" />
    <x-field name="employees" label="Number of employees" type="number" min="1" />
    <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="sm:col-span-2"><label class="label" for="m">Message</label><textarea id="m" name="message" rows="5" class="input" required>{{ old('message') }}</textarea></div>
    <div><button class="btn">Send</button></div>
  </form>
</section>
@endsection
