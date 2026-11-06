<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware(EnsureRole::class.':super_admin,support_admin') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // A suspended company's owner keeps access to billing only, so they can pay and reactivate.
        if ($user?->is_active && $user->tenant?->status === 'suspended' && $user->hasRole('company_owner')) {
            if (! $request->routeIs('portal.billing.*')) {
                return redirect()->route('portal.billing.index')->withErrors(['account' => 'Your subscription is suspended. Pay to restore access.']);
            }

            return $next($request);
        }

        if (! $user || ! $user->is_active || ($user->tenant && $user->tenant->isBlocked())) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Your account or company is not active. Contact support.']);
        }

        abort_unless($user->hasRole(...$roles), 403);

        if ($user->mustUseTwoFactor() && ! $user->hasTwoFactor()) {
            return redirect()->route('account.security')->withErrors(['2fa' => 'Turn on two-factor sign-in to use the admin area.']);
        }

        return $next($request);
    }
}
