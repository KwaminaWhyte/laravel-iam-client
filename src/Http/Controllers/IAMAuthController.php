<?php

namespace Adamus\LaravelIamClient\Http\Controllers;

use Adamus\LaravelIamClient\Services\IAMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class IAMAuthController extends Controller
{
    public function __construct(
        protected IAMService $iamService
    ) {}

    /**
     * Handle IAM login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        // Use IAM service to authenticate with the IAM system
        $loginResponse = $this->iamService->login($credentials['email'], $credentials['password']);

        if (!$loginResponse) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $iamUser = $loginResponse['user'];
        $userModel = config('iam.user_model', \App\Models\User::class);

        // Create or update local user record
        $user = $userModel::updateOrCreate(
            ['email' => $iamUser['email']],
            [
                'name' => $iamUser['name'],
                'email' => $iamUser['email'],
                'password' => bcrypt('iam-managed'), // Placeholder password
            ]
        );

        // Store IAM token and user data in session
        session([
            'iam_token' => $loginResponse['access_token'],
            'iam_permissions' => $this->extractPermissions($loginResponse),
            'iam_roles' => array_column($iamUser['roles'] ?? [], 'name'),
        ]);

        // Log in the user with the IAM guard
        Auth::guard('iam')->login($user, $request->filled('remember'));

        // Regenerate session for security
        $request->session()->regenerate();

        // Return redirect for Inertia
        return redirect()->intended(route('dashboard'));
    }

    /**
     * Extract permissions from IAM response
     */
    protected function extractPermissions(array $iamResponse): array
    {
        $permissions = [];

        // Get permissions from direct response
        if (isset($iamResponse['permissions'])) {
            $permissions = array_merge($permissions, $iamResponse['permissions']);
        }

        // Extract permissions from roles
        if (isset($iamResponse['user']['roles'])) {
            foreach ($iamResponse['user']['roles'] as $role) {
                if (isset($role['permissions'])) {
                    foreach ($role['permissions'] as $permission) {
                        $permissions[] = is_array($permission) ? $permission['name'] : $permission;
                    }
                }
            }
        }

        return array_unique($permissions);
    }

    /**
     * Get authenticated user information
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::guard('iam')->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->iam_roles ?? [],
                'departments' => [], // Map from local database if needed
                'positions' => [], // Map from local database if needed
            ],
            'permissions' => $user->iam_permissions ?? [],
            'roles' => $user->iam_roles ?? [],
        ]);
    }

    /**
     * Check if user has specific permission
     */
    public function checkPermission(Request $request): JsonResponse
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $user = Auth::guard('iam')->user();

        if (!$user || !$user->iam_token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $hasPermission = $this->iamService->hasPermission($user->iam_token, $request->permission);

        return response()->json([
            'has_permission' => $hasPermission,
            'permission' => $request->permission,
        ]);
    }

    /**
     * Check if user has specific role
     */
    public function checkRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|string',
        ]);

        $user = Auth::guard('iam')->user();

        if (!$user || !$user->iam_token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $hasRole = $this->iamService->hasRole($user->iam_token, $request->role);

        return response()->json([
            'has_role' => $hasRole,
            'role' => $request->role,
        ]);
    }

    /**
     * Refresh access token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = Auth::guard('iam')->user();

        if (!$user || !$user->iam_token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $response = $this->iamService->refreshToken($user->iam_token);

        if (!$response) {
            return response()->json(['error' => 'Token refresh failed'], 401);
        }

        return response()->json($response);
    }

    /**
     * Logout from current session
     */
    public function logout(Request $request)
    {
        $token = session('iam_token');

        if ($token) {
            $this->iamService->logout($token);
        }

        // Clear IAM token from session
        session()->forget('iam_token');
        
        // Logout the user
        Auth::guard('iam')->logout();

        // Regenerate session for security
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Logout from all sessions
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = Auth::guard('iam')->user();

        if ($user && $user->iam_token) {
            $this->iamService->logoutAll($user->iam_token);
        }

        Auth::guard('iam')->logout();

        return response()->json([
            'message' => 'Successfully logged out from all devices',
        ]);
    }
}