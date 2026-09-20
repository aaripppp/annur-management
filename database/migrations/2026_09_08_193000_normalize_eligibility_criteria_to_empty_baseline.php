<?php

use App\Models\StudentEligibilityConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-time baseline reset: eligibility criteria that were materialized
     * active by previous implementations are normalized to the empty baseline
     * (0 active per jenjang: TK/SD/SMP/SMA). Only the persisted `is_active`
     * flags on student_exam_requirements are updated. Requirement rows, pooled
     * memberships and thresholds stay intact; no financial or student table is
     * touched. Idempotent.
     */
    public function up(): void
    {
        StudentEligibilityConfig::normalizeToEmptyBaseline();
    }

    /**
     * Intentionally non-reversible: the prior per-row active flags cannot be
     * reconstructed. Structural eligibility data is left untouched, so an
     * idempotent initialization may restore the default structure on demand.
     */
    public function down(): void {}
};
