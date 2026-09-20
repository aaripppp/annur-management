<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Support\ClassPromotionMapping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentGraduationMarkerService
{
    /**
     * @var array<int, int>
     */
    private const CONTINUATION_LEVELS = [
        -1 => 1,
        6 => 7,
        9 => 10,
    ];

    /**
     * @return array{target: StudentAcademicEnrollment|null, source: StudentAcademicEnrollment|null, reusable: bool, financial_conflict: bool}
     */
    public function assess(
        Student $student,
        AcademicYear $targetYear,
        SchoolClass $importClass,
        bool $lockForUpdate = false,
    ): array {
        $targetQuery = $student->enrollments()
            ->with('schoolClass')
            ->where('academic_year_id', $targetYear->id);

        if ($lockForUpdate) {
            $targetQuery->lockForUpdate();
        }

        $target = $targetQuery->first();

        if (! $target) {
            return [
                'target' => null,
                'source' => null,
                'reusable' => false,
                'financial_conflict' => false,
            ];
        }

        $sourceQuery = $student->enrollments()
            ->with(['academicYear', 'schoolClass'])
            ->whereHas('academicYear', fn (Builder $query) => $query->whereDate('start_date', '<', $targetYear->start_date))
            ->orderByDesc(
                AcademicYear::query()
                    ->select('start_date')
                    ->whereColumn('academic_years.id', 'student_academic_enrollments.academic_year_id')
                    ->limit(1)
            );

        if ($lockForUpdate) {
            $sourceQuery->lockForUpdate();
        }

        $source = $sourceQuery->first();
        $financialConflict = $target->status === 'lulus'
            && $this->hasTargetYearFinancialData($student, $targetYear);
        $graduationLevel = $target->schoolClass?->level;
        $expectedContinuationLevel = $graduationLevel === null
            ? null
            : self::CONTINUATION_LEVELS[$graduationLevel] ?? null;
        $storedStudentStatus = StudentStatus::tryFrom((string) $student->getRawOriginal('status'));

        $reusable = $storedStudentStatus === StudentStatus::Graduated
            && $target->status === 'lulus'
            && $targetYear->promotion_processed_at !== null
            && $target->school_class_id !== null
            && $target->school_class_id === $student->class_id
            && $source !== null
            && in_array($source->status, ['active', 'lulus'], true)
            && $source->school_class_id === $target->school_class_id
            && $graduationLevel !== null
            && ClassPromotionMapping::isGraduationLevel($graduationLevel)
            && $expectedContinuationLevel !== null
            && $importClass->level === $expectedContinuationLevel
            && $target->created_at?->equalTo($target->updated_at) === true
            && ! $financialConflict;

        return [
            'target' => $target,
            'source' => $source,
            'reusable' => $reusable,
            'financial_conflict' => $financialConflict,
        ];
    }

    private function hasTargetYearFinancialData(Student $student, AcademicYear $targetYear): bool
    {
        $hasBills = $student->bills()
            ->where(function (Builder $query) use ($targetYear): void {
                $query->where('academic_year', $targetYear->year)
                    ->orWhere(fn (Builder $query) => $this->constrainPeriodToAcademicYear($query, $targetYear));
            })
            ->exists();

        if ($hasBills) {
            return true;
        }

        return PaymentDetail::query()
            ->whereHas('payment', fn (Builder $query) => $query->where('student_id', $student->id))
            ->where(function (Builder $query) use ($targetYear): void {
                $query->where('academic_year', $targetYear->year)
                    ->orWhere(fn (Builder $query) => $this->constrainPeriodToAcademicYear($query, $targetYear));
            })
            ->exists();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function constrainPeriodToAcademicYear(Builder $query, AcademicYear $academicYear): void
    {
        $startYear = $academicYear->start_date->year;
        $startMonth = $academicYear->start_date->month;
        $endYear = $academicYear->end_date->year;
        $endMonth = $academicYear->end_date->month;

        if ($startYear === $endYear) {
            $query->where('period_year', $startYear)
                ->whereBetween('period_month', [$startMonth, $endMonth]);

            return;
        }

        $query->where(function (Builder $query) use ($startYear, $startMonth, $endYear, $endMonth): void {
            $query->where(function (Builder $query) use ($startYear, $startMonth): void {
                $query->where('period_year', $startYear)
                    ->where('period_month', '>=', $startMonth);
            })->orWhere(function (Builder $query) use ($endYear, $endMonth): void {
                $query->where('period_year', $endYear)
                    ->where('period_month', '<=', $endMonth);
            });

            if ($endYear - $startYear > 1) {
                $query->orWhereBetween('period_year', [$startYear + 1, $endYear - 1]);
            }
        });
    }
}
