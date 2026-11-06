@extends('layouts.app', ['title' => 'Users & roles'])
@section('actions')<button class="btn btn-sm" onclick="document.getElementById('add-user').scrollIntoView({behavior:'smooth'})"><x-icon name="users" class="w-4 h-4" /> Add user</button>@endsection
@section('content')
@php($labels = \App\Models\User::ROLES)
<div class="card p-0 overflow-x-auto">
  <div class="p-5 pb-2 card-t">Portal users <span class="text-sm font-normal text-slate-500">· {{ $users->count() }}</span></div>
  <table class="tbl"><tr><th>User</th><th>Role · office scope · status</th><th>Last login</th><th></th></tr>
  @foreach($users as $u)
  <tr x-data="{e:false}">
    <td><div class="flex items-center gap-3"><span class="h-8 w-8 rounded-full bg-navy/10 text-navy flex items-center justify-center text-xs font-bold">{{ collect(explode(' ', $u->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('') }}</span>
      <div>{{ $u->name }}<div class="text-xs text-slate-500">{{ $u->email }}</div></div></div></td>
    <td x-show="!e"><span class="chip bg-navy/5 text-navy">{{ $u->roleLabel() }}</span> <span class="text-xs text-slate-500">{{ $u->offices->pluck('name')->join(', ') ?: 'All offices' }}</span> @unless($u->is_active)<span class="chip bg-red-100 text-red-700">Disabled</span>@endunless</td>
    <td x-show="e" x-cloak>
      <form method="post" action="{{ route('portal.users.update', $u) }}" class="flex flex-wrap gap-2 items-center">
        @csrf @method('PUT')
        <select name="role" class="input max-w-[13rem]">@foreach($roles as $r)<option value="{{ $r }}" @selected($u->role === $r)>{{ $labels[$r] }}</option>@endforeach</select>
        <select name="offices[]" multiple class="input max-w-[12rem]" size="3" aria-label="Office scope">@foreach($offices as $o)<option value="{{ $o->id }}" @selected($u->offices->contains($o))>{{ $o->name }}</option>@endforeach</select>
        <label class="text-sm inline-flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($u->is_active)> Active</label>
        <button class="btn btn-sm">Save</button>
      </form>
    </td>
    <td class="text-xs text-slate-500 whitespace-nowrap">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
    <td class="text-right">@unless($u->is(auth()->user()) || ($u->role === 'company_owner' && !auth()->user()->hasRole('company_owner')))<button class="btn-ghost btn-sm" @click="e=!e" x-text="e?'Close':'Edit'"></button>@endunless</td>
  </tr>
  @endforeach
  </table>
  <p class="text-xs text-slate-500 px-5 py-3">Office scope applies to Office Managers and Attendance Approvers; other roles see every office.</p>
</div>

{{-- Role permission matrix --}}
@php($owner = auth()->user()->hasRole('company_owner'))
<form method="post" action="{{ route('portal.users.permissions') }}" class="card p-0 overflow-x-auto">
  @csrf @method('PUT')
  <div class="p-5 pb-3 flex flex-wrap items-start gap-3">
    <div><div class="card-t">Role permissions</div><p class="text-sm text-slate-500">Choose what each role can open in this portal. The Company Owner always has everything.</p></div>
    @if($owner)<button class="btn btn-sm ml-auto">Save permissions</button>@else<span class="ml-auto chip bg-slate-100 text-slate-600">Only the Company Owner can change these</span>@endif
  </div>
  <table class="tbl">
    <tr><th>Area</th><th class="text-center">Company Owner</th>@foreach(\App\Support\Permissions::EDITABLE_ROLES as $r)<th class="text-center">{{ $labels[$r] }}</th>@endforeach</tr>
    @foreach(\App\Support\Permissions::COMPANY as $ability => [$label, $help])
      <tr><td><div class="font-medium text-navy">{{ $label }}</div><div class="text-xs text-slate-500">{{ $help }}</div></td>
        <td class="text-center"><input type="checkbox" checked disabled aria-label="Owner always" class="accent-teal h-4 w-4"></td>
        @foreach(\App\Support\Permissions::EDITABLE_ROLES as $r)
          <td class="text-center"><input type="checkbox" name="perm[{{ $r }}][{{ $ability }}]" value="1" class="accent-teal h-4 w-4" aria-label="{{ $labels[$r] }}: {{ $label }}" @checked(in_array($ability, $matrix[$r] ?? [], true)) @disabled(! $owner)></td>
        @endforeach
      </tr>
    @endforeach
  </table>
  <p class="text-xs text-slate-500 px-5 py-3">Everyone can see the dashboard, attendance ledger, reports and help. Office Managers and Approvers only ever see their own offices.</p>
</form>

<form id="add-user" method="post" action="{{ route('portal.users.store') }}" class="card grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
  @csrf <div class="col-span-full"><div class="card-t">Add user</div><p class="text-sm text-slate-500">Share the temporary password securely; they can change it under Account &amp; security.</p></div>
  <x-field name="name" label="Name" required />
  <x-field name="email" label="Email" type="email" required />
  <x-field name="password" label="Temporary password" type="password" required autocomplete="new-password" hint="At least 8 characters (numbers only is fine)" />
  <div><label class="label">Role</label><select name="role" class="input">@foreach($roles as $r)<option value="{{ $r }}" @selected(old('role') === $r)>{{ $labels[$r] }}</option>@endforeach</select></div>
  <div><label class="label">Offices (scoped roles)</label><select name="offices[]" multiple size="3" class="input">@foreach($offices as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
  <div><button class="btn">Create user</button></div>
</form>
@endsection
