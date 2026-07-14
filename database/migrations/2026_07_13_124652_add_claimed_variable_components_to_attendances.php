<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Attendance;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->json('claimed_variable_components')->nullable()->after('variable_allowance_paid');
        });

        // Data migration
        $attendances = Attendance::where('variable_allowance_paid', true)->get();
        
        // Cache user variable components to avoid n+1
        $userComponents = [];
        
        foreach ($attendances as $att) {
            if (!isset($userComponents[$att->user_id])) {
                $user = User::with(['salaryComponents' => function($q) {
                    $q->whereHas('salaryComponent', function($q2) {
                        $q2->where('type', 'VARIABLE');
                    });
                }])->find($att->user_id);
                
                if ($user) {
                    $userComponents[$att->user_id] = $user->salaryComponents->pluck('salary_component_id')->toArray();
                } else {
                    $userComponents[$att->user_id] = [];
                }
            }
            
            // We use DB facade or raw query if we don't want to trigger model events or if it's simpler
            \Illuminate\Support\Facades\DB::table('attendances')
                ->where('id', $att->id)
                ->update(['claimed_variable_components' => json_encode($userComponents[$att->user_id])]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('claimed_variable_components');
        });
    }
};
