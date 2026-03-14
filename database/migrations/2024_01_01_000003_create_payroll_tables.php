<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['FIXED', 'VARIABLE'])->comment('FIXED=tunjangan tetap, VARIABLE=tunjangan tidak tetap');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('salary_component_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2)->default(0)->comment('Monthly amount for this user');
            $table->timestamps();

            $table->unique(['user_id', 'salary_component_id'], 'user_salary_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_salary_components');
        Schema::dropIfExists('salary_components');
    }
};
