<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    /**
     * Get all leave types.
     */
    /**
     * Get all leave types.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.leave-types.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $types = LeaveType::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Create a new leave type.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.leave-types.create')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:leave_types,code',
            'default_days' => 'required|integer|min:0|max:365',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $leaveType = LeaveType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Leave type created successfully',
            'data' => $leaveType,
        ], 201);
    }

    /**
     * Get a specific leave type.
     */
    public function show(Request $request, LeaveType $leaveType): JsonResponse
    {
        if (!$request->user()->can('admin.leave-types.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $leaveType,
        ]);
    }

    /**
     * Update a leave type.
     */
    public function update(Request $request, LeaveType $leaveType): JsonResponse
    {
        if (!$request->user()->can('admin.leave-types.update')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'sometimes|required|string|max:20|unique:leave_types,code,' . $leaveType->id,
            'default_days' => 'sometimes|required|integer|min:0|max:365',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $leaveType->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Leave type updated successfully',
            'data' => $leaveType->fresh(),
        ]);
    }

    /**
     * Delete a leave type.
     */
    public function destroy(Request $request, LeaveType $leaveType): JsonResponse
    {
        if (!$request->user()->can('admin.leave-types.delete')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        // Check if there are any leave requests using this type
        if ($leaveType->leaveRequests()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete leave type with existing requests. Deactivate it instead.',
            ], 400);
        }

        $leaveType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave type deleted successfully',
        ]);
    }
}
