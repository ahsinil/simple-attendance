<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get real-time statistics for today.
     */
    public function getRealtimeStats(): array
    {
        $today = today();
        $totalEmployees = User::whereHas('roles', fn($q) => $q->whereIn('name', ['employee', 'supervisor']))->count();

        // Present today (checked in)
        $presentToday = Attendance::whereDate('scan_time', $today)
            ->where('check_type', 'IN')
            ->distinct('user_id')
            ->count('user_id');

        // Late arrivals today
        $lateToday = Attendance::whereDate('scan_time', $today)
            ->where('check_type', 'IN')
            ->where('status', 'LATE')
            ->count();

        // Average late minutes today
        $avgLateMinutes = Attendance::whereDate('scan_time', $today)
            ->where('check_type', 'IN')
            ->where('status', 'LATE')
            ->avg('late_min') ?? 0;

        // On-time today
        $onTimeToday = Attendance::whereDate('scan_time', $today)
            ->where('check_type', 'IN')
            ->where('status', 'ON_TIME')
            ->count();

        // Not yet checked in
        $notCheckedIn = $totalEmployees - $presentToday;

        return [
            'total_employees' => $totalEmployees,
            'present_today' => $presentToday,
            'late_today' => $lateToday,
            'on_time_today' => $onTimeToday,
            'avg_late_minutes' => round($avgLateMinutes),
            'not_checked_in' => max(0, $notCheckedIn),
            'attendance_rate' => $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0,
        ];
    }

    /**
     * Get monthly statistics with trends.
     */
    public function getMonthlyStats(): array
    {
        $startOfMonth = now()->startOfMonth();
        $today = today();

        // Get daily attendance data for chart
        $dailyData = Attendance::select(
                DB::raw('DATE(scan_time) as date'),
                DB::raw('COUNT(DISTINCT CASE WHEN check_type = "IN" THEN user_id END) as present'),
                DB::raw('SUM(CASE WHEN check_type = "IN" AND status = "LATE" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN check_type = "IN" AND status = "ON_TIME" THEN 1 ELSE 0 END) as on_time')
            )
            ->whereDate('scan_time', '>=', $startOfMonth)
            ->whereDate('scan_time', '<=', $today)
            ->groupBy(DB::raw('DATE(scan_time)'))
            ->orderBy('date')
            ->get();

        // Calculate totals for the month
        $totalCheckIns = Attendance::whereDate('scan_time', '>=', $startOfMonth)
            ->whereDate('scan_time', '<=', $today)
            ->where('check_type', 'IN')
            ->count();

        $totalLate = Attendance::whereDate('scan_time', '>=', $startOfMonth)
            ->whereDate('scan_time', '<=', $today)
            ->where('check_type', 'IN')
            ->where('status', 'LATE')
            ->count();

        $totalOnTime = Attendance::whereDate('scan_time', '>=', $startOfMonth)
            ->whereDate('scan_time', '<=', $today)
            ->where('check_type', 'IN')
            ->where('status', 'ON_TIME')
            ->count();

        // Calculate average work duration (from paired check-ins/check-outs)
        $attendances = Attendance::whereDate('scan_time', '>=', $startOfMonth)
            ->whereDate('scan_time', '<=', $today)
            ->get();

        $groupedByUserDate = $attendances->groupBy(function($a) {
            return $a->user_id . '-' . $a->scan_time->toDateString();
        });

        $totalWorkMinutes = 0;
        $workDayCount = 0;
        foreach ($groupedByUserDate as $dayAttendances) {
            $checkIn = $dayAttendances->where('check_type', 'IN')->sortBy('scan_time')->first();
            $checkOut = $dayAttendances->where('check_type', 'OUT')->sortByDesc('scan_time')->first();
            if ($checkIn && $checkOut) {
                $totalWorkMinutes += $checkOut->scan_time->diffInMinutes($checkIn->scan_time);
                $workDayCount++;
            }
        }

        $avgWorkMinutes = $workDayCount > 0 ? round($totalWorkMinutes / $workDayCount) : 0;

        return [
            'chart_data' => $dailyData->map(fn($d) => [
                'date' => $d->date,
                'present' => $d->present,
                'late' => $d->late,
                'on_time' => $d->on_time,
            ]),
            'late_vs_ontime' => [
                'late' => $totalLate,
                'on_time' => $totalOnTime,
                'late_percentage' => $totalCheckIns > 0 ? round(($totalLate / $totalCheckIns) * 100, 1) : 0,
            ],
            'avg_work_duration' => [
                'minutes' => $avgWorkMinutes,
                'hours' => round($avgWorkMinutes / 60, 1),
            ],
        ];
    }

    /**
     * Get employee insights.
     */
    public function getEmployeeInsights(): array
    {
        $startOfMonth = now()->startOfMonth();
        $today = today();

        // Top 5 late employees this month
        $topLate = Attendance::select('user_id', DB::raw('COUNT(*) as late_count'), DB::raw('SUM(late_min) as total_late_min'))
            ->whereDate('scan_time', '>=', $startOfMonth)
            ->whereDate('scan_time', '<=', $today)
            ->where('check_type', 'IN')
            ->where('status', 'LATE')
            ->groupBy('user_id')
            ->orderByDesc('late_count')
            ->limit(5)
            ->with('user:id,name,employee_id')
            ->get()
            ->map(fn($item) => [
                'user' => $item->user?->only(['id', 'name', 'employee_id']),
                'late_count' => $item->late_count,
                'total_late_minutes' => $item->total_late_min,
            ]);

        // Perfect attendance (employees with all check-ins on time this month)
        $allCheckIns = Attendance::whereDate('scan_time', '>=', $startOfMonth)
            ->whereDate('scan_time', '<=', $today)
            ->where('check_type', 'IN')
            ->get()
            ->groupBy('user_id');

        $perfectAttendance = [];
        foreach ($allCheckIns as $userId => $userAttendances) {
            $hasLate = $userAttendances->where('status', 'LATE')->count() > 0;
            if (!$hasLate && $userAttendances->count() > 0) {
                $user = User::select('id', 'name', 'employee_id')->find($userId);
                if ($user) {
                    $perfectAttendance[] = [
                        'user' => $user->only(['id', 'name', 'employee_id']),
                        'days_present' => $userAttendances->count(),
                    ];
                }
            }
        }

        usort($perfectAttendance, fn($a, $b) => $b['days_present'] <=> $a['days_present']);
        $perfectAttendance = array_slice($perfectAttendance, 0, 5);

        // Employees missing checkout today
        $checkedInToday = Attendance::whereDate('scan_time', $today)
            ->where('check_type', 'IN')
            ->pluck('user_id')
            ->unique();

        $checkedOutToday = Attendance::whereDate('scan_time', $today)
            ->where('check_type', 'OUT')
            ->pluck('user_id')
            ->unique();

        $missingCheckout = User::select('id', 'name', 'employee_id')
            ->whereIn('id', $checkedInToday->diff($checkedOutToday))
            ->get()
            ->map(fn($u) => $u->only(['id', 'name', 'employee_id']));

        return [
            'top_late' => $topLate,
            'perfect_attendance' => $perfectAttendance,
            'missing_checkout' => $missingCheckout,
        ];
    }

    /**
     * Get pending requests for quick approval.
     */
    public function getPendingRequests(): array
    {
        return AttendanceRequest::where('status', 'PENDING')
            ->with(['user:id,name,employee_id', 'location:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'user' => $r->user?->only(['id', 'name', 'employee_id']),
                'location' => $r->location?->name,
                'check_type' => $r->check_type,
                'request_time' => $r->request_time?->toIso8601String(),
                'reason' => $r->reason,
                'created_at' => $r->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get recent activity (latest check-ins/outs).
     */
    public function getRecentActivity(): array
    {
        return Attendance::with(['user:id,name,employee_id', 'location:id,name'])
            ->orderBy('scan_time', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'user' => $a->user?->only(['id', 'name', 'employee_id']),
                'location' => $a->location?->name,
                'check_type' => $a->check_type,
                'status' => $a->status,
                'scan_time' => $a->scan_time?->toIso8601String(),
                'late_min' => $a->late_min,
            ])
            ->toArray();
    }

    /**
     * Build per-employee report data.
     */
    public function buildEmployeeReport(string $startDate, string $endDate, ?int $locationId = null, ?int $userId = null, ?string $department = null)
    {
        $usersQuery = User::where('status', 'active');

        if ($userId) {
            $usersQuery->where('id', $userId);
        }

        if ($department) {
            $usersQuery->where('department', $department);
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
     * Get report summary/KPIs.
     */
    public function getReportSummary(string $startDate, string $endDate, ?int $locationId = null): array
    {
        $query = Attendance::whereDate('scan_time', '>=', $startDate)
            ->whereDate('scan_time', '<=', $endDate);

        if ($locationId) {
            $query->where('location_id', $locationId);
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

        return [
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
    }
}
