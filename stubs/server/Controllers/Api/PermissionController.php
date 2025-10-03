<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission:permissions.read'])->only(['index', 'show']);
        $this->middleware(['auth:sanctum', 'permission:permissions.create'])->only('store');
        $this->middleware(['auth:sanctum', 'permission:permissions.update'])->only('update');
        $this->middleware(['auth:sanctum', 'permission:permissions.delete'])->only('destroy');
    }

    public function index(Request $request)
    {
        $query = Permission::with(['roles']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('group')) {
            $group = $request->get('group');
            $query->where('name', 'like', "{$group}.%");
        }

        $permissions = $request->boolean('paginate', true)
            ? $query->paginate($request->get('per_page', 15))
            : $query->get();

        return response()->json($permissions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions'],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        return response()->json($permission, Response::HTTP_CREATED);
    }

    public function show(Permission $permission)
    {
        return response()->json($permission->load('roles'));
    }

    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $permission->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $permission->guard_name,
        ]);

        return response()->json($permission);
    }

    public function destroy(Permission $permission)
    {
        if ($permission->roles()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete permission that is assigned to roles.'
            ], Response::HTTP_CONFLICT);
        }

        $permission->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function getGrouped(Request $request)
    {
        $permissions = Permission::all();

        $grouped = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            return $parts[0] ?? 'other';
        });

        return response()->json($grouped);
    }
}
