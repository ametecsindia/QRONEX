<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\Tenant;
use App\Models\WebhookDelivery;

class ApiUsageController extends Controller
{
    public function __invoke()
    {
        $since = now()->subDay();

        return view('admin.api', [
            'byTenant' => ApiRequest::where('created_at', '>=', $since)
                ->selectRaw('tenant_id, count(*) total, sum(case when status >= 400 then 1 else 0 end) errors, sum(case when status = 429 then 1 else 0 end) limited, avg(ms) avg_ms')
                ->groupBy('tenant_id')->orderByDesc('total')->get(),
            'tenants' => Tenant::pluck('name', 'id'),
            'keys' => ApiKey::whereNull('revoked_at')->selectRaw('tenant_id, count(*) c')->groupBy('tenant_id')->pluck('c', 'tenant_id'),
            'failed' => WebhookDelivery::with('endpoint')->where('status', 'failed')->latest()->take(30)->get(),
            'pending' => WebhookDelivery::where('status', 'pending')->count(),
        ]);
    }
}
