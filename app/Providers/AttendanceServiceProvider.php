<?php

namespace App\Providers;

use App\Services\AttendanceService;
use App\Services\BarcodeService;
use App\Services\DeviceService;
use App\Services\GpsService;
use Illuminate\Support\ServiceProvider;

class AttendanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(BarcodeService::class, function ($app) {
            return new BarcodeService();
        });

        $this->app->singleton(GpsService::class, function ($app) {
            return new GpsService();
        });

        $this->app->singleton(DeviceService::class, function ($app) {
            return new DeviceService();
        });

        $this->app->singleton(AttendanceService::class, function ($app) {
            return new AttendanceService(
                $app->make(GpsService::class),
                $app->make(BarcodeService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
