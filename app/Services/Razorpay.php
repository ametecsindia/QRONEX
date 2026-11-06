<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Plain HTTP client for the two Razorpay calls we need — no SDK. */
class Razorpay
{
    public static function configured(): bool
    {
        return filled(config('qronex.razorpay.key_id')) && filled(config('qronex.razorpay.key_secret'));
    }

    public static function mode(): string
    {
        return ! self::configured() ? 'Not configured' : (str_starts_with(config('qronex.razorpay.key_id'), 'rzp_live_') ? 'Live' : 'Test');
    }

    /** @return string Razorpay order id */
    public static function createOrder(int $amountPaise, string $receipt, array $notes = []): string
    {
        $res = Http::withBasicAuth(config('qronex.razorpay.key_id'), config('qronex.razorpay.key_secret'))
            ->timeout(20)->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise, 'currency' => 'INR', 'receipt' => $receipt, 'notes' => $notes,
            ]);

        if (! $res->successful() || ! $res->json('id')) {
            throw new RuntimeException('Razorpay order failed: '.($res->json('error.description') ?? $res->status()));
        }

        return $res->json('id');
    }

    public static function validPaymentSignature(string $orderId, string $paymentId, string $signature, ?string $secret = null): bool
    {
        return hash_equals(hash_hmac('sha256', "$orderId|$paymentId", $secret ?? (string) config('qronex.razorpay.key_secret')), $signature);
    }

    public static function validWebhookSignature(string $rawBody, string $signature, ?string $secret = null): bool
    {
        $secret ??= (string) config('qronex.razorpay.webhook_secret');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
