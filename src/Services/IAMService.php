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

    // ==================== User Management ====================

    /**
     * Get all users from IAM
     *
     * @param array $params Query parameters (page, per_page, search, etc.)
     */
    public function getUsers(string $token, array $params = []): ?array
    {
        try {
            $response = $this->client->get('users', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'query' => $params,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get users failed', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);

            return null;
        }
    }

    /**
     * Get a specific user by ID
     */
    public function getUser(string $token, string $userId): ?array
    {
        try {
            $response = $this->client->get("users/{$userId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get user failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return null;
        }
    }

    /**
     * Create a new user
     */
    public function createUser(string $token, array $userData): ?array
    {
        try {
            $response = $this->client->post('users', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => $userData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM create user failed', [
                'error' => $e->getMessage(),
                'user_data' => $userData,
            ]);

            return null;
        }
    }

    /**
     * Update an existing user
     */
    public function updateUser(string $token, string $userId, array $userData): ?array
    {
        try {
            $response = $this->client->put("users/{$userId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => $userData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM update user failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'user_data' => $userData,
            ]);

            return null;
        }
    }

    /**
     * Delete a user
     */
    public function deleteUser(string $token, string $userId): bool
    {
        try {
            $this->client->delete("users/{$userId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return true;
        } catch (RequestException $e) {
            Log::error('IAM delete user failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return false;
        }
    }

    // ==================== Department Management ====================

    /**
     * Get all departments from IAM
     *
     * @param array $params Query parameters (page, per_page, search, etc.)
     */
    public function getDepartments(string $token, array $params = []): ?array
    {
        try {
            $response = $this->client->get('departments', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'query' => $params,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get departments failed', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);

            return null;
        }
    }

    /**
     * Get a specific department by ID
     */
    public function getDepartment(string $token, string $departmentId): ?array
    {
        try {
            $response = $this->client->get("departments/{$departmentId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get department failed', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId,
            ]);

            return null;
        }
    }

    /**
     * Create a new department
     */
    public function createDepartment(string $token, array $departmentData): ?array
    {
        try {
            $response = $this->client->post('departments', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => $departmentData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM create department failed', [
                'error' => $e->getMessage(),
                'department_data' => $departmentData,
            ]);

            return null;
        }
    }

    /**
     * Update an existing department
     */
    public function updateDepartment(string $token, string $departmentId, array $departmentData): ?array
    {
        try {
            $response = $this->client->put("departments/{$departmentId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => $departmentData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM update department failed', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId,
                'department_data' => $departmentData,
            ]);

            return null;
        }
    }

    /**
     * Delete a department
     */
    public function deleteDepartment(string $token, string $departmentId): bool
    {
        try {
            $this->client->delete("departments/{$departmentId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return true;
        } catch (RequestException $e) {
            Log::error('IAM delete department failed', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId,
            ]);

            return false;
        }
    }

    // ==================== Position Management ====================

    /**
     * Get all positions from IAM
     *
     * @param array $params Query parameters (page, per_page, search, etc.)
     */
    public function getPositions(string $token, array $params = []): ?array
    {
        try {
            $response = $this->client->get('positions', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'query' => $params,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get positions failed', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);

            return null;
        }
    }

    /**
     * Get a specific position by ID
     */
    public function getPosition(string $token, string $positionId): ?array
    {
        try {
            $response = $this->client->get("positions/{$positionId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get position failed', [
                'error' => $e->getMessage(),
                'position_id' => $positionId,
            ]);

            return null;
        }
    }

    /**
     * Get positions by department
     */
    public function getPositionsByDepartment(string $token, string $departmentId): ?array
    {
        try {
            $response = $this->client->get("departments/{$departmentId}/positions", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get positions by department failed', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId,
            ]);

            return null;
        }
    }

    /**
     * Create a new position
     */
    public function createPosition(string $token, array $positionData): ?array
    {
        try {
            $response = $this->client->post('positions', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => $positionData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM create position failed', [
                'error' => $e->getMessage(),
                'position_data' => $positionData,
            ]);

            return null;
        }
    }

    /**
     * Update an existing position
     */
    public function updatePosition(string $token, string $positionId, array $positionData): ?array
    {
        try {
            $response = $this->client->put("positions/{$positionId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'json' => $positionData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM update position failed', [
                'error' => $e->getMessage(),
                'position_id' => $positionId,
                'position_data' => $positionData,
            ]);

            return null;
        }
    }

    /**
     * Delete a position
     */
    public function deletePosition(string $token, string $positionId): bool
    {
        try {
            $this->client->delete("positions/{$positionId}", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return true;
        } catch (RequestException $e) {
            Log::error('IAM delete position failed', [
                'error' => $e->getMessage(),
                'position_id' => $positionId,
            ]);

            return false;
        }
    }

    /**
     * Get users assigned to a specific position
     */
    public function getUsersByPosition(string $token, string $positionId): ?array
    {
        try {
            $response = $this->client->get("positions/{$positionId}/users", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get users by position failed', [
                'error' => $e->getMessage(),
                'position_id' => $positionId,
            ]);

            return null;
        }
    }

    /**
     * Get users in a specific department
     */
    public function getUsersByDepartment(string $token, string $departmentId): ?array
    {
        try {
            $response = $this->client->get("departments/{$departmentId}/users", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('IAM get users by department failed', [
                'error' => $e->getMessage(),
                'department_id' => $departmentId,
            ]);

            return null;
        }
    }
}