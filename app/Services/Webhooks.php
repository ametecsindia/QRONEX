<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Outbound events. Payload: {id, event, created_at, tenant, data}.
 * Signature header: X-Qronex-Signature: t=<unix>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>
 */
class Webhooks
{
    public static function dispatch(int $tenantId, string $event, array $data): void
    {
        $endpoints = WebhookEndpoint::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->get()
            ->filter(fn ($e) => $event === 'ping' || in_array($event, $e->events ?? [], true));

        foreach ($endpoints as $endpoint) {
            self::queue($endpoint, $event, $data);
        }
    }

    public static function queue(WebhookEndpoint $endpoint, string $event, array $data): WebhookDelivery
    {
        $uuid = 'evt_'.Str::lower(Str::random(20));
        $delivery = WebhookDelivery::create([
            'uuid' => $uuid, 'tenant_id' => $endpoint->tenant_id, 'webhook_endpoint_id' => $endpoint->id, 'event' => $event,
            'payload' => ['id' => $uuid, 'event' => $event, 'created_at' => now()->toIso8601ZuluString(), 'tenant' => $endpoint->tenant?->slug, 'data' => $data],
        ]);
        try {
            DeliverWebhook::dispatch($delivery->id)->afterCommit();
        } catch (\Throwable $e) {
            report($e); // only reachable with QUEUE_CONNECTION=sync; a failed webhook must never fail the business action
        }

        return $delivery;
    }

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** Blocks SSRF: production webhooks must be HTTPS to a public address. Local dev may use http / private IPs. */
    public static function urlProblem(string $url): ?string
    {
        $parts = parse_url($url);
        if (! $parts || empty($parts['host']) || ! in_array($parts['scheme'] ?? '', ['https', 'http'], true)) {
            return 'Not a valid URL.';
        }
        if (app()->isLocal() || app()->runningUnitTests()) {
            return null;
        }
        if ($parts['scheme'] !== 'https') {
            return 'Webhook URLs must use HTTPS.';
        }
        $ips = filter_var($parts['host'], FILTER_VALIDATE_IP) ? [$parts['host']] : (gethostbynamel($parts['host']) ?: []);
        if (! $ips) {
            return 'Host does not resolve.';
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'Webhook URLs must point to a public address.';
            }
        }

        return null;
    }
}
