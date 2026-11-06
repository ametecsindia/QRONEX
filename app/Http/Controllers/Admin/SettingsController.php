<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        $s = collect(PlatformSetting::DEFAULTS)->map(fn ($v, $k) => PlatformSetting::get($k));

        return view('admin.settings', compact('s'));
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'qr_min_s' => 'required|integer|min:1|max:60',
            'qr_max_s' => 'required|integer|gte:qr_min_s|max:120',
            'qr_default_s' => 'required|integer|gte:qr_min_s|lte:qr_max_s',
            'grace_max_s' => 'required|integer|min:0|max:60',
            'device_limit_max' => 'required|integer|min:1|max:10',
            'gst_pct' => 'required|numeric|min:0|max:40',
            'trial_days' => 'required|integer|min:0|max:90',
        ]);

        foreach ($d as $k => $v) {
            PlatformSetting::put($k, $k === 'gst_pct' ? (float) $v : (int) $v);
        }
        AuditLog::record('platform.settings', null, $d);

        return back()->with('ok', 'Platform settings saved.');
    }
}
