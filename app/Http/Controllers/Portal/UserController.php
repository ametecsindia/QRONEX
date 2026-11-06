<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /** Roles a company owner/admin may hand out. Only an owner can create another owner. */
    private function assignable(): array
    {
        return auth()->user()->hasRole('company_owner') ? User::TENANT_ROLES : array_values(array_diff(User::TENANT_ROLES, ['company_owner']));
    }

    public function index(Request $request)
    {
        return view('portal.users.index', [
            'users' => User::where('tenant_id', $request->user()->tenant_id)->with('offices')->orderBy('name')->get(),
            'offices' => Office::orderBy('code')->get(),
            'roles' => $this->assignable(),
            'matrix' => \App\Support\Permissions::matrix($request->user()->tenant_id),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in($this->assignable())],
            'offices' => 'array',
            'offices.*' => Rule::exists('offices', 'id')->where('tenant_id', $request->user()->tenant_id),
        ]);

        $user = User::create(collect($d)->except('offices')->all() + ['tenant_id' => $request->user()->tenant_id]);
        $user->offices()->sync($d['offices'] ?? []);
        AuditLog::record('user.created', $user->email, ['role' => $user->role]);

        return back()->with('ok', "User {$user->name} created. Share the temporary password securely.");
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);
        abort_if($user->hasRole('company_owner') && ! $request->user()->hasRole('company_owner'), 403);

        $d = $request->validate([
            'role' => ['required', Rule::in($this->assignable())],
            'offices' => 'array',
            'offices.*' => Rule::exists('offices', 'id')->where('tenant_id', $request->user()->tenant_id),
        ]);
        $d['is_active'] = $request->boolean('is_active');

        if ($user->is($request->user()) && ($d['role'] !== $user->role || ! $d['is_active'])) {
            return back()->withErrors(['user' => 'You cannot change your own role or deactivate yourself.']);
        }

        $user->update(collect($d)->except('offices')->all());
        $user->offices()->sync($d['offices'] ?? []);
        AuditLog::record('user.updated', $user->email, ['role' => $user->role, 'active' => $user->is_active]);

        return back()->with('ok', 'User updated.');
    }

    /** Company Owner only: which company areas each role can use. The owner always keeps everything. */
    public function permissions(Request $request)
    {
        abort_unless($request->user()->hasRole('company_owner'), 403, 'Only the Company Owner can change role permissions.');
        $abilities = array_keys(\App\Support\Permissions::COMPANY);
        $in = (array) $request->input('perm', []);
        $matrix = [];
        foreach (\App\Support\Permissions::EDITABLE_ROLES as $role) {
            $matrix[$role] = array_values(array_intersect($abilities, array_keys((array) ($in[$role] ?? []))));
        }
        $s = \App\Models\TenantSetting::firstOrCreate(['tenant_id' => $request->user()->tenant_id], \App\Models\TenantSetting::defaults());
        $before = \App\Support\Permissions::matrix($s->tenant_id);
        $s->update(['role_permissions' => $matrix]);
        AuditLog::record('roles.permissions_updated', null, ['before' => $before, 'after' => $matrix]);

        return back()->with('ok', 'Role permissions saved. They apply on each user’s next page load.');
    }
}
