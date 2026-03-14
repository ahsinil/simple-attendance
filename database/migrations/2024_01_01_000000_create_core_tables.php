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
        // 1. Locations Table
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('e.g., OFFICE-JKT-01');
            $table->string('name');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('allowed_radius_m')->default(100)->comment('Allowed radius in meters');
            $table->string('timezone')->default('UTC')->comment('e.g., Asia/Jakarta');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Users Table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->foreignId('default_location_id')->nullable()
                ->constrained('locations')->onDelete('set null');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('base_salary', 15, 2)->nullable()->default(0)->comment('Base salary per month');
            $table->string('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Laravel Default Auth Tables
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 3. App Settings Table
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string')->comment('string, boolean, integer, json');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 4. Devices Table
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_fingerprint')->comment('Unique hash of device info');
            $table->string('device_name')->nullable()->comment('e.g., John\'s iPhone 15');
            $table->json('device_info')->nullable()->comment('JSON: model, OS, browser, etc.');
            $table->boolean('is_approved')->default(false);
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_fingerprint']);
            $table->index('device_fingerprint');
        });

        // 5. Notifications Table
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('message');
            $table->enum('channel', ['in_app', 'email', 'sms'])->default('in_app');
            $table->enum('type', ['info', 'warning', 'success', 'error'])->default('info');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        // Drop users before locations because of foreign key
        Schema::dropIfExists('users');
        Schema::dropIfExists('locations');
    }
};
