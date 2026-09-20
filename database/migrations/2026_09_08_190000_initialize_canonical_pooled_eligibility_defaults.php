<?php

use App\Models\StudentEligibilityConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the canonical pooled defaults to every eligibility config and
     * materialize the canonical pooled yearly structure for every applicable
     * level. This is additive and idempotent: existing customized criteria are
     * left untouched, and any level that already owns a pooled yearly
     * requirement is left as-is.
     */
    public function up(): void
    {
        Schema::table('student_eligibility_configs', function (Blueprint $table) {
            $table->json('default_pooled_payment_type_ids')->nullable();
            $table->decimal('default_pooled_threshold', 8, 2)->default(50);
            $table->boolean('default_pooled_is_active')->default(true);
        });

        StudentEligibilityConfig::initializeCanonicalDefaults();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_eligibility_configs', function (Blueprint $table) {
            $table->dropColumn(['default_pooled_is_active', 'default_pooled_threshold', 'default_pooled_payment_type_ids']);
        });
    }
};
