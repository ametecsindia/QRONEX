@extends('layouts.app', ['title' => 'Sign in'])
@section('content')
<form method="post" action="{{ route('login') }}" class="card space-y-4">
  @csrf
  <h2 class="text-xl font-bold text-navy">Sign in</h2>
  <x-field name="email" label="Email" type="email" required autofocus autocomplete="username" />
  <x-field name="password" label="Password" type="password" required autocomplete="current-password" />
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Keep me signed in</label>
  <button class="btn w-full justify-center">Sign in</button>
  <p class="text-sm text-center"><a class="text-teal" href="{{ route('password.request') }}">Forgot password?</a></p>
  <p class="text-sm text-center">New company? <a class="text-teal font-semibold" href="{{ route('register') }}">Start a free trial</a></p>
</form>
@endsection
