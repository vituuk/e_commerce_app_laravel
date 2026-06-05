<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCors
{
    /**
     * Allowed origins — add your Vercel URL and any other frontend domains here.
     */
    private array $allowedOrigins = [
        'https://e-commerce-app-flutter.vercel.app',
        'https://e-commerce-app-flutter-git-main-ukvitu9999-7456s-projects.vercel.app',
        'http://localhost:3000',
        'http://localhost:8080',
        'http://localhost',
    ];

    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');

        // Allow request if origin matches or is a Vercel preview URL
        $allowed = in_array($origin, $this->allowedOrigins)
            || str_ends_with((string) $origin, '.vercel.app')
            || str_ends_with((string) $origin, 'localhost');

        if ($request->isMethod('OPTIONS')) {
            // Pre-flight response
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $allowed ? $origin : '')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        if ($allowed && $origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
