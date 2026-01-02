<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    protected LeaveService $leaveService;

    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    /**
     * Get all active leave types.
     */
    public function types(): JsonResponse
    {
        $types = LeaveType::active()->get();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Get user's leave balances.
     */
    public function balances(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        $year = $request->input('year', now()->year);
        $balances = $this->leaveService->getUserBalances($request->user(), $year);

        return response()->json([
            'success' => true,
            'data' => $balances,
        ]);
    }

    /**
     * Get user's leave requests.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:PENDING,APPROVED,REJECTED,CANCELLED',
            'year' => 'nullable|integer|min:2020|max:2100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $year = $request->input('year', now()->year);

        $requests = LeaveRequest::with(['leaveType', 'reviewer'])
            ->where('user_id', $request->user()->id)
            ->whereYear('start_date', $year)
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Submit a new leave request.
     */
    public function submitRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10|max:500',
        ]);

        $result = $this->leaveService->submitRequest($request->user(), $validated);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'data' => $result['data'],
        ], 201);
    }

    /**
     * Cancel a pending leave request.
     */
    public function cancelRequest(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        // Ensure user owns this request
        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $result = $this->leaveService->cancelRequest($leaveRequest);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request cancelled',
            'data' => $result['data'],
        ]);
    }
}
