@extends('layouts.app', ['title' => 'Two-factor sign-in'])
@section('content')
<form method="post" action="{{ route('two-factor.challenge') }}" class="card space-y-4">
  @csrf
  <h2 class="text-xl font-bold text-navy">Enter your sign-in code</h2>
  <p class="text-sm text-slate-600">Open your authenticator app and enter the 6-digit code for QRONEX. Lost your phone? Enter one of your recovery codes.</p>
  <x-field name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" autofocus required />
  <button class="btn w-full justify-center">Verify</button>
  <p class="text-sm text-center"><a class="text-teal" href="{{ route('login') }}">Start over</a></p>
</form>
@endsection
