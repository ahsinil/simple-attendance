<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login and get API token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active.'],
            ]);
        }

        $deviceName = $request->device_name ?? $request->userAgent() ?? 'Unknown Device';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout from all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices',
        ]);
    }

    /**
     * Get current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles', 'defaultLocation']);

        return response()->json([
            'success' => true,
            'data' => $this->formatUser($user),
        ]);
    }

    /**
     * Format user for API response.
     */
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'phone' => $user->phone,
            'department' => $user->department,
            'position' => $user->position,
            'status' => $user->status,
            'avatar' => $user->avatar,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'default_location' => $user->defaultLocation ? [
                'id' => $user->defaultLocation->id,
                'code' => $user->defaultLocation->code,
                'name' => $user->defaultLocation->name,
            ] : null,
        ];
    }
    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $this->formatUser($user),
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get user active devices (tokens).
     */
    public function devices(Request $request): JsonResponse
    {
        $currentAccessToken = $request->user()->currentAccessToken();
        
        $devices = $request->user()->tokens->map(function ($token) use ($currentAccessToken) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never',
                'is_current' => $token->id === $currentAccessToken->id,
                'created_at' => $token->created_at->format('M d, Y'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    /**
     * Get user's registered devices (for attendance).
     */
    public function myDevices(Request $request): JsonResponse
    {
        $deviceService = app(\App\Services\DeviceService::class);
        $devices = $deviceService->getUserDevices($request->user());

        // Get device fingerprint from current request for comparison
        $currentFingerprint = $deviceService->generateFingerprint($request);

        $formattedDevices = $devices->map(function ($device) use ($currentFingerprint) {
            $deviceInfo = $device->device_info ?? [];
            return [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'platform' => $deviceInfo['platform'] ?? 'Unknown',
                'browser' => $deviceInfo['browser'] ?? 'Unknown',
                'is_approved' => $device->is_approved,
                'is_current' => $device->device_fingerprint === $currentFingerprint,
                'registered_at' => $device->registered_at?->format('M d, Y'),
                'last_used_at' => $device->last_used_at?->diffForHumans() ?? 'Never',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'devices' => $formattedDevices,
                'settings' => [
                    'device_registration_enabled' => $deviceService->isDeviceRegistrationEnabled(),
                    'max_devices_per_user' => $deviceService->getMaxDevicesPerUser(),
                ],
            ],
        ]);
    }

    /**
     * Register the current device for attendance.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $request->validate([
            'device_name' => 'nullable|string|max:100',
            'screen_resolution' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
            'canvas_fingerprint' => 'nullable|string|max:64',
        ]);

        $deviceService = app(\App\Services\DeviceService::class);

        // Check if device registration is enabled
        if (!$deviceService->isDeviceRegistrationEnabled()) {
            return response()->json([
                'success' => false,
                'error' => 'Device registration is not enabled',
            ], 400);
        }

        $user = $request->user();
        $fingerprint = $deviceService->generateFingerprint($request);
        $deviceInfo = $deviceService->getDeviceInfo($request);

        try {
            $autoApprove = $deviceService->isAutoApproveEnabled();
            $device = $deviceService->registerDevice(
                $user,
                $fingerprint,
                $deviceInfo,
                $request->input('device_name'),
                $autoApprove
            );

            $message = $autoApprove 
                ? 'Device registered successfully' 
                : 'Device registered and pending admin approval';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'is_approved' => $device->is_approved,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Rename a registered device.
     */
    public function renameDevice(Request $request, \App\Models\Device $device): JsonResponse
    {
        // Ensure user owns the device
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'device_name' => 'required|string|max:100',
        ]);

        $device->update(['device_name' => $request->input('device_name')]);

        return response()->json([
            'success' => true,
            'message' => 'Device renamed successfully',
            'data' => $device->fresh(),
        ]);
    }

    /**
     * Remove a registered device.
     */
    public function removeDevice(Request $request, \App\Models\Device $device): JsonResponse
    {
        // Ensure user owns the device
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device removed successfully',
        ]);
    }
}

