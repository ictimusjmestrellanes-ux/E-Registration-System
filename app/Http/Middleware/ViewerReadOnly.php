<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ViewerReadOnly
{
    /**
     * Allow the Viewer role to view pages only.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->role_name === 'Viewer') {
            if (!in_array($request->method(), ['GET', 'HEAD'])) {
                abort(403, 'Viewer role is read-only.');
            }
        }

        return $next($request);
    }
}