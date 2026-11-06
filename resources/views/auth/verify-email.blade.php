@extends('layouts.app', ['title' => 'Verify your email'])
@section('content')
<div class="card max-w-md space-y-3">
  <p class="text-sm">We sent a 6-digit code to <b>{{ auth()->user()->email }}</b>. It expires in 30 minutes.</p>
  <form method="post" action="{{ route('account.verify-email') }}" class="flex gap-2 items-end">@csrf
    <x-field name="code" label="Code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required /><button class="btn">Verify</button></form>
  <form method="post" action="{{ route('account.verify-email.resend') }}">@csrf<button class="text-sm text-teal underline">Send a new code</button></form>
</div>
@endsection
