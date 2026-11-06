<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** 6-digit code emailed at sign-up. Pending verification blocks online payment, not the trial. */
class VerifyEmailController extends Controller
{
    public static function sendCode(User $u): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $u->forceFill(['email_code_hash' => Hash::make($code), 'email_code_expires_at' => now()->addMinutes(30)])->save();
        Notifier::email('verify', $u->email, ['otp' => $code, 'admin_name' => $u->name], $u->tenant_id);
    }

    public function show()
    {
        return view('auth.verify-email');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $u = $request->user();
        if (! $u->email_code_hash || $u->email_code_expires_at?->isPast() || ! Hash::check($request->input('code'), $u->email_code_hash)) {
            return back()->withErrors(['code' => 'Code is wrong or expired. Send a new one.']);
        }
        $u->forceFill(['email_verified_at' => now(), 'email_code_hash' => null, 'email_code_expires_at' => null])->save();
        AuditLog::record('auth.email_verified', $u->email);

        return redirect()->route('portal.dashboard')->with('ok', 'Email verified.');
    }

    public function resend(Request $request)
    {
        self::sendCode($request->user());

        return back()->with('ok', 'A new code is on its way.');
    }
}
