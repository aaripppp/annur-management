<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The canonical pooled yearly structure remains in place, but the stored
     * default active state is now inactive. Only the eligibility "default"
     * storage on student_eligibility_configs is updated: persisted requirement
     * rows and their active states are left untouched so intentional selections
     * stay intact. The explicit "Reset Kriteria" action restores an empty state.
     */
    public function up(): void
    {
        Schema::table('student_eligibility_configs', function (Blueprint $table) {
            $table->boolean('default_pooled_is_active')->default(false)->change();
        });

        DB::table('student_eligibility_configs')->update(['default_pooled_is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('student_eligibility_configs', function (Blueprint $table) {
            $table->boolean('default_pooled_is_active')->default(true)->change();
        });

        DB::table('student_eligibility_configs')->update(['default_pooled_is_active' => true]);
    }
};
