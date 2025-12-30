<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LatePenaltyTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            [
                'code' => 'TIER-1',
                'name' => 'Warning',
                'min_late_min' => 1,
                'max_late_min' => 15,
                'penalty_type' => 'WARNING',
                'deduction_pct' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'TIER-2',
                'name' => 'Minor Deduction',
                'min_late_min' => 16,
                'max_late_min' => 30,
                'penalty_type' => 'DEDUCTION',
                'deduction_pct' => 25,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'TIER-3',
                'name' => 'Major Deduction',
                'min_late_min' => 31,
                'max_late_min' => 60,
                'penalty_type' => 'DEDUCTION',
                'deduction_pct' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'TIER-4',
                'name' => 'Half Day',
                'min_late_min' => 61,
                'max_late_min' => null,
                'penalty_type' => 'HALF_DAY',
                'deduction_pct' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('late_penalty_tiers')->insert($tiers);
    }
}
