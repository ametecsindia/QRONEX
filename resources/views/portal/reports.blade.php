@extends('layouts.app', ['title' => 'Reports', 'crumb' => 'Overview'])
@section('content')
@php($icons = ['register' => ['list', 'bg-teal-soft text-teal'], 'monthly' => ['chart', 'bg-sky-100 text-sky-700'], 'remote' => ['globe', 'bg-amber-100 text-amber-700'], 'adjustments' => ['sliders', 'bg-navy/5 text-navy']])
<div class="grid md:grid-cols-2 gap-4">
@foreach($reports as $key => [$title, $desc])
  <form method="get" action="{{ route('portal.reports.download', $key) }}" class="card flex flex-col gap-4">
    <div class="flex items-start gap-3"><span class="rounded-xl p-2.5 {{ $icons[$key][1] ?? 'bg-navy/5 text-navy' }}"><x-icon :name="$icons[$key][0] ?? 'list'" /></span>
      <div><div class="card-t">{{ $title }}</div><p class="text-sm text-slate-600">{{ $desc }}</p></div></div>
    <div class="grid sm:grid-cols-2 gap-3">
      @if($key === 'monthly')<div><label class="label">Month</label><input type="month" name="month" value="{{ now('Asia/Kolkata')->format('Y-m') }}" class="input"></div>
      @else<div><label class="label">{{ $key === 'register' ? 'Date' : 'Up to (last 30 days)' }}</label><input type="date" name="date" value="{{ today('Asia/Kolkata')->toDateString() }}" class="input"></div>@endif
      <div><label class="label">Office</label><select name="office" class="input"><option value="">All offices</option>@foreach($offices as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
    </div>
    <div class="mt-auto flex gap-2 border-t pt-4"><button class="btn btn-sm" name="format" value="xlsx">Download Excel</button><button class="btn-ghost btn-sm" name="format" value="csv">CSV</button></div>
  </form>
@endforeach
</div>
<div class="rounded-xl bg-slate-100 text-slate-600 px-4 py-3 text-xs flex gap-2"><x-icon name="help" class="w-4 h-4 shrink-0" />
  <span>Reports contain verified attendance and clearly marked adjustments. Salary, leave and overtime are calculated outside QRONEX. @can('manage-company')The daily register can be emailed to HR every morning — <a class="font-semibold text-teal" href="{{ route('portal.policy.edit') }}">set it in Attendance policy</a>.@endcan</span></div>
@endsection
