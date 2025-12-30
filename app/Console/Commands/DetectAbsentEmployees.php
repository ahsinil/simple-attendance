<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DetectAbsentEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:detect-absent 
                            {--date= : Date to check (default: today)}
                            {--dry-run : Show what would be marked without actually marking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and mark employees who did not check in as ABSENT';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') 
            ? Carbon::parse($this->option('date'))->toDateString()
            : today()->toDateString();
        
        $dryRun = $this->option('dry-run');

        $this->info("Checking for absent employees on {$date}" . ($dryRun ? ' (DRY RUN)' : ''));

        // Get all users with active schedules for this date
        $usersWithSchedules = UserSchedule::with(['user', 'shift'])
            ->activeOn($date)
            ->get()
            ->pluck('user')
            ->unique('id');

        $absentCount = 0;

        foreach ($usersWithSchedules as $user) {
            if (!$user || $user->status !== 'active') {
                continue;
            }

            // Check if user has any attendance for this date
            $hasAttendance = Attendance::where('user_id', $user->id)
                ->whereDate('scan_time', $date)
                ->exists();

            if (!$hasAttendance) {
                $absentCount++;
                
                $identifier = $user->employee_id ?? $user->email;
                $this->line("  - {$user->name} ({$identifier}): No attendance");

                if (!$dryRun) {
                    $this->markAsAbsent($user, $date);
                }
            }
        }

        if ($absentCount === 0) {
            $this->info('No absent employees detected.');
        } else {
            $this->info(
                $dryRun 
                    ? "Found {$absentCount} absent employee(s) (not marked - dry run)" 
                    : "Marked {$absentCount} employee(s) as ABSENT"
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Mark a user as absent for a given date.
     */
    protected function markAsAbsent(User $user, string $date): void
    {
        DB::transaction(function () use ($user, $date) {
            // Get user's schedule to find their expected location
            $schedule = UserSchedule::where('user_id', $user->id)
                ->activeOn($date)
                ->with('shift')
                ->first();

            $locationId = $user->default_location_id;

            // Create attendance record with ABSENT status
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'location_id' => $locationId,
                'scan_time' => Carbon::parse($date)->setTime(0, 0, 0),
                'check_type' => 'IN',
                'status' => 'ABSENT',
                'penalty_tier' => 'ABSENT',
                'method' => 'SYSTEM',
            ]);

            // Log the system action
            AttendanceLog::log(
                'SYSTEM_ABSENT',
                $user->id,
                $attendance->id,
                null,
                null, // No actor - system action
                'Automatically marked absent by system',
                [
                    'date' => $date,
                    'schedule_id' => $schedule?->id,
                    'shift_name' => $schedule?->shift?->name,
                ]
            );
        });
    }
}
