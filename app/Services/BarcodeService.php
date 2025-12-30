<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Location;
use Illuminate\Support\Facades\Cache;

class BarcodeService
{
    /**
     * Rotation interval in seconds (default: 5 minutes).
     */
    protected int $rotationSeconds;

    /**
     * Secret key for HMAC generation.
     */
    protected string $secretKey;

    /**
     * Tolerance for time slot validation (accept current and previous slot).
     */
    protected int $tolerance = 1;

    public function __construct()
    {
        $this->rotationSeconds = (int) config('attendance.barcode_rotation_seconds', 300);
        $this->secretKey = config('attendance.barcode_secret_key', config('app.key'));
    }

    /**
     * Get the current time slot based on rotation interval.
     */
    public function getCurrentTimeSlot(): string
    {
        $timestamp = now()->timestamp;
        $slot = floor($timestamp / $this->rotationSeconds);
        
        return (string) $slot;
    }

    /**
     * Get time slot for a specific number of intervals ago.
     */
    public function getTimeSlot(int $intervalsAgo = 0): string
    {
        $timestamp = now()->timestamp;
        $slot = floor($timestamp / $this->rotationSeconds) - $intervalsAgo;
        
        return (string) $slot;
    }

    /**
     * Generate barcode data for a location.
     */
    public function generateBarcode(Location $location): array
    {
        $timeSlot = $this->getCurrentTimeSlot();
        $payload = $this->createPayload($location->code, $timeSlot);
        $signature = $this->sign($payload);

        // Calculate when this barcode expires
        $currentSlotStart = (int) $timeSlot * $this->rotationSeconds;
        $expiresAt = $currentSlotStart + $this->rotationSeconds;
        $secondsRemaining = $expiresAt - now()->timestamp;

        return [
            'barcode_data' => $this->encodeBarcode($payload, $signature),
            'location_code' => $location->code,
            'location_name' => $location->name,
            'time_slot' => $timeSlot,
            'generated_at' => now()->toIso8601String(),
            'expires_in_seconds' => $secondsRemaining,
            'rotation_interval' => $this->rotationSeconds,
        ];
    }

    /**
     * Create the payload string for signing.
     */
    protected function createPayload(string $locationCode, string $timeSlot): string
    {
        return "{$locationCode}:{$timeSlot}";
    }

    /**
     * Sign the payload using HMAC-SHA256.
     */
    protected function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secretKey);
    }

    /**
     * Encode barcode data for QR/barcode generation.
     */
    protected function encodeBarcode(string $payload, string $signature): string
    {
        // Format: base64(payload|signature)
        // Short signature (first 16 chars) for smaller QR code
        $shortSignature = substr($signature, 0, 16);
        $data = "{$payload}|{$shortSignature}";
        
        return base64_encode($data);
    }

    /**
     * Decode and validate a scanned barcode.
     * 
     * @return array{valid: bool, location_code?: string, time_slot?: string, error?: string}
     */
    public function validateBarcode(string $barcodeData): array
    {
        try {
            $decoded = base64_decode($barcodeData, true);
            
            if ($decoded === false) {
                return ['valid' => false, 'error' => 'Invalid barcode format'];
            }

            $parts = explode('|', $decoded);
            
            if (count($parts) !== 2) {
                return ['valid' => false, 'error' => 'Invalid barcode structure'];
            }

            [$payload, $providedSignature] = $parts;
            $payloadParts = explode(':', $payload);
            
            if (count($payloadParts) !== 2) {
                return ['valid' => false, 'error' => 'Invalid payload format'];
            }

            [$locationCode, $timeSlot] = $payloadParts;

            // Verify signature
            $expectedSignature = substr($this->sign($payload), 0, 16);
            
            if (!hash_equals($expectedSignature, $providedSignature)) {
                return ['valid' => false, 'error' => 'Invalid signature'];
            }

            // Validate time slot (accept current and previous slot for tolerance)
            $validSlots = [];
            for ($i = 0; $i <= $this->tolerance; $i++) {
                $validSlots[] = $this->getTimeSlot($i);
            }

            if (!in_array($timeSlot, $validSlots, true)) {
                return ['valid' => false, 'error' => 'Barcode expired'];
            }

            // Verify location exists
            $location = Location::where('code', $locationCode)->where('is_active', true)->first();
            
            if (!$location) {
                return ['valid' => false, 'error' => 'Invalid or inactive location'];
            }

            return [
                'valid' => true,
                'location_code' => $locationCode,
                'location_id' => $location->id,
                'time_slot' => $timeSlot,
            ];

        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Barcode validation failed'];
        }
    }

    /**
     * Get cached barcode for a location, or generate new one.
     */
    public function getCachedBarcode(Location $location): array
    {
        $cacheKey = "barcode:{$location->code}:{$this->getCurrentTimeSlot()}";
        
        return Cache::remember($cacheKey, $this->rotationSeconds, function () use ($location) {
            return $this->generateBarcode($location);
        });
    }

    /**
     * Get rotation interval in seconds.
     */
    public function getRotationInterval(): int
    {
        return $this->rotationSeconds;
    }
}
