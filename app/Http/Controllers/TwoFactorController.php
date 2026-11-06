<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    /* ---- sign-in challenge (password already checked) ---- */

    private function pending(Request $request): ?User
    {
        $p = $request->session()->get('2fa');

        return $p && time() - $p['at'] < 300 ? User::find($p['id']) : null; // 5 minutes to enter the code
    }

    public function challenge(Request $request)
    {
        return $this->pending($request) ? view('auth.two-factor') : redirect()->route('login');
    }

    public function verify(Request $request, AuthController $auth)
    {
        $request->validate(['code' => 'required|string|max:20']);
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Sign-in timed out. Enter your password again.']);
        }

        $code = $request->input('code');
        $ok = Totp::verify($user->two_factor_secret, $code) || (strlen(preg_replace('/\W/', '', $code)) === 10 && $user->useRecoveryCode($code));
        if (! $ok) {
            AuditLog::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'action' => 'auth.2fa_failed', 'subject' => $user->email, 'ip' => $request->ip()]);

            return back()->withErrors(['code' => 'That code is not valid. Use the current code from your authenticator app, or a recovery code.']);
        }

        $remember = $request->session()->pull('2fa')['remember'] ?? false;

        return $auth->finishLogin($request, $user, $remember);
    }

    /* ---- account security page ---- */

    public function show(Request $request)
    {
        $u = $request->user();
        $setup = ! $u->hasTwoFactor() && $u->two_factor_secret ? Totp::uri($u->two_factor_secret, $u->email) : null;

        return view('account.security', ['user' => $u, 'otpauth' => $setup, 'secret' => $setup ? $u->two_factor_secret : null]);
    }

    public function password(Request $request)
    {
        $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->mixedCase()->numbers()]]);
        $request->user()->forceFill(['password' => $request->input('password')])->save();
        AuditLog::record('auth.password_changed', $request->user()->email);

        return back()->with('ok', 'Password changed.');
    }

    /** Step 1: create a secret (not active until confirmed with a code). */
    public function enable(Request $request)
    {
        $request->user()->forceFill(['two_factor_secret' => Totp::secret(), 'two_factor_confirmed_at' => null])->save();

        return back();
    }

    /** Step 2: prove the app is set up; issues 8 single-use recovery codes, shown once. */
    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|max:10']);
        $u = $request->user();
        if (! $u->two_factor_secret || ! Totp::verify($u->two_factor_secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Code did not match. Check the time on your phone and try the newest code.']);
        }
        $codes = $this->newRecoveryCodes($u);
        $u->forceFill(['two_factor_confirmed_at' => now()])->save();
        AuditLog::record('auth.2fa_enabled', $u->email);

        return back()->with('recovery', $codes)->with('ok', 'Two-factor sign-in is on.');
    }

    public function recovery(Request $request)
    {
        $request->validate(['current_password' => 'required|current_password']);

        return back()->with('recovery', $this->newRecoveryCodes($request->user()))->with('ok', 'New recovery codes issued; the old ones no longer work.');
    }

    public function disable(Request $request)
    {
        $request->validate(['current_password' => 'required|current_password']);
        $u = $request->user();
        abort_if($u->mustUseTwoFactor(), 403, 'Two-factor sign-in is required for platform administrators.');
        $u->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('auth.2fa_disabled', $u->email);

        return back()->with('ok', 'Two-factor sign-in is off.');
    }

    private function newRecoveryCodes(User $u): array
    {
        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();
        $u->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => hash('sha256', str_replace('-', '', $c)), $codes)])->save();

        return $codes;
    }
}
