<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // 1. Create Essential Location (HQ)
            $hq = Location::updateOrCreate(
                ['code' => 'OFFICE-JKT-01'],
                [
                    'name' => 'Jakarta HQ',
                    'latitude' => -6.20000000,
                    'longitude' => 106.81666600,
                    'allowed_radius_m' => 150,
                    'timezone' => 'Asia/Jakarta',
                    'is_active' => true,
                ]
            );

            // 2. Create Super Admin
            $admin = User::updateOrCreate(
                ['email' => 'admin@email.id'],
                [
                    'name' => 'Admin User',
                    'password' => 'password',
                    'employee_id' => 'ADM-001',
                    'phone' => '081200000001',
                    'department' => 'Management',
                    'position' => 'System Administrator',
                    'default_location_id' => $hq->id,
                    'status' => 'active',
                    'base_salary' => 18000000,
                ]
            );
            $admin->forceFill(['email_verified_at' => now()])->save();
            $admin->syncRoles(['super_admin']);

            // 3. Create Display User
            $display = User::updateOrCreate(
                ['email' => 'display@email.id'],
                [
                    'name' => 'Barcode Display',
                    'password' => 'password',
                    'employee_id' => 'DISPLAY-001',
                    'phone' => null,
                    'department' => 'Operations',
                    'position' => 'Kiosk Display',
                    'default_location_id' => $hq->id,
                    'status' => 'active',
                    'base_salary' => 0,
                ]
            );
            $display->forceFill(['email_verified_at' => now()])->save();
            $display->syncRoles(['display_screen']);
        });

        $this->command?->info('Production dummy data seeded successfully.');
        $this->command?->info('Production Logins:');
        $this->command?->info('  Super Admin: admin@email.id / password');
        $this->command?->info('  Display Screen: display@email.id / password');
    }
}
