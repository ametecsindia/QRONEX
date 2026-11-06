<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API: Bearer qrx_live_… → tenant key. Usage: ->middleware(ApiKeyAuth::class.':attendance:read')
 * Also: plan gating, per-key rate limit (429 + Retry-After), Idempotency-Key replay on POST/PATCH, request log.
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $start = hrtime(true);
        $token = (string) $request->bearerToken();
        $key = str_starts_with($token, 'qrx_') ? ApiKey::withoutGlobalScopes()->with('tenant.subscription.planVersion')->where('key_hash', hash('sha256', $token))->whereNull('revoked_at')->first() : null;

        if (! $key) {
            return self::error(401, 'invalid_key', 'Missing, unknown or revoked API key.');
        }
        $tenant = $key->tenant;
        if ($tenant->isBlocked()) {
            return $this->log($key, $request, self::error(403, 'tenant_inactive', 'This workspace is suspended.'), $start);
        }
        if (! $tenant->apiAllowed()) {
            return $this->log($key, $request, self::error(403, 'api_not_in_plan', 'API access is not included in the current plan.'), $start);
        }
        if (! $key->hasScope($scope)) {
            return $this->log($key, $request, self::error(403, 'insufficient_scope', "This key lacks the '$scope' scope."), $start);
        }

        $limiter = 'api-key:'.$key->id;
        if (RateLimiter::tooManyAttempts($limiter, $key->rate_limit_per_min)) {
            $retry = RateLimiter::availableIn($limiter);

            return $this->log($key, $request, self::error(429, 'rate_limited', 'Too many requests.')->withHeaders(['Retry-After' => $retry]), $start);
        }
        RateLimiter::hit($limiter, 60);

        $request->attributes->set('api_key', $key);
        $request->attributes->set('tenant', $tenant);

        // Idempotency: same key + same body → replay stored response; same key + different body → 422.
        $idem = $request->isMethod('post') || $request->isMethod('patch') ? $request->header('Idempotency-Key') : null;
        if ($idem) {
            $hash = hash('sha256', $request->method().' '.$request->path().' '.$request->getContent());
            if ($prev = IdempotencyKey::where(['api_key_id' => $key->id, 'key' => mb_substr($idem, 0, 100)])->first()) {
                $res = $prev->request_hash === $hash
                    ? response()->json($prev->body, $prev->status)->header('Idempotent-Replayed', 'true')
                    : self::error(422, 'idempotency_conflict', 'Idempotency-Key was already used with a different request.');

                return $this->log($key, $request, $res, $start);
            }
        }

        $response = $next($request);

        if ($idem && $response->getStatusCode() < 500) {
            IdempotencyKey::create(['api_key_id' => $key->id, 'key' => mb_substr($idem, 0, 100), 'request_hash' => $hash,
                'status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true) ?? []]);
        }
        $response->headers->set('X-RateLimit-Limit', (string) $key->rate_limit_per_min);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($limiter, $key->rate_limit_per_min));
        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        return $this->log($key, $request, $response, $start);
    }

    public static function error(int $status, string $code, string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    private function log(ApiKey $key, Request $request, Response $response, int $start): Response
    {
        ApiRequest::create([
            'tenant_id' => $key->tenant_id, 'api_key_id' => $key->id, 'method' => $request->method(), 'path' => mb_substr('/'.$request->path(), 0, 190),
            'status' => $response->getStatusCode(), 'ms' => (int) ((hrtime(true) - $start) / 1e6), 'ip' => $request->ip(),
        ]);

        return $response;
    }
}
