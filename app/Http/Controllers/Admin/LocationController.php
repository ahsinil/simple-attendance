<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Get all locations.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('locations.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $locations = Location::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
        ]);
    }

    /**
     * Create a new location.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('locations.create')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:locations,code',
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'allowed_radius_m' => 'nullable|integer|min:10|max:5000',
            'timezone' => 'nullable|string|timezone',
            'is_active' => 'nullable|boolean',
        ]);

        $location = Location::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'allowed_radius_m' => $validated['allowed_radius_m'] ?? 100,
            'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location created successfully',
            'data' => $location,
        ], 201);
    }

    /**
     * Get a specific location.
     */
    public function show(Request $request, Location $location): JsonResponse
    {
        if (!$request->user()->can('locations.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $location->load(['users' => fn($q) => $q->limit(10)]);

        return response()->json([
            'success' => true,
            'data' => $location,
        ]);
    }

    /**
     * Update a location.
     */
    public function update(Request $request, Location $location): JsonResponse
    {
        if (!$request->user()->can('locations.update')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:locations,code,' . $location->id,
            'name' => 'sometimes|string|max:255',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'allowed_radius_m' => 'sometimes|integer|min:10|max:5000',
            'timezone' => 'sometimes|string|timezone',
            'is_active' => 'sometimes|boolean',
        ]);

        $location->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => $location->fresh(),
        ]);
    }

    /**
     * Delete a location.
     */
    public function destroy(Request $request, Location $location): JsonResponse
    {
        if (!$request->user()->can('locations.delete')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        // Check if location has attendances
        if ($location->attendances()->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete location with attendance records. Deactivate it instead.',
            ], 400);
        }

        $location->delete();

        return response()->json([
            'success' => true,
            'message' => 'Location deleted successfully',
        ]);
    }
}
