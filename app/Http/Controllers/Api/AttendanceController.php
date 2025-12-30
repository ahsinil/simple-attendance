<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Location;
use App\Services\AttendanceService;
use App\Services\DeviceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;
    protected DeviceService $deviceService;

    public function __construct(
        AttendanceService $attendanceService,
        DeviceService $deviceService
    ) {
        $this->attendanceService = $attendanceService;
        $this->deviceService = $deviceService;
    }

    /**
     * Submit attendance scan.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string',
            'gps_lat' => 'required|numeric|between:-90,90',
            'gps_lng' => 'required|numeric|between:-180,180',
            'gps_accuracy' => 'nullable|numeric|min:0',
            'device_fingerprint' => 'nullable|string',
        ]);

        $user = $request->user();

        // Validate device if device registration is enabled
        $deviceFingerprint = $request->input('device_fingerprint');
        if ($deviceFingerprint) {
            $deviceResult = $this->deviceService->validateDevice($user, $deviceFingerprint);
            
            if (!$deviceResult['valid']) {
                return response()->json([
                    'success' => false,
                    'error' => $deviceResult['error'],
                    'needs_registration' => $deviceResult['needs_registration'] ?? false,
                ], 400);
            }
        }

        // Process the scan
        $result = $this->attendanceService->processScan(
            $user,
            $request->input('barcode'),
            (float) $request->input('gps_lat'),
            (float) $request->input('gps_lng'),
            $request->input('gps_accuracy') ? (float) $request->input('gps_accuracy') : null,
            $deviceFingerprint,
            $request->ip()
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Get today's attendance summary for current user.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->attendanceService->getTodaySummary($user);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get attendance history for current user.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $perPage = $request->input('per_page', 15);

        $attendances = Attendance::where('user_id', $user->id)
            ->whereDate('scan_time', '>=', $startDate)
            ->whereDate('scan_time', '<=', $endDate)
            ->with('location')
            ->orderBy('scan_time', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $attendances,
        ]);
    }

    /**
     * Get monthly summary for current user.
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2020',
        ]);

        $user = $request->user();
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereDate('scan_time', '>=', $startDate)
            ->whereDate('scan_time', '<=', $endDate)
            ->get();

        $summary = [
            'month' => $month,
            'year' => $year,
            'total_days' => $endDate->day,
            'present_days' => $attendances->where('check_type', 'IN')
                ->whereIn('status', ['ON_TIME', 'LATE', 'EARLY'])
                ->groupBy(fn($a) => $a->scan_time->toDateString())
                ->count(),
            'absent_days' => $attendances->where('status', 'ABSENT')->count(),
            'late_days' => $attendances->where('status', 'LATE')->count(),
            'total_work_minutes' => $attendances->where('check_type', 'OUT')->sum('work_minutes'),
            'total_overtime_minutes' => $attendances->sum('overtime_min'),
            'total_late_minutes' => $attendances->sum('late_min'),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Submit manual attendance request.
     */
    public function manualRequest(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'check_type' => 'required|in:IN,OUT',
            'request_time' => 'required|date',
            'reason' => 'required|string|min:10|max:500',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_lng' => 'nullable|numeric|between:-180,180',
            'gps_accuracy' => 'nullable|numeric|min:0',
            'photo' => 'nullable|image|max:5120', // 5MB max
            'failure_reason' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        // Check if user can make manual requests
        if (!$user->can('attendance.manual-request')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to make manual requests',
            ], 403);
        }

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('attendance-photos', 'public');
        }

        $attendanceRequest = $this->attendanceService->createManualRequest(
            $user,
            $request->input('location_id'),
            $request->input('check_type'),
            Carbon::parse($request->input('request_time')),
            $request->input('reason'),
            $request->input('gps_lat') ? (float) $request->input('gps_lat') : null,
            $request->input('gps_lng') ? (float) $request->input('gps_lng') : null,
            $request->input('gps_accuracy') ? (float) $request->input('gps_accuracy') : null,
            $photoPath,
            $request->input('failure_reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Manual attendance request submitted',
            'data' => $attendanceRequest,
        ], 201);
    }

    /**
     * Get user's manual requests.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:PENDING,APPROVED,REJECTED',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $perPage = $request->input('per_page', 15);

        $query = AttendanceRequest::where('user_id', $user->id)
            ->with(['location', 'reviewer']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Get available locations for attendance.
     */
    public function locations(): JsonResponse
    {
        $locations = Location::where('is_active', true)
            ->select('id', 'code', 'name', 'latitude', 'longitude', 'allowed_radius_m', 'timezone')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
        ]);
    }
}
