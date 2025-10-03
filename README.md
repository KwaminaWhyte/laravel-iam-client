# Adamus Laravel IAM

A complete Laravel package for Identity and Access Management (IAM) that can be installed as either a **Client** or **Server**.

## Dual-Mode Package

This package can be installed in two modes:

### Client Mode
Use your application to authenticate against a central IAM server. Perfect for microservices and distributed applications.

### Server Mode
Run the complete IAM authentication service. Manage users, roles, permissions, departments, and positions centrally.

## Features

### Client Features
- 🔐 **Centralized Authentication** - Authenticate users against a central IAM service
- 🎫 **JWT Token Management** - Secure token-based authentication with session storage
- 🔑 **Permission & Role Management** - Check user permissions and roles via IAM API
- 👥 **User/Department/Position Management** - Fetch and manage organizational data from IAM
- 🎨 **Inertia.js Support** - Pre-built React login component
- 🛡️ **Middleware Protection** - Protect routes with IAM authentication
- 💾 **Local User Sync** - Automatically sync IAM users to local database
- ⚡ **Request Caching** - Minimize API calls with intelligent caching

### Server Features
- 👤 **Complete User Management** - Full CRUD operations for users
- 🎭 **Roles & Permissions** - Powered by Spatie Laravel Permission
- 🏢 **Department Management** - Organizational structure support
- 💼 **Position Management** - Job positions and hierarchies
- 🔐 **JWT Authentication** - Secure token-based auth
- 📝 **Audit Logging** - Track all authentication and authorization events
- 🚫 **Login Attempt Tracking** - Security monitoring and rate limiting
- 📨 **User Invitations** - Invite system for new users
- 🔒 **Two-Factor Authentication** - Enhanced security support

## Requirements

- PHP 8.2 or higher
- Laravel 11.0 or higher
- Inertia.js (for the login component)
- React (for the login component)

## Installation

### 1. Install via Composer

Install the package using Composer:

```bash
composer require adamus/laravel-iam-client
```

### 2. Choose Installation Mode

Run the installation command and select your preferred mode:

```bash
php artisan iam:install
```

This will prompt you to choose between:
- **Client** - For applications that authenticate against an IAM server
- **Server** - For running the IAM authentication service

Alternatively, you can specify the mode directly:

```bash
# Install as client
php artisan iam:install-client

# Install as server
php artisan iam:install-server
```

## Client Installation

When installing as a client, the command will:
- Publish the configuration file to `config/iam.php`
- Publish the login page component to `resources/js/pages/auth/login.tsx`
- Update your `config/auth.php` with IAM guard configuration
- Guide you through environment variable setup

## Server Installation

When installing as a server, the command will:
- Publish all migrations (users, roles, permissions, departments, positions, audit logs, etc.)
- Publish all models (User, Role, Permission, Department, Position, etc.)
- Publish API controllers for authentication and management
- Publish API routes
- Publish server configuration to `config/iam-server.php`
- Guide you through dependency installation

### 3. Configure Environment Variables

Add the following to your `.env` file:

```env
# Required
IAM_BASE_URL=http://your-iam-service.com/api/v1
AUTH_GUARD=iam

# Optional (with defaults)
IAM_TIMEOUT=10
IAM_VERIFY_SSL=true
IAM_USER_MODEL=App\Models\User
```

### 4. Ensure User Model Exists

Make sure you have a User model at `app/Models/User.php` with at least these fields:
- `id` (or uuid)
- `name`
- `email`
- `password`

## Usage

### Basic Authentication

The package automatically registers a login route at `/login`. Users can authenticate using their IAM credentials.

### Protecting Routes

Use the `iam.auth` middleware to protect your routes:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('iam.auth')->group(function () {
    Route::get('/dashboard', function () {
        return inertia('dashboard');
    })->name('dashboard');

    // More protected routes...
});
```

### Getting the Authenticated User

```php
use Illuminate\Support\Facades\Auth;

// Get the current user
$user = Auth::guard('iam')->user();

// Check if user is authenticated
if (Auth::guard('iam')->check()) {
    // User is authenticated
}

// Get user data
$userId = $user->id;
$userName = $user->name;
$userEmail = $user->email;
```

### Checking Permissions

```php
use Illuminate\Support\Facades\Auth;

// Check if user has a specific permission
if (Auth::guard('iam')->hasPermission('forms.create')) {
    // User has permission
}

// In your controller
public function store(Request $request)
{
    if (!Auth::guard('iam')->hasPermission('forms.create')) {
        abort(403, 'Unauthorized');
    }

    // Create form...
}
```

### Checking Roles

```php
use Illuminate\Support\Facades\Auth;

