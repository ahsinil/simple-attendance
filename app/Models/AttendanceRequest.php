<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'request_time',
        'check_type',
        'gps_lat',
        'gps_lng',
        'distance_m',
        'gps_accuracy_m',
        'reason',
        'photo_path',
        'failure_reason',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'request_time' => 'datetime',
        'gps_lat' => 'decimal:8',
        'gps_lng' => 'decimal:8',
        'distance_m' => 'decimal:2',
        'gps_accuracy_m' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the user for this request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the location for this request.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the reviewer for this request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope for approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    /**
     * Scope for rejected requests.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }
}
