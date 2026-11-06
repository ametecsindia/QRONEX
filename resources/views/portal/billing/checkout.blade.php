@extends('layouts.app', ['title' => 'Complete payment'])
@section('content')
<div class="card max-w-lg space-y-3">
  <h2 class="font-semibold text-navy">{{ $payment->meta['plan'] }} · {{ $payment->meta['employees'] }} employees · {{ $payment->meta['cycle'] }}</h2>
  <div class="text-2xl font-bold text-navy">₹{{ number_format($payment->rupees(), 2) }} <span class="text-xs text-slate-500 font-normal">incl. GST</span></div>
  <div class="text-xs text-slate-500 font-mono">Order {{ $payment->order_id }}</div>
  @if($simulated)
    <div class="rounded bg-amber-50 border border-amber-200 text-amber-800 p-2 text-sm">Development mode: Razorpay keys are not set, so payment is simulated. This option is not available in production.</div>
    <div class="flex gap-2">
      <form method="post" action="{{ route('portal.billing.simulate', $payment) }}">@csrf<input type="hidden" name="success" value="1"><button class="btn">Simulate success</button></form>
      <form method="post" action="{{ route('portal.billing.simulate', $payment) }}">@csrf<input type="hidden" name="success" value="0"><button class="btn-ghost">Simulate failure</button></form>
    </div>
  @else
    <button id="pay" class="btn">Pay with Razorpay</button>
    <form id="done" method="post" action="{{ route('portal.billing.callback') }}">@csrf
      <input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_signature"></form>
    <form id="fail" method="post" action="{{ route('portal.billing.failed', $payment) }}">@csrf<input type="hidden" name="reason"></form>
  @endif
  <a class="text-sm text-teal" href="{{ route('portal.billing.index') }}">Cancel</a>
</div>
@unless($simulated)
@push('scripts')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const rzp = new Razorpay({
  key: @js(config('qronex.razorpay.key_id')), order_id: @js($payment->order_id), amount: @js($payment->amount_paise), currency: 'INR',
  name: 'QRONEX', description: @js($payment->meta['plan'].' plan'),
  prefill: { name: @js($user->name), email: @js($user->email) }, theme: { color: '#0A8278' },
  handler: r => { const f = document.getElementById('done'); for (const k of ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature']) f.elements[k].value = r[k]; f.submit(); },
});
rzp.on('payment.failed', r => { const f = document.getElementById('fail'); f.elements.reason.value = r.error?.description || 'Payment failed'; f.submit(); });
document.getElementById('pay').onclick = () => rzp.open();
rzp.open();
</script>
@endpush
@endunless
@endsection
