<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Platform staff (Super Admin / Support Admin). Only Super Admins can change them. */
class AdminUserController extends Controller
{
    private const ROLES = ['super_admin', 'support_admin'];

    public function index()
    {
        return view('admin.users.index', ['users' => User::whereNull('tenant_id')->whereIn('role', self::ROLES)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', Password::min(10)->mixedCase()->numbers()],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);
        $u = User::create($d + ['tenant_id' => null]);
        AuditLog::record('admin_user.created', $u->email, ['role' => $u->role]);

        return back()->with('ok', "{$u->name} can now sign in. Share the temporary password securely; they should turn on two-factor sign-in.");
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->tenant_id === null && in_array($user->role, self::ROLES, true), 404);
        $d = $request->validate(['role' => ['required', Rule::in(self::ROLES)]]);
        $d['is_active'] = $request->boolean('is_active');
        if ($user->is($request->user()) && ($d['role'] !== $user->role || ! $d['is_active'])) {
            return back()->withErrors(['user' => 'You cannot change your own role or deactivate yourself.']);
        }
        if ($user->role === 'super_admin' && ($d['role'] !== 'super_admin' || ! $d['is_active'])
            && User::whereNull('tenant_id')->where('role', 'super_admin')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['user' => 'Keep at least one active Super Admin.']);
        }
        $user->update($d);
        AuditLog::record('admin_user.updated', $user->email, $d);

        return back()->with('ok', 'Admin user updated.');
    }

    /** Lost phone: clear 2FA so they can sign in with the password and set it up again. */
    public function resetTwoFactor(Request $request, User $user)
    {
        abort_unless($user->tenant_id === null, 404);
        abort_if($user->is($request->user()), 422, 'Use Account & security to change your own two-factor sign-in.');
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('admin_user.2fa_reset', $user->email);

        return back()->with('ok', "Two-factor sign-in reset for {$user->name}. They must set it up again at next sign-in.");
    }
}
