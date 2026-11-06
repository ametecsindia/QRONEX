@extends('layouts.app', ['title' => 'Admin users'])
@section('content')
@php
  $labels = ['super_admin' => 'Super Admin', 'support_admin' => 'Support Admin'];
  $can = auth()->user()->can('manage-platform');
@endphp
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
  <x-stat label="Platform staff" :value="$users->count()" icon="users" />
  <x-stat label="Super Admins" :value="$users->where('role', 'super_admin')->where('is_active', true)->count()" icon="shield" tone="teal" sub="Full control of the platform" />
  @php($no2fa = $users->where('is_active', true)->filter(fn ($u) => ! $u->hasTwoFactor())->count())
  <x-stat label="Without two-factor" :value="$no2fa" icon="alert" :tone="$no2fa ? 'amber' : 'navy'" sub="Required in production" />
</div>
<div class="card p-0 overflow-x-auto">
  <div class="p-5 pb-2"><div class="card-t">Platform staff</div><p class="text-sm text-slate-500">Super Admins manage everything. Support Admins can view companies and answer tickets, but cannot change settings, plans or prices.</p></div>
  <table class="tbl"><tr><th>User</th><th>Role</th><th>Two-factor</th><th>Last login</th><th>Status</th><th></th></tr>
  @foreach($users as $u)
  <tr x-data="{e:false}">
    <td><div class="flex items-center gap-3"><span class="h-8 w-8 rounded-full bg-navy text-white flex items-center justify-center text-xs font-bold">{{ collect(explode(' ', $u->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('') }}</span>
      <div>{{ $u->name }} @if($u->is(auth()->user()))<span class="chip bg-slate-100 text-slate-600">you</span>@endif<div class="text-xs text-slate-500">{{ $u->email }}</div></div></div></td>
    <td x-show="!e"><span class="chip {{ $u->role === 'super_admin' ? 'bg-navy text-white' : 'bg-navy/5 text-navy' }}">{{ $labels[$u->role] }}</span></td>
    <td x-show="!e">@if($u->hasTwoFactor())<span class="chip bg-teal-soft text-teal">On</span>@else<span class="chip bg-amber-100 text-amber-800">Off</span>@endif</td>
    <td x-show="e" x-cloak colspan="2">
      <form method="post" action="{{ route('admin.users.update', $u) }}" class="flex flex-wrap items-center gap-2">@csrf @method('PUT')
        <select name="role" class="input max-w-[11rem]">@foreach($labels as $r => $l)<option value="{{ $r }}" @selected($u->role === $r)>{{ $l }}</option>@endforeach</select>
        <label class="text-sm inline-flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($u->is_active)> Active</label>
        <button class="btn btn-sm">Save</button>
      </form>
      @if($u->hasTwoFactor() && ! $u->is(auth()->user()))<form method="post" action="{{ route('admin.users.reset2fa', $u) }}" class="mt-2" data-confirm="Reset two-factor sign-in for {{ $u->name }}? Use this only if they lost their phone.">@csrf<button class="btn-ghost btn-sm text-red-700">Reset two-factor (lost phone)</button></form>@endif
    </td>
    <td class="text-xs text-slate-500 whitespace-nowrap">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
    <td>@if($u->is_active)<span class="chip bg-teal-soft text-teal">Active</span>@else<span class="chip bg-red-100 text-red-700">Disabled</span>@endif</td>
    <td class="text-right">@if($can && ! $u->is(auth()->user()))<button class="btn-ghost btn-sm" @click="e=!e" x-text="e?'Close':'Edit'"></button>@endif</td>
  </tr>
  @endforeach
  </table>
</div>
@if($can)
<form method="post" action="{{ route('admin.users.store') }}" class="card grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
  @csrf <div class="col-span-full"><div class="card-t">Add platform admin</div><p class="text-sm text-slate-500">For Ametecs staff only. Customer users are added by each company in their own portal.</p></div>
  <x-field name="name" label="Name" required />
  <x-field name="email" label="Email" type="email" required />
  <x-field name="password" label="Temporary password" type="password" required autocomplete="new-password" hint="10+ chars, upper, lower, number" />
  <div><label class="label">Role</label><select name="role" class="input">@foreach($labels as $r => $l)<option value="{{ $r }}" @selected(old('role', 'support_admin') === $r)>{{ $l }}</option>@endforeach</select></div>
  <div><button class="btn">Add admin</button></div>
</form>
@endif
@endsection
