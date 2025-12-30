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
    }
};
