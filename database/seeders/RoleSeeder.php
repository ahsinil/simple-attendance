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
            // Attendance permissions
            'attendance.view-own',
            'attendance.view-all',
            'attendance.create',
            'attendance.manual-request',
            'attendance.approve-request',
            'attendance.reject-request',
            'attendance.override',
            
            // User management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            
            // Shift management
            'shifts.view',
            'shifts.create',
            'shifts.update',
            'shifts.delete',
            
            // Location management
            'locations.view',
            'locations.create',
            'locations.update',
            'locations.delete',
            
            // Reports
            'reports.view',
            'reports.export',
            
            // Settings
            'settings.view',
            'settings.update',
            
            // Barcode display
            'barcode.display',
            
            // Role management
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
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
            'attendance.view-all',
            'attendance.approve-request',
            'attendance.reject-request',
            'attendance.override',
            'users.view',
            'users.create',
            'users.update',
            'shifts.view',
            'shifts.create',
            'shifts.update',
            'shifts.delete',
            'locations.view',
            'locations.create',
            'locations.update',
            'reports.view',
            'reports.export',
            'barcode.display',
        ]);

        // Supervisor - view team attendance, recommend approvals
        $supervisor = Role::create(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->givePermissionTo([
            'attendance.view-own',
            'attendance.view-all',
            'attendance.create',
            'attendance.manual-request',
            'users.view',
            'shifts.view',
            'locations.view',
            'reports.view',
        ]);

        // Employee - basic attendance operations
        $employee = Role::create(['name' => 'employee', 'guard_name' => 'web']);
        $employee->givePermissionTo([
            'attendance.view-own',
            'attendance.create',
            'attendance.manual-request',
            'shifts.view',
            'locations.view',
        ]);
    }
}
