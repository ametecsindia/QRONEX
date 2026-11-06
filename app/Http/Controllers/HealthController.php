<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/** For uptime monitors: 200 when healthy, 503 otherwise. No secrets in the output. */
class HealthController extends Controller
{
    public function __invoke()
    {
        $c = [];
        try {
            DB::select('select 1');
            $c['database'] = 'ok';
            $beat = PlatformSetting::get('scheduler_heartbeat');
            $c['scheduler'] = $beat && Carbon::parse($beat)->gt(now()->subMinutes(10)) ? 'ok' : 'stale';
            $oldest = DB::table('jobs')->min('created_at');
            $c['queue'] = ! $oldest || time() - (int) $oldest < 300 ? 'ok' : 'backlog';
        } catch (Throwable) {
            $c['database'] = 'down';
        }
        $ok = ! array_diff(array_values($c), ['ok']);

        return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => $c, 'time' => now()->toIso8601ZuluString()], $ok ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
