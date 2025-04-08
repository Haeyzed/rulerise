<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Http\Middleware\BaseMiddleware;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

/**
 * Middleware for JWT authentication.
 *
 * @package App\Http\Middleware
 */
class JwtMiddleware extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Check if user is active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact support.'
                ], 403);
            }

            // Check if user is banned
            if ($user->is_banned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been banned. Please contact support.'
                ], 403);
            }

            // Check if email is verified
            if (!$user->email_verified_at && config('auth.email_verification_required')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not verified. Please verify your email address.'
                ], 403);
            }

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid'
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token absent'
            ], 401);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization error',
                'error' => $e->getMessage()
            ], 500);
        }

        return $next($request);
    }
}
