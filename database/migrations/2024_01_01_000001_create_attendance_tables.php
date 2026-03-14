<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Shifts Table
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('e.g., SHIFT-MORNING');
            $table->string('name');
            $table->time('start_time')->comment('Expected start time');
            $table->time('end_time')->comment('Expected end time');
            $table->integer('late_after_min')->default(15)->comment('Grace period in minutes');
            $table->integer('early_checkout_min')->default(0)->comment('Allowed early checkout minutes');
            $table->boolean('allow_checkout_before_end')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. User Schedules Table
        Schema::create('user_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('Null means indefinite');
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
        });

        // 3. Holidays Table
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->enum('type', ['NATIONAL', 'COMPANY', 'OPTIONAL'])->default('NATIONAL');
            $table->decimal('overtime_multiplier', 3, 1)->default(2.0)->comment('e.g., 2.0 = 200% pay');
            $table->timestamps();

            $table->index('date');
        });

        // 4. Late Penalty Tiers Table
        Schema::create('late_penalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('e.g., TIER-1');
            $table->string('name')->comment('e.g., Warning, Deduction');
            $table->integer('min_late_min')->comment('Minimum late minutes');
            $table->integer('max_late_min')->nullable()->comment('Max late minutes, null = unlimited');
            $table->enum('penalty_type', ['WARNING', 'DEDUCTION', 'HALF_DAY', 'ABSENT'])->default('WARNING');
            $table->decimal('deduction_pct', 5, 2)->default(0)->comment('Percentage of daily pay to deduct');
            $table->timestamps();
        });

        // 5. Attendances Table
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('scan_time');
            $table->enum('check_type', ['IN', 'OUT']);
            
            // GPS data
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();
            $table->decimal('gps_accuracy_m', 8, 2)->nullable()->comment('GPS accuracy in meters');
            $table->decimal('distance_m', 10, 2)->nullable()->comment('Distance from location center');
            
            // Barcode verification
            $table->string('time_slot')->nullable()->comment('Time slot used for barcode');
            
            // Device info
            $table->string('ip_address')->nullable();
            $table->string('device_id')->nullable()->comment('Device fingerprint for audit');
            
            // Status and calculation
            $table->enum('status', ['ON_TIME', 'LATE', 'EARLY', 'ABSENT', 'EXCUSED'])->default('ON_TIME');
            $table->integer('late_min')->default(0)->comment('Minutes late');
            $table->integer('early_leave_min')->default(0)->comment('Minutes left early');
            $table->integer('work_minutes')->default(0)->comment('Total work minutes');
            $table->enum('penalty_tier', ['NONE', 'WARNING', 'DEDUCTION', 'HALF_DAY', 'ABSENT'])->default('NONE');
            
            // Overtime
            $table->boolean('is_holiday')->default(false);
            $table->integer('overtime_min')->default(0);
            $table->decimal('overtime_multiplier', 3, 1)->default(1.0);
            
            // Manual attendance
            $table->enum('method', ['AUTO', 'MANUAL', 'SYSTEM'])->default('AUTO');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();

            $table->index(['user_id', 'scan_time']);
            $table->index(['location_id', 'scan_time']);
            $table->index('status');
        });

        // 6. Attendance Requests Table
        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('request_time');
            $table->enum('check_type', ['IN', 'OUT']);
            
            // GPS data at time of request
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();
            $table->decimal('distance_m', 10, 2)->nullable();
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            
            // Request details
            $table->text('reason');
            $table->string('photo_path')->nullable();
            $table->string('ip_address')->nullable(); // From 2024_01_01_000015
            $table->string('failure_reason')->nullable()->comment('Why auto-attendance failed');
            
            // Approval status
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        // 7. Attendance Logs Table
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('attendance_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('attendance_request_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('action', [
                'AUTO_CHECKIN',
                'AUTO_CHECKOUT',
                'MANUAL_REQUEST',
                'MANUAL_APPROVE',
                'MANUAL_REJECT',
                'SYSTEM_ABSENT',
                'ADMIN_OVERRIDE'
            ]);
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null')
                ->comment('User who performed the action');
            $table->text('reason')->nullable();
            $table->json('payload')->nullable()->comment('Additional data as JSON');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('late_penalty_tiers');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('user_schedules');
        Schema::dropIfExists('shifts');
    }
};