// Check if user has a specific role
if (Auth::guard('iam')->hasRole('admin')) {
    // User has role
}
```

### Manual Login (Programmatic)

```php
use Adamus\LaravelIamClient\Services\IAMService;
use Illuminate\Support\Facades\Auth;

public function login(Request $request, IAMService $iamService)
{
    $response = $iamService->login(
        $request->email,
        $request->password
    );

    if ($response) {
        // Store token in session
        session(['iam_token' => $response['access_token']]);

        // Authenticate user with IAM guard
        $user = Auth::guard('iam')->user();

        return redirect()->route('dashboard');
    }

    return back()->withErrors(['email' => 'Invalid credentials']);
}
```

### Logout

```php
use Illuminate\Support\Facades\Auth;
use Adamus\LaravelIamClient\Services\IAMService;

public function logout(Request $request, IAMService $iamService)
{
    // Logout from IAM
    $token = session('iam_token');
    if ($token) {
        $iamService->logout($token);
    }

    // Clear local session
    Auth::guard('iam')->logout();
    session()->forget('iam_token');

    return redirect()->route('login');
}
```

### Using IAM Service Directly

```php
use Adamus\LaravelIamClient\Services\IAMService;

public function checkAccess(IAMService $iamService)
{
    $token = session('iam_token');

    // Verify token
    $userData = $iamService->verifyToken($token);

    // Check permission
    $hasPermission = $iamService->hasPermission($token, 'users.edit');

    // Check role
    $hasRole = $iamService->hasRole($token, 'admin');

    // Refresh token
    $newToken = $iamService->refreshToken($token);

    // Logout from all devices
    $iamService->logoutAll($token);
}
```

### Managing Users, Departments, and Positions

The package provides methods to fetch and manage users, departments, and positions from the IAM system:

#### User Management

```php
use Adamus\LaravelIamClient\Services\IAMService;

public function manageUsers(IAMService $iamService)
{
    $token = session('iam_token');

    // Get all users (with optional pagination/filters)
    $users = $iamService->getUsers($token, [
        'page' => 1,
        'per_page' => 15,
        'search' => 'john'
    ]);

    // Get a specific user
    $user = $iamService->getUser($token, $userId);

    // Create a new user
    $newUser = $iamService->createUser($token, [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'status' => 'active'
    ]);

    // Update a user
    $updatedUser = $iamService->updateUser($token, $userId, [
        'name' => 'Jane Doe',
        'status' => 'active'
    ]);

    // Delete a user
    $iamService->deleteUser($token, $userId);
}
```

#### Department Management

```php
use Adamus\LaravelIamClient\Services\IAMService;

public function manageDepartments(IAMService $iamService)
{
    $token = session('iam_token');

    // Get all departments
    $departments = $iamService->getDepartments($token, [
        'page' => 1,
        'per_page' => 20
    ]);

    // Get a specific department
    $department = $iamService->getDepartment($token, $departmentId);

    // Create a new department
    $newDepartment = $iamService->createDepartment($token, [
        'name' => 'Engineering',
        'description' => 'Engineering Department',
        'parent_department_id' => null,
        'manager_id' => $userId
    ]);

    // Update a department
    $updatedDepartment = $iamService->updateDepartment($token, $departmentId, [
        'name' => 'Software Engineering',
        'description' => 'Updated description'
    ]);

    // Delete a department
    $iamService->deleteDepartment($token, $departmentId);
}
```

#### Position Management

```php
use Adamus\LaravelIamClient\Services\IAMService;

