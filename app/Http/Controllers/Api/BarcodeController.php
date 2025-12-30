<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    protected BarcodeService $barcodeService;

    public function __construct(BarcodeService $barcodeService)
    {
        $this->barcodeService = $barcodeService;
    }

    /**
     * Get current barcode for a location (for display on office screen).
     */
    public function show(Request $request, Location $location): JsonResponse
    {
        // Check if user has permission to display barcode
        if (!$request->user()->can('barcode.display')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to display barcode',
            ], 403);
        }

        if (!$location->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Location is not active',
            ], 400);
        }

        $barcode = $this->barcodeService->getCachedBarcode($location);

        return response()->json([
            'success' => true,
            'data' => $barcode,
        ]);
    }

    /**
     * Get all active locations with their current barcodes.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('barcode.display')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $locations = Location::where('is_active', true)->get();

        $barcodes = $locations->map(function ($location) {
            return $this->barcodeService->getCachedBarcode($location);
        });

        return response()->json([
            'success' => true,
            'data' => $barcodes,
        ]);
    }

    /**
     * Get barcode rotation info (for client to know when to refresh).
     */
    public function info(): JsonResponse
    {
        $rotationSeconds = $this->barcodeService->getRotationInterval();
        $currentSlot = $this->barcodeService->getCurrentTimeSlot();
        
        // Calculate seconds until next rotation
        $currentSlotStart = (int) $currentSlot * $rotationSeconds;
        $nextRotation = $currentSlotStart + $rotationSeconds;
        $secondsUntilRotation = $nextRotation - now()->timestamp;

        return response()->json([
            'success' => true,
            'data' => [
                'rotation_interval_seconds' => $rotationSeconds,
                'current_time_slot' => $currentSlot,
                'seconds_until_rotation' => $secondsUntilRotation,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
