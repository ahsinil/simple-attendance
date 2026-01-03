<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // =========================================
            // EMPLOYEE PORTAL NAVIGATION PERMISSIONS
            // =========================================
            
            // Dashboard (Employee)
            'dashboard.view',              // View employee dashboard
            
            // Attendance
            'attendance.view-own',         // View own attendance records
            'attendance.create',           // Check in/out via QR/barcode
            
            // History
            'history.view',                // View attendance history
            
            // My Requests
            'requests.view',               // View own requests list
            'requests.create',             // Submit manual attendance request
            
            // My Leaves  
            'leaves.view',                 // View own leave requests
            'leaves.create',               // Submit leave request
            
            // My Schedules
            'schedules.view',              // View own schedules
            
            // =========================================
            // ADMIN PANEL NAVIGATION PERMISSIONS
            // =========================================
            
            // Admin Dashboard
            'admin.dashboard.view',        // View admin dashboard
            
            // Admin Requests
            'admin.requests.view',         // View all attendance requests
            'admin.requests.approve',      // Approve attendance requests
            'admin.requests.reject',       // Reject attendance requests
            
            // Admin Leave Requests
            'admin.leaves.view',           // View all leave requests
            'admin.leaves.approve',        // Approve leave requests
            'admin.leaves.reject',         // Reject leave requests
            
            // Admin Users
            'admin.users.view',            // View users list
            'admin.users.create',          // Create new users
            'admin.users.update',          // Update users
            'admin.users.delete',          // Delete users
            
            // Admin Roles
            'admin.roles.view',            // View roles list
            'admin.roles.create',          // Create new roles
            'admin.roles.update',          // Update roles
            'admin.roles.delete',          // Delete roles
            
            // Admin Shifts
            'admin.shifts.view',           // View shifts list
            'admin.shifts.create',         // Create new shifts
            'admin.shifts.update',         // Update shifts
            'admin.shifts.delete',         // Delete shifts
            
            // Admin Leave Types
            'admin.leave-types.view',      // View leave types list
            'admin.leave-types.create',    // Create new leave types
            'admin.leave-types.update',    // Update leave types
            'admin.leave-types.delete',    // Delete leave types
            
            // Admin Locations
            'admin.locations.view',        // View locations list
            'admin.locations.create',      // Create new locations
            'admin.locations.update',      // Update locations
            'admin.locations.delete',      // Delete locations
            
            // Admin Reports
            'admin.reports.view',          // View reports page
            'admin.reports.export',        // Export reports to Excel
            
            // Admin Settings
            'admin.settings.view',         // View system settings
            'admin.settings.update',       // Update system settings
            
            // =========================================
            // SPECIAL PERMISSIONS
            // =========================================
            'barcode.display',             // Access barcode display page (kiosks)
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        
        // Super Admin - has all permissions
        $superAdmin = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - manage attendance approvals, users, shifts, locations
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo([
            // Employee portal
            'dashboard.view',
            'attendance.view-own',
            'attendance.create',
            'history.view',
            'requests.view',
            'requests.create',
            'leaves.view',
            'leaves.create',
            'schedules.view',
            // Admin panel
            'admin.dashboard.view',
            'admin.requests.view',
            'admin.requests.approve',
            'admin.requests.reject',
            'admin.leaves.view',
            'admin.leaves.approve',
            'admin.leaves.reject',
            'admin.users.view',
            'admin.users.create',
            'admin.users.update',
            'admin.shifts.view',
            'admin.shifts.create',
            'admin.shifts.update',
            'admin.shifts.delete',
            'admin.leave-types.view',
            'admin.locations.view',
            'admin.locations.create',
            'admin.locations.update',
            'admin.reports.view',
            'admin.reports.export',
            'barcode.display',
        ]);

        // Supervisor - view team attendance, recommend approvals
        $supervisor = Role::create(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->givePermissionTo([
            // Employee portal
            'dashboard.view',
            'attendance.view-own',
            'attendance.create',
            'history.view',
            'requests.view',
            'requests.create',
            'leaves.view',
            'leaves.create',
            'schedules.view',
            // Admin panel
            'admin.dashboard.view',
            'admin.requests.view',
            'admin.users.view',
            'admin.reports.view',
        ]);

        // Employee - basic attendance operations
        $employee = Role::create(['name' => 'employee', 'guard_name' => 'web']);
        $employee->givePermissionTo([
            'dashboard.view',
            'attendance.view-own',
            'attendance.create',
            'history.view',
            'requests.view',
            'requests.create',
            'leaves.view',
            'leaves.create',
            'schedules.view',
        ]);

        // Display Screen - only for displaying barcodes on kiosks/TVs
        $displayScreen = Role::create(['name' => 'display_screen', 'guard_name' => 'web']);
        $displayScreen->givePermissionTo([
            'barcode.display',
        ]);
    }
}

