<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceRequest;
use App\Models\Holiday;
use App\Models\LatePenaltyTier;
use App\Models\Location;
use App\Models\User;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    protected GpsService $gpsService;
    protected BarcodeService $barcodeService;

    public function __construct(GpsService $gpsService, BarcodeService $barcodeService)
    {
        $this->gpsService = $gpsService;
        $this->barcodeService = $barcodeService;
    }

    /**
     * Process attendance scan (check-in or check-out).
     */
    public function processScan(
        User $user,
        string $barcodeData,
        float $gpsLat,
        float $gpsLng,
        ?float $gpsAccuracy = null,
        ?string $deviceId = null,
        ?string $ipAddress = null
    ): array {
        // Validate GPS coordinates
        if (!$this->gpsService->validateCoordinates($gpsLat, $gpsLng)) {
            return $this->failResponse('Invalid GPS coordinates');
        }

        // Validate barcode
        $barcodeResult = $this->barcodeService->validateBarcode($barcodeData);
        
        if (!$barcodeResult['valid']) {
            return $this->failResponse($barcodeResult['error'] ?? 'Invalid barcode');
        }

        // Get location
        $location = Location::find($barcodeResult['location_id']);
        
        if (!$location) {
            return $this->failResponse('Location not found');
        }

        // Validate GPS against location
        $gpsResult = $this->gpsService->validateLocation(
            $gpsLat,
            $gpsLng,
            (float) $location->latitude,
            (float) $location->longitude,
            $location->allowed_radius_m,
            $gpsAccuracy
        );

        if (!$gpsResult['valid']) {
            return $this->failResponse($gpsResult['error'] ?? 'GPS validation failed', [
                'distance_m' => $gpsResult['distance_m'],
                'allowed_radius_m' => $gpsResult['allowed_radius_m'] ?? $location->allowed_radius_m,
            ]);
        }

        // Determine check type (IN or OUT)
        $checkType = $this->determineCheckType($user, $location);
        
        // Process the attendance
        return $this->recordAttendance(
            $user,
            $location,
            $checkType,
            $barcodeResult['time_slot'],
            $gpsLat,
            $gpsLng,
            $gpsAccuracy,
            $gpsResult['distance_m'],
            $deviceId,
            $ipAddress
        );
    }

    /**
     * Determine if this is a check-in or check-out.
     */
    protected function determineCheckType(User $user, Location $location): string
    {
        // Get today's attendance for this user
        $lastAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('scan_time', today())
            ->orderBy('scan_time', 'desc')
            ->first();

        // If no attendance today, it's a check-in
        if (!$lastAttendance) {
            return 'IN';
        }

        // Alternate between IN and OUT
        return $lastAttendance->check_type === 'IN' ? 'OUT' : 'IN';
    }

    /**
     * Record the attendance.
     */
    protected function recordAttendance(
        User $user,
        Location $location,
        string $checkType,
        string $timeSlot,
        float $gpsLat,
        float $gpsLng,
        ?float $gpsAccuracy,
        float $distance,
        ?string $deviceId,
        ?string $ipAddress
    ): array {
        $now = now();
        
        // Get user's schedule for today
        $schedule = $this->getUserSchedule($user, $now->toDateString());
        
        // Calculate late/early status
        $statusData = $this->calculateStatus($checkType, $now, $schedule, $location->timezone);

        // Check if today is a holiday
        $holiday = $this->getHoliday($now->toDateString());
        $isHoliday = $holiday !== null;
        $overtimeMultiplier = $holiday ? (float) $holiday->overtime_multiplier : 1.0;

        // Calculate work minutes if checking out
        $workMinutes = 0;
        $overtimeMin = 0;
        
        if ($checkType === 'OUT') {
            $checkIn = Attendance::where('user_id', $user->id)
                ->whereDate('scan_time', today())
                ->where('check_type', 'IN')
                ->orderBy('scan_time', 'desc')
                ->first();
            
            if ($checkIn) {
                $workMinutes = $now->diffInMinutes($checkIn->scan_time);
                
                // Calculate overtime if applicable
                if ($schedule && $schedule->shift) {
                    $expectedMinutes = $this->calculateExpectedMinutes($schedule->shift);
                    $overtimeMin = max(0, $workMinutes - $expectedMinutes);
                }
            }
        }

        return DB::transaction(function () use (
            $user, $location, $checkType, $timeSlot, $gpsLat, $gpsLng,
            $gpsAccuracy, $distance, $deviceId, $ipAddress, $now,
            $statusData, $isHoliday, $overtimeMultiplier, $workMinutes, $overtimeMin
        ) {
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'scan_time' => $now,
                'check_type' => $checkType,
                'gps_lat' => $gpsLat,
                'gps_lng' => $gpsLng,
                'gps_accuracy_m' => $gpsAccuracy,
                'distance_m' => $distance,
                'time_slot' => $timeSlot,
                'ip_address' => $ipAddress,
                'device_id' => $deviceId,
                'status' => $statusData['status'],
                'late_min' => $statusData['late_min'],
                'early_leave_min' => $statusData['early_leave_min'],
                'work_minutes' => $workMinutes,
                'penalty_tier' => $statusData['penalty_tier'],
                'is_holiday' => $isHoliday,
                'overtime_min' => $overtimeMin,
                'overtime_multiplier' => $overtimeMultiplier,
                'method' => 'AUTO',
            ]);

            // Log the action
            AttendanceLog::log(
                $checkType === 'IN' ? 'AUTO_CHECKIN' : 'AUTO_CHECKOUT',
                $user->id,
                $attendance->id,
                null,
                $user->id,
                null,
                [
                    'location_code' => $location->code,
                    'distance_m' => $distance,
                    'gps_accuracy_m' => $gpsAccuracy,
                ]
            );

            return [
                'success' => true,
                'message' => $checkType === 'IN' ? 'Check-in successful' : 'Check-out successful',
                'attendance' => [
                    'id' => $attendance->id,
                    'check_type' => $checkType,
                    'scan_time' => $attendance->scan_time->toIso8601String(),
                    'location' => $location->name,
                    'status' => $statusData['status'],
                    'late_min' => $statusData['late_min'],
                    'work_minutes' => $workMinutes,
                ],
            ];
        });
    }

    /**
     * Get user's schedule for a specific date.
     */
    protected function getUserSchedule(User $user, string $date): ?UserSchedule
    {
        return UserSchedule::where('user_id', $user->id)
            ->activeOn($date)
            ->with('shift')
            ->first();
    }

    /**
     * Get holiday for a specific date.
     */
    protected function getHoliday(string $date): ?Holiday
    {
        return Holiday::whereDate('date', $date)->first();
    }

    /**
     * Calculate attendance status (late, early, on-time).
     */
    protected function calculateStatus(
        string $checkType,
        Carbon $scanTime,
        ?UserSchedule $schedule,
        string $timezone
    ): array {
        $status = 'ON_TIME';
        $lateMin = 0;
        $earlyLeaveMin = 0;
        $penaltyTier = 'NONE';

        if (!$schedule || !$schedule->shift) {
            return compact('status', 'lateMin', 'earlyLeaveMin', 'penaltyTier');
        }

        $shift = $schedule->shift;
        $localTime = $scanTime->copy()->setTimezone($timezone);
        $timeOnly = $localTime->format('H:i:s');

        if ($checkType === 'IN') {
            // Parse shift start time
            $shiftStart = Carbon::parse($shift->start_time)->format('H:i:s');
            $graceEnd = Carbon::parse($shift->start_time)
                ->addMinutes($shift->late_after_min)
                ->format('H:i:s');

            if ($timeOnly > $graceEnd) {
                $status = 'LATE';
                $shiftStartTime = Carbon::parse($localTime->format('Y-m-d') . ' ' . $shiftStart);
                $lateMin = $localTime->diffInMinutes($shiftStartTime);
                
                // Determine penalty tier
                $tier = LatePenaltyTier::findForMinutes($lateMin);
                $penaltyTier = $tier ? $tier->penalty_type : 'NONE';
            }
        } else {
            // Check-out logic
            $shiftEnd = Carbon::parse($shift->end_time)->format('H:i:s');
            
            if ($timeOnly < $shiftEnd && !$shift->allow_checkout_before_end) {
                $status = 'EARLY';
                $shiftEndTime = Carbon::parse($localTime->format('Y-m-d') . ' ' . $shiftEnd);
                $earlyLeaveMin = $shiftEndTime->diffInMinutes($localTime);
            }
        }

        return [
            'status' => $status,
            'late_min' => $lateMin,
            'early_leave_min' => $earlyLeaveMin,
            'penalty_tier' => $penaltyTier,
        ];
    }

    /**
     * Calculate expected work minutes from shift.
     */
    protected function calculateExpectedMinutes($shift): int
    {
        $start = Carbon::parse($shift->start_time);
        $end = Carbon::parse($shift->end_time);
        
        // Handle overnight shifts
        if ($end < $start) {
            $end->addDay();
        }
        
        return $start->diffInMinutes($end);
    }

    /**
     * Create a manual attendance request.
     */
    public function createManualRequest(
        User $user,
        int $locationId,
        string $checkType,
        Carbon $requestTime,
        string $reason,
        ?float $gpsLat = null,
        ?float $gpsLng = null,
        ?float $gpsAccuracy = null,
        ?string $photoPath = null,
        ?string $failureReason = null
    ): AttendanceRequest {
        $location = Location::findOrFail($locationId);
        
        $distance = null;
        if ($gpsLat !== null && $gpsLng !== null) {
            $distance = $this->gpsService->calculateDistance(
                $gpsLat,
                $gpsLng,
                (float) $location->latitude,
                (float) $location->longitude
            );
        }

        $request = AttendanceRequest::create([
            'user_id' => $user->id,
            'location_id' => $locationId,
            'request_time' => $requestTime,
            'check_type' => $checkType,
            'gps_lat' => $gpsLat,
            'gps_lng' => $gpsLng,
            'distance_m' => $distance,
            'gps_accuracy_m' => $gpsAccuracy,
            'reason' => $reason,
            'photo_path' => $photoPath,
            'failure_reason' => $failureReason,
            'status' => 'PENDING',
        ]);

        // Log the request
        AttendanceLog::log(
            'MANUAL_REQUEST',
            $user->id,
            null,
            $request->id,
            $user->id,
            $reason
        );

        return $request;
    }

    /**
     * Approve a manual attendance request.
     */
    public function approveRequest(
        AttendanceRequest $request,
        User $admin,
        ?string $adminNote = null
    ): Attendance {
        return DB::transaction(function () use ($request, $admin, $adminNote) {
            // Update request status
            $request->update([
                'status' => 'APPROVED',
                'admin_note' => $adminNote,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // Create attendance record
            $attendance = Attendance::create([
                'user_id' => $request->user_id,
                'location_id' => $request->location_id,
                'scan_time' => $request->request_time,
                'check_type' => $request->check_type,
                'gps_lat' => $request->gps_lat,
                'gps_lng' => $request->gps_lng,
                'gps_accuracy_m' => $request->gps_accuracy_m,
                'distance_m' => $request->distance_m,
                'status' => 'ON_TIME', // Manual approvals default to on-time
                'method' => 'MANUAL',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            // Log the approval
            AttendanceLog::log(
                'MANUAL_APPROVE',
                $request->user_id,
                $attendance->id,
                $request->id,
                $admin->id,
                $adminNote
            );

            return $attendance;
        });
    }

    /**
     * Reject a manual attendance request.
     */
    public function rejectRequest(
        AttendanceRequest $request,
        User $admin,
        string $adminNote
    ): AttendanceRequest {
        $request->update([
            'status' => 'REJECTED',
            'admin_note' => $adminNote,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Log the rejection
        AttendanceLog::log(
            'MANUAL_REJECT',
            $request->user_id,
            null,
            $request->id,
            $admin->id,
            $adminNote
        );

        return $request;
    }

    /**
     * Get today's attendance summary for a user.
     */
    public function getTodaySummary(User $user): array
    {
        $checkIn = Attendance::where('user_id', $user->id)
            ->whereDate('scan_time', today())
            ->where('check_type', 'IN')
            ->orderBy('scan_time', 'asc')
            ->first();

        $checkOut = Attendance::where('user_id', $user->id)
            ->whereDate('scan_time', today())
            ->where('check_type', 'OUT')
            ->orderBy('scan_time', 'desc')
            ->first();

        $schedule = $this->getUserSchedule($user, today()->toDateString());

        return [
            'has_checked_in' => $checkIn !== null,
            'has_checked_out' => $checkOut !== null,
            'check_in_time' => $checkIn?->scan_time?->toIso8601String(),
            'check_out_time' => $checkOut?->scan_time?->toIso8601String(),
            'status' => $checkIn?->status ?? null,
            'late_min' => $checkIn?->late_min ?? 0,
            'work_minutes' => $checkOut?->work_minutes ?? 0,
            'shift' => $schedule?->shift?->name ?? null,
        ];
    }

    /**
     * Create a failure response.
     */
    protected function failResponse(string $error, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'error' => $error,
        ], $extra);
    }
}
