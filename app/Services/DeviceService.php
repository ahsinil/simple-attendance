<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

class DeviceService
{
    /**
     * Check if device registration is enabled.
     */
    public function isDeviceRegistrationEnabled(): bool
    {
        return AppSetting::get('device_registration_enabled', false);
    }

    /**
     * Get max devices per user.
     */
    public function getMaxDevicesPerUser(): int
    {
        return AppSetting::get('max_devices_per_user', 2);
    }

    /**
     * Generate device fingerprint from request.
     */
    public function generateFingerprint(Request $request): string
    {
        $components = [
            $request->userAgent() ?? '',
            $request->header('Accept-Language', ''),
            $request->input('screen_resolution', ''),
            $request->input('timezone', ''),
            $request->input('canvas_fingerprint', ''),
        ];

        $data = implode('|', $components);
        
        return hash('sha256', $data);
    }

    /**
     * Get device info from request.
     */
    public function getDeviceInfo(Request $request): array
    {
        return [
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('Accept-Language'),
            'screen_resolution' => $request->input('screen_resolution'),
            'timezone' => $request->input('timezone'),
            'platform' => $this->parsePlatform($request->userAgent()),
            'browser' => $this->parseBrowser($request->userAgent()),
        ];
    }

    /**
     * Parse platform from user agent.
     */
    protected function parsePlatform(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown';
        }

        $patterns = [
            'Windows' => '/Windows NT/i',
            'macOS' => '/Macintosh/i',
            'Linux' => '/Linux/i',
            'iOS' => '/iPhone|iPad|iPod/i',
            'Android' => '/Android/i',
        ];

        foreach ($patterns as $platform => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $platform;
            }
        }

        return 'Unknown';
    }

    /**
     * Parse browser from user agent.
     */
    protected function parseBrowser(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown';
        }

        $patterns = [
            'Chrome' => '/Chrome\/[\d.]+/i',
            'Firefox' => '/Firefox\/[\d.]+/i',
            'Safari' => '/Safari\/[\d.]+/i',
            'Edge' => '/Edg\/[\d.]+/i',
            'Opera' => '/OPR\/[\d.]+/i',
        ];

        foreach ($patterns as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                // Special case: Chrome user agent also contains Safari
                if ($browser === 'Safari' && preg_match('/Chrome/i', $userAgent)) {
                    continue;
                }
                return $browser;
            }
        }

        return 'Unknown';
    }

    /**
     * Register a device for a user.
     */
    public function registerDevice(
        User $user,
        string $fingerprint,
        array $deviceInfo,
        ?string $deviceName = null,
        bool $autoApprove = false
    ): Device {
        // Check if device already exists
        $existing = Device::where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->first();

        if ($existing) {
            $existing->update([
                'device_info' => $deviceInfo,
                'device_name' => $deviceName ?? $existing->device_name,
                'last_used_at' => now(),
            ]);
            return $existing;
        }

        // Check device limit
        $deviceCount = Device::where('user_id', $user->id)->count();
        $maxDevices = $this->getMaxDevicesPerUser();

        if ($deviceCount >= $maxDevices) {
            throw new \Exception("Maximum devices ({$maxDevices}) reached for this user");
        }

        return Device::create([
            'user_id' => $user->id,
            'device_fingerprint' => $fingerprint,
            'device_name' => $deviceName ?? $this->generateDeviceName($deviceInfo),
            'device_info' => $deviceInfo,
            'is_approved' => $autoApprove,
            'registered_at' => now(),
        ]);
    }

    /**
     * Generate a device name from device info.
     */
    protected function generateDeviceName(array $deviceInfo): string
    {
        $platform = $deviceInfo['platform'] ?? 'Unknown';
        $browser = $deviceInfo['browser'] ?? 'Unknown';
        
        return "{$platform} - {$browser}";
    }

    /**
     * Validate device for attendance.
     */
    public function validateDevice(User $user, string $fingerprint): array
    {
        // If device registration is not enabled, always pass
        if (!$this->isDeviceRegistrationEnabled()) {
            return ['valid' => true, 'device_id' => $fingerprint];
        }

        $device = Device::where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->first();

        if (!$device) {
            return [
                'valid' => false,
                'error' => 'Device not registered',
                'needs_registration' => true,
            ];
        }

        if (!$device->is_approved) {
            return [
                'valid' => false,
                'error' => 'Device pending approval',
                'device_id' => $device->id,
            ];
        }

        // Update last used
        $device->update(['last_used_at' => now()]);

        return [
            'valid' => true,
            'device_id' => $device->device_fingerprint,
        ];
    }

    /**
     * Get user's registered devices.
     */
    public function getUserDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Device::where('user_id', $user->id)
            ->orderBy('last_used_at', 'desc')
            ->get();
    }

    /**
     * Remove a device.
     */
    public function removeDevice(Device $device): bool
    {
        return $device->delete();
    }

    /**
     * Approve a device.
     */
    public function approveDevice(Device $device): Device
    {
        $device->update(['is_approved' => true]);
        return $device;
    }
}
