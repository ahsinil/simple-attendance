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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('department')->nullable()->after('phone');
            $table->string('position')->nullable()->after('department');
            $table->foreignId('default_location_id')->nullable()->after('position')
                ->constrained('locations')->onDelete('set null');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('default_location_id');
            $table->string('avatar')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_location_id']);
            $table->dropColumn([
                'employee_id',
                'phone',
                'department',
                'position',
                'default_location_id',
                'status',
                'avatar'
            ]);
        });
    }
};
