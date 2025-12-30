<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shifts = [
            [
                'code' => 'SHIFT-MORNING',
                'name' => 'Morning Shift',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'late_after_min' => 15,
                'early_checkout_min' => 0,
                'allow_checkout_before_end' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SHIFT-AFTERNOON',
                'name' => 'Afternoon Shift',
                'start_time' => '13:00:00',
                'end_time' => '22:00:00',
                'late_after_min' => 15,
                'early_checkout_min' => 0,
                'allow_checkout_before_end' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SHIFT-NIGHT',
                'name' => 'Night Shift',
                'start_time' => '22:00:00',
                'end_time' => '07:00:00',
                'late_after_min' => 15,
                'early_checkout_min' => 0,
                'allow_checkout_before_end' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SHIFT-FLEXI',
                'name' => 'Flexible Hours',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'late_after_min' => 30,
                'early_checkout_min' => 30,
                'allow_checkout_before_end' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('shifts')->insert($shifts);
    }
}
