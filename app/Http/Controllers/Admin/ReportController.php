<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Get attendance report data.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.reports.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_id' => 'nullable|exists:locations,id',
            'status' => 'nullable|in:ON_TIME,LATE,EARLY,ABSENT,EXCUSED',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $perPage = $request->input('per_page', 20);

        $query = Attendance::with(['user', 'location'])
            ->whereDate('scan_time', '>=', $startDate)
            ->whereDate('scan_time', '<=', $endDate)
            ->orderBy('scan_time', 'desc');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $attendances = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $attendances,
        ]);
    }

    /**
     * Get report summary/KPIs.
     */
    public function summary(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.reports.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $query = Attendance::whereDate('scan_time', '>=', $startDate)
            ->whereDate('scan_time', '<=', $endDate);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        $attendances = $query->get();

        // Calculate KPIs
        $checkIns = $attendances->where('check_type', 'IN');
        $presentCount = $checkIns->whereIn('status', ['ON_TIME', 'LATE', 'EARLY'])->count();
        $lateCount = $checkIns->where('status', 'LATE')->count();
        $manualCount = $attendances->where('method', 'MANUAL')->count();
        $avgLateMinutes = $checkIns->where('status', 'LATE')->avg('late_min') ?? 0;

        // Get unique users who checked in
        $uniqueUsers = $checkIns->pluck('user_id')->unique()->count();

        $summary = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'present_count' => $presentCount,
            'late_count' => $lateCount,
            'manual_overrides' => $manualCount,
            'avg_late_minutes' => round($avgLateMinutes),
            'unique_employees' => $uniqueUsers,
            'total_records' => $attendances->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Export attendance report to Excel.
     */
    public function export(Request $request)
    {
        if (!$request->user()->can('admin.reports.export')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $locationId = $request->input('location_id');

        $filename = 'attendance_report_' . $startDate . '_to_' . $endDate . '.xlsx';

        return Excel::download(
            new AttendanceExport($startDate, $endDate, $locationId),
            $filename
        );
    }


    /**
     * Get available locations for filter dropdown.
     */
    public function locations(): JsonResponse
    {
        $locations = Location::where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
        ]);
    }
}
