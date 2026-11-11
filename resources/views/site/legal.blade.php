@extends('layouts.site', ['title' => $title])
@section('content')
<section class="max-w-3xl mx-auto px-4 py-12 space-y-4 text-slate-700 leading-relaxed [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-navy [&_h2]:pt-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:space-y-1 [&_a]:underline">
  <h1 class="text-3xl font-bold text-navy">{{ $title }}</h1>
  <p class="text-sm text-slate-500">Last updated: 11 November 2026 · Ametecs India Private Limited</p>
  @include('site.legal.'.$page)
</section>
@endsection
