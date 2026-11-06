@extends('layouts.app', ['title' => 'Start your trial', 'wide' => true])
@section('content')
<form method="post" action="{{ route('register') }}" class="card space-y-5" x-data="{slug:@js(old('slug', '')), touched:false}">
  @csrf
  <div>
    <h2 class="text-xl font-bold text-navy">Start your QRONEX trial</h2>
    <p class="text-sm text-slate-500">No payment now. Choose a plan before the trial ends.</p>
  </div>
  <fieldset class="grid sm:grid-cols-2 gap-4">
    <legend class="font-semibold text-navy mb-2 col-span-full">Company</legend>
    <x-field class="sm:col-span-2" name="company" label="Company name" required
      x-on:input="if(!touched) slug=$event.target.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,40)" />
    <div class="sm:col-span-2">
      <label class="label" for="f-slug">Workspace address</label>
      <div class="flex items-center gap-1 text-sm"><span class="text-slate-500">qronex.app/</span>
        <input id="f-slug" name="slug" class="input" x-model="slug" @input="touched=true" required pattern="[a-z0-9]+(-[a-z0-9]+)*" minlength="3" maxlength="40"></div>
    </div>
    <x-field name="industry" label="Industry" />
    <x-field name="gstin" label="GSTIN (optional)" maxlength="15" />
    <x-field name="state" label="State" />
    <x-field name="city" label="City" />
    <x-field name="employees" label="Number of employees" type="number" min="1" value="25" required />
    <div>
      <label class="label" for="f-cycle">Billing cycle</label>
      <select id="f-cycle" name="billing_cycle" class="input">
        @foreach(['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'] as $k => $v)<option value="{{ $k }}" @selected(old('billing_cycle') === $k)>{{ $v }}</option>@endforeach
      </select>
    </div>
  </fieldset>
  <fieldset class="grid sm:grid-cols-2 gap-4">
    <legend class="font-semibold text-navy mb-2 col-span-full">Your account (company owner)</legend>
    <x-field name="name" label="Full name" required />
    <x-field name="email" label="Work email" type="email" required autocomplete="username" />
    <x-field name="password" label="Password" type="password" required autocomplete="new-password" hint="At least 8 characters (numbers only is fine)." />
    <x-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
  </fieldset>
  <p class="text-xs text-slate-500">Plans: @foreach($plans as $p){{ $p->name }} ({{ $p->min_employees }}–{{ $p->max_employees >= 100000 ? '∞' : $p->max_employees }})@if(!$loop->last), @endif @endforeach. Your plan is picked from team size.</p>
  <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" required class="mt-1"> I accept the Terms of Service and Privacy Policy.</label>
  <button class="btn w-full justify-center">Create workspace</button>
  <p class="text-sm text-center">Already registered? <a class="text-teal font-semibold" href="{{ route('login') }}">Sign in</a></p>
</form>
@endsection
