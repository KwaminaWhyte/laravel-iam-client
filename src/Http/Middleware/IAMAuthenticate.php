<?php

namespace Adamus\LaravelIamClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IAMAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('iam')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            // Redirect to local login page
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}