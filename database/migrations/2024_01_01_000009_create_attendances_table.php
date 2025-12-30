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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
