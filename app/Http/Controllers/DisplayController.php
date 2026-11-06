<?php

namespace App\Http\Controllers;

use App\Models\Gate;
use App\Models\Office;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\QrToken;
use Illuminate\Http\Request;

/** Public entrance screen: /display/{company}/{office}/{gate}?key=… — the key is the gate's display secret. */
class DisplayController extends Controller
{
    private function gate(string $tenant, string $office, string $gate, Request $request): Gate
    {
        $t = Tenant::where('slug', $tenant)->firstOrFail();
        abort_if($t->isBlocked(), 403, 'This workspace is not active.');
        $o = Office::withoutGlobalScopes()->where(['tenant_id' => $t->id, 'slug' => $office])->firstOrFail();
        $g = Gate::withoutGlobalScopes()->where(['office_id' => $o->id, 'code' => $gate])->firstOrFail();
        abort_unless(hash_equals((string) $g->display_secret, (string) $request->query('key')), 403, 'Display link is invalid or was reset.');

        return $g->setRelation('office', $o)->setRelation('tenant', $t);
    }

    public function show(Request $request, string $tenant, string $office, string $gate)
    {
        $g = $this->gate($tenant, $office, $gate, $request);

        return view('display.show', ['gate' => $g, 'tokenUrl' => route('display.token', [$tenant, $office, $gate, 'key' => $request->query('key')])]);
    }

    /** Polled by the display every rotation. Also the display heartbeat. */
    public function token(Request $request, string $tenant, string $office, string $gate)
    {
        $g = $this->gate($tenant, $office, $gate, $request);
        $interval = TenantSetting::withoutGlobalScopes()->where('tenant_id', $g->tenant_id)->value('qr_interval_s') ?: 3;
        $g->forceFill(['display_last_seen_at' => now(), 'display_agent' => mb_substr((string) $request->userAgent(), 0, 250)])->save();

        $active = $g->is_active && $g->office->is_active;

        return response()->json(($active ? QrToken::make($g, $interval) : ['qr' => null, 'expires_in' => 10]) + [
            'interval' => $interval, 'active' => $active, 'server_time' => now()->toIso8601ZuluString(),
        ])->header('Cache-Control', 'no-store');
    }
}
