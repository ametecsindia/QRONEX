@extends('layouts.app', ['title' => 'Choose a new password'])
@section('content')
<form method="post" action="{{ route('password.update') }}" class="card space-y-4">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <h2 class="text-xl font-bold text-navy">Choose a new password</h2>
  <x-field name="email" label="Email" type="email" :value="$email" required />
  <x-field name="password" label="New password" type="password" required autocomplete="new-password" hint="10+ characters, upper and lower case, a number." />
  <x-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
  <button class="btn w-full justify-center">Save password</button>
</form>
@endsection
