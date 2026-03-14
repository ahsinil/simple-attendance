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

        $data = $this->buildEmployeeReport($request, $startDate, $endDate);

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

        $data = $this->buildEmployeeReport($request, $startDate, $endDate);

        $filename = "employee_report_{$startDate}_to_{$endDate}.xlsx";

        return Excel::download(new EmployeeReportExport($data), $filename);
    }

    /**
     * Build per-employee report data.
     */
    protected function buildEmployeeReport(Request $request, string $startDate, string $endDate)
    {
        $usersQuery = User::where('status', 'active');

        if ($request->filled('user_id')) {
            $usersQuery->where('id', $request->input('user_id'));
        }

        if ($request->filled('department')) {
            $usersQuery->where('department', $request->input('department'));
        }

        $users = $usersQuery->orderBy('name')->get();

        // Count working days in range (Mon-Fri excl. holidays)
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $workDaysInPeriod = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $workDaysInPeriod++;
            }
            $current->addDay();
        }
        $workDaysInPeriod = max($workDaysInPeriod, 1);

        $locationId = $request->input('location_id');

        return $users->map(function (User $user) use ($startDate, $endDate, $locationId, $workDaysInPeriod) {
            // Check-ins
            $checkInQuery = Attendance::where('user_id', $user->id)
                ->where('check_type', 'IN')
                ->whereDate('scan_time', '>=', $startDate)
                ->whereDate('scan_time', '<=', $endDate);

            if ($locationId) {
                $checkInQuery->where('location_id', $locationId);
            }

            $checkIns = $checkInQuery->get();

            $daysPresent = $checkIns
                ->whereIn('status', ['ON_TIME', 'LATE', 'EARLY'])
                ->groupBy(fn($att) => Carbon::parse($att->scan_time)->toDateString())
                ->count();

            $lateCount = $checkIns->where('status', 'LATE')->count();
            $totalLateMin = (int) $checkIns->where('status', 'LATE')->sum('late_min');
            $onTimeCount = $checkIns->where('status', 'ON_TIME')->count();
            $absentDays = $checkIns->where('status', 'ABSENT')->count();

            // Check-outs (for work hours / OT)
            $checkOutQuery = Attendance::where('user_id', $user->id)
                ->where('check_type', 'OUT')
                ->whereDate('scan_time', '>=', $startDate)
                ->whereDate('scan_time', '<=', $endDate);

            if ($locationId) {
                $checkOutQuery->where('location_id', $locationId);
            }

            $checkOuts = $checkOutQuery->get();
            $totalWorkHours = round($checkOuts->sum('work_minutes') / 60, 1);
            $overtimeHours = round($checkOuts->sum('overtime_min') / 60, 1);

            // Leave days
            $leaveDays = (int) LeaveRequest::where('user_id', $user->id)
                ->where('status', 'APPROVED')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q2) use ($startDate, $endDate) {
                            $q2->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->sum('days_requested');

            $attendanceRate = round(($daysPresent / $workDaysInPeriod) * 100, 1);

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->employee_id,
                'department' => $user->department,
                'position' => $user->position,
                'days_present' => $daysPresent,
                'on_time_count' => $onTimeCount,
                'late_count' => $lateCount,
                'total_late_minutes' => $totalLateMin,
                'absent_days' => $absentDays,
                'leave_days' => $leaveDays,
                'total_work_hours' => $totalWorkHours,
                'overtime_hours' => $overtimeHours,
                'attendance_rate' => $attendanceRate,
            ];
        })->filter(function ($row) {
            return $row['days_present'] > 0 || $row['absent_days'] > 0 || $row['leave_days'] > 0;
        });
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
}
