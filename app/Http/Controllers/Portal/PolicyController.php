<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting as P;
use App\Models\TenantSetting;
use Illuminate\Http\Request;

class PolicyController extends Controller
{
    private function bounds(): array
    {
        return ['qr_min' => P::get('qr_min_s'), 'qr_max' => P::get('qr_max_s'), 'grace_max' => P::get('grace_max_s'), 'device_max' => P::get('device_limit_max')];
    }

    public function edit()
    {
        $settings = TenantSetting::firstOrCreate(['tenant_id' => auth()->user()->tenant_id], TenantSetting::defaults());

        return view('portal.policy', ['s' => $settings, 'b' => $this->bounds()]);
    }

    public function update(Request $request)
    {
        $b = $this->bounds();
        $d = $request->validate([
            'qr_interval_s' => "required|integer|between:{$b['qr_min']},{$b['qr_max']}",
            'grace_s' => "required|integer|between:0,{$b['grace_max']}",
            'device_limit' => "required|integer|between:1,{$b['device_max']}",
            'duplicate_window_min' => 'required|integer|between:0,60',
            'gps_accuracy_m' => 'required|integer|between:10,500',
            'retention_months' => 'required|integer|between:6,120',
            'location_notice' => 'required|string|max:500',
            'daily_report_email' => 'nullable|email|max:190',
            'shift_start' => 'required|date_format:H:i',
            'late_after_min' => 'required|integer|between:0,240',
        ]);
        foreach (['require_device_approval', 'notify_device_requests', 'notify_remote_requests'] as $flag) {
            $d[$flag] = $request->boolean($flag);
        }

        $s = TenantSetting::firstOrNew(['tenant_id' => $request->user()->tenant_id]);
        $s->fill($d)->save();
        AuditLog::record('policy.updated', null, $s->getChanges());

        return back()->with('ok', 'Attendance policy saved.');
    }
}
