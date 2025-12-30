<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'scan_time',
        'check_type',
        'gps_lat',
        'gps_lng',
        'gps_accuracy_m',
        'distance_m',
        'time_slot',
        'ip_address',
        'device_id',
        'status',
        'late_min',
        'early_leave_min',
        'work_minutes',
        'penalty_tier',
        'is_holiday',
        'overtime_min',
        'overtime_multiplier',
        'method',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'scan_time' => 'datetime',
        'gps_lat' => 'decimal:8',
        'gps_lng' => 'decimal:8',
        'gps_accuracy_m' => 'decimal:2',
        'distance_m' => 'decimal:2',
        'is_holiday' => 'boolean',
        'overtime_multiplier' => 'decimal:1',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the user for this attendance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the location for this attendance.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the approver for this attendance.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get all logs for this attendance.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * Scope for check-ins.
     */
    public function scopeCheckIns($query)
    {
        return $query->where('check_type', 'IN');
    }

    /**
     * Scope for check-outs.
     */
    public function scopeCheckOuts($query)
    {
        return $query->where('check_type', 'OUT');
    }

    /**
     * Scope for a specific date.
     */
    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('scan_time', $date);
    }

    /**
     * Scope for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scan_time', today());
    }
}
