<?php

namespace Adamus\LaravelIamClient\Http\Middleware;

use Adamus\LaravelIamClient\Services\IAMService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IAMSessionAuth
{
    protected IAMService $iamService;

    public function __construct(IAMService $iamService)
    {
        $this->iamService = $iamService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if we have IAM token in session
        $token = session('iam_token');

        if (!$token) {
            return $this->redirectToLogin($request);
        }

        // Cache key for this token
        $cacheKey = 'iam_token_' . md5($token);

        // Try to get cached authentication result (1 minute cache)
        $authData = Cache::remember($cacheKey, 60, function () use ($token) {
            // Verify token with IAM
            return $this->iamService->verifyToken($token);
        });

        if (!$authData || !isset($authData['user'])) {
            // Token invalid, clear cache and redirect to login
            Cache::forget($cacheKey);
            session()->forget('iam_token');
            return $this->redirectToLogin($request);
        }

        // Store user data in request for this request cycle
        $request->attributes->set('iam_user', $authData['user']);
        $request->attributes->set('iam_authenticated', true);

        // Share with Inertia
        $request->merge(['auth' => ['user' => $authData['user']]]);

        return $next($request);
    }

    /**
     * Redirect to local login
     */
    protected function redirectToLogin(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return redirect()->guest(route('login'));
    }
}