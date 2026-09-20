<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Exceptions\BlockedPromotionException;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Support\ClassPromotionMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClassPromotionService
{
    public function __construct(
        protected BillGenerationService $billGenerationService,
    ) {}

    /**
     * Build preview data for class promotion.
     *
     * @return array{students: Collection, grouped: Collection, summary: array{total_promoted: int, total_graduated: int, total_blocked: int}}
     */
    public function getPreviewData(AcademicYear $fromYear, AcademicYear $toYear): array
    {
        $activeEnrollments = StudentAcademicEnrollment::query()
            ->where('academic_year_id', $fromYear->id)
            ->where('status', 'active')
            ->with(['student', 'schoolClass'])
            ->get();

        $students = $activeEnrollments->map(function (StudentAcademicEnrollment $enrollment): ?Student {
            $student = $enrollment->student;

            if ($student !== null) {
                $student->setRelation('schoolClass', $enrollment->schoolClass);
            }

            return $student;
        })->filter();

        $grouped = $students->groupBy(fn (Student $student) => $student->schoolClass?->id)->map(function ($group) {
            $class = $group->first()->schoolClass;
            $decision = $class ? ClassPromotionMapping::runtimeDecisionFor($class) : null;

            $isBlocked = $decision !== null && $decision['action'] === 'blocked';

            return [
                'current_class' => $class,
                'students' => $group,
                'count' => $group->count(),
                'target_class' => $decision['target'] ?? null,
                'target_label' => $isBlocked ? ($decision['label'] ?? 'Aturan belum dikonfigurasi') : ($decision['label'] ?? 'Lulus'),
                'is_graduation' => $decision !== null && $decision['action'] === 'graduate',
                'is_blocked' => $isBlocked,
                'blocked_reason' => $isBlocked ? ($decision['reason'] ?? null) : null,
                'decision_source' => $decision['source'] ?? 'rule',
            ];
        })->values();

        $totalGraduated = $grouped->where('is_graduation', true)->sum('count');
        $totalPromoted = $grouped->where('is_graduation', false)->where('is_blocked', false)->sum('count');
        $totalBlocked = $grouped->where('is_blocked', true)->sum('count');

        return [
            'students' => $students,
            'grouped' => $grouped,
            'summary' => [
                'total_promoted' => $totalPromoted,
                'total_graduated' => $totalGraduated,
                'total_blocked' => $totalBlocked,
            ],
        ];
    }

    /**
     * Check if promotion has already been processed for the target year.
     */
    public function isAlreadyProcessed(AcademicYear $toYear): bool
    {
        return $toYear->promotion_processed_at !== null;
    }

    /**
     * Process the class promotion in a single DB transaction.
     *
     * Server-side preflight: when any source class has no valid active rule
     * (missing, inactive, or with an unavailable target), the whole promotion
     * is rejected with a BlockedPromotionException BEFORE any write. No
     * student is promoted/graduated, no enrollment is created, no bill
     * generated, and the active AcademicYear is unchanged.
     *
     * @return array{promoted: int, graduated: int, blocked: int}
     */
    public function processPromotion(AcademicYear $fromYear, AcademicYear $toYear): array
    {
        if ($this->isAlreadyProcessed($toYear)) {
            return ['promoted' => 0, 'graduated' => 0, 'blocked' => 0];
        }

        $preview = $this->getPreviewData($fromYear, $toYear);

        $blockedMappings = $this->blockedMappings($preview['grouped']);

        if ($blockedMappings !== []) {
            throw new BlockedPromotionException($blockedMappings);
        }

        $students = $preview['students'];

        $promoted = 0;
        $graduated = 0;
        $blocked = 0;

        DB::transaction(function () use ($students, $fromYear, $toYear, &$promoted, &$graduated, &$blocked) {
            foreach ($students as $student) {
                $class = $student->schoolClass;

                if ($class === null) {
                    continue;
                }

                $decision = ClassPromotionMapping::runtimeDecisionFor($class);

                if ($decision['action'] === 'graduate') {
                    $this->graduateStudent($student, $class, $fromYear, $toYear);
                    $graduated++;
                } elseif ($decision['target'] !== null && $decision['action'] === 'promote') {
                    $this->promoteStudent($student, $decision['target'], $fromYear, $toYear);
                    $promoted++;
                } else {
                    $blocked++;
                }
            }

            AcademicYear::deactivateAll();
            $toYear->update(['is_active' => true]);
            $toYear->update(['promotion_processed_at' => now()]);
        });

        return compact('promoted', 'graduated', 'blocked');
    }

    /**
     * Collect blocked source classes with the actionable reason label.
     *
     * @param  Collection<int, array{current_class: ?SchoolClass, count: int, is_blocked: bool, target_label: string}>  $grouped
     * @return list<array{source_class: ?SchoolClass, reason_label: string, student_count: int}>
     */
    public function blockedMappings(Collection $grouped): array
    {
        return $grouped
            ->where('is_blocked', true)
            ->map(fn (array $group): array => [
                'source_class' => $group['current_class'],
                'reason_label' => $group['target_label'] ?? 'Aturan belum dikonfigurasi',
                'student_count' => $group['count'],
            ])
            ->values()
            ->all();
    }

    protected function promoteStudent(Student $student, SchoolClass $targetClass, AcademicYear $fromYear, AcademicYear $toYear): void
    {
        $existingTargetEnrollment = StudentAcademicEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $toYear->id)
            ->first();

        if ($existingTargetEnrollment) {
            return;
        }

        $student->update([
            'class_id' => $targetClass->id,
            'status' => StudentStatus::Active,
        ]);

        $student->refresh();

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $toYear->id,
            'school_class_id' => $targetClass->id,
            'status' => 'active',
        ]);

        $this->billGenerationService->generateBillbook($student, $toYear->start_date);
    }

    protected function graduateStudent(Student $student, SchoolClass $sourceClass, AcademicYear $fromYear, AcademicYear $toYear): void
    {
        $existingTargetEnrollment = StudentAcademicEnrollment::where('student_id', $student->id)
            ->where('academic_year_id', $toYear->id)
            ->first();

        if ($existingTargetEnrollment) {
            return;
        }

        $student->update(['status' => StudentStatus::Graduated]);

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $toYear->id,
            'school_class_id' => $sourceClass->id,
            'status' => 'lulus',
        ]);
    }
}
