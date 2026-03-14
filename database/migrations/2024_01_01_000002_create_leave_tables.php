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
        // 1. Leave Types Table
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // "Annual Leave", "Sick Leave"
            $table->string('code')->unique();                // "ANNUAL", "SICK"
            $table->integer('default_days')->default(0);     // Annual allocation
            $table->boolean('is_paid')->default(true);       // Paid or unpaid leave
            $table->boolean('requires_approval')->default(true);
            $table->string('color')->default('#4CAF50');     // UI display color
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Leave Requests Table
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 4, 1);         // Supports half days: 2.5
            $table->text('reason');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // 3. Leave Balances Table
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained()->onDelete('cascade');
            $table->year('year');
            $table->decimal('allocated_days', 5, 1)->default(0);  // Total allocation
            $table->decimal('used_days', 5, 1)->default(0);       // Approved leaves
            $table->decimal('pending_days', 5, 1)->default(0);    // Pending requests
            $table->timestamps();

            // Each user has one balance per leave type per year
            $table->unique(['user_id', 'leave_type_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};
