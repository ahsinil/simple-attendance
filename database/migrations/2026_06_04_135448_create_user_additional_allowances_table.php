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
        Schema::create('user_additional_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->comment('Nama tunjangan bebas, contoh: Bonus Proyek, THR');
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('period_year')->comment('Tahun periode, contoh: 2026');
            $table->unsignedTinyInteger('period_month')->comment('Bulan periode 1-12');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['user_id', 'period_year', 'period_month'], 'uaa_user_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_additional_allowances');
    }
};
