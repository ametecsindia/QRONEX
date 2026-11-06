<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public static function home(User $u): string
    {
        return $u->isPlatform() ? route('admin.dashboard') : route('portal.dashboard', ['company' => $u->tenant->slug]);
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::validate($cred + ['is_active' => true])) {
            return back()->withErrors(['email' => 'These credentials do not match an active account.'])->onlyInput('email');
        }
        $user = User::where('email', $cred['email'])->first();

        if ($user->hasTwoFactor()) {
            // Password is right; the session is not signed in until the 6-digit code is checked.
            $request->session()->put('2fa', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => time()]);

            return redirect()->route('two-factor.challenge');
        }

        return $this->finishLogin($request, $user, $request->boolean('remember'));
    }

    public function finishLogin(Request $request, User $user, bool $remember)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('auth.login', $user->email);

        return redirect()->intended(self::home($user));
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', $request->user()->email);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showRegister()
    {
        return view('auth.register', ['plans' => Plan::where('is_active', true)->with('currentVersion')->orderBy('sort')->get()]);
    }

    /** Self-registration → trial tenant + owner + default settings + trial subscription. No payment until Stage 3. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'company' => 'required|string|max:150',
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Tenant::RESERVED_SLUGS), Rule::unique('tenants', 'slug')],
            'industry' => 'nullable|string|max:80',
            'state' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:80',
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'employees' => 'required|integer|min:1|max:100000',
            'billing_cycle' => 'required|in:monthly,quarterly,annual',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => 'accepted',
        ], ['slug.not_in' => 'That address is reserved. Choose another.', 'gstin.regex' => 'Enter a valid 15-character GSTIN.']);

        $plan = Plan::forEmployees((int) $data['employees']);
        abort_unless($plan, 422, 'No plan is available for that team size.');

        $user = Tenant::provision($data, $plan);

        \App\Services\Notifier::email('registration', $user->email, ['company_name' => $user->tenant->name, 'admin_name' => $user->name], $user->tenant_id);
        \App\Http\Controllers\VerifyEmailController::sendCode($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard', ['company' => $user->tenant->slug])->with('ok', 'Welcome to QRONEX. Your trial has started — add your first office to begin.');
    }
}
