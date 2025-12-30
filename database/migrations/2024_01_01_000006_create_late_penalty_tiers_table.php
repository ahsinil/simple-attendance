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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('late_penalty_tiers');
    }
};
