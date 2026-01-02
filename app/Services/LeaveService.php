<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    /**
     * Calculate the number of leave days between two dates.
     * Excludes weekends and holidays.
     */
    public function calculateLeaveDays(Carbon $startDate, Carbon $endDate): float
    {
        $days = 0;
        $current = $startDate->copy();

        // Get holidays in the date range
        $holidays = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();

        while ($current <= $endDate) {
            // Skip weekends (Saturday = 6, Sunday = 0)
            if (!$current->isWeekend()) {
                // Skip holidays
                if (!in_array($current->format('Y-m-d'), $holidays)) {
                    $days++;
                }
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Get or create leave balance for a user and leave type.
     */
    public function getOrCreateBalance(User $user, LeaveType $leaveType, int $year = null): LeaveBalance
    {
        $year = $year ?? now()->year;

        return LeaveBalance::firstOrCreate(
            [
                'user_id' => $user->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ],
            [
                'allocated_days' => $leaveType->default_days,
                'used_days' => 0,
                'pending_days' => 0,
            ]
        );
    }

    /**
     * Submit a new leave request.
     */
    public function submitRequest(User $user, array $data): array
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        
        // Validate dates
        if ($endDate < $startDate) {
            return [
                'success' => false,
                'error' => 'End date must be after or equal to start date',
            ];
        }

        // Calculate leave days
        $daysRequested = $this->calculateLeaveDays($startDate, $endDate);
        
        if ($daysRequested <= 0) {
            return [
                'success' => false,
                'error' => 'No working days in the selected date range',
            ];
        }

        // Get balance for the year of start date
        $balance = $this->getOrCreateBalance($user, $leaveType, $startDate->year);

        // Check if user has enough balance
        if (!$balance->hasEnoughBalance($daysRequested)) {
            return [
                'success' => false,
                'error' => 'Insufficient leave balance. Available: ' . $balance->remaining_days . ' days',
            ];
        }

        // Check for overlapping requests
        $hasOverlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                          ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($hasOverlap) {
            return [
                'success' => false,
                'error' => 'You already have a leave request for these dates',
            ];
        }

        return DB::transaction(function () use ($user, $leaveType, $startDate, $endDate, $daysRequested, $balance, $data) {
            // Create the request
            $request = LeaveRequest::create([
                'user_id' => $user->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_requested' => $daysRequested,
                'reason' => $data['reason'],
                'status' => $leaveType->requires_approval ? 'PENDING' : 'APPROVED',
            ]);

            if ($leaveType->requires_approval) {
                // Add to pending days
                $balance->addPendingDays($daysRequested);
            } else {
                // Auto-approve: directly add to used days
                $balance->increment('used_days', $daysRequested);
                $request->update([
                    'reviewed_at' => now(),
                    'admin_note' => 'Auto-approved (no approval required)',
                ]);
            }

            return [
                'success' => true,
                'data' => $request->load(['leaveType', 'user']),
            ];
        });
    }

    /**
     * Approve a leave request.
     */
    public function approveRequest(LeaveRequest $request, User $admin, ?string $note = null): array
    {
        if (!$request->canBeReviewed()) {
            return [
                'success' => false,
                'error' => 'Request cannot be approved',
            ];
        }

        return DB::transaction(function () use ($request, $admin, $note) {
            // Get the balance
            $balance = $this->getOrCreateBalance($request->user, $request->leaveType, $request->start_date->year);

            // Convert pending to used
            $balance->convertPendingToUsed($request->days_requested);

            // Update request
            $request->update([
                'status' => 'APPROVED',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            return [
                'success' => true,
                'data' => $request->fresh(['user', 'leaveType', 'reviewer']),
            ];
        });
    }

    /**
     * Reject a leave request.
     */
    public function rejectRequest(LeaveRequest $request, User $admin, string $note): array
    {
        if (!$request->canBeReviewed()) {
            return [
                'success' => false,
                'error' => 'Request cannot be rejected',
            ];
        }

        return DB::transaction(function () use ($request, $admin, $note) {
            // Get the balance
            $balance = $this->getOrCreateBalance($request->user, $request->leaveType, $request->start_date->year);

            // Release pending days
            $balance->releasePendingDays($request->days_requested);

            // Update request
            $request->update([
                'status' => 'REJECTED',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            return [
                'success' => true,
                'data' => $request->fresh(['user', 'leaveType', 'reviewer']),
            ];
        });
    }

    /**
     * Cancel a leave request (by employee).
     */
    public function cancelRequest(LeaveRequest $request): array
    {
        if (!$request->canBeCancelled()) {
            return [
                'success' => false,
                'error' => 'Request cannot be cancelled',
            ];
        }

        return DB::transaction(function () use ($request) {
            // Get the balance
            $balance = $this->getOrCreateBalance($request->user, $request->leaveType, $request->start_date->year);

            // Release pending days
            $balance->releasePendingDays($request->days_requested);

            // Update request
            $request->update([
                'status' => 'CANCELLED',
            ]);

            return [
                'success' => true,
                'data' => $request->fresh(['user', 'leaveType']),
            ];
        });
    }

    /**
     * Get user's leave balances for a year.
     */
    public function getUserBalances(User $user, int $year = null): array
    {
        $year = $year ?? now()->year;
        
        $leaveTypes = LeaveType::active()->get();
        $balances = [];

        foreach ($leaveTypes as $leaveType) {
            $balance = $this->getOrCreateBalance($user, $leaveType, $year);
            $balances[] = [
                'leave_type' => $leaveType,
                'balance' => $balance,
                'remaining' => $balance->remaining_days,
            ];
        }

        return $balances;
    }
}
