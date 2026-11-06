@extends('layouts.app', ['title' => 'Audit log'])
@section('actions')<a class="btn-ghost btn-sm" href="{{ request()->fullUrlWithQuery(['format' => 'csv', 'page' => null]) }}">Export CSV</a>@endsection
@section('content')
<form class="card grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
  <div class="lg:col-span-2"><label class="label" for="q">Search</label><input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Subject, user name or email"></div>
  <div><label class="label" for="c">Company</label><select id="c" name="company" class="input"><option value="">All</option><option value="platform" @selected(request('company') === 'platform')>Platform only</option>@foreach($companies as $id => $n)<option value="{{ $id }}" @selected(request('company') == $id)>{{ $n }}</option>@endforeach</select></div>
  <div><label class="label" for="a">Area</label><select id="a" name="action" class="input"><option value="">All</option>@foreach($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></div>
  <div><label class="label" for="f">From</label><input id="f" type="date" name="from" value="{{ request('from') }}" class="input"></div>
  <div><label class="label" for="t">To</label><input id="t" type="date" name="to" value="{{ request('to') }}" class="input"></div>
  <div class="col-span-full flex gap-2"><button class="btn btn-sm">Apply</button>@if(request()->hasAny(['q', 'company', 'action', 'from', 'to']))<a class="btn-ghost btn-sm" href="{{ route('admin.audit') }}">Clear</a>@endif<span class="ml-auto text-xs text-slate-500 self-center">{{ number_format($rows->total()) }} entries · append-only, cannot be edited or deleted</span></div>
</form>
<div class="card p-0 overflow-x-auto">
  <table class="tbl"><tr><th>When (IST)</th><th>Who</th><th>Company</th><th>Action</th><th>Subject</th><th>IP</th></tr>
  @forelse($rows as $x)<tr x-data="{o:false}">
    <td class="text-xs whitespace-nowrap text-slate-500">{{ $x->created_at->timezone('Asia/Kolkata')->format('d M Y, H:i:s') }}</td>
    <td>{{ $x->user?->name ?? 'System' }}<div class="text-xs text-slate-500">{{ $x->user?->email }}</div></td>
    <td class="text-xs">{{ $x->tenant?->name ?? 'Platform' }}</td>
    <td><code class="text-xs bg-slate-100 rounded px-1.5 py-0.5">{{ $x->action }}</code>@if($x->meta)<button class="text-xs text-teal ml-1" @click="o=!o" x-text="o?'hide':'details'"></button><pre x-show="o" x-cloak class="mt-1 text-[11px] bg-slate-50 rounded p-2 max-w-md whitespace-pre-wrap break-all">{{ json_encode($x->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>@endif</td>
    <td class="text-sm text-slate-600 break-all">{{ $x->subject }}</td>
    <td class="text-xs font-mono text-slate-500">{{ $x->ip }}</td></tr>
  @empty<tr><td colspan="6"><x-empty icon="list" title="No entries match" text="Try a wider date range or clear the filters." /></td></tr>@endforelse
  </table>
</div>
<div>{{ $rows->links() }}</div>
@endsection
