<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gate;
use App\Models\Tenant;
use Illuminate\Http\Request;

class DisplayMonitorController extends Controller
{
    public function __invoke(Request $request)
    {
        $live = now()->subMinute();
        $base = fn () => Gate::query()->when($request->company, fn ($q, $c) => $q->where('tenant_id', $c));
        $state = $request->query('state');

        return view('admin.displays', [
            'counts' => [
                'live' => $base()->where('is_active', true)->where('display_last_seen_at', '>', $live)->count(),
                'offline' => $base()->where('is_active', true)->where('display_last_seen_at', '<=', $live)->count(),
                'never' => $base()->where('is_active', true)->whereNull('display_last_seen_at')->count(),
                'inactive' => $base()->where('is_active', false)->count(),
            ],
            'companies' => Tenant::whereHas('offices')->orderBy('name')->pluck('name', 'id'),
            'gates' => $base()->with('office', 'tenant')
                ->when($state === 'live', fn ($q) => $q->where('is_active', true)->where('display_last_seen_at', '>', $live))
                ->when($state === 'offline', fn ($q) => $q->where('is_active', true)->where('display_last_seen_at', '<=', $live))
                ->when($state === 'never', fn ($q) => $q->where('is_active', true)->whereNull('display_last_seen_at'))
                ->when($state === 'inactive', fn ($q) => $q->where('is_active', false))
                ->orderByDesc('display_last_seen_at')->paginate(100)->withQueryString(),
        ]);
    }
}
