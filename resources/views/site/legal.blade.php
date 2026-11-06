@extends('layouts.site', ['title' => $title])
@section('content')
<section class="max-w-3xl mx-auto px-4 py-12 space-y-4">
  <h1 class="text-3xl font-bold text-navy">{{ $title }}</h1>
  <div class="rounded-md bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm"><b>Draft placeholder.</b> This policy has not been supplied or reviewed. Ametecs India Private Limited will publish the approved text after legal review.</div>
  <p class="text-slate-600">The final document is expected to cover {{ $covers }}</p>
</section>
@endsection
