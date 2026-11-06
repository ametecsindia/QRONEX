@extends('layouts.app', ['title' => 'Forgot password'])
@section('content')
<form method="post" action="{{ route('password.email') }}" class="card space-y-4">
  @csrf
  <h2 class="text-xl font-bold text-navy">Reset your password</h2>
  <x-field name="email" label="Email" type="email" required autofocus />
  <button class="btn w-full justify-center">Send reset link</button>
  <p class="text-sm text-center"><a class="text-teal" href="{{ route('login') }}">Back to sign in</a></p>
</form>
@endsection
