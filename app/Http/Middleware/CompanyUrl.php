<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Client portal lives at /{company-slug}/... (e.g. /demo/employees).
 * - web group (no route param): fills {company} for route('portal.*') from the signed-in user.
 * - portal group (with :check): the slug must be the user's own company; then the param is dropped
 *   so controllers never see it.
 */
class CompanyUrl
{
    public function handle(Request $request, Closure $next, ?string $mode = null)
    {
        $u = $request->user();
        if ($u && ! $u->isPlatform() && $u->tenant) {
            URL::defaults(['company' => $u->tenant->slug]);
        }

        if ($mode === 'check' && $route = $request->route()) {
            $slug = $route->parameter('company');
            if ($u && ! $u->isPlatform() && $u->tenant && $slug !== $u->tenant->slug) {
                // someone else's company address: send GETs to the same page of their own company
                abort_unless($request->isMethod('GET'), 404);

                return redirect('/'.$u->tenant->slug.substr($request->getPathInfo(), strlen('/'.$slug)).($request->getQueryString() ? '?'.$request->getQueryString() : ''));
            }
            $route->forgetParameter('company');
        }

        return $next($request);
    }
}
