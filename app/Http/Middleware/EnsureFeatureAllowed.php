<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Automatically enforces the Permissions matrix: if the current URL belongs
 * to a feature that exists in the matrix and the user's role is disallowed,
 * the request is blocked with 403. Features without matrix rows stay open.
 */
class EnsureFeatureAllowed
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $feature = resolve_feature_for_path($request->path());

        if ($feature === null || $user === null) {
            return $next($request);
        }

        if (! feature_allowed($feature)) {
            // Feature disabled for this role — show 404 so the module's
            // existence stays hidden.
            abort(404);
        }

        return $next($request);
    }
}
