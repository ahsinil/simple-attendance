<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'year',
        'allocated_days',
        'used_days',
        'pending_days',
    ];

    protected $casts = [
        'year' => 'integer',
        'allocated_days' => 'decimal:1',
        'used_days' => 'decimal:1',
        'pending_days' => 'decimal:1',
    ];

    /**
     * Get the user for this balance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the leave type for this balance.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get remaining available days.
     */
    public function getRemainingDaysAttribute(): float
    {
        return $this->allocated_days - $this->used_days - $this->pending_days;
    }

    /**
     * Check if user has enough balance for a request.
     */
    public function hasEnoughBalance(float $days): bool
    {
        return $this->remaining_days >= $days;
    }

    /**
     * Add pending days (when request is submitted).
     */
    public function addPendingDays(float $days): void
    {
        $this->increment('pending_days', $days);
    }

    /**
     * Release pending days (when request is rejected/cancelled).
     */
    public function releasePendingDays(float $days): void
    {
        $this->decrement('pending_days', $days);
    }

    /**
     * Convert pending to used (when request is approved).
     */
    public function convertPendingToUsed(float $days): void
    {
        $this->decrement('pending_days', $days);
        $this->increment('used_days', $days);
    }

    /**
     * Scope for a specific year.
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
