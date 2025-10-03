<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission:positions.read'])->only(['index', 'show']);
        $this->middleware(['auth:sanctum', 'permission:positions.create'])->only('store');
        $this->middleware(['auth:sanctum', 'permission:positions.update'])->only('update');
        $this->middleware(['auth:sanctum', 'permission:positions.delete'])->only('destroy');
        $this->middleware(['auth:sanctum', 'permission:positions.assign'])->only(['assignUsers', 'removeUsers']);
    }

    public function index(Request $request)
    {
        $query = Position::with(['department', 'users']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->get('department_id'));
        }

        if ($request->filled('level')) {
            $query->where('level', $request->get('level'));
        }

        $positions = $request->boolean('paginate', true)
            ? $query->paginate($request->get('per_page', 15))
            : $query->get();

        return response()->json($positions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => ['required', 'exists:departments,id'],
            'level' => ['required', 'string', Rule::in(['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'])],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'reports_to_position_id' => ['nullable', 'exists:positions,id'],
        ]);

        // Prevent position from reporting to itself
        if (isset($validated['reports_to_position_id'])) {
            $validated['reports_to_position_id'] = $validated['reports_to_position_id'] ?: null;
        }

        $position = Position::create($validated);

        return response()->json($position->load(['department', 'reportsToPosition']), Response::HTTP_CREATED);
    }

    public function show(Position $position)
    {
        return response()->json($position->load([
            'department',
            'users',
            'reportsToPosition',
            'subordinatePositions'
        ]));
    }

    public function update(Request $request, Position $position)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => ['required', 'exists:departments,id'],
            'level' => ['required', 'string', Rule::in(['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'])],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'reports_to_position_id' => [
                'nullable',
                'exists:positions,id',
                function ($attribute, $value, $fail) use ($position) {
                    if ($value === $position->id) {
                        $fail('A position cannot report to itself.');
                    }

                    if ($value && $this->wouldCreateCycle($position, $value)) {
                        $fail('This would create a circular reporting structure.');
                    }
                },
            ],
        ]);

        // Prevent position from reporting to itself
        if (isset($validated['reports_to_position_id'])) {
            $validated['reports_to_position_id'] = $validated['reports_to_position_id'] ?: null;
        }

        $position->update($validated);

        return response()->json($position->load(['department', 'reportsToPosition', 'subordinatePositions']));
    }

    public function destroy(Position $position)
    {
        if ($position->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete position with assigned users.'
            ], Response::HTTP_CONFLICT);
        }

        if ($position->subordinatePositions()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete position with subordinate positions.'
            ], Response::HTTP_CONFLICT);
        }

        $position->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function assignUsers(Request $request, Position $position)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $position->users()->attach($validated['user_ids']);

        return response()->json($position->load('users'));
    }

    public function removeUsers(Request $request, Position $position)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $position->users()->detach($validated['user_ids']);

        return response()->json($position->load('users'));
    }

    public function getByDepartment(Department $department)
    {
        $positions = $department->positions()->with(['users', 'reportsToPosition'])->get();

        return response()->json($positions);
    }

    public function getUsers(Position $position)
    {
        $users = $position->users()->with(['departments', 'positions'])->get();

        return response()->json(['data' => $users]);
    }

    private function wouldCreateCycle(Position $position, string $reportsToId): bool
    {
        $current = Position::find($reportsToId);

        while ($current) {
            if ($current->id === $position->id) {
                return true;
            }
            $current = $current->reportsToPosition;
        }

        return false;
    }
}
