<?php

namespace App\Services;

class GpsService
{
    /**
     * Earth radius in meters.
     */
    protected const EARTH_RADIUS_M = 6371000;

    /**
     * Maximum allowed GPS accuracy in meters.
     */
    protected int $maxAccuracyMeters;

    public function __construct()
    {
        $this->maxAccuracyMeters = (int) config('attendance.gps_max_accuracy_meters', 100);
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula.
     * 
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @return float Distance in meters
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Convert to radians
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        // Haversine formula
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLng / 2) * sin($deltaLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * Validate GPS coordinates against a location.
     * 
     * @param float $userLat User's latitude
     * @param float $userLng User's longitude
     * @param float $locationLat Location's latitude
     * @param float $locationLng Location's longitude
     * @param int $allowedRadiusM Allowed radius in meters
     * @param float|null $accuracy GPS accuracy reported by device
     * @return array{valid: bool, distance_m: float, error?: string}
     */
    public function validateLocation(
        float $userLat,
        float $userLng,
        float $locationLat,
        float $locationLng,
        int $allowedRadiusM,
        ?float $accuracy = null
    ): array {
        // Check GPS accuracy first
        if ($accuracy !== null && $accuracy > $this->maxAccuracyMeters) {
            return [
                'valid' => false,
                'distance_m' => 0,
                'accuracy_m' => $accuracy,
                'error' => "GPS accuracy too low ({$accuracy}m > {$this->maxAccuracyMeters}m max)",
            ];
        }

        // Calculate distance
        $distance = $this->calculateDistance($userLat, $userLng, $locationLat, $locationLng);
        $roundedDistance = round($distance, 2);

        // Validate against allowed radius
        if ($distance > $allowedRadiusM) {
            return [
                'valid' => false,
                'distance_m' => $roundedDistance,
                'allowed_radius_m' => $allowedRadiusM,
                'accuracy_m' => $accuracy,
                'error' => "Outside allowed area ({$roundedDistance}m from location, max {$allowedRadiusM}m)",
            ];
        }

        return [
            'valid' => true,
            'distance_m' => $roundedDistance,
            'allowed_radius_m' => $allowedRadiusM,
            'accuracy_m' => $accuracy,
        ];
    }

    /**
     * Validate GPS coordinates are reasonable (not null island, etc).
     */
    public function validateCoordinates(float $lat, float $lng): bool
    {
        // Check valid ranges
        if ($lat < -90 || $lat > 90) {
            return false;
        }

        if ($lng < -180 || $lng > 180) {
            return false;
        }

        // Check for null island (0, 0) - common GPS error
        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
            return false;
        }

        return true;
    }

    /**
     * Get the maximum allowed GPS accuracy.
     */
    public function getMaxAccuracy(): int
    {
        return $this->maxAccuracyMeters;
    }

    /**
     * Format distance for display.
     */
    public function formatDistance(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters) . 'm';
        }

        return round($meters / 1000, 2) . 'km';
    }
}
