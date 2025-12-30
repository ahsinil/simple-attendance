<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'type',
        'overtime_multiplier',
    ];

    protected $casts = [
        'date' => 'date',
        'overtime_multiplier' => 'decimal:1',
    ];

    /**
     * Scope to check if a date is a holiday.
     */
    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }
}
