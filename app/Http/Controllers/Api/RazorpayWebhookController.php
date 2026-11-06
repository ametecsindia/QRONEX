<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Billing;
use App\Services\Razorpay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

/** Signature-checked, idempotent by event id (X-Razorpay-Event-Id). Activation never relies on the browser alone. */
class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, Billing $billing)
    {
        $raw = $request->getContent();
        if (! Razorpay::validWebhookSignature($raw, (string) $request->header('X-Razorpay-Signature'))) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $payload = json_decode($raw, true) ?? [];
        $eventId = $request->header('X-Razorpay-Event-Id') ?: hash('sha256', $raw);
        try {
            PaymentEvent::create(['event_id' => $eventId, 'event' => $payload['event'] ?? 'unknown', 'payload' => $payload]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);
        }

        $entity = $payload['payload']['payment']['entity'] ?? null;
        $payment = $entity ? Payment::withoutGlobalScopes()->where('order_id', $entity['order_id'] ?? '')->first() : null;
        if ($payment && $entity) {
            match ($payload['event'] ?? '') {
                'payment.captured', 'order.paid' => $billing->markPaid($payment, $entity['id']),
                'payment.failed' => $billing->markFailed($payment, $entity['error_description'] ?? 'Payment failed'),
                default => null,
            };
        }

        return response()->json(['status' => 'ok']);
    }
}
