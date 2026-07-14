<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarcodeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Admin\AttendanceRequestController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\LeaveApprovalController;
use App\Http\Controllers\Admin\LeaveTypeController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserAdditionalAllowanceController;
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
        Route::get('/devices', [AuthController::class, 'devices']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        
        // Registered Devices (for attendance)
        Route::get('/my-devices', [AuthController::class, 'myDevices']);
        Route::post('/devices/register', [AuthController::class, 'registerDevice']);
        Route::put('/devices/{device}/rename', [AuthController::class, 'renameDevice']);
        Route::delete('/devices/{device}', [AuthController::class, 'removeDevice']);
    });

    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::post('/scan', [AttendanceController::class, 'scan']);
        Route::get('/today', [AttendanceController::class, 'today']);
        Route::get('/history', [AttendanceController::class, 'history']);
        Route::get('/monthly-summary', [AttendanceController::class, 'monthlySummary']);
        Route::post('/manual-request', [AttendanceController::class, 'manualRequest']);
        Route::get('/my-requests', [AttendanceController::class, 'myRequests']);
        Route::get('/my-schedules', [AttendanceController::class, 'mySchedules']);
        Route::get('/locations', [AttendanceController::class, 'locations']);
        Route::post('/{attendance}/overtime-reason', [AttendanceController::class, 'submitOvertimeReason']);
    });

    // Barcode (for display screens)
    Route::prefix('barcode')->group(function () {
        Route::get('/info', [BarcodeController::class, 'info']);
        Route::get('/locations', [BarcodeController::class, 'index']);
        Route::get('/location/{location}', [BarcodeController::class, 'show']);
    });

    // Leave Management (Employee)
    Route::prefix('leave')->group(function () {
        Route::get('/types', [LeaveController::class, 'types']);
        Route::get('/balances', [LeaveController::class, 'balances']);
        Route::get('/my-requests', [LeaveController::class, 'myRequests']);
        Route::post('/request', [LeaveController::class, 'submitRequest']);
        Route::delete('/request/{leaveRequest}', [LeaveController::class, 'cancelRequest']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

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

        // Roles & Permissions
        Route::apiResource('roles', App\Http\Controllers\Admin\RoleController::class);
        Route::get('/permissions', [App\Http\Controllers\Admin\PermissionController::class, 'index']);


        // Reports
        Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index']);
        Route::put('/reports/attendances/{attendance}/toggle-allowance-paid', [App\Http\Controllers\Admin\ReportController::class, 'toggleAllowancePaid']);
        Route::get('/reports/summary', [App\Http\Controllers\Admin\ReportController::class, 'summary']);
        Route::get('/reports/export', [App\Http\Controllers\Admin\ReportController::class, 'export']);
        Route::get('/reports/locations', [App\Http\Controllers\Admin\ReportController::class, 'locations']);
        Route::get('/reports/departments', [App\Http\Controllers\Admin\ReportController::class, 'departments']);
        Route::get('/reports/employees', [App\Http\Controllers\Admin\ReportController::class, 'employees']);
        Route::get('/reports/employee', [App\Http\Controllers\Admin\ReportController::class, 'employeeReport']);
        Route::get('/reports/employee/export', [App\Http\Controllers\Admin\ReportController::class, 'exportEmployeeReport']);

        // Settings
        Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'index']);
        Route::post('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update']);

        // Devices
        Route::prefix('devices')->group(function () {
            Route::get('/', [DeviceController::class, 'index']);
            Route::get('/stats', [DeviceController::class, 'stats']);
            Route::get('/user/{user}', [DeviceController::class, 'userDevices']);
            Route::post('/{device}/approve', [DeviceController::class, 'approve']);
            Route::post('/{device}/reject', [DeviceController::class, 'reject']);
            Route::delete('/{device}', [DeviceController::class, 'revoke']);
        });

        // Leave Types
        Route::apiResource('leave-types', LeaveTypeController::class);

        // Leave Requests (Admin approval)
        Route::prefix('leave-requests')->group(function () {
            Route::get('/', [LeaveApprovalController::class, 'index']);
            Route::get('/stats', [LeaveApprovalController::class, 'stats']);
            Route::post('/{leaveRequest}/approve', [LeaveApprovalController::class, 'approve']);
            Route::post('/{leaveRequest}/reject', [LeaveApprovalController::class, 'reject']);
        });

        // Payroll
        Route::prefix('payroll')->group(function () {
            Route::get('/summary', [App\Http\Controllers\Admin\PayrollController::class, 'summary']);
            Route::get('/export', [App\Http\Controllers\Admin\PayrollController::class, 'export']);
            Route::get('/departments', [App\Http\Controllers\Admin\PayrollController::class, 'departments']);
        });

        // Salary Components
        Route::apiResource('salary-components', App\Http\Controllers\Admin\SalaryComponentController::class);

        // User Salary
        Route::get('/users/{user}/salary', [App\Http\Controllers\Admin\SalaryComponentController::class, 'getUserSalary']);
        Route::post('/users/{user}/salary', [App\Http\Controllers\Admin\SalaryComponentController::class, 'updateUserSalary']);

        // User Additional (Custom) Allowances
        Route::get('/users/{user}/additional-allowances', [UserAdditionalAllowanceController::class, 'index']);
        Route::post('/users/{user}/additional-allowances', [UserAdditionalAllowanceController::class, 'store']);
        Route::put('/users/{user}/additional-allowances/{allowance}', [UserAdditionalAllowanceController::class, 'update']);
        Route::delete('/users/{user}/additional-allowances/{allowance}', [UserAdditionalAllowanceController::class, 'destroy']);

        // Holidays
        Route::post('/holidays/sync', [App\Http\Controllers\Admin\HolidayController::class, 'sync']);
        Route::apiResource('holidays', App\Http\Controllers\Admin\HolidayController::class);
    });
});
