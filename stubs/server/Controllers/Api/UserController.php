<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission:users.read'])->only(['index', 'show']);
        $this->middleware(['auth:sanctum', 'permission:users.create'])->only('store');
        $this->middleware(['auth:sanctum', 'permission:users.update'])->only('update');
        $this->middleware(['auth:sanctum', 'permission:users.delete'])->only('destroy');
    }

    public function index(Request $request)
    {
        $query = User::with(['roles', 'departments', 'positions']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->get('role'));
            });
        }

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'status' => ['required', Rule::in(['active', 'inactive', 'locked'])],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
            'department_ids' => ['array'],
            'department_ids.*' => ['exists:departments,id'],
            'position_ids' => ['array'],
            'position_ids.*' => ['exists:positions,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
        ]);

        if (isset($validated['roles'])) {
            $user->assignRole($validated['roles']);
        }

        if (isset($validated['department_ids'])) {
            $user->departments()->attach($validated['department_ids']);
        }

        if (isset($validated['position_ids'])) {
            $user->positions()->attach($validated['position_ids']);
        }

        return response()->json($user->load(['roles', 'departments', 'positions']), Response::HTTP_CREATED);
    }

    public function show(User $user)
    {
        return response()->json($user->load(['roles', 'departments', 'positions']));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'status' => ['required', Rule::in(['active', 'inactive', 'locked'])],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
            'department_ids' => ['array'],
            'department_ids.*' => ['exists:departments,id'],
            'position_ids' => ['array'],
            'position_ids.*' => ['exists:positions,id'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'status' => $validated['status'],
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
            $updateData['password_changed_at'] = now();
        }

        $user->update($updateData);

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        if (isset($validated['department_ids'])) {
            $user->departments()->sync($validated['department_ids']);
        }

        if (isset($validated['position_ids'])) {
            $user->positions()->sync($validated['position_ids']);
        }

        return response()->json($user->load(['roles', 'departments', 'positions']));
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $roles = Role::whereIn('id', $validated['role_ids'])->get();
        $user->assignRole($roles);

        return response()->json($user->load('roles'));
    }

    public function removeRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $roles = Role::whereIn('id', $validated['role_ids'])->get();
        $user->removeRole($roles);

        return response()->json($user->load('roles'));
    }
}
