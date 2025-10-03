<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission:roles.read'])->only(['index', 'show']);
        $this->middleware(['auth:sanctum', 'permission:roles.create'])->only('store');
        $this->middleware(['auth:sanctum', 'permission:roles.update'])->only('update');
        $this->middleware(['auth:sanctum', 'permission:roles.delete'])->only('destroy');
        $this->middleware(['auth:sanctum', 'permission:roles.assign'])->only(['assignPermissions', 'removePermissions']);
    }

    public function index(Request $request)
    {
        $query = Role::with(['permissions', 'users']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $request->boolean('paginate', true)
            ? $query->paginate($request->get('per_page', 15))
            : $query->get();

        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        if (isset($validated['permissions'])) {
            $permissions = Permission::whereIn('id', $validated['permissions'])->get();
            $role->givePermissionTo($permissions);
        }

        return response()->json($role->load('permissions'), Response::HTTP_CREATED);
    }

    public function show(Role $role)
    {
        return response()->json($role->load(['permissions', 'users']));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $role->guard_name,
        ]);

        if (isset($validated['permissions'])) {
            $permissions = Permission::whereIn('id', $validated['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        return response()->json($role->load('permissions'));
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'super-admin') {
            return response()->json([
                'message' => 'Cannot delete super-admin role.'
            ], Response::HTTP_FORBIDDEN);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role with assigned users.'
            ], Response::HTTP_CONFLICT);
        }

        $role->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function assignPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $permissions = Permission::whereIn('id', $validated['permission_ids'])->get();
        $role->givePermissionTo($permissions);

        return response()->json($role->load('permissions'));
    }

    public function removePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $permissions = Permission::whereIn('id', $validated['permission_ids'])->get();
        $role->revokePermissionTo($permissions);

        return response()->json($role->load('permissions'));
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $permissions = isset($validated['permission_ids'])
            ? Permission::whereIn('id', $validated['permission_ids'])->get()
            : [];

        $role->syncPermissions($permissions);

        return response()->json($role->load('permissions'));
    }
}
