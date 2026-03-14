<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class OvertimeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'weekend_overtime_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Treat weekend work as overtime',
            ],
            [
                'key' => 'saturday_multiplier',
                'value' => '1.5',
                'type' => 'float',
                'description' => 'Overtime multiplier for Saturday',
            ],
            [
                'key' => 'sunday_multiplier',
                'value' => '2.0',
                'type' => 'float',
                'description' => 'Overtime multiplier for Sunday',
            ],
            [
                'key' => 'monthly_working_hours',
                'value' => '173',
                'type' => 'integer',
                'description' => 'Monthly working hours divisor for hourly rate calculation (PP 35/2021)',
            ],
        ];

        foreach ($settings as $setting) {
            AppSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
