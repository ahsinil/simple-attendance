<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * Get all shifts.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.shifts.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $shifts = Shift::orderBy('start_time')->get();

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }

    /**
     * Create a new shift.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.shifts.create')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:shifts,code',
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'late_after_min' => 'nullable|integer|min:0|max:120',
            'early_checkout_min' => 'nullable|integer|min:0|max:120',
            'allow_checkout_before_end' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $shift = Shift::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'late_after_min' => $validated['late_after_min'] ?? 15,
            'early_checkout_min' => $validated['early_checkout_min'] ?? 0,
            'allow_checkout_before_end' => $validated['allow_checkout_before_end'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shift created successfully',
            'data' => $shift,
        ], 201);
    }

    /**
     * Get a specific shift.
     */
    public function show(Request $request, Shift $shift): JsonResponse
    {
        if (!$request->user()->can('admin.shifts.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $shift,
        ]);
    }

    /**
     * Update a shift.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        if (!$request->user()->can('admin.shifts.update')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:shifts,code,' . $shift->id,
            'name' => 'sometimes|string|max:255',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'late_after_min' => 'sometimes|integer|min:0|max:120',
            'early_checkout_min' => 'sometimes|integer|min:0|max:120',
            'allow_checkout_before_end' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shift updated successfully',
            'data' => $shift->fresh(),
        ]);
    }

    /**
     * Delete a shift.
     */
    public function destroy(Request $request, Shift $shift): JsonResponse
    {
        if (!$request->user()->can('admin.shifts.delete')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        // Check if shift is in use
        if ($shift->userSchedules()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete shift that is assigned to users',
            ], 400);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift deleted successfully',
        ]);
    }
}
