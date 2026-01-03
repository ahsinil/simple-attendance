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
    /**
     * Get all permissions grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.roles.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $permissions = Permission::all(['id', 'name'])->groupBy(function ($permission) {
            // Group by category, handling "admin" prefix
            $parts = explode('.', $permission->name);
            if ($parts[0] === 'admin') {
                return $parts[1] ?? 'other';
            }
            return $parts[0] ?? 'other';
        })->map(function ($group, $category) {
            // Sort: admin permissions first (View All), then employee permissions (View Own)
            $sorted = $group->sortBy(function ($permission) {
                return str_starts_with($permission->name, 'admin.') ? 0 : 1;
            });

            return [
                'category' => $category,
                'label' => $this->getCategoryLabel($category),
                'permissions' => $sorted->map(function ($permission) {
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
            'dashboard' => 'Dashboard',
            'attendance' => 'Attendance',
            'history' => 'History',
            'requests' => 'Requests',
            'leaves' => 'Leaves',
            'schedules' => 'Schedules',
            'users' => 'User Management',
            'rights' => 'Access Rights',
            'shifts' => 'Shift Management',
            'leave-types' => 'Leave Types',
            'locations' => 'Location Management',
            'reports' => 'Reports',
            'settings' => 'Settings',
            'barcode' => 'Barcode Display',
            'roles' => 'Role Management',
            default => ucfirst(str_replace('-', ' ', $category)),
        };
    }

    /**
     * Get human-readable label for permission.
     */
    protected function getPermissionLabel(string $permission): string
    {
        $parts = explode('.', $permission);
        $action = end($parts);
        $isAdmin = $parts[0] === 'admin';

        // Special cases
        if (str_contains($permission, 'dashboard')) {
            return $isAdmin ? 'Admin Panel' : 'Employee Portal';
        }

        return match ($action) {
            'view' => $isAdmin ? 'View All' : 'View Own',
            'view-own' => 'View Own',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'export' => 'Export',
            'display' => 'Display Access',
            default => ucfirst(str_replace('-', ' ', $action)),
        };
    }
}
