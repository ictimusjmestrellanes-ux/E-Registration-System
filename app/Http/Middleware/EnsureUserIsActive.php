<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * End stale sessions after an administrator deactivates an account.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && strcasecmp(trim((string) $user->status), 'Inactive') === 0) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account is not active.',
                ], 401);
            }

            return redirect()->route('login')
                ->with('error', 'Your account is not active.');
        }

        return $next($request);
    }
}
