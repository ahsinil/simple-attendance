<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveApprovalController extends Controller
{
    protected LeaveService $leaveService;

    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    /**
     * Get all leave requests.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.leaves.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'nullable|in:PENDING,APPROVED,REJECTED,CANCELLED',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 15);
        $status = $request->input('status', 'PENDING');

        $requests = LeaveRequest::with(['user', 'leaveType', 'reviewer'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($request->leave_type_id, fn($q) => $q->where('leave_type_id', $request->leave_type_id))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->start_date, fn($q) => $q->whereDate('start_date', '>=', $request->start_date))
            ->when($request->end_date, fn($q) => $q->whereDate('end_date', '<=', $request->end_date))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Get leave request statistics.
     */
    public function stats(): JsonResponse
    {
        if (!auth()->user()->can('admin.leaves.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $stats = [
            'pending' => LeaveRequest::where('status', 'PENDING')->count(),
            'approved_today' => LeaveRequest::where('status', 'APPROVED')
                ->whereDate('reviewed_at', today())
                ->count(),
            'rejected_today' => LeaveRequest::where('status', 'REJECTED')
                ->whereDate('reviewed_at', today())
                ->count(),
            'total_this_month' => LeaveRequest::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Approve a leave request.
     */
    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if (!$request->user()->can('admin.leaves.approve')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        if ($leaveRequest->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'error' => 'Request has already been processed',
            ], 400);
        }

        $result = $this->leaveService->approveRequest(
            $leaveRequest,
            $request->user(),
            $request->input('admin_note')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request approved',
            'data' => $result['data'],
        ]);
    }

    /**
     * Reject a leave request.
     */
    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if (!$request->user()->can('admin.leaves.reject')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'admin_note' => 'required|string|min:5|max:500',
        ]);

        if ($leaveRequest->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'error' => 'Request has already been processed',
            ], 400);
        }

        $result = $this->leaveService->rejectRequest(
            $leaveRequest,
            $request->user(),
            $request->input('admin_note')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request rejected',
            'data' => $result['data'],
        ]);
    }
}
