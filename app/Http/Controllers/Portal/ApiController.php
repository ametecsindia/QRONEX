<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhook;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Customer portal → API & webhooks. Keys are shown once; only the SHA-256 is stored. */
class ApiController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant->load('subscription.planVersion');
        $tab = in_array($request->query('tab'), ['keys', 'webhooks', 'usage'], true) ? $request->query('tab') : 'keys';

        // ponytail: days are UTC (DATE() is portable across MySQL/SQLite); IST days need a timezone-aware query.
        $daily = ApiRequest::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) d, count(*) total, sum(case when status >= 400 then 1 else 0 end) errors')
            ->groupBy('d')->get()->keyBy('d');

        return view('portal.api.index', [
            'tab' => $tab, 'tenant' => $tenant, 'allowed' => $tenant->apiAllowed(),
            'keys' => ApiKey::latest()->get(),
            'endpoints' => WebhookEndpoint::withCount(['deliveries as failed_count' => fn ($q) => $q->where('status', 'failed')])->get(),
            'deliveries' => WebhookDelivery::with('endpoint')->latest()->take(50)->get(),
            'days' => collect(range(13, 0))->mapWithKeys(fn ($i) => [$d = now()->subDays($i)->toDateString() => ['total' => (int) ($daily[$d]->total ?? 0), 'errors' => (int) ($daily[$d]->errors ?? 0)]]),
            'recent' => ApiRequest::latest('created_at')->take(20)->get(),
        ]);
    }

    public function storeKey(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:80', 'scopes' => 'required|array|min:1', 'scopes.*' => Rule::in(array_keys(ApiKey::SCOPES))]);
        [$key, $plain] = ApiKey::issue($request->user()->tenant_id, $d['name'], $d['scopes'], $request->user()->id);
        AuditLog::record('api_key.created', $key->prefix, ['scopes' => $key->scopes]);

        return redirect()->route('portal.api.index')->with('newKey', $plain)->with('ok', "Key “{$key->name}” created. Copy it now — it will not be shown again.");
    }

    /** Rotate = revoke + issue a new key with the same name and scopes. */
    public function rotateKey(ApiKey $key)
    {
        abort_if($key->revoked_at, 422, 'Key already revoked.');
        $key->update(['revoked_at' => now()]);
        [$new, $plain] = ApiKey::issue($key->tenant_id, $key->name, $key->scopes, auth()->id());
        AuditLog::record('api_key.rotated', $key->prefix, ['new' => $new->prefix]);

        return redirect()->route('portal.api.index')->with('newKey', $plain)->with('ok', "Key “{$key->name}” rotated. The old key stopped working.");
    }

    public function revokeKey(ApiKey $key)
    {
        $key->update(['revoked_at' => now()]);
        AuditLog::record('api_key.revoked', $key->prefix);

        return back()->with('ok', "Key “{$key->name}” revoked.");
    }

    public function storeEndpoint(Request $request)
    {
        $d = $request->validate(['url' => 'required|url|max:500', 'description' => 'nullable|string|max:120',
            'events' => 'required|array|min:1', 'events.*' => Rule::in(array_keys(WebhookEndpoint::EVENTS))]);
        if ($problem = Webhooks::urlProblem($d['url'])) {
            return back()->withErrors(['url' => $problem])->withInput();
        }
        $w = WebhookEndpoint::create($d + ['secret' => 'whsec_'.Str::random(32)]);
        AuditLog::record('webhook.created', $w->url);

        return redirect()->route('portal.api.index', ['tab' => 'webhooks'])->with('newSecret', $w->secret)->with('ok', 'Endpoint added. Copy the signing secret now.');
    }

    public function updateEndpoint(Request $request, WebhookEndpoint $endpoint)
    {
        $action = $request->input('action');
        match ($action) {
            'toggle' => $endpoint->update(['is_active' => ! $endpoint->is_active]),
            'roll' => $endpoint->update(['secret' => 'whsec_'.Str::random(32)]),
            'delete' => $endpoint->delete(),
            'ping' => Webhooks::queue($endpoint, 'ping', ['message' => 'Test event from QRONEX']),
            default => abort(422),
        };
        AuditLog::record("webhook.$action", $endpoint->url);

        if ($action === 'roll') {
            session()->flash('newSecret', $endpoint->secret);
        }

        return redirect()->route('portal.api.index', ['tab' => 'webhooks'])
            ->with('ok', ['toggle' => 'Endpoint updated.', 'roll' => 'New signing secret issued — update your receiver.', 'delete' => 'Endpoint deleted.', 'ping' => 'Test event queued.'][$action]);
    }

    public function redeliver(WebhookDelivery $delivery)
    {
        $delivery->update(['status' => 'pending', 'last_error' => null]);
        try {
            DeliverWebhook::dispatch($delivery->id);
        } catch (\Throwable $e) {
            report($e); // sync queue only
        }

        return back()->with('ok', 'Delivery queued again.');
    }
}
