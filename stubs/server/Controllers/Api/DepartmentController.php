<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission:departments.read'])->only(['index', 'show']);
        $this->middleware(['auth:sanctum', 'permission:departments.create'])->only('store');
        $this->middleware(['auth:sanctum', 'permission:departments.update'])->only('update');
        $this->middleware(['auth:sanctum', 'permission:departments.delete'])->only('destroy');
    }

    public function index(Request $request)
    {
        $query = Department::with(['parentDepartment', 'childDepartments', 'manager', 'users']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_department_id', $request->get('parent_id'));
        }

        if ($request->boolean('root_only')) {
            $query->whereNull('parent_department_id');
        }

        $departments = $request->boolean('paginate', true)
            ? $query->paginate($request->get('per_page', 15))
            : $query->get();

        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments'],
            'description' => ['nullable', 'string'],
            'parent_department_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
        ]);

        $department = Department::create($validated);

        return response()->json($department->load(['parentDepartment', 'manager']), Response::HTTP_CREATED);
    }

    public function show(Department $department)
    {
        return response()->json($department->load([
            'parentDepartment',
            'childDepartments',
            'manager',
            'users',
            'positions'
        ]));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($department->id)],
            'description' => ['nullable', 'string'],
            'parent_department_id' => [
                'nullable',
                'exists:departments,id',
                function ($attribute, $value, $fail) use ($department) {
                    if ($value === $department->id) {
                        $fail('A department cannot be its own parent.');
                    }

                    if ($value && $this->wouldCreateCycle($department, $value)) {
                        $fail('This would create a circular dependency.');
                    }
                },
            ],
            'manager_id' => ['nullable', 'exists:users,id'],
        ]);

        $department->update($validated);

        return response()->json($department->load(['parentDepartment', 'childDepartments', 'manager']));
    }

    public function destroy(Department $department)
    {
        if ($department->childDepartments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete department with child departments.'
            ], Response::HTTP_CONFLICT);
        }

        if ($department->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete department with assigned users.'
            ], Response::HTTP_CONFLICT);
        }

        $department->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function assignUsers(Request $request, Department $department)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $department->users()->attach($validated['user_ids']);

        return response()->json($department->load('users'));
    }

    public function removeUsers(Request $request, Department $department)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $department->users()->detach($validated['user_ids']);

        return response()->json($department->load('users'));
    }

    public function getUsers(Department $department)
    {
        $users = $department->users()->with(['departments', 'positions'])->get();

        return response()->json(['data' => $users]);
    }

    private function wouldCreateCycle(Department $department, string $parentId): bool
    {
        $current = Department::find($parentId);

        while ($current) {
            if ($current->id === $department->id) {
                return true;
            }
            $current = $current->parentDepartment;
        }

        return false;
    }
}
