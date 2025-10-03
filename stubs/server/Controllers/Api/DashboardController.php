<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\LoginAttempt;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics
     */
    public function index(): JsonResponse
    {
        $stats = [
            'overview' => $this->getOverviewStats(),
            'user_analytics' => $this->getUserAnalytics(),
            'role_distribution' => $this->getRoleDistribution(),
            'department_analytics' => $this->getDepartmentAnalytics(),
            'security_metrics' => $this->getSecurityMetrics(),
            'recent_activity' => $this->getRecentActivity(),
            'growth_trends' => $this->getGrowthTrends(),
        ];

        return response()->json($stats);
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_departments' => Department::count(),
            'total_positions' => Position::count(),
            'total_roles' => Role::count(),
            'users_with_2fa' => User::whereNotNull('two_factor_secret')->count(),
            'locked_accounts' => User::whereNotNull('locked_until')
                ->where('locked_until', '>', now())
                ->count(),
        ];
    }

    /**
     * Get user analytics
     */
    private function getUserAnalytics(): array
    {
        $statusDistribution = User::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $recentLogins = User::whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->subDays(30))
            ->count();

        $neverLoggedIn = User::whereNull('last_login_at')->count();

        return [
            'status_distribution' => $statusDistribution,
            'recent_logins_30d' => $recentLogins,
            'never_logged_in' => $neverLoggedIn,
            'average_failed_attempts' => User::avg('failed_login_attempts') ?? 0,
        ];
    }

    /**
     * Get role distribution
     */
    private function getRoleDistribution(): array
    {
        $roles = Role::get();

        return $roles->map(function ($role) {
            return [
                'name' => $role->name,
                'display_name' => ucwords(str_replace('-', ' ', $role->name)),
                'user_count' => User::role($role->name)->count(),
                'permissions_count' => $role->permissions()->count(),
            ];
        })->toArray();
    }

    /**
     * Get department analytics
     */
    private function getDepartmentAnalytics(): array
    {
        $departments = Department::withCount(['users', 'positions'])
            ->with('parent')
            ->get();

        $departmentStats = $departments->map(function ($dept) {
            return [
                'id' => $dept->id,
                'name' => $dept->name,
                'parent_name' => $dept->parent?->name,
                'user_count' => $dept->users_count,
                'position_count' => $dept->positions_count,
                'is_parent' => $departments->where('parent_id', $dept->id)->count() > 0,
            ];
        });

        return [
            'departments' => $departmentStats->toArray(),
            'total_departments' => $departments->count(),
            'departments_with_users' => $departments->where('users_count', '>', 0)->count(),
            'largest_department' => $departments->sortByDesc('users_count')->first()?->only(['name', 'users_count']),
        ];
    }

    /**
     * Get security metrics
     */
    private function getSecurityMetrics(): array
    {
        $last30Days = now()->subDays(30);

        $failedLoginAttempts = LoginAttempt::where('created_at', '>=', $last30Days)
            ->where('successful', false)
            ->count();

        $successfulLogins = LoginAttempt::where('created_at', '>=', $last30Days)
            ->where('successful', true)
            ->count();

        $uniqueFailedIPs = LoginAttempt::where('created_at', '>=', $last30Days)
            ->where('successful', false)
            ->distinct('ip_address')
            ->count();

        $mfaEnabledUsers = User::whereNotNull('two_factor_secret')->count();
        $totalUsers = User::count();
        $mfaAdoptionRate = $totalUsers > 0 ? ($mfaEnabledUsers / $totalUsers) * 100 : 0;

        return [
            'failed_login_attempts_30d' => $failedLoginAttempts,
            'successful_logins_30d' => $successfulLogins,
            'unique_failed_ips_30d' => $uniqueFailedIPs,
            'mfa_adoption_rate' => round($mfaAdoptionRate, 1),
            'mfa_enabled_users' => $mfaEnabledUsers,
            'accounts_locked' => User::whereNotNull('locked_until')
                ->where('locked_until', '>', now())
                ->count(),
        ];
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(): array
    {
        // Recent user registrations
        $recentUsers = User::with('roles')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'user_created',
                    'description' => "New user: {$user->name}",
                    'user' => $user->name,
                    'roles' => $user->roles->pluck('name')->toArray(),
                    'created_at' => $user->created_at,
                ];
            });

        // Recent login attempts (successful)
        $recentLogins = User::whereNotNull('last_login_at')
            ->orderBy('last_login_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'user_login',
                    'description' => "User logged in: {$user->name}",
                    'user' => $user->name,
                    'created_at' => $user->last_login_at,
                ];
            });

        // Combine and sort activities
        $activities = collect()
            ->merge($recentUsers)
            ->merge($recentLogins)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        return $activities->toArray();
    }

    /**
     * Get growth trends
     */
    private function getGrowthTrends(): array
    {
        $last12Months = collect(range(11, 0))->map(function ($monthsBack) {
            $date = now()->subMonths($monthsBack);
            $startOfMonth = $date->startOfMonth()->copy();
            $endOfMonth = $date->endOfMonth()->copy();

            return [
                'month' => $date->format('M Y'),
                'users_created' => User::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'departments_created' => Department::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
            ];
        });

        return $last12Months->toArray();
    }

    /**
     * Get system health metrics
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'database' => $this->checkDatabaseHealth(),
            'users' => $this->checkUserHealth(),
            'security' => $this->checkSecurityHealth(),
            'performance' => $this->checkPerformanceMetrics(),
        ];

        // Determine overall status
        $hasIssues = collect($health)->except('status')->contains(function ($check) {
            return isset($check['status']) && $check['status'] !== 'healthy';
        });

        $health['status'] = $hasIssues ? 'warning' : 'healthy';

        return response()->json($health);
    }

    private function checkDatabaseHealth(): array
    {
        try {
            $userCount = User::count();
            $avgQueryTime = $this->measureQueryTime();

            return [
                'status' => 'healthy',
                'total_users' => $userCount,
                'avg_query_time_ms' => $avgQueryTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
        }
    }

    private function checkUserHealth(): array
    {
        $inactiveUsers = User::where('last_login_at', '<', now()->subDays(30))->count();
        $lockedUsers = User::whereNotNull('locked_until')
            ->where('locked_until', '>', now())
            ->count();

        return [
            'status' => $lockedUsers > 10 ? 'warning' : 'healthy',
            'inactive_users_30d' => $inactiveUsers,
            'locked_users' => $lockedUsers,
        ];
    }

    private function checkSecurityHealth(): array
    {
        $recentFailedAttempts = LoginAttempt::where('created_at', '>=', now()->subHour())
            ->where('successful', false)
            ->count();

        $mfaAdoptionRate = User::count() > 0
            ? (User::whereNotNull('two_factor_secret')->count() / User::count()) * 100
            : 0;

        return [
            'status' => $recentFailedAttempts > 50 ? 'warning' : 'healthy',
            'recent_failed_attempts' => $recentFailedAttempts,
            'mfa_adoption_rate' => round($mfaAdoptionRate, 1),
        ];
    }

    private function checkPerformanceMetrics(): array
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB

        return [
            'status' => $memoryUsage > 128 ? 'warning' : 'healthy',
            'memory_usage_mb' => round($memoryUsage, 2),
            'peak_memory_mb' => round($peakMemory, 2),
        ];
    }

    private function measureQueryTime(): float
    {
        $start = microtime(true);
        User::limit(1)->get();
        $end = microtime(true);

        return round(($end - $start) * 1000, 2);
    }
}