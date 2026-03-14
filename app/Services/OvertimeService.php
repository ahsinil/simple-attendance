<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    /**
     * Check if weekend overtime is enabled and return multiplier for the day.
     */
    public function getWeekendMultiplier(Carbon $date): ?float
    {
        $enabled = AppSetting::get('weekend_overtime_enabled', true);

        if (!$enabled) {
            return null;
        }

        if ($date->isSaturday()) {
            return (float) AppSetting::get('saturday_multiplier', 1.5);
        }

        if ($date->isSunday()) {
            return (float) AppSetting::get('sunday_multiplier', 2.0);
        }

        return null;
    }

    /**
     * Get the overtime multiplier for a given date.
     * Priority: Holiday > Weekend > Regular (1.0)
     */
    public function getOvertimeMultiplier(Carbon $date): array
    {
        // Check holiday first
        $holiday = Holiday::whereDate('date', $date->toDateString())->first();
        if ($holiday) {
            return [
                'multiplier' => (float) $holiday->overtime_multiplier,
                'type' => 'HOLIDAY',
                'is_holiday' => true,
            ];
        }

        // Check weekend
        $weekendMultiplier = $this->getWeekendMultiplier($date);
        if ($weekendMultiplier !== null) {
            return [
                'multiplier' => $weekendMultiplier,
                'type' => 'WEEKEND',
                'is_holiday' => false,
            ];
        }

        // Regular day
        return [
            'multiplier' => 1.0,
            'type' => 'REGULAR',
            'is_holiday' => false,
        ];
    }

    /**
     * Calculate overtime pay using Indonesian labor law progressive rates.
     * PP 35/2021: First hour = 1.5x, subsequent hours = 2.0x (weekday)
     * Holiday: all hours use the holiday multiplier
     */
    public function calculateOvertimePay(float $hourlyRate, float $overtimeHours, string $overtimeType, float $multiplier): float
    {
        if ($overtimeHours <= 0 || $hourlyRate <= 0) {
            return 0;
        }

        if ($overtimeType === 'HOLIDAY' || $overtimeType === 'WEEKEND') {
            // Holiday/weekend: all hours at multiplier rate
            return $overtimeHours * $hourlyRate * $multiplier;
        }

        // Regular weekday overtime: 1.5x first hour, 2.0x subsequent
        $firstHour = min($overtimeHours, 1.0);
        $remainingHours = max(0, $overtimeHours - 1.0);

        return ($firstHour * $hourlyRate * 1.5) + ($remainingHours * $hourlyRate * 2.0);
    }

    /**
     * Calculate variable allowance deduction for absent/leave days.
     */
    public function calculateVariableDeduction(float $totalVariableAllowance, int $absentDays, int $workDaysInMonth): float
    {
        if ($workDaysInMonth <= 0 || $absentDays <= 0 || $totalVariableAllowance <= 0) {
            return 0;
        }

        return round(($totalVariableAllowance / $workDaysInMonth) * $absentDays, 2);
    }

    /**
     * Get payroll summary for a date range.
     */
    public function getPayrollSummary(
        string $startDate,
        string $endDate,
        ?int $userId = null,
        ?int $locationId = null,
        ?string $department = null
    ): Collection {
        // Get all users with salary info
        $usersQuery = User::with(['salaryComponents.salaryComponent'])
            ->where('status', 'active');

        if ($userId) {
            $usersQuery->where('id', $userId);
        }

        if ($department) {
            $usersQuery->where('department', $department);
        }

        $users = $usersQuery->get();

        // Calculate work days in the period
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $totalWorkDays = $this->countWorkDays($start, $end);

        return $users->map(function (User $user) use ($startDate, $endDate, $locationId, $totalWorkDays) {
            return $this->getUserPayrollSummary($user, $startDate, $endDate, $locationId, $totalWorkDays);
        })->filter(function ($summary) {
            // Only include users with attendance data or salary data
            return $summary['work_days_present'] > 0 || $summary['base_salary'] > 0;
        })->values();
    }

    /**
     * Get payroll summary for a single user.
     */
    protected function getUserPayrollSummary(
        User $user,
        string $startDate,
        string $endDate,
        ?int $locationId,
        int $totalWorkDays
    ): array {
        // Get attendance records (check-outs with work data)
        $attendanceQuery = Attendance::where('user_id', $user->id)
            ->where('check_type', 'OUT')
            ->whereDate('scan_time', '>=', $startDate)
            ->whereDate('scan_time', '<=', $endDate);

        if ($locationId) {
            $attendanceQuery->where('location_id', $locationId);
        }

        $checkOuts = $attendanceQuery->get();

        // Get check-ins to count present days
        $checkInQuery = Attendance::where('user_id', $user->id)
            ->where('check_type', 'IN')
            ->whereDate('scan_time', '>=', $startDate)
            ->whereDate('scan_time', '<=', $endDate);

        if ($locationId) {
            $checkInQuery->where('location_id', $locationId);
        }

        $checkIns = $checkInQuery->get();
        $presentDays = $checkIns->pluck(DB::raw("DATE(scan_time)"))->unique()->count();

        // Actually, let's use groupBy on date
        $presentDays = $checkIns->groupBy(fn ($att) => Carbon::parse($att->scan_time)->toDateString())->count();

        // Count absent days (marked by system)
        $absentDays = Attendance::where('user_id', $user->id)
            ->where('check_type', 'IN')
            ->where('status', 'ABSENT')
            ->whereDate('scan_time', '>=', $startDate)
            ->whereDate('scan_time', '<=', $endDate)
            ->count();

        // Count leave days
        $leaveDays = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'APPROVED')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->sum('days');

        // Calculate hours
        $totalWorkMinutes = $checkOuts->sum('work_minutes');
        $totalOvertimeMinutes = $checkOuts->sum('overtime_min');
        $holidayOvertimeMinutes = $checkOuts->where('is_holiday', true)->sum('overtime_min');
        $regularOvertimeMinutes = $totalOvertimeMinutes - $holidayOvertimeMinutes;

        // Separate weekend OT from regular OT (check the day of week)
        $weekendOvertimeMinutes = $checkOuts->filter(function ($att) {
            $day = Carbon::parse($att->scan_time);
            return ($day->isSaturday() || $day->isSunday()) && !$att->is_holiday;
        })->sum('overtime_min');

        $normalOvertimeMinutes = $regularOvertimeMinutes - $weekendOvertimeMinutes;

        // Salary calculations
        $baseSalary = (float) ($user->base_salary ?? 0);
        $fixedAllowances = $user->totalFixedAllowances();
        $variableAllowances = $user->totalVariableAllowances();
        $hourlyRate = $user->overtimeHourlyRate();

        // Calculate OT pay
        $overtimePay = 0;
        foreach ($checkOuts as $checkout) {
            if ($checkout->overtime_min > 0) {
                $otHours = $checkout->overtime_min / 60;
                $otType = $checkout->is_holiday ? 'HOLIDAY'
                    : (Carbon::parse($checkout->scan_time)->isWeekend() ? 'WEEKEND' : 'REGULAR');
                $overtimePay += $this->calculateOvertimePay(
                    $hourlyRate,
                    $otHours,
                    $otType,
                    (float) $checkout->overtime_multiplier
                );
            }
        }

        // Variable deduction for absent + unpaid leave days
        $deductibleDays = $absentDays + (int) $leaveDays;
        $variableDeduction = $this->calculateVariableDeduction($variableAllowances, $deductibleDays, $totalWorkDays);

        // Estimated total
        $estimatedTotal = $baseSalary + $fixedAllowances + $variableAllowances - $variableDeduction + $overtimePay;

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'employee_id' => $user->employee_id,
            'department' => $user->department,
            'position' => $user->position,

            // Attendance data
            'work_days_present' => $presentDays,
            'absent_days' => $absentDays,
            'leave_days' => (int) $leaveDays,

            // Hours
            'total_work_hours' => round($totalWorkMinutes / 60, 1),
            'regular_hours' => round(($totalWorkMinutes - $totalOvertimeMinutes) / 60, 1),
            'overtime_hours' => round($totalOvertimeMinutes / 60, 1),
            'holiday_ot_hours' => round($holidayOvertimeMinutes / 60, 1),
            'weekend_ot_hours' => round($weekendOvertimeMinutes / 60, 1),
            'normal_ot_hours' => round($normalOvertimeMinutes / 60, 1),

            // Salary
            'base_salary' => $baseSalary,
            'fixed_allowances' => $fixedAllowances,
            'variable_allowances' => $variableAllowances,
            'hourly_rate' => round($hourlyRate, 2),
            'overtime_pay' => round($overtimePay, 2),
            'variable_deduction' => round($variableDeduction, 2),
            'estimated_total' => round($estimatedTotal, 2),
        ];
    }

    /**
     * Count working days (Mon-Fri) in a date range, excluding holidays.
     */
    protected function countWorkDays(Carbon $start, Carbon $end): int
    {
        $holidays = Holiday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        $count = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($current->isWeekday() && !in_array($current->toDateString(), $holidays)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /**
     * Get payroll summary KPIs for a date range.
     */
    public function getPayrollKpis(Collection $payrollData): array
    {
        return [
            'total_employees' => $payrollData->count(),
            'total_overtime_hours' => round($payrollData->sum('overtime_hours'), 1),
            'total_overtime_pay' => round($payrollData->sum('overtime_pay'), 2),
            'total_variable_deductions' => round($payrollData->sum('variable_deduction'), 2),
            'total_estimated_payroll' => round($payrollData->sum('estimated_total'), 2),
            'avg_overtime_per_employee' => $payrollData->count() > 0
                ? round($payrollData->avg('overtime_hours'), 1)
                : 0,
        ];
    }
}
