<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {

    // Public routes (no authentication required)
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

        // Support both session and token authentication for cross-service auth
        Route::middleware(['auth:sanctum,web', 'throttle:auth-general'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
            Route::get('tokens', [AuthController::class, 'tokens']);
            Route::delete('tokens/{tokenId}', [AuthController::class, 'revokeToken']);
            Route::post('check-permission', [AuthController::class, 'checkPermission']);
            Route::post('check-role', [AuthController::class, 'checkRole']);

            // Session management endpoints
            Route::get('sessions', [AuthController::class, 'sessions']);
            Route::get('session-config', [AuthController::class, 'sessionConfig']);
            Route::delete('sessions/{tokenId}', [AuthController::class, 'terminateSession']);
            Route::get('security-alerts', [AuthController::class, 'securityAlerts']);
        });
    });

    // Protected routes (require authentication - support both sanctum and web session)
    Route::middleware(['auth:sanctum,web', 'throttle:api'])->group(function () {

        // User management
        Route::apiResource('users', UserController::class);
        Route::post('users/{user}/assign-role', [UserController::class, 'assignRole']);
        Route::post('users/{user}/remove-role', [UserController::class, 'removeRole']);

        // Department management
        Route::apiResource('departments', DepartmentController::class);
        Route::post('departments/{department}/assign-users', [DepartmentController::class, 'assignUsers']);
        Route::post('departments/{department}/remove-users', [DepartmentController::class, 'removeUsers']);
        Route::get('departments/{department}/users', [DepartmentController::class, 'getUsers']);

        // Position management
        Route::apiResource('positions', PositionController::class);
        Route::post('positions/{position}/assign-users', [PositionController::class, 'assignUsers']);
        Route::post('positions/{position}/remove-users', [PositionController::class, 'removeUsers']);
        Route::get('departments/{department}/positions', [PositionController::class, 'getByDepartment']);
        Route::get('positions/{position}/users', [PositionController::class, 'getUsers']);

        // Role management
        Route::apiResource('roles', RoleController::class);
        Route::post('roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions']);
        Route::post('roles/{role}/remove-permissions', [RoleController::class, 'removePermissions']);
        Route::post('roles/{role}/sync-permissions', [RoleController::class, 'syncPermissions']);

        // Permission management
        Route::apiResource('permissions', PermissionController::class);
        Route::get('permissions/grouped', [PermissionController::class, 'getGrouped']);

        // Invitation management
        Route::apiResource('invitations', InvitationController::class);
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend']);

        // Dashboard analytics
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('dashboard/health', [DashboardController::class, 'health']);

        // Additional user endpoints
        Route::get('user-permissions', [UserController::class, 'getUserPermissions']);
        Route::get('user-roles', [UserController::class, 'getUserRoles']);

        // Audit log management
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('statistics', [AuditLogController::class, 'statistics']);
            Route::get('export', [AuditLogController::class, 'export']);
            Route::get('my-activity', [AuditLogController::class, 'myActivity']);
            Route::get('user/{userId}/activity', [AuditLogController::class, 'userActivity']);
            Route::get('suspicious-activity', [AuditLogController::class, 'suspiciousActivity']);
            Route::get('security-dashboard', [AuditLogController::class, 'securityDashboard']);
            Route::get('entity-timeline', [AuditLogController::class, 'entityTimeline']);
            Route::get('{id}', [AuditLogController::class, 'show']);
            Route::post('log-event', [AuditLogController::class, 'logEvent']);
            Route::post('cleanup', [AuditLogController::class, 'cleanup']);
        });

    });
});