<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LatePenaltyTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'min_late_min',
        'max_late_min',
        'penalty_type',
        'deduction_pct',
    ];

    protected $casts = [
        'deduction_pct' => 'decimal:2',
    ];

    /**
     * Find the penalty tier for a given number of late minutes.
     */
    public static function findForMinutes(int $lateMinutes): ?self
    {
        return self::query()
            ->where('min_late_min', '<=', $lateMinutes)
            ->where(function ($query) use ($lateMinutes) {
                $query->whereNull('max_late_min')
                    ->orWhere('max_late_min', '>=', $lateMinutes);
            })
            ->orderBy('min_late_min', 'desc')
            ->first();
    }
}
