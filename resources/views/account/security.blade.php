@extends('layouts.app', ['title' => 'Account & security'])
@section('content')
<div class="grid lg:grid-cols-2 gap-4 items-start">
  <div class="card space-y-3">
    <h2 class="font-semibold text-navy">Two-factor sign-in</h2>
    @if($user->hasTwoFactor())
      <p class="text-sm"><span class="chip bg-emerald-100 text-emerald-800">On</span> since {{ $user->two_factor_confirmed_at->timezone('Asia/Kolkata')->format('d M Y') }} · {{ count($user->two_factor_recovery_codes ?? []) }} recovery codes left</p>
      @if(session('recovery'))
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm"><b>Save these recovery codes now.</b> Each works once if you lose your phone. They will not be shown again.
          <ul class="grid grid-cols-2 gap-1 font-mono mt-2">@foreach(session('recovery') as $c)<li>{{ $c }}</li>@endforeach</ul></div>
      @endif
      <form method="post" action="{{ route('account.2fa.recovery') }}" class="flex flex-wrap gap-2 items-end">@csrf
        <div><label class="label">Current password</label><input type="password" name="current_password" class="input" required autocomplete="current-password"></div><button class="btn-ghost">New recovery codes</button></form>
      @unless($user->mustUseTwoFactor())
      <form method="post" action="{{ route('account.2fa.disable') }}" class="flex flex-wrap gap-2 items-end" onsubmit="return confirm('Turn off two-factor sign-in?')">@csrf
        <div><label class="label">Current password</label><input type="password" name="current_password" class="input" required autocomplete="current-password"></div><button class="btn-ghost">Turn off</button></form>
      @endunless
    @elseif($otpauth)
      <ol class="text-sm list-decimal pl-5 space-y-1"><li>Install Google Authenticator, Microsoft Authenticator or Authy.</li><li>Scan this code (or type the key).</li><li>Enter the 6-digit code it shows.</li></ol>
      <div id="otpqr" class="bg-white p-2 border rounded w-48 h-48"></div>
      <div class="text-xs">Key: <code class="break-all">{{ trim(chunk_split($secret, 4, ' ')) }}</code></div>
      <form method="post" action="{{ route('account.2fa.confirm') }}" class="flex gap-2 items-end">@csrf
        <x-field name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" required /><button class="btn">Confirm</button></form>
      @push('scripts')<script src="{{ asset('vendor/qrcode.min.js') }}"></script><script>new QRCode(document.getElementById('otpqr'), { text: @js($otpauth), width: 176, height: 176 });</script>@endpush
    @else
      <p class="text-sm text-slate-600">Protect your account with a code from your phone in addition to your password.@if($user->mustUseTwoFactor()) <b>Required for platform administrators.</b>@endif</p>
      <form method="post" action="{{ route('account.2fa.enable') }}">@csrf<button class="btn">Set up two-factor sign-in</button></form>
    @endif
  </div>
  <form method="post" action="{{ route('account.password') }}" class="card space-y-3">
    @csrf <h2 class="font-semibold text-navy">Change password</h2>
    <x-field name="current_password" label="Current password" type="password" required autocomplete="current-password" />
    <x-field name="password" label="New password" type="password" required autocomplete="new-password" hint="At least 8 characters (numbers only is fine)." />
    <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
    <button class="btn">Change password</button>
  </form>
</div>
@endsection
