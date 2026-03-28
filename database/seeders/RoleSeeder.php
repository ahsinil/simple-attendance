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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'attendance.view-own',
            'attendance.create',
            'history.view',
            'requests.view',
            'requests.create',
            'leaves.view',
            'leaves.create',
            'schedules.view',
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
            'admin.users.delete',
            'admin.roles.view',
            'admin.roles.create',
            'admin.roles.update',
            'admin.roles.delete',
            'admin.shifts.view',
            'admin.shifts.create',
            'admin.shifts.update',
            'admin.shifts.delete',
            'admin.leave-types.view',
            'admin.leave-types.create',
            'admin.leave-types.update',
            'admin.leave-types.delete',
            'admin.locations.view',
            'admin.locations.create',
            'admin.locations.update',
            'admin.locations.delete',
            'admin.reports.view',
            'admin.reports.export',
            'admin.settings.view',
            'admin.settings.update',
            'admin.devices.view',
            'admin.devices.approve',
            'admin.devices.delete',
            'admin.payroll.view',
            'admin.payroll.export',
            'admin.salary.view',
            'admin.salary.manage',
            'barcode.display',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions([
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
            'admin.payroll.view',
            'admin.payroll.export',
            'admin.salary.view',
            'admin.salary.manage',
            'barcode.display',
        ]);

        $supervisor = Role::findOrCreate('supervisor', 'web');
        $supervisor->syncPermissions([
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

        $employee = Role::findOrCreate('employee', 'web');
        $employee->syncPermissions([
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

        $displayScreen = Role::findOrCreate('display_screen', 'web');
        $displayScreen->syncPermissions([
            'barcode.display',
        ]);
    }
}
