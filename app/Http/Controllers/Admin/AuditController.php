<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;

/** Searchable, exportable audit trail across the platform (append-only). */
class AuditController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = AuditLog::with('user', 'tenant')
            ->when($request->company === 'platform', fn ($q) => $q->whereNull('tenant_id'))
            ->when(ctype_digit((string) $request->company), fn ($q) => $q->where('tenant_id', $request->company))
            ->when($request->action, fn ($q, $a) => $q->where('action', 'like', "$a%"))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('subject', 'like', "%$s%")->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', \Carbon\Carbon::parse($d, 'Asia/Kolkata')->startOfDay()->utc()))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<', \Carbon\Carbon::parse($d, 'Asia/Kolkata')->addDay()->startOfDay()->utc()))
            ->latest('created_at')->latest('id');

        if ($request->format === 'csv') {
            return response()->streamDownload(function () use ($q) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['When (IST)', 'Company', 'User', 'Action', 'Subject', 'IP', 'Details']);
                $q->limit(20000)->each(fn ($a) => fputcsv($out, [$a->created_at->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'), $a->tenant?->name ?? 'Platform',
                    $a->user?->email ?? 'system', $a->action, $a->subject, $a->ip, $a->meta ? json_encode($a->meta) : '']));
                fclose($out);
            }, 'qronex-audit-'.now('Asia/Kolkata')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.audit', [
            'rows' => $q->paginate(50)->withQueryString(),
            'companies' => Tenant::orderBy('name')->pluck('name', 'id'),
            'actions' => AuditLog::selectRaw('action')->distinct()->orderBy('action')->pluck('action')
                ->map(fn ($a) => explode('.', $a)[0])->unique()->values(),
        ]);
    }
}
