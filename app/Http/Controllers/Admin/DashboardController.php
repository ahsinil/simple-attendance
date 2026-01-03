<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
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
                'realtime' => $this->getRealtimeStats(),
                'monthly' => $this->getMonthlyStats(),
                'employee_insights' => $this->getEmployeeInsights(),
                'pending_requests' => $this->getPendingRequests(),
                'recent_activity' => $this->getRecentActivity(),
            ],
        ]);
    }

    /**
     * Get real-time statistics for today.
     */
    protected function getRealtimeStats(): array
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
    protected function getMonthlyStats(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
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
        foreach ($groupedByUserDate as $key => $dayAttendances) {
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
    protected function getEmployeeInsights(): array
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

        // Sort by days present descending and limit to 5
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
    protected function getPendingRequests(): array
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
    protected function getRecentActivity(): array
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
}
