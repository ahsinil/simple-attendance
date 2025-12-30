<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarcodeController;
use App\Http\Controllers\Admin\AttendanceRequestController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::post('/scan', [AttendanceController::class, 'scan']);
        Route::get('/today', [AttendanceController::class, 'today']);
        Route::get('/history', [AttendanceController::class, 'history']);
        Route::get('/monthly-summary', [AttendanceController::class, 'monthlySummary']);
        Route::post('/manual-request', [AttendanceController::class, 'manualRequest']);
        Route::get('/my-requests', [AttendanceController::class, 'myRequests']);
        Route::get('/locations', [AttendanceController::class, 'locations']);
    });

    // Barcode (for display screens)
    Route::prefix('barcode')->group(function () {
        Route::get('/info', [BarcodeController::class, 'info']);
        Route::get('/locations', [BarcodeController::class, 'index']);
        Route::get('/location/{location}', [BarcodeController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->group(function () {
        
        // Attendance Requests
        Route::prefix('requests')->group(function () {
            Route::get('/', [AttendanceRequestController::class, 'index']);
            Route::get('/stats', [AttendanceRequestController::class, 'stats']);
            Route::get('/{attendanceRequest}', [AttendanceRequestController::class, 'show']);
            Route::post('/{attendanceRequest}/approve', [AttendanceRequestController::class, 'approve']);
            Route::post('/{attendanceRequest}/reject', [AttendanceRequestController::class, 'reject']);
        });

        // Shifts
        Route::apiResource('shifts', ShiftController::class);

        // Locations
        Route::apiResource('locations', LocationController::class);

        // Users
        Route::get('/roles', [UserController::class, 'roles']);
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/schedule', [UserController::class, 'assignSchedule']);
        Route::get('/users/{user}/schedules', [UserController::class, 'schedules']);
        Route::delete('/users/{user}/schedules/{schedule}', [UserController::class, 'removeSchedule']);
    });
});
