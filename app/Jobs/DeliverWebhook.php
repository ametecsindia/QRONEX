<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Retries with backoff for ~24 h (1 m, 5 m, 30 m, 2 h, 6 h, 15 h). Needs `php artisan queue:work`. */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 7;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300, 1800, 7200, 21600, 54000];
    }

    public function handle(): void
    {
        $d = WebhookDelivery::withoutGlobalScopes()->with('endpoint')->find($this->deliveryId);
        if (! $d || $d->status === 'delivered' || ! $d->endpoint) {
            return;
        }
        if (! $d->endpoint->is_active) {
            $d->update(['status' => 'failed', 'last_error' => 'Endpoint disabled']);

            return;
        }
        if ($problem = Webhooks::urlProblem($d->endpoint->url)) {
            $d->update(['status' => 'failed', 'attempts' => $d->attempts + 1, 'last_error' => $problem]);

            return;
        }

        $body = json_encode($d->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $t = time();
        $start = hrtime(true);
        $code = null;
        $error = null;
        try {
            $res = Http::timeout(10)->withoutRedirecting()->withHeaders([
                'Content-Type' => 'application/json', 'User-Agent' => 'QRONEX-Webhooks/1',
                'X-Qronex-Event' => $d->event, 'X-Qronex-Delivery' => $d->uuid,
                'X-Qronex-Signature' => Webhooks::sign($d->endpoint->secret, $t, $body),
            ])->withBody($body, 'application/json')->post($d->endpoint->url);
            $code = $res->status();
            $error = $res->successful() ? null : "HTTP $code";
        } catch (Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 250);
        }

        $d->update([
            'attempts' => $d->attempts + 1, 'response_code' => $code, 'latency_ms' => (int) ((hrtime(true) - $start) / 1e6),
            'status' => $error ? 'pending' : 'delivered', 'last_error' => $error,
        ]);

        if ($error) {
            throw new RuntimeException("Webhook {$d->uuid} failed: $error"); // queue retries with backoff
        }
    }

    public function failed(?Throwable $e): void
    {
        WebhookDelivery::withoutGlobalScopes()->whereKey($this->deliveryId)->where('status', '!=', 'delivered')->update(['status' => 'failed']);
    }
}
