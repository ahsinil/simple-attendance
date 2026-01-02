<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * System roles that cannot be deleted or have their name changed.
     */
    protected array $systemRoles = ['super_admin', 'admin', 'supervisor', 'employee'];

    /**
     * Get all roles with their permissions.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $roles = Role::with('permissions:id,name')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => in_array($role->name, $this->systemRoles),
                'permissions' => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users()->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Create a new role.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('roles.create')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name|regex:/^[a-z_]+$/',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ], [
            'name.regex' => 'Role name must be lowercase letters and underscores only.',
        ]);

        // Create role with web guard (default) to match permission checks
        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        if (!empty($validated['permissions'])) {
            // Get permissions with matching guard
            $permissions = Permission::whereIn('name', $validated['permissions'])
                ->where('guard_name', 'web')
                ->pluck('name')
                ->toArray();
            $role->syncPermissions($permissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => false,
                'permissions' => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
                'users_count' => 0,
            ],
        ], 201);
    }

    /**
     * Get a specific role with its permissions.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $role->load('permissions:id,name');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => in_array($role->name, $this->systemRoles),
                'permissions' => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users()->count(),
            ],
        ]);
    }

    /**
     * Update a role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.update')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $isSystem = in_array($role->name, $this->systemRoles);

        $rules = [
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];

        // Only allow name change for non-system roles
        if (!$isSystem) {
            $rules['name'] = 'sometimes|string|max:50|unique:roles,name,' . $role->id . '|regex:/^[a-z_]+$/';
        }

        $validated = $request->validate($rules, [
            'name.regex' => 'Role name must be lowercase letters and underscores only.',
        ]);

        // Update name only for non-system roles
        if (!$isSystem && isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        // Update permissions
        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $role->refresh();
        $role->load('permissions:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $isSystem,
                'permissions' => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users()->count(),
            ],
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.delete')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        // Prevent deletion of system roles
        if (in_array($role->name, $this->systemRoles)) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete system role',
            ], 400);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete role that has assigned users',
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }
}
