<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run seeders in order
        $this->call([
            RoleSeeder::class,
            AppSettingSeeder::class,
            LatePenaltyTierSeeder::class,
            ShiftSeeder::class,
        ]);

        // Create default admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'employee_id' => 'EMP-001',
            'status' => 'active',
        ]);
        $admin->assignRole('super_admin');

        // Create test employee
        $employee = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'employee_id' => 'EMP-002',
            'department' => 'Engineering',
            'position' => 'Software Developer',
            'status' => 'active',
        ]);
        $employee->assignRole('employee');

        $this->command->info('Default users created:');
        $this->command->info('  Admin: admin@example.com / password');
        $this->command->info('  Employee: john@example.com / password');
    }
}
