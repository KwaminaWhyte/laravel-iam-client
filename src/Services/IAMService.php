<?php

namespace Adamus\LaravelIamClient\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class IAMService
{
    private Client $client;
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('iam.base_url');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => config('iam.timeout', 10),
            'verify' => config('iam.verify_ssl', true),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Login with email and password
     */
    public function login(string $email, string $password): ?array
    {
        try {
            $response = $this->client->post('auth/login', [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM login failed', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);

            return null;
        }
    }

    /**
     * Verify token and get user data
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $response = $this->client->get('auth/me', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM token verification failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $token, string $permission): bool
    {
        try {
            $response = $this->client->post('auth/check-permission', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => [
                    'permission' => $permission,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['has_permission'] ?? false;
        } catch (RequestException $e) {
            Log::error('IAM permission check failed', [
                'error' => $e->getMessage(),
                'permission' => $permission,
            ]);

            return false;
        }
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $token, string $role): bool
    {
        try {
            $response = $this->client->post('auth/check-role', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => [
                    'role' => $role,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['has_role'] ?? false;
        } catch (RequestException $e) {
            Log::error('IAM role check failed', [
                'error' => $e->getMessage(),
                'role' => $role,
            ]);

            return false;
        }
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $token): ?array
    {
        try {
            $response = $this->client->post('auth/refresh', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Logout from current session
     */
    public function logout(string $token): bool
    {
        try {
            $this->client->post('auth/logout', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return true;
        } catch (RequestException $e) {
            Log::error('IAM logout failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Logout from all sessions
     */
    public function logoutAll(string $token): bool
    {
        try {
            $this->client->post('auth/logout-all', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return true;
        } catch (RequestException $e) {
            Log::error('IAM logout all failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify session with IAM using session cookie
     */
    public function verifySession(string $sessionCookie): ?array
    {
        try {
            $iamBaseUrl = str_replace('/api/v1', '', $this->baseUrl);

            $response = $this->client->get($iamBaseUrl . 'api/v1/auth/me', [
                'headers' => [
                    'Cookie' => 'laravel_session=' . $sessionCookie,
                    'Accept' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM session verification failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}