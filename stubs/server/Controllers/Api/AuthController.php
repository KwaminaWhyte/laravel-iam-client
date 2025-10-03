<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected SessionManagerService $sessionManager;

    public function __construct(SessionManagerService $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    /**
     * Login user and generate access token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'sometimes|string|max:255'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active.'],
            ]);
        }

        // Check if user is locked
        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => ['Your account is temporarily locked.'],
            ]);
        }

        // Load user relationships for the response
        $user->load(['roles.permissions', 'departments', 'positions']);

        // Create token with user abilities
        $abilities = $user->getAllPermissions()->pluck('name')->toArray();
        $deviceName = $request->device_name ?? $request->header('User-Agent', 'Unknown Device');

        // Detect suspicious activity
        $suspiciousFlags = $this->sessionManager->detectSuspiciousActivity($user, $request);

        // Create token with user abilities
        $token = $user->createToken($deviceName, $abilities);

        // Track session activity and enforce limits
        $this->sessionManager->trackActivity($user, $request);
        $this->sessionManager->enforceSessionLimits($user);

        $response = [
            'user' => $user,
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
        ];

        // Include security warnings if suspicious activity detected
        if (!empty($suspiciousFlags)) {
            $response['security_warnings'] = $suspiciousFlags;
        }

        return response()->json($response);
    }

    /**
     * Get current authenticated user with relationships
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['roles.permissions', 'departments', 'positions']);

        return response()->json([
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * Logout current user (revoke current token)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logoutAll(Request $request)
    {
        // Revoke all tokens for this user
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Successfully logged out from all devices'
        ]);
    }

    /**
     * Get all active tokens for current user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function tokens(Request $request)
    {
        $tokens = $request->user()->tokens()->select(['id', 'name', 'created_at', 'last_used_at'])->get();

        return response()->json([
            'tokens' => $tokens
        ]);
    }

    /**
     * Revoke a specific token
     *
     * @param Request $request
     * @param string $tokenId
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeToken(Request $request, $tokenId)
    {
        $token = $request->user()->tokens()->find($tokenId);

        if (!$token) {
            return response()->json([
                'message' => 'Token not found'
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Token revoked successfully'
        ]);
    }

    /**
     * Refresh current token (generate new token and revoke current one)
     * This simulates JWT refresh functionality with Sanctum
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        // Get current token info
        $deviceName = $currentToken->name;
        $abilities = $currentToken->abilities;

        // Create new token with same abilities
        $newToken = $user->createToken($deviceName, $abilities);

        // Revoke current token
        $currentToken->delete();

        return response()->json([
            'access_token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
        ]);
    }

    /**
     * Check if user has specific permission
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPermission(Request $request)
    {
        $request->validate([
            'permission' => 'required|string'
        ]);

        $hasPermission = $request->user()->can($request->permission);

        return response()->json([
            'has_permission' => $hasPermission,
            'permission' => $request->permission,
        ]);
    }

    /**
     * Check if user has specific role
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkRole(Request $request)
    {
        $request->validate([
            'role' => 'required|string'
        ]);

        $hasRole = $request->user()->hasRole($request->role);

        return response()->json([
            'has_role' => $hasRole,
            'role' => $request->role,
        ]);
    }

    /**
     * Get active sessions for current user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sessions(Request $request)
    {
        $sessions = $this->sessionManager->getActiveSessions($request->user());

        return response()->json([
            'sessions' => $sessions,
            'total_sessions' => count($sessions),
        ]);
    }

    /**
     * Get session timeout configuration
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sessionConfig(Request $request)
    {
        $config = $this->sessionManager->getTimeoutConfig();

        return response()->json($config);
    }

    /**
     * Force logout from specific session
     *
     * @param Request $request
     * @param int $tokenId
     * @return \Illuminate\Http\JsonResponse
     */
    public function terminateSession(Request $request, $tokenId)
    {
        $success = $this->sessionManager->forceLogout($request->user(), $tokenId);

        if (!$success) {
            return response()->json([
                'message' => 'Session not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Session terminated successfully'
        ]);
    }

    /**
     * Get security alerts for current user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function securityAlerts(Request $request)
    {
        $alerts = $this->sessionManager->detectSuspiciousActivity($request->user(), $request);

        return response()->json([
            'alerts' => $alerts,
            'count' => count($alerts),
            'has_alerts' => !empty($alerts),
        ]);
    }
}