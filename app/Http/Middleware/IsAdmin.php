<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     * This middleware handles BOTH authentication AND admin role check
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get authenticated user
        $user = $request->user();
        
        // Check if user is authenticated (has valid token)
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Debug: Log user info
        \Log::info('IsAdmin Middleware - User:', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'role_type' => gettype($user->role),
        ]);

        // Check if authenticated user has admin role
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
                'debug' => [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_role' => $user->role,
                    'expected_role' => 'admin'
                ]
            ], 403);
        }

        // User is authenticated AND is admin - allow access
        return $next($request);
    }
}
