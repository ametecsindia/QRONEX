@extends('layouts.app', ['title' => 'Website content'])
@section('actions')<a class="btn-ghost" href="{{ route('home') }}" target="_blank">View website</a>@endsection
@section('content')
<form method="post" action="{{ route('admin.website.update') }}" class="card space-y-4 max-w-4xl">
  @csrf
  <x-field name="hero_title" label="Headline" :value="$cms['hero_title']" required />
  <div><label class="label" for="hs">Sub-headline</label><textarea id="hs" name="hero_sub" rows="2" class="input" required>{{ old('hero_sub', $cms['hero_sub']) }}</textarea></div>
  <x-field name="announcement" label="Announcement banner (empty = hidden)" :value="$cms['announcement']" placeholder="e.g. Scheduled maintenance tonight 23:00–23:30 IST" />
  <div><label class="label" for="fq">FAQ — question line, answer line, blank line between entries</label>
    <textarea id="fq" name="faqs" rows="18" class="input font-mono text-xs" required>{{ old('faqs', $faqText) }}</textarea></div>
  <div><button class="btn">Save</button></div>
</form>
@endsection
