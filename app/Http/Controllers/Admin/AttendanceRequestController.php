<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRequest;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceRequestController extends Controller
{
    protected AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Get all pending attendance requests.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:PENDING,APPROVED,REJECTED',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();

        if (!$user->can('attendance.approve-request')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $perPage = $request->input('per_page', 15);
        $status = $request->input('status', 'PENDING');

        $requests = AttendanceRequest::with(['user', 'location', 'reviewer'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Get a specific attendance request.
     */
    public function show(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        if (!$request->user()->can('attendance.approve-request')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $attendanceRequest->load(['user', 'location', 'reviewer']);

        return response()->json([
            'success' => true,
            'data' => $attendanceRequest,
        ]);
    }

    /**
     * Approve an attendance request.
     */
    public function approve(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('attendance.approve-request')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        if ($attendanceRequest->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'error' => 'Request has already been processed',
            ], 400);
        }

        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $attendance = $this->attendanceService->approveRequest(
            $attendanceRequest,
            $user,
            $request->input('admin_note')
        );

        return response()->json([
            'success' => true,
            'message' => 'Request approved successfully',
            'data' => [
                'request' => $attendanceRequest->fresh(['user', 'location', 'reviewer']),
                'attendance' => $attendance,
            ],
        ]);
    }

    /**
     * Reject an attendance request.
     */
    public function reject(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('attendance.reject-request')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        if ($attendanceRequest->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'error' => 'Request has already been processed',
            ], 400);
        }

        $request->validate([
            'admin_note' => 'required|string|min:5|max:500',
        ]);

        $attendanceRequest = $this->attendanceService->rejectRequest(
            $attendanceRequest,
            $user,
            $request->input('admin_note')
        );

        return response()->json([
            'success' => true,
            'message' => 'Request rejected',
            'data' => $attendanceRequest->fresh(['user', 'location', 'reviewer']),
        ]);
    }

    /**
     * Get request statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        if (!$request->user()->can('attendance.approve-request')) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $stats = [
            'pending' => AttendanceRequest::where('status', 'PENDING')->count(),
            'approved_today' => AttendanceRequest::where('status', 'APPROVED')
                ->whereDate('reviewed_at', today())
                ->count(),
            'rejected_today' => AttendanceRequest::where('status', 'REJECTED')
                ->whereDate('reviewed_at', today())
                ->count(),
            'total_today' => AttendanceRequest::whereDate('created_at', today())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
