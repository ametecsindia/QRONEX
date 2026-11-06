@extends('layouts.app', ['title' => 'Companies', 'crumb' => 'Overview'])
@can('manage-platform')@section('actions')<a class="btn btn-sm" href="{{ route('admin.companies.create') }}"><x-icon name="plus" class="w-4 h-4" /> Add company</a>@endsection @endcan
@section('content')
<div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
  <x-stat label="All companies" :value="$byStatus->sum()" icon="building" :href="route('admin.companies.index')" />
  <x-stat label="Active" :value="$byStatus['active'] ?? 0" icon="check" tone="teal" :href="route('admin.companies.index', ['status' => 'active'])" />
  <x-stat label="On trial" :value="$byStatus['trial'] ?? 0" icon="clock" tone="sky" :sub="$trialEnding.' ending within 7 days'" :href="route('admin.companies.index', ['status' => 'trial'])" />
  <x-stat label="Grace (unpaid)" :value="$byStatus['grace'] ?? 0" icon="alert" :tone="($byStatus['grace'] ?? 0) ? 'amber' : 'navy'" :href="route('admin.companies.index', ['status' => 'grace'])" />
  <x-stat label="Suspended" :value="($byStatus['suspended'] ?? 0) + ($byStatus['cancelled'] ?? 0)" icon="x" :tone="($byStatus['suspended'] ?? 0) ? 'red' : 'navy'" sub="incl. cancelled" :href="route('admin.companies.index', ['status' => 'suspended'])" />
</div>
<form class="flex flex-wrap gap-2"><input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">
  <input name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Search name or slug">
  <select name="status" class="input max-w-[10rem]"><option value="">All statuses</option>
    @foreach(['trial', 'active', 'grace', 'suspended', 'cancelled'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
  <button class="btn-ghost">Filter</button>
</form>
<div class="card p-0 overflow-x-auto">
  <table class="tbl"><tr><x-sort col="name">Company</x-sort><th>Type</th><th>Plan</th><th class="text-right">Employees</th><th class="text-right">Offices</th><x-sort col="status">Status</x-sort><x-sort col="created_at">Since</x-sort></tr>
  @forelse($tenants as $t)<tr>
    <td><a class="text-teal font-semibold" href="{{ route('admin.companies.show', $t) }}">{{ $t->name }}</a><div class="text-xs text-slate-500">{{ $t->city }} · {{ $t->slug }}</div></td>
    <td class="text-xs">{{ $t->customer_type }}</td>
    <td>{{ $t->subscription?->planVersion?->plan?->name ?? '—' }} <span class="text-xs text-slate-500">v{{ $t->subscription?->planVersion?->version }}</span></td>
    <td class="text-right">{{ $t->employees_count }}</td><td class="text-right">{{ $t->offices_count }}</td>
    <td><x-status :s="$t->status" /></td><td class="text-xs">{{ $t->created_at->format('d M Y') }}</td></tr>
  @empty<tr><td colspan="7" class="text-center text-slate-500 py-6">No companies.</td></tr>@endforelse
  </table>
  <div class="mt-3">{{ $tenants->links() }}</div>
</div>
@endsection
