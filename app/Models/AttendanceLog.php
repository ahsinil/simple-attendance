<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'attendance_request_id',
        'action',
        'actor_id',
        'reason',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the user for this log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attendance for this log.
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Get the attendance request for this log.
     */
    public function attendanceRequest(): BelongsTo
    {
        return $this->belongsTo(AttendanceRequest::class);
    }

    /**
     * Get the actor (user who performed the action).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Create a log entry.
     */
    public static function log(
        string $action,
        ?int $userId = null,
        ?int $attendanceId = null,
        ?int $attendanceRequestId = null,
        ?int $actorId = null,
        ?string $reason = null,
        ?array $payload = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'attendance_id' => $attendanceId,
            'attendance_request_id' => $attendanceRequestId,
            'action' => $action,
            'actor_id' => $actorId,
            'reason' => $reason,
            'payload' => $payload,
        ]);
    }
}
