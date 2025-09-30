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
        // This is primarily used for session-based authentication
        $user = $this->createModel()->newQuery()->find($identifier);

        if ($user && session('iam_token')) {
            // Enrich the user with IAM session data for permissions/roles
            $user->iam_token = session('iam_token');
            $user->iam_permissions = session('iam_permissions', []);
            $user->iam_roles = session('iam_roles', []);
        }

        return $user;
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

        // Create or update local user record
        $user = $this->createModel()->newQuery()->updateOrCreate(
            ['email' => $iamUser['email']],
            [
                'name' => $iamUser['name'],
                'email' => $iamUser['email'],
                'password' => bcrypt('iam-managed'), // Placeholder password since auth is managed by IAM
            ]
        );

        // Store IAM data temporarily for the session
        $user->iam_token = $iamResponse['access_token'];
        $user->iam_roles = $iamUser['roles'] ?? [];
        $user->iam_permissions = $this->extractPermissions($iamResponse);

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
     */
    public function retrieveByIAMToken(string $token)
    {
        $iamResponse = $this->iamService->verifyToken($token);

        if (!$iamResponse || !isset($iamResponse['user'])) {
            return null;
        }

        $iamUser = $iamResponse['user'];

        // Create or update local user record
        $user = $this->createModel()->newQuery()->updateOrCreate(
            ['email' => $iamUser['email']],
            [
                'name' => $iamUser['name'],
                'email' => $iamUser['email'],
                'password' => bcrypt('iam-managed'), // Placeholder password since auth is managed by IAM
            ]
        );

        // Store IAM data temporarily
        $user->iam_token = $token;
        $user->iam_roles = $iamUser['roles'] ?? [];
        $user->iam_permissions = $iamResponse['permissions'] ?? [];

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