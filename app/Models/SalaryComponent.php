<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all user assignments for this component.
     */
    public function userSalaryComponents(): HasMany
    {
        return $this->hasMany(UserSalaryComponent::class);
    }

    /**
     * Scope to only active components.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to fixed components (tunjangan tetap).
     */
    public function scopeFixed($query)
    {
        return $query->where('type', 'FIXED');
    }

    /**
     * Scope to variable components (tunjangan tidak tetap).
     */
    public function scopeVariable($query)
    {
        return $query->where('type', 'VARIABLE');
    }
}