public function managePositions(IAMService $iamService)
{
    $token = session('iam_token');

    // Get all positions
    $positions = $iamService->getPositions($token, [
        'page' => 1,
        'per_page' => 20
    ]);

    // Get a specific position
    $position = $iamService->getPosition($token, $positionId);

    // Get positions by department
    $departmentPositions = $iamService->getPositionsByDepartment($token, $departmentId);

    // Create a new position
    $newPosition = $iamService->createPosition($token, [
        'department_id' => $departmentId,
        'title' => 'Senior Developer',
        'description' => 'Senior software developer position',
        'level' => 'senior',
        'salary_min' => 80000,
        'salary_max' => 120000,
        'reports_to_position_id' => $managerPositionId
    ]);

    // Update a position
    $updatedPosition = $iamService->updatePosition($token, $positionId, [
        'title' => 'Lead Developer',
        'level' => 'lead'
    ]);

    // Delete a position
    $iamService->deletePosition($token, $positionId);
}
```

#### Example Controller Using IAM Data

```php
use Adamus\LaravelIamClient\Services\IAMService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private IAMService $iamService)
    {
    }

    public function index(Request $request)
    {
        $token = session('iam_token');

        $users = $this->iamService->getUsers($token, [
            'page' => $request->get('page', 1),
            'per_page' => 15,
            'search' => $request->get('search')
        ]);

        return inertia('users/index', [
            'users' => $users
        ]);
    }

    public function show(string $id)
    {
        $token = session('iam_token');
        $user = $this->iamService->getUser($token, $id);

        return inertia('users/show', [
            'user' => $user
        ]);
    }
}
```

## Configuration

The configuration file is published to `config/iam.php`:

```php
return [
    // Base URL of your IAM service
    'base_url' => env('IAM_BASE_URL', 'http://localhost:8000/api/v1'),

    // API timeout in seconds
    'timeout' => env('IAM_TIMEOUT', 10),

    // Verify SSL certificates
    'verify_ssl' => env('IAM_VERIFY_SSL', true),

    // Default guard name
    'guard' => 'iam',

    // User model class
    'user_model' => env('IAM_USER_MODEL', \App\Models\User::class),

    // Token configuration
    'token_header' => 'Authorization',
    'token_prefix' => 'Bearer',
];
```

## Frontend Integration

### Login Component

The package includes a pre-built Inertia.js + React login component. After installation, it will be available at `resources/js/pages/auth/login.tsx`.

The component uses Inertia's `useForm` hook and posts to the `iam.login` route.

### Customizing the Login Page

You can customize the login page by editing `resources/js/pages/auth/login.tsx`:

```tsx
import { useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('iam.login'));
    };

    return (
        // Your custom UI
    );
}
```

## Available Routes

The package automatically registers these routes:

| Method | URI | Name | Middleware |
|--------|-----|------|------------|
| GET | `/login` | `login` | `guest` |
| POST | `/login` | `iam.login` | `guest` |
| POST | `/logout` | `logout` | `iam.auth` |
| GET | `/auth/me` | `iam.me` | `iam.auth` |
| POST | `/auth/check-permission` | `iam.check-permission` | `iam.auth` |
| POST | `/auth/check-role` | `iam.check-role` | `iam.auth` |
| POST | `/auth/refresh` | `iam.refresh` | `iam.auth` |
| POST | `/auth/logout-all` | `iam.logout-all` | `iam.auth` |

## Middleware

### `iam.auth`

The primary middleware for protecting routes. It:
- Checks for IAM token in session
- Verifies token with IAM service (with 1-minute cache)
- Stores user data in request attributes
- Redirects to login if unauthenticated

### `iam.authenticate`

Alternative middleware that uses Laravel's Auth guard directly:
- Checks authentication via IAM guard
- Redirects to login if not authenticated
- Returns 401 for JSON requests

## How It Works

1. **User Login**: When a user submits the login form, credentials are sent to the IAM service
2. **Token Storage**: On successful authentication, the JWT token is stored in Laravel session
3. **User Sync**: User data from IAM is synced to your local database
4. **Session Data**: Permissions and roles are cached in session for quick access
5. **Request Protection**: Middleware checks token validity on each protected request
6. **Token Caching**: Valid tokens are cached for 1 minute to reduce API calls
7. **Permission Checks**: Permissions/roles are checked from session first, then IAM API if needed

## Architecture

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Browser   │────────▶│ Laravel App  │────────▶│ IAM Service │
│  (Inertia)  │◀────────│  (Package)   │◀────────│   (API)     │
└─────────────┘         └──────────────┘         └─────────────┘
                              │
                              ▼
                        ┌──────────────┐
                        │ Local Users  │
                        │   Database   │
                        └──────────────┘
```

## Troubleshooting

### Token Verification Fails

Check your `IAM_BASE_URL` in `.env` and ensure it's pointing to the correct IAM service.

### User Not Found After Login

Ensure your User model matches the `user_model` configuration and has the required fields.

### Middleware Not Working

Make sure you've set `AUTH_GUARD=iam` in your `.env` file and the IAM guard is registered in `config/auth.php`.

### CORS Issues

If you're running IAM on a different domain, ensure CORS is properly configured on the IAM service.

## Security Considerations

- Always use HTTPS in production
- Set `IAM_VERIFY_SSL=true` in production
- Tokens are stored in encrypted Laravel sessions
- Local passwords are placeholder values - actual auth is via IAM
- Session regeneration on login prevents session fixation
- 1-minute token cache balances security and performance

## Testing

To test the package installation in your application:

```bash
# Test login flow
php artisan tinker

use Adamus\LaravelIamClient\Services\IAMService;
$service = app(IAMService::class);
$response = $service->login('user@example.com', 'password');
dd($response);
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Support

For issues, questions, or contributions, please contact the Adamus development team.