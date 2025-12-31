<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_fingerprint',
        'device_name',
        'device_info',
        'is_approved',
        'registered_at',
        'last_used_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'is_approved' => 'boolean',
        'registered_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user for this device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update the last used timestamp.
     */
    public function touchLastUsed(): bool
    {
        $this->last_used_at = now();
        return $this->save();
    }
}
