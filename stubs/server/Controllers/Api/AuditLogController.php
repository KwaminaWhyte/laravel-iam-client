<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    protected AuditLogService $auditService;

    public function __construct(AuditLogService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Get audit logs with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'user_id' => 'string|exists:users,id',
            'action' => 'string|max:100',
            'entity_type' => 'string|max:100',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'ip_address' => 'ip',
            'search' => 'string|max:255',
        ]);

        $filters = $request->only([
            'user_id', 'action', 'entity_type', 'start_date',
            'end_date', 'ip_address', 'search'
        ]);

        $logs = $this->auditService->getLogs($filters)
            ->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }

    /**
     * Get audit log statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'string|exists:users,id',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
        ]);

        $filters = $request->only(['user_id', 'start_date', 'end_date']);
        $statistics = $this->auditService->getStatistics($filters);

        return response()->json($statistics);
    }

    /**
     * Export audit logs
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'in:json,csv',
            'user_id' => 'string|exists:users,id',
            'action' => 'string|max:100',
            'entity_type' => 'string|max:100',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'ip_address' => 'ip',
            'search' => 'string|max:255',
        ]);

        $filters = $request->only([
            'user_id', 'action', 'entity_type', 'start_date',
            'end_date', 'ip_address', 'search'
        ]);

        $logs = $this->auditService->exportLogs($filters);

        $format = $request->get('format', 'json');

        if ($format === 'csv') {
            return $this->exportToCsv($logs);
        }

        return response()->json([
            'data' => $logs,
            'total' => count($logs),
            'exported_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get user activity summary
     */
    public function userActivity(Request $request, string $userId): JsonResponse
    {
        $request->validate([
            'days' => 'integer|min:1|max:365',
        ]);

        $user = User::findOrFail($userId);
        $days = $request->get('days', 30);

        $activity = $this->auditService->getUserActivitySummary($user, $days);

        return response()->json($activity);
    }

    /**
     * Get current user's activity summary
     */
    public function myActivity(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'integer|min:1|max:365',
        ]);

        $user = Auth::user();
        $days = $request->get('days', 30);

        $activity = $this->auditService->getUserActivitySummary($user, $days);

        return response()->json($activity);
    }

    /**
     * Detect suspicious patterns
     */
    public function suspiciousActivity(): JsonResponse
    {
        $patterns = $this->auditService->detectSuspiciousPatterns();

        return response()->json([
            'patterns' => $patterns,
            'detected_at' => now()->toISOString(),
            'total_patterns' => count($patterns),
        ]);
    }

    /**
     * Log a custom event (for manual logging)
     */
    public function logEvent(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|max:100',
            'entity_type' => 'required|string|max:100',
            'entity_id' => 'nullable|string|max:100',
            'old_values' => 'nullable|array',
            'new_values' => 'nullable|array',
            'context' => 'nullable|array',
        ]);

        $data = [];
        if ($request->has('old_values')) {
            $data['old_values'] = $request->get('old_values');
        }
        if ($request->has('new_values')) {
            $data['new_values'] = $request->get('new_values');
        }
        if ($request->has('context')) {
            $data = array_merge($data, $request->get('context'));
        }

        $auditLog = $this->auditService->log(
            $request->get('action'),
            $request->get('entity_type'),
            $request->get('entity_id'),
            $data
        );

        return response()->json($auditLog, 201);
    }

    /**
     * Get audit log by ID
     */
    public function show(string $id): JsonResponse
    {
        $log = $this->auditService->getLogs(['id' => $id])
            ->with(['user:id,name,email'])
            ->firstOrFail();

        return response()->json($log);
    }

    /**
     * Clean up old audit logs
     */
    public function cleanup(Request $request): JsonResponse
    {
        $request->validate([
            'days_to_keep' => 'integer|min:1|max:3650', // Max 10 years
        ]);

        $daysToKeep = $request->get('days_to_keep', 90);
        $deletedCount = $this->auditService->cleanup($daysToKeep);

        return response()->json([
            'message' => "Cleaned up {$deletedCount} old audit logs",
            'deleted_count' => $deletedCount,
            'days_kept' => $daysToKeep,
            'cleaned_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get audit log timeline for a specific entity
     */
    public function entityTimeline(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|max:100',
            'entity_id' => 'required|string|max:100',
            'limit' => 'integer|min:1|max:100',
        ]);

        $logs = $this->auditService->getLogs([
            'entity_type' => $request->get('entity_type'),
            'entity_id' => $request->get('entity_id'),
        ])->limit($request->get('limit', 50))
          ->get();

        return response()->json([
            'timeline' => $logs,
            'entity_type' => $request->get('entity_type'),
            'entity_id' => $request->get('entity_id'),
        ]);
    }

    /**
     * Get security dashboard data
     */
    public function securityDashboard(): JsonResponse
    {
        $patterns = $this->auditService->detectSuspiciousPatterns();
        $securityStats = $this->auditService->getStatistics([
            'entity_type' => 'security',
            'start_date' => now()->subDays(30)->toDateString(),
        ]);

        return response()->json([
            'suspicious_patterns' => $patterns,
            'security_statistics' => $securityStats,
            'recent_security_events' => $this->auditService->getLogs([
                'entity_type' => 'security',
            ])->limit(20)->get(),
        ]);
    }

    /**
     * Export logs to CSV format
     */
    private function exportToCsv(array $logs): JsonResponse
    {
        if (empty($logs)) {
            return response()->json([
                'csv' => '',
                'total' => 0,
                'exported_at' => now()->toISOString(),
            ]);
        }

        $csv = "ID,Timestamp,User,Email,Action,Entity Type,Entity ID,IP Address,User Agent\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log['id'],
                $log['timestamp'],
                str_replace('"', '""', $log['user'] ?? ''),
                $log['user_email'] ?? '',
                $log['action'],
                $log['entity_type'],
                $log['entity_id'] ?? '',
                $log['ip_address'],
                str_replace('"', '""', $log['user_agent'] ?? '')
            );
        }

        return response()->json([
            'csv' => $csv,
            'total' => count($logs),
            'exported_at' => now()->toISOString(),
        ]);
    }
}