<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get comprehensive dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.dashboard.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'realtime' => $this->reportService->getRealtimeStats(),
                'monthly' => $this->reportService->getMonthlyStats(),
                'employee_insights' => $this->reportService->getEmployeeInsights(),
                'pending_requests' => $this->reportService->getPendingRequests(),
                'recent_activity' => $this->reportService->getRecentActivity(),
            ],
        ]);
    }
}
