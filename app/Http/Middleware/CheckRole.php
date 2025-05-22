<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware for checking user roles.
 *
 * @package App\Http\Middleware
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|array  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles): mixed
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $user = Auth::user();

        if (empty($roles)) {
            return $next($request);
        }

        // If no specific roles are required, just proceed
//        if (empty($roles)) {
//            return $next($request);
//        }

        // Normalize all types to lowercase for consistent comparison
        $allowedTypes = array_map('strtolower', $roles);

        // If user_type matches any of the allowed types, continue
        if (in_array(strtolower($user->user_type), $allowedTypes, true)) {
            return $next($request);
        }

        // Check if user has any of the required roles
//        foreach ($roles as $role) {
//            if ($user->hasRole($role)) {
//                return $next($request);
//            }
//        }

        return response()->forbidden('Access denied. You do not have the required permissions.');
    }
}
