@extends('layouts.app', ['title' => 'Import employees', 'crumb' => '<a class="hover:text-teal" href="'.route('portal.employees.index').'">Employees</a> · CSV'])
@section('content')
<div class="grid lg:grid-cols-3 gap-4 items-start max-w-6xl">
  <form method="post" action="{{ route('portal.employees.import') }}" enctype="multipart/form-data" class="card lg:col-span-2 space-y-4" x-data="{f:''}">
    @csrf
    <div class="card-t">Upload CSV</div>
    <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 hover:border-teal bg-slate-50 px-6 py-10 text-center cursor-pointer">
      <span class="rounded-full bg-teal-soft text-teal p-3"><x-icon name="list" /></span>
      <span class="font-semibold text-navy" x-text="f || 'Choose a .csv file'">Choose a .csv file</span>
      <span class="text-xs text-slate-500">Saved from Excel as “CSV UTF-8”</span>
      <input type="file" name="file" accept=".csv,text/csv" required class="sr-only" @change="f = $event.target.files[0]?.name || ''">
    </label>
    <div class="flex flex-wrap gap-2"><button class="btn">Validate &amp; import</button><a class="btn-ghost" href="{{ route('portal.employees.template') }}">Download template</a></div>
    <p class="text-xs text-slate-500">All rows are checked first. If any row has an error, nothing is imported and the errors are listed below.</p>
  </form>
  <div class="card space-y-3 text-sm">
    <div class="card-t">Columns</div>
    <ol class="space-y-1.5">@foreach(['employee_code' => true, 'name' => true, 'email' => false, 'mobile' => false, 'department' => false, 'designation' => false, 'office_code' => true, 'hrms_ref' => false] as $c => $req)
      <li class="flex items-center justify-between"><code class="text-xs">{{ $c }}</code>@if($req)<span class="chip bg-navy text-white">required</span>@else<span class="text-xs text-slate-400">optional</span>@endif</li>@endforeach</ol>
    <div class="border-t pt-3"><div class="text-xs text-slate-500 mb-1">Your office codes</div>
      <div class="flex flex-wrap gap-1">@forelse($offices as $o)<span class="chip bg-teal-soft text-teal font-mono">{{ $o->code }}</span>@empty<span class="text-amber-700 text-xs">Add an office first.</span>@endforelse</div></div>
  </div>
</div>
@if(session('importErrors'))
<div class="card overflow-x-auto max-w-6xl border-red-200"><div class="card-t text-red-700 mb-2">{{ count(session('importErrors')) }} row(s) need fixing — nothing was imported</div>
  <table class="tbl"><tr><th>Line</th><th>Problems</th></tr>
  @foreach(session('importErrors') as $line => $msgs)<tr><td class="font-mono">{{ $line }}</td><td class="text-red-700">{{ implode(' ', $msgs) }}</td></tr>@endforeach</table></div>
@endif
@endsection
