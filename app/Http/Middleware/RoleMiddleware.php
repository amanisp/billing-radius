<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\ResponseFormatter;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        // Check if user is not authenticated
        if (!$user) {
            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                return ResponseFormatter::error(null, 'Unauthorized', 401);
            }
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        // Check if user's role is not in allowed roles
        if (!in_array($user->role, $roles)) {
            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                return ResponseFormatter::error(
                    null,
                    'Forbidden: Anda tidak memiliki akses ke resource ini',
                    403
                );
            }
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        return $next($request);
    }
}
