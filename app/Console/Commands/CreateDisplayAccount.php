<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateDisplayAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:create-display-account 
                            {name? : The name for the display account}
                            {--email= : The email for the account}
                            {--password= : The password for the account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a display screen account for showing barcodes on kiosks/TVs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Ensure display_screen role exists
        $role = Role::firstOrCreate(
            ['name' => 'display_screen', 'guard_name' => 'web']
        );

        // Ensure barcode.display permission exists and is assigned to role
        $permission = Permission::firstOrCreate(
            ['name' => 'barcode.display', 'guard_name' => 'web']
        );
        
        if (!$role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        // Get or generate account details
        $name = $this->argument('name') ?? $this->ask('Display account name', 'Display Screen 1');
        $email = $this->option('email') ?? $this->ask('Email', 'display@attendance.local');
        $password = $this->option('password') ?? $this->secret('Password (leave empty for auto-generated)');

        if (empty($password)) {
            $password = \Str::random(16);
            $this->info("Generated password: {$password}");
        }

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error("A user with email '{$email}' already exists.");
            return Command::FAILURE;
        }

        // Create the user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('display_screen');

        $this->newLine();
        $this->info('✅ Display screen account created successfully!');
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $name],
                ['Email', $email],
                ['Password', $password],
                ['Role', 'display_screen'],
            ]
        );
        $this->newLine();
        $this->info('This account can only access the barcode display page.');

        return Command::SUCCESS;
    }
}
