<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Get all permissions grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $permissions = Permission::all(['id', 'name'])->groupBy(function ($permission) {
            // Group by prefix (e.g., "users.view" -> "users")
            $parts = explode('.', $permission->name);
            return $parts[0] ?? 'other';
        })->map(function ($group, $category) {
            return [
                'category' => $category,
                'label' => $this->getCategoryLabel($category),
                'permissions' => $group->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $this->getPermissionLabel($permission->name),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Get human-readable label for permission category.
     */
    protected function getCategoryLabel(string $category): string
    {
        return match ($category) {
            'attendance' => 'Attendance',
            'users' => 'User Management',
            'shifts' => 'Shift Management',
            'locations' => 'Location Management',
            'reports' => 'Reports',
            'settings' => 'Settings',
            'barcode' => 'Barcode Display',
            'roles' => 'Role Management',
            default => ucfirst($category),
        };
    }

    /**
     * Get human-readable label for permission.
     */
    protected function getPermissionLabel(string $permission): string
    {
        $parts = explode('.', $permission);
        $action = $parts[1] ?? $permission;

        return match ($action) {
            'view' => 'View',
            'view-own' => 'View Own',
            'view-all' => 'View All',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'manual-request' => 'Manual Request',
            'approve-request' => 'Approve Request',
            'reject-request' => 'Reject Request',
            'override' => 'Override',
            'export' => 'Export',
            'display' => 'Display',
            default => ucfirst(str_replace('-', ' ', $action)),
        };
    }
}
