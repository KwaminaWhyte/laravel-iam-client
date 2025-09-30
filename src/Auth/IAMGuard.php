<?php

namespace Adamus\LaravelIamClient\Auth;

use Adamus\LaravelIamClient\Services\IAMService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class IAMGuard implements Guard
{
    protected $user;
    protected IAMUserProvider $provider;
    protected Request $request;
    protected IAMService $iamService;

    public function __construct(IAMUserProvider $provider, Request $request, IAMService $iamService)
    {
        $this->provider = $provider;
        $this->request = $request;
        $this->iamService = $iamService;
    }

    public function check()
    {
        return ! is_null($this->user());
    }

    public function guest()
    {
        return ! $this->check();
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        // Get token from session or Authorization header
        $token = session('iam_token') ?: $this->getTokenFromRequest();

        if (!$token) {
            return null;
        }

        // Check request-level cache first (prevents multiple API calls per request)
        $cacheKey = 'iam_user_' . md5($token);
        $cachedUser = $this->request->attributes->get($cacheKey);

        if ($cachedUser !== null) {
            $this->user = $cachedUser;
            return $this->user;
        }

        // Verify with IAM service and cache for this request
        $this->user = $this->provider->retrieveByIAMToken($token);

        // Store in request attributes for the duration of this request
        $this->request->attributes->set($cacheKey, $this->user);

        return $this->user;
    }

    public function id()
    {
        return $this->user() ? $this->user()->getAuthIdentifier() : null;
    }

    public function validate(array $credentials = [])
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->setUser($user);
            return true;
        }

        return false;
    }

    public function hasUser()
    {
        return ! is_null($this->user);
    }

    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
    }

    /**
     * Get the user provider used by the guard
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Log a user into the application
     */
    public function login(Authenticatable $user, $remember = false)
    {
        $this->updateSession($user->getAuthIdentifier());

        $this->setUser($user);
    }

    /**
     * Log the user out of the application
     */
    public function logout()
    {
        $user = $this->user();

        $this->clearUserDataFromStorage();

        if (! is_null($this->user)) {
            $this->user = null;
        }
    }

    /**
     * Update the session with the given ID
     */
    protected function updateSession($id)
    {
        $this->request->session()->put($this->getName(), $id);
        $this->request->session()->migrate(true);
    }

    /**
     * Remove the user data from the session and cookies
     */
    protected function clearUserDataFromStorage()
    {
        $this->request->session()->remove($this->getName());
    }

    /**
     * Get the name of the session key used to store the user identifier
     */
    public function getName()
    {
        return 'login_iam_' . sha1(static::class);
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        // First check session permissions if available (faster)
        $sessionPermissions = session('iam_permissions', []);
        if (!empty($sessionPermissions)) {
            return in_array($permission, $sessionPermissions);
        }

        // Fallback to API call
        if (!isset($user->iam_token)) {
            return false;
        }

        return $this->iamService->hasPermission($user->iam_token, $permission);
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        // First check session roles if available (faster)
        $sessionRoles = session('iam_roles', []);
        if (!empty($sessionRoles)) {
            return in_array($role, $sessionRoles);
        }

        // Fallback to API call
        if (!isset($user->iam_token)) {
            return false;
        }

        return $this->iamService->hasRole($user->iam_token, $role);
    }

    /**
     * Get IAM token from request
     */
    protected function getTokenFromRequest(): ?string
    {
        $header = $this->request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }
}