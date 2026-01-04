<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = [
            [
                'name' => 'Annual Leave',
                'code' => 'ANNUAL',
                'default_days' => 12,
                'is_paid' => true,
                'requires_approval' => true,
                'color' => '#4CAF50',
                'is_active' => true,
            ],
            [
                'name' => 'Sick Leave',
                'code' => 'SICK',
                'default_days' => 14,
                'is_paid' => true,
                'requires_approval' => false,
                'color' => '#F44336',
                'is_active' => true,
            ],
            [
                'name' => 'Personal Leave',
                'code' => 'PERSONAL',
                'default_days' => 3,
                'is_paid' => true,
                'requires_approval' => true,
                'color' => '#2196F3',
                'is_active' => true,
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => 'UNPAID',
                'default_days' => 0,
                'is_paid' => false,
                'requires_approval' => true,
                'color' => '#9E9E9E',
                'is_active' => true,
            ],
            [
                'name' => 'Maternity Leave',
                'code' => 'MATERNITY',
                'default_days' => 90,
                'is_paid' => true,
                'requires_approval' => true,
                'color' => '#E91E63',
                'is_active' => true,
            ],
            [
                'name' => 'Paternity Leave',
                'code' => 'PATERNITY',
                'default_days' => 5,
                'is_paid' => true,
                'requires_approval' => true,
                'color' => '#3F51B5',
                'is_active' => true,
            ],
        ];

        foreach ($leaveTypes as $type) {
            LeaveType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
