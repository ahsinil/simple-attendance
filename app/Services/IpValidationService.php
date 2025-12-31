<?php

namespace App\Services;

use App\Models\AppSetting;

class IpValidationService
{
    /**
     * Check if IP validation is enabled.
     */
    public function isEnabled(): bool
    {
        $setting = AppSetting::where('key', 'ip_whitelist_enabled')->first();
        return $setting && $setting->value === 'true';
    }

    /**
     * Get the list of allowed IPs/CIDRs.
     */
    public function getAllowedIps(): array
    {
        $setting = AppSetting::where('key', 'ip_whitelist')->first();
        if (!$setting || empty($setting->value)) {
            return [];
        }

        // Split by newlines or commas, trim whitespace, filter empty
        return array_filter(
            array_map('trim', preg_split('/[\n,]+/', $setting->value)),
            fn($ip) => !empty($ip)
        );
    }

    /**
     * Validate if an IP is allowed.
     */
    public function isAllowed(string $ip): bool
    {
        if (!$this->isEnabled()) {
            return true; // No whitelist enabled = all IPs allowed
        }

        $allowedIps = $this->getAllowedIps();
        
        if (empty($allowedIps)) {
            return true; // Empty whitelist = all IPs allowed
        }

        foreach ($allowedIps as $allowed) {
            if ($this->matchesIp($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP matches an allowed entry (IP or CIDR).
     */
    private function matchesIp(string $ip, string $allowed): bool
    {
        // Exact match
        if ($ip === $allowed) {
            return true;
        }

        // CIDR match
        if (strpos($allowed, '/') !== false) {
            return $this->cidrMatch($ip, $allowed);
        }

        return false;
    }

    /**
     * Check if IP falls within a CIDR range.
     */
    private function cidrMatch(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false; // Only IPv4 supported for now
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int) $mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
