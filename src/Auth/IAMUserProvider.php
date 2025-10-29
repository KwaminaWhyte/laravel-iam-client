<?php

namespace Adamus\LaravelIamClient\Auth;

use Adamus\LaravelIamClient\Services\IAMService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

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
        // For IAMUser, we need to fetch from IAM since there's no local database
        $token = session('iam_token');

        if (!$token) {
            return null;
        }

        // Use cached token verification to get user data
        return $this->retrieveByIAMToken($token);
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
     * Verify IAM token and retrieve user
     * Returns virtual IAMUser instance (no database interaction)
     */
    public function retrieveByIAMToken(string $token)
    {
        $iamResponse = $this->iamService->verifyToken($token);

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
            'iam_token' => $token,
            'roles' => $iamUser['roles'] ?? [],
            'permissions' => $iamResponse['permissions'] ?? $this->extractPermissions($iamResponse),
        ]);

        return $user;
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