<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'phone',
        'department',
        'position',
        'default_location_id',
        'status',
        'avatar',
        'base_salary',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the default location for this user.
     */
    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    /**
     * Get all attendances for this user.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get all attendance requests for this user.
     */
    public function attendanceRequests(): HasMany
    {
        return $this->hasMany(AttendanceRequest::class);
    }

    /**
     * Get all schedules for this user.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(UserSchedule::class);
    }

    /**
     * Get all devices for this user.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Get all notifications for this user.
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get all leave requests for this user.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get all leave balances for this user.
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get all salary component assignments for this user.
     */
    public function salaryComponents(): HasMany
    {
        return $this->hasMany(UserSalaryComponent::class);
    }

    /**
     * Get total fixed allowances (tunjangan tetap).
     */
    public function totalFixedAllowances(): float
    {
        return (float) $this->salaryComponents()
            ->whereHas('salaryComponent', fn ($q) => $q->where('type', 'FIXED')->where('is_active', true))
            ->sum('amount');
    }

    /**
     * Get total variable allowances (tunjangan tidak tetap).
     */
    public function totalVariableAllowances(): float
    {
        return (float) $this->salaryComponents()
            ->whereHas('salaryComponent', fn ($q) => $q->where('type', 'VARIABLE')->where('is_active', true))
            ->sum('amount');
    }

    /**
     * Calculate overtime hourly rate per PP 35/2021.
     * Formula: (base_salary + fixed_allowances) / monthly_working_hours
     */
    public function overtimeHourlyRate(): float
    {
        $monthlyHours = (int) AppSetting::get('monthly_working_hours', 173);
        $base = (float) ($this->base_salary ?? 0);
        $fixed = $this->totalFixedAllowances();

        if ($monthlyHours <= 0) {
            return 0;
        }

        return ($base + $fixed) / $monthlyHours;
    }

    /**
     * Check if user is admin or super admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super_admin']);
    }

    /**
     * Check if user is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }
}
