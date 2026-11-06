@extends('layouts.app', ['title' => 'Employees', 'crumb' => 'Organisation'])
@section('actions')<a class="btn-ghost btn-sm" href="{{ route('portal.employees.import') }}"><x-icon name="list" class="w-4 h-4" /> Import CSV</a><a class="btn btn-sm" href="{{ route('portal.employees.create') }}"><x-icon name="plus" class="w-4 h-4" /> Add employee</a>@endsection
@section('content')
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <x-stat label="Active employees" :value="$counts['active']" icon="users" tone="teal" sub="Can punch with the app" :href="route('portal.employees.index', ['status' => 'active'])" />
  <x-stat label="App not set up" :value="$counts['no_phone']" icon="phone" :tone="$counts['no_phone'] ? 'amber' : 'navy'" sub="Active, no approved phone yet" />
  <x-stat label="Phones waiting" :value="$counts['pending']" icon="check" :tone="$counts['pending'] ? 'red' : 'navy'" sub="Need your approval" :href="auth()->user()->can('approve-devices') ? route('portal.approvals') : null" />
  <x-stat label="Inactive" :value="$counts['inactive']" icon="x" sub="Left or on hold" :href="route('portal.employees.index', ['status' => 'inactive'])" />
</div>

<form class="card flex flex-wrap items-end gap-3"><input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">
  <div class="flex-1 min-w-[200px]"><label class="label">Search</label><input name="q" value="{{ request('q') }}" class="input" placeholder="Name, code or email"></div>
  <div><label class="label">Office</label><select name="office" class="input" onchange="this.form.submit()"><option value="">All offices</option>@foreach($offices as $o)<option value="{{ $o->id }}" @selected(request('office') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
  <div><label class="label">Status</label><select name="status" class="input" onchange="this.form.submit()"><option value="">Any status</option>@foreach(['active', 'inactive'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select></div>
  <button class="btn-ghost">Filter</button>
  @if(request()->hasAny(['q', 'office', 'status']))<a class="btn-ghost btn-sm" href="{{ route('portal.employees.index') }}">Clear</a>@endif
</form>

<div class="card p-0 overflow-x-auto">
  <table class="tbl"><tr><x-sort col="name">Employee</x-sort><x-sort col="employee_code">Code</x-sort><th>Office</th><x-sort col="department">Department</x-sort><th>App</th><x-sort col="status">Status</x-sort><th></th></tr>
  @forelse($employees as $e)
    @php($ini = collect(explode(' ', $e->name))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join(''))
    <tr class="hover:bg-slate-50">
    <td><a href="{{ route('portal.employees.show', $e) }}" class="flex items-center gap-3 group"><span class="h-9 w-9 shrink-0 rounded-full bg-navy/5 text-navy text-xs font-bold flex items-center justify-center group-hover:bg-teal-soft group-hover:text-teal">{{ $ini }}</span>
      <span class="min-w-0"><span class="block font-medium text-navy group-hover:text-teal">{{ $e->name }}</span><span class="block text-xs text-slate-500 truncate">{{ $e->email ?: $e->mobile }}</span></span></a></td>
    <td class="font-mono text-xs">{{ $e->employee_code }}</td>
    <td class="text-sm">{{ $e->office?->name }}</td><td>{{ $e->department }}<div class="text-xs text-slate-500">{{ $e->designation }}</div></td>
    <td>@if($e->approved_devices_count)<span class="chip bg-emerald-100 text-emerald-800">{{ $e->approved_devices_count }} phone{{ $e->approved_devices_count > 1 ? 's' : '' }}</span>@else<span class="chip bg-amber-100 text-amber-800">Not set up</span>@endif</td>
    <td><x-status :s="$e->status" /></td>
    <td class="text-right whitespace-nowrap">@if($e->status === 'active')<form method="post" action="{{ route('portal.employees.activation', $e) }}" class="inline">@csrf<button class="btn-ghost btn-sm" title="Send app activation code by SMS and email">Activation code</button></form>@endif
      <a class="btn-ghost btn-sm" href="{{ route('portal.employees.edit', $e) }}">Edit</a></td></tr>
  @empty<tr><td colspan="7"><x-empty icon="users" title="{{ request()->hasAny(['q', 'office', 'status']) ? 'No employees match these filters' : 'No employees yet' }}" text="Add employees one by one or import a CSV." /></td></tr>@endforelse
  </table>
  <div class="p-3">{{ $employees->links() }}</div>
</div>
@endsection
