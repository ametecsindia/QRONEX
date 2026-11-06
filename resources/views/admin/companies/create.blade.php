@extends('layouts.app', ['title' => 'Add company', 'crumb' => '<a class="hover:text-teal" href="'.route('admin.companies.index').'">Companies</a> · new'])
@section('content')
<form method="post" action="{{ route('admin.companies.store') }}" class="grid lg:grid-cols-3 gap-4 items-start" x-data="{name:@js(old('company', '')), slug:@js(old('slug', '')), touched:{{ old('slug') ? 'true' : 'false' }}}">
  @csrf
  <div class="lg:col-span-2 space-y-4">
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="card-t col-span-full">Company</div>
      <div class="sm:col-span-2"><label class="label" for="f-company">Company name</label><input id="f-company" name="company" class="input" required x-model="name" @input="if(!touched) slug = name.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,40)"></div>
      <div><label class="label" for="f-slug">Web address</label><div class="flex items-center rounded-lg border border-slate-300 focus-within:ring-2 focus-within:ring-teal/40 bg-white"><span class="pl-3 text-sm text-slate-500">qronex.app/</span><input id="f-slug" name="slug" class="input flex-1 min-w-0 border-0 shadow-none focus:ring-0 pl-0.5" required x-model="slug" @input="touched=true"></div></div>
      <div><label class="label">Customer type</label><select name="customer_type" class="input">@foreach(['independent' => 'Independent', 'smartprs' => 'SmartPRS customer', 'smartept' => 'SmartEPT customer', 'smartdcm' => 'SmartDCM customer'] as $k => $l)<option value="{{ $k }}" @selected(old('customer_type') === $k)>{{ $l }}</option>@endforeach</select></div>
      <x-field name="industry" label="Industry" placeholder="e.g. Manufacturing" />
      <x-field name="gstin" label="GSTIN (optional)" placeholder="36ABCDE1234F1Z5" />
      <x-field name="city" label="City" />
      <x-field name="state" label="State" />
    </div>
    <div class="card grid sm:grid-cols-2 gap-4">
      <div class="card-t col-span-full">Owner (company admin)</div>
      <x-field name="name" label="Full name" required />
      <x-field name="email" label="Email (sign-in)" type="email" required />
      <x-field name="password" label="First password" type="password" required hint="At least 8 characters. Share it with the owner; they can change it after signing in." />
    </div>
  </div>
  <div class="space-y-4">
    <div class="card space-y-4">
      <div class="card-t">Plan</div>
      <x-field name="employees" label="Employees" type="number" min="1" :value="old('employees', 25)" required />
      <div><label class="label">Billing cycle</label><select name="billing_cycle" class="input">@foreach(['monthly', 'quarterly', 'annual'] as $c)<option @selected(old('billing_cycle') === $c)>{{ $c }}</option>@endforeach</select></div>
      <ul class="text-xs text-slate-600 space-y-1">@foreach($plans as $p)<li class="flex justify-between"><span class="font-medium text-navy">{{ $p->name }}</span><span>{{ $p->min_employees }}–{{ $p->max_employees >= 100000 ? '∞' : $p->max_employees }} employees</span></li>@endforeach</ul>
      <p class="text-xs text-slate-500">The plan is picked from the team size. The company starts on a free trial; change terms on the next page.</p>
    </div>
    <button class="btn w-full"><x-icon name="plus" class="w-4 h-4" /> Create company</button>
  </div>
</form>
@endsection
