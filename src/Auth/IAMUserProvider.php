<?php

namespace Adamus\LaravelIamClient\Auth;

use Adamus\LaravelIamClient\Services\IAMService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Cache;

class IAMUserProvider implements UserProvider
{
    protected IAMService $iamService;
    protected string $model;

    public function __construct(IAMService $iamService, array $config = [])
    {
        $this->iamService = $iamService;
        $this->model = $config['model'] ?? \App\Models\User::class;
    }

    public function retrieveById($identifier)
    {
        // Retrieve user from session (stateless - no database query)
        if (!session('iam_user') || !session('iam_token')) {
            return null;
        }

        $sessionUser = session('iam_user');

        // Only return user if the identifier matches
        if ($sessionUser['id'] !== $identifier) {
            return null;
        }

        // Create virtual IAMUser from session data
        $userClass = $this->model;
        return new $userClass([
            'id' => $sessionUser['id'],
            'name' => $sessionUser['name'],
            'email' => $sessionUser['email'],
            'phone' => $sessionUser['phone'] ?? null,
            'department_id' => $sessionUser['department_id'] ?? null,
            'position_id' => $sessionUser['position_id'] ?? null,
            'status' => $sessionUser['status'] ?? 'active',
            'iam_token' => session('iam_token'),
            'permissions' => session('iam_permissions', []),
            'roles' => session('iam_roles', []),
        ]);
    }

    public function retrieveByToken($identifier, $token)
    {
        // Not applicable for JWT tokens
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Not applicable for JWT tokens
    }

    public function retrieveByCredentials(array $credentials)
    {
        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            return null;
        }

        $iamResponse = $this->iamService->login($credentials['email'], $credentials['password']);

        if (!$iamResponse || !isset($iamResponse['user'])) {
            return null;
        }

        $iamUser = $iamResponse['user'];

        // Create virtual IAMUser (no database interaction)
        $userClass = $this->model;
        $user = new $userClass([
            'id' => $iamUser['id'],
            'name' => $iamUser['name'],
            'email' => $iamUser['email'],
            'phone' => $iamUser['phone'] ?? null,
            'department_id' => $iamUser['department_id'] ?? null,
            'position_id' => $iamUser['position_id'] ?? null,
            'status' => $iamUser['status'] ?? 'active',
            'iam_token' => $iamResponse['access_token'],
            'roles' => $iamUser['roles'] ?? [],
            'permissions' => $this->extractPermissions($iamResponse),
        ]);

        return $user;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        // Validation is done through IAM service in retrieveByCredentials
        return true;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        // Not applicable for IAM authentication
    }

    /**
     * Create a virtual IAMUser from raw user data (no API call).
     */
    public function createUserFromData(array $userData, string $token)
    {
        $userClass = $this->model;
        return new $userClass([
            'id' => $userData['id'],
            'name' => $userData['name'],
            'email' => $userData['email'],
            'phone' => $userData['phone'] ?? null,
            'department_id' => $userData['department_id'] ?? null,
            'position_id' => $userData['position_id'] ?? null,
            'status' => $userData['status'] ?? 'active',
            'iam_token' => $token,
            'roles' => $userData['roles'] ?? [],
            'permissions' => session('iam_permissions', []),
        ]);
    }

    /**
     * Verify IAM token and retrieve user
     * Returns virtual IAMUser instance (no database interaction)
     * Uses the same cache key as IAMSessionAuth middleware to avoid duplicate API calls.
     */
    public function retrieveByIAMToken(string $token)
    {
        $cacheKey = 'iam_token_' . md5($token);

        $iamResponse = Cache::remember($cacheKey, 60, function () use ($token) {
            return $this->iamService->verifyToken($token);
        });

        if (!$iamResponse || !isset($iamResponse['user'])) {
            Cache::forget($cacheKey);
            return null;
        }

        return $this->createUserFromData(
            $iamResponse['user'],
            $token
        );
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
     * Create a new instance of the model
     */
    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Get the name of the Eloquent user model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set the name of the Eloquent user model
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }
}