<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Get all users.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('users.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'search' => 'nullable|string|max:100',
            'role' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,suspended',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 15);

        $query = User::with(['roles', 'defaultLocation', 'schedules.shift']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->input('role')));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get available roles.
     */
    public function roles(Request $request): JsonResponse
    {
        if (!$request->user()->can('users.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $roles = Role::all(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('users.create')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'default_location_id' => 'nullable|exists:locations,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'employee_id' => $validated['employee_id'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'department' => $validated['department'] ?? null,
            'position' => $validated['position'] ?? null,
            'default_location_id' => $validated['default_location_id'] ?? null,
            'status' => 'active',
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->load(['roles', 'defaultLocation']),
        ], 201);
    }

    /**
     * Get a specific user.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('users.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $user->load(['roles', 'defaultLocation', 'schedules.shift']);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('users.update')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'employee_id' => 'sometimes|nullable|string|max:50|unique:users,employee_id,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'department' => 'sometimes|nullable|string|max:100',
            'position' => 'sometimes|nullable|string|max:100',
            'default_location_id' => 'sometimes|nullable|exists:locations,id',
            'status' => 'sometimes|in:active,inactive,suspended',
            'role' => 'sometimes|string|exists:roles,name',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $role = $validated['role'] ?? null;
        unset($validated['role']);

        $user->update($validated);

        if ($role) {
            $user->syncRoles([$role]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->fresh(['roles', 'defaultLocation']),
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('users.delete')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete your own account',
            ], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Assign schedule to user.
     */
    public function assignSchedule(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('users.update')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $schedule = UserSchedule::create([
            'user_id' => $user->id,
            'shift_id' => $validated['shift_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule assigned successfully',
            'data' => $schedule->load('shift'),
        ], 201);
    }

    /**
     * Get user's schedules.
     */
    public function schedules(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('users.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $schedules = UserSchedule::where('user_id', $user->id)
            ->with('shift')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    /**
     * Remove a schedule from user.
     */
    public function removeSchedule(Request $request, User $user, UserSchedule $schedule): JsonResponse
    {
        if (!$request->user()->can('users.update')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if ($schedule->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Schedule does not belong to this user',
            ], 400);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule removed successfully',
        ]);
    }
}
