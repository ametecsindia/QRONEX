<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Billing;
use App\Services\Razorpay;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class BillingController extends Controller
{
    public function __construct(private Billing $billing) {}

    private function simulated(): bool
    {
        return app()->isLocal() && ! Razorpay::configured();
    }

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant->load('subscription.planVersion.plan');
        $sub = $tenant->subscription;
        $employees = (int) $request->query('employees', $sub?->employee_count ?? 10);
        $cycle = in_array($request->query('cycle'), ['monthly', 'quarterly', 'annual'], true) ? $request->query('cycle') : ($sub?->billing_cycle ?? 'monthly');
        $plan = Plan::forEmployees(max(1, $employees));

        return view('portal.billing.index', [
            'tenant' => $tenant, 'sub' => $sub, 'employees' => $employees, 'cycle' => $cycle, 'plan' => $plan,
            'quote' => $plan ? $this->billing->quote($tenant, $plan->currentVersion, $employees, $cycle) : null,
            'invoices' => Invoice::latest('issued_on')->latest('id')->get(),
            'payments' => Payment::latest()->take(10)->get(),
            'mode' => $this->simulated() ? 'Simulated (dev — no Razorpay keys)' : Razorpay::mode(),
        ]);
    }

    /** Server-side order: amount comes from our quote, never from the browser. */
    public function checkout(Request $request)
    {
        if ($request->user()->emailPending()) {
            return redirect()->route('account.verify-email')->withErrors(['code' => 'Verify your email before paying.']);
        }
        $d = $request->validate(['employees' => 'required|integer|min:1|max:100000', 'cycle' => 'required|in:monthly,quarterly,annual']);
        $tenant = $request->user()->tenant;
        $plan = Plan::forEmployees((int) $d['employees']);
        $quote = $plan ? $this->billing->quote($tenant, $plan->currentVersion, (int) $d['employees'], $d['cycle']) : null;
        if (! $quote) {
            return back()->withErrors(['employees' => 'Above 500 employees we prepare a custom contract — contact sales.']);
        }
        if (! $this->simulated() && ! Razorpay::configured()) {
            return back()->withErrors(['payment' => 'Online payment is not configured yet. Contact support.']);
        }

        $receipt = 'rcpt_'.$tenant->id.'_'.Str::lower(Str::random(8));
        try {
            $orderId = $this->simulated() ? 'order_SIM'.Str::random(10) : Razorpay::createOrder($quote['total'] * 100, $receipt, ['tenant' => $tenant->slug]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['payment' => 'Could not start the payment. Try again in a minute.']);
        }

        $payment = Payment::create([
            'tenant_id' => $tenant->id, 'order_id' => $orderId, 'amount_paise' => $quote['total'] * 100,
            'meta' => ['plan_version_id' => $plan->currentVersion->id, 'plan' => $plan->name, 'employees' => (int) $d['employees'], 'cycle' => $d['cycle'], 'quote' => $quote, 'receipt' => $receipt],
        ]);
        AuditLog::record('billing.checkout', $orderId, ['total' => $quote['total']]);

        return view('portal.billing.checkout', ['payment' => $payment, 'quote' => $quote, 'simulated' => $this->simulated(), 'tenant' => $tenant, 'user' => $request->user()]);
    }

    /** Razorpay Checkout handler posts here; the HMAC signature is what proves payment. */
    public function callback(Request $request)
    {
        $d = $request->validate(['razorpay_order_id' => 'required|string', 'razorpay_payment_id' => 'required|string', 'razorpay_signature' => 'required|string']);
        $payment = Payment::where('order_id', $d['razorpay_order_id'])->firstOrFail();

        if (! Razorpay::validPaymentSignature($d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature'])) {
            AuditLog::record('billing.bad_signature', $d['razorpay_order_id']);

            return redirect()->route('portal.billing.index')->withErrors(['payment' => 'Payment could not be verified. If money was debited it will be confirmed automatically shortly.']);
        }
        $this->billing->markPaid($payment, $d['razorpay_payment_id']);

        return redirect()->route('portal.billing.index')->with('ok', 'Payment received. Your subscription is active.');
    }

    public function failed(Request $request, Payment $payment)
    {
        $this->billing->markFailed($payment, $request->input('reason', 'Cancelled or failed at checkout'));

        return redirect()->route('portal.billing.index')->withErrors(['payment' => 'Payment did not go through. You can retry.']);
    }

    public function simulate(Request $request, Payment $payment)
    {
        abort_unless($this->simulated(), 404);
        $request->boolean('success')
            ? $this->billing->markPaid($payment, 'pay_SIM'.Str::random(10))
            : $this->billing->markFailed($payment, 'Simulated failure');

        return redirect()->route('portal.billing.index')->with('ok', $request->boolean('success') ? 'Simulated payment succeeded.' : 'Simulated payment failed.');
    }

    public function invoice(Invoice $invoice)
    {
        return view('portal.billing.invoice', ['invoice' => $invoice, 'tenant' => auth()->user()->tenant]);
    }
}
