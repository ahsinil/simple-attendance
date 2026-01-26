<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    protected DeviceService $deviceService;

    public function __construct(DeviceService $deviceService)
    {
        $this->deviceService = $deviceService;
    }

    /**
     * Get all devices with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.devices.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'nullable|in:pending,approved,all',
            'user_id' => 'nullable|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Device::with('user:id,name,email,employee_id');

        // Filter by status
        $status = $request->input('status', 'all');
        if ($status === 'pending') {
            $query->where('is_approved', false);
        } elseif ($status === 'approved') {
            $query->where('is_approved', true);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $perPage = $request->input('per_page', 15);
        $devices = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    /**
     * Get device statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.devices.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $stats = [
            'total' => Device::count(),
            'pending' => Device::where('is_approved', false)->count(),
            'approved' => Device::where('is_approved', true)->count(),
            'users_with_devices' => Device::distinct('user_id')->count('user_id'),
            'settings' => [
                'device_registration_enabled' => $this->deviceService->isDeviceRegistrationEnabled(),
                'device_registration_mode' => $this->deviceService->getRegistrationMode(),
                'max_devices_per_user' => $this->deviceService->getMaxDevicesPerUser(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Approve a pending device.
     */
    public function approve(Request $request, Device $device): JsonResponse
    {
        if (!$request->user()->can('admin.devices.approve')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if ($device->is_approved) {
            return response()->json([
                'success' => false,
                'error' => 'Device is already approved',
            ], 400);
        }

        $this->deviceService->approveDevice($device);

        return response()->json([
            'success' => true,
            'message' => 'Device approved successfully',
            'data' => $device->fresh()->load('user:id,name,email'),
        ]);
    }

    /**
     * Reject a pending device (delete it).
     */
    public function reject(Request $request, Device $device): JsonResponse
    {
        if (!$request->user()->can('admin.devices.approve')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if ($device->is_approved) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot reject an already approved device. Use revoke instead.',
            ], 400);
        }

        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device rejected and removed',
        ]);
    }

    /**
     * Revoke an approved device.
     */
    public function revoke(Request $request, Device $device): JsonResponse
    {
        if (!$request->user()->can('admin.devices.delete')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device revoked successfully',
        ]);
    }

    /**
     * Get devices for a specific user.
     */
    public function userDevices(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('admin.devices.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $devices = $this->deviceService->getUserDevices($user);

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }
}
