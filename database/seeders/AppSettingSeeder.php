<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'device_registration_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Require registered devices for attendance',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'max_devices_per_user',
                'value' => '2',
                'type' => 'integer',
                'description' => 'Maximum devices an employee can register',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'barcode_rotation_seconds',
                'value' => '300',
                'type' => 'integer',
                'description' => 'Barcode rotation interval in seconds (default: 5 minutes)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'gps_max_accuracy_meters',
                'value' => '100',
                'type' => 'integer',
                'description' => 'Maximum GPS accuracy allowed in meters',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'weekend_overtime_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable overtime calculation for weekends',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'saturday_multiplier',
                'value' => '1.5',
                'type' => 'string',
                'description' => 'Overtime multiplier for Saturday',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sunday_multiplier',
                'value' => '2.0',
                'type' => 'string',
                'description' => 'Overtime multiplier for Sunday',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('app_settings')->insert($settings);
    }
}
