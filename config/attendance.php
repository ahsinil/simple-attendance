<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Barcode Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the time-based dynamic barcode system.
    |
    */

    'barcode_rotation_seconds' => env('BARCODE_ROTATION_SECONDS', 300),
    
    'barcode_secret_key' => env('BARCODE_SECRET_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | GPS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GPS validation.
    |
    */

    'gps_max_accuracy_meters' => env('GPS_MAX_ACCURACY_METERS', 100),

    /*
    |--------------------------------------------------------------------------
    | Device Registration
    |--------------------------------------------------------------------------
    |
    | Configuration for optional device registration feature.
    | When enabled, employees must use registered devices for attendance.
    |
    */

    'device_registration_enabled' => env('DEVICE_REGISTRATION_ENABLED', false),
    
    'max_devices_per_user' => env('MAX_DEVICES_PER_USER', 2),

    /*
    |--------------------------------------------------------------------------
    | Overtime Configuration
    |--------------------------------------------------------------------------
    |
    | Weekend overtime multipliers.
    |
    */

    'weekend_overtime_enabled' => env('WEEKEND_OVERTIME_ENABLED', true),
    
    'saturday_multiplier' => env('SATURDAY_MULTIPLIER', 1.5),
    
    'sunday_multiplier' => env('SUNDAY_MULTIPLIER', 2.0),

];
