<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        foreach (['TK', 'SD', 'SMP', 'SMA'] as $schoolLevel) {
            DB::table('student_eligibility_configs')->updateOrInsert(
                ['school_level' => $schoolLevel],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }

        $activeAcademicYearId = DB::table('academic_years')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if ($activeAcademicYearId === null) {
            return;
        }

        $sourceRequirements = DB::table('student_exam_requirements')
            ->join('student_exams', 'student_exams.id', '=', 'student_exam_requirements.student_exam_id')
            ->where('student_exams.academic_year_id', $activeAcademicYearId)
            ->where('student_exams.is_active', true)
            ->whereNull('student_exam_requirements.student_eligibility_config_id')
            ->orderBy('student_exam_requirements.id')
            ->select('student_exam_requirements.*')
            ->get();
        $canonicalRequirements = [];

        foreach ($sourceRequirements as $sourceRequirement) {
            $memberIds = DB::table('student_exam_requirement_payment_types')
                ->where('student_exam_requirement_id', $sourceRequirement->id)
                ->orderBy('payment_type_id')
                ->pluck('payment_type_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $key = implode('|', [
                $sourceRequirement->school_level,
                $sourceRequirement->payment_type_id,
                $sourceRequirement->billing_frequency,
            ]);
            $signature = [
                'start_month' => $sourceRequirement->start_month,
                'end_month' => $sourceRequirement->end_month,
                'required_percentage' => (string) $sourceRequirement->required_percentage,
                'is_active' => (bool) $sourceRequirement->is_active,
                'member_ids' => $memberIds,
            ];

            if (isset($canonicalRequirements[$key])) {
                if ($canonicalRequirements[$key]['signature'] !== $signature) {
                    throw new RuntimeException("Conflicting eligibility criteria found for {$key}.");
                }

                continue;
            }

            $canonicalRequirements[$key] = [
                'source' => $sourceRequirement,
                'signature' => $signature,
                'member_ids' => $memberIds,
            ];
        }

        DB::transaction(function () use ($canonicalRequirements, $now): void {
            $configIds = DB::table('student_eligibility_configs')->pluck('id', 'school_level');

            foreach ($canonicalRequirements as $criterion) {
                $source = $criterion['source'];

                if (DB::table('student_exam_requirements')->where('source_student_exam_requirement_id', $source->id)->exists()) {
                    continue;
                }

                $requirementId = DB::table('student_exam_requirements')->insertGetId([
                    'student_exam_id' => null,
                    'student_eligibility_config_id' => $configIds[$source->school_level],
                    'source_student_exam_requirement_id' => $source->id,
                    'school_level' => $source->school_level,
                    'payment_type_id' => $source->payment_type_id,
                    'billing_frequency' => $source->billing_frequency,
                    'start_month' => $source->start_month,
                    'end_month' => $source->end_month,
                    'required_percentage' => $source->required_percentage,
                    'is_active' => $source->is_active,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($criterion['member_ids'] !== []) {
                    DB::table('student_exam_requirement_payment_types')->insert(array_map(
                        fn (int $paymentTypeId): array => [
                            'student_exam_requirement_id' => $requirementId,
                            'payment_type_id' => $paymentTypeId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $criterion['member_ids'],
                    ));
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('student_exam_requirements')
            ->whereNotNull('student_eligibility_config_id')
            ->delete();
    }
};
