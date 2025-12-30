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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
