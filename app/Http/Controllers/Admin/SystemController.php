<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Preflight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function index()
    {
        return view('admin.system', [
            'checks' => Preflight::run(),
            'failed' => DB::table('failed_jobs')->latest('failed_at')->take(50)->get(),
            'versions' => ['PHP' => PHP_VERSION, 'Laravel' => app()->version(), 'Database' => DB::getDriverName(), 'Queue' => config('queue.default'), 'Timezone' => config('app.timezone')],
        ]);
    }

    public function retry(Request $request, string $uuid)
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AuditLog::record('system.job_retried', $uuid);

        return back()->with('ok', 'Job queued again.');
    }

    public function forget(string $uuid)
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
        AuditLog::record('system.job_deleted', $uuid);

        return back()->with('ok', 'Failed job removed.');
    }
}
