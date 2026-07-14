<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Exports\EmployeeReportExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

use App\Services\ReportService;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

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

        $query = Attendance::with(['user.salaryComponents.salaryComponent', 'location'])
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
        $locationId = $request->input('location_id');

        $summary = $this->reportService->getReportSummary($startDate, $endDate, $locationId);

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
     * Get per-employee attendance report.
     */
    public function employeeReport(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.reports.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_id' => 'nullable|exists:locations,id',
            'user_id' => 'nullable|exists:users,id',
            'department' => 'nullable|string',
        ]);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $locationId = $request->input('location_id');
        $userId = $request->input('user_id');
        $department = $request->input('department');

        $data = $this->reportService->buildEmployeeReport($startDate, $endDate, $locationId, $userId, $department);

        // KPIs
        $totalEmployees = $data->count();
        $totalLate = $data->sum('late_count');
        $totalOtHours = round($data->sum('overtime_hours'), 1);
        $avgAttendanceRate = $totalEmployees > 0
            ? round($data->avg('attendance_rate'), 1)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'employees' => $data->values(),
                'kpis' => [
                    'total_employees' => $totalEmployees,
                    'total_late' => $totalLate,
                    'total_overtime_hours' => $totalOtHours,
                    'avg_attendance_rate' => $avgAttendanceRate,
                ],
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }

    /**
     * Export per-employee report to Excel.
     */
    public function exportEmployeeReport(Request $request)
    {
        if (!$request->user()->can('admin.reports.export')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location_id' => 'nullable|exists:locations,id',
            'user_id' => 'nullable|exists:users,id',
            'department' => 'nullable|string',
        ]);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $locationId = $request->input('location_id');
        $userId = $request->input('user_id');
        $department = $request->input('department');

        $data = $this->reportService->buildEmployeeReport($startDate, $endDate, $locationId, $userId, $department);

        $filename = "employee_report_{$startDate}_to_{$endDate}.xlsx";

        return Excel::download(new EmployeeReportExport($data), $filename);
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

    /**
     * Get available departments for filter dropdown.
     */
    public function departments(): JsonResponse
    {
        $departments = User::whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }

    /**
     * Get employee list for filter dropdown.
     */
    public function employees(): JsonResponse
    {
        $employees = User::where('status', 'active')
            ->select('id', 'name', 'employee_id', 'department')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    /**
     * Toggle variable allowance paid status for a specific attendance record and component.
     */
    public function toggleAllowancePaid(Request $request, Attendance $attendance): JsonResponse
    {
        if (!$request->user()->can('admin.reports.update') && !$request->user()->can('admin.reports.view')) {
            // Using view permission temporarily if update is not defined, adjust as needed based on actual roles
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'component_id' => 'required|exists:salary_components,id'
        ]);

        $attendance->toggleComponentClaim($request->component_id);

        return response()->json([
            'success' => true,
            'message' => 'Status pencairan tunjangan berhasil diperbarui',
            'data' => $attendance->fresh()
        ]);
    }
}
