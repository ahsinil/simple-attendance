<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'late_after_min',
        'early_checkout_min',
        'allow_checkout_before_end',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'allow_checkout_before_end' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all user schedules for this shift.
     */
    public function userSchedules(): HasMany
    {
        return $this->hasMany(UserSchedule::class);
    }
}
