<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class StudentUnitScope
{
    private const ENROLLMENT_ACTIVE = 'active';

    public function classBelongsToUser(?int $schoolClassId, User $user): bool
    {
        if (! $user->isTuUnit()) {
            return true;
        }

        if ($schoolClassId === null || $user->school_level === null) {
            return false;
        }

        return SchoolClass::query()
            ->whereKey($schoolClassId)
            ->whereIn('level', $user->school_level->classLevels())
            ->exists();
    }

    /**
     * Apply the authenticated user's mandatory Student visibility boundary.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function applyForUser(
        Builder $query,
        User $user,
        ?AcademicYear $activeYear = null,
        ?int $effectiveClassId = null,
    ): Builder {
        if (! $user->isTuUnit()) {
            return $query;
        }

        if ($user->school_level === null) {
            return $query->whereRaw('0 = 1');
        }

        return $this->apply($query, $user->school_level, $activeYear, $effectiveClassId);
    }

    /**
     * Scope Students by their effective class using enrollment precedence.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function apply(
        Builder $query,
        SchoolLevel $schoolLevel,
        ?AcademicYear $activeYear = null,
        ?int $effectiveClassId = null,
    ): Builder {
        $activeYear ??= AcademicYear::active();

        return $query->where(function (Builder $visibility) use ($schoolLevel, $activeYear, $effectiveClassId): void {
            if ($activeYear !== null) {
                $visibility->whereExists(function (QueryBuilder $current) use ($schoolLevel, $activeYear, $effectiveClassId): void {
                    $this->currentEnrollmentQuery($current, $activeYear);
                    $this->applyCandidateClass($current, 'effective_current', 'current_class', $schoolLevel, $effectiveClassId);
                });

                $visibility->orWhere(function (Builder $futureStudent) use ($schoolLevel, $activeYear, $effectiveClassId): void {
                    $futureStudent
                        ->where('students.status', StudentStatus::Active->value)
                        ->whereNotExists(fn (QueryBuilder $current) => $this->currentEnrollmentQuery($current, $activeYear))
                        ->whereExists(function (QueryBuilder $future) use ($schoolLevel, $activeYear, $effectiveClassId): void {
                            $future
                                ->selectRaw('1')
                                ->from('student_academic_enrollments as effective_future')
                                ->join('academic_years as future_year', 'future_year.id', '=', 'effective_future.academic_year_id')
                                ->join('school_classes as future_class', 'future_class.id', '=', 'effective_future.school_class_id')
                                ->whereColumn('effective_future.student_id', 'students.id')
                                ->where('effective_future.status', self::ENROLLMENT_ACTIVE)
                                ->whereDate('future_year.start_date', '>', $activeYear->start_date)
                                ->whereNotExists(function (QueryBuilder $earlier) use ($activeYear): void {
                                    $earlier
                                        ->selectRaw('1')
                                        ->from('student_academic_enrollments as earlier_future')
                                        ->join('academic_years as earlier_year', 'earlier_year.id', '=', 'earlier_future.academic_year_id')
                                        ->whereColumn('earlier_future.student_id', 'effective_future.student_id')
                                        ->where('earlier_future.status', self::ENROLLMENT_ACTIVE)
                                        ->whereDate('earlier_year.start_date', '>', $activeYear->start_date)
                                        ->where(function (QueryBuilder $precedence): void {
                                            $precedence
                                                ->whereColumn('earlier_year.start_date', '<', 'future_year.start_date')
                                                ->orWhere(function (QueryBuilder $sameYear): void {
                                                    $sameYear
                                                        ->whereColumn('earlier_year.start_date', 'future_year.start_date')
                                                        ->whereColumn('earlier_future.id', '<', 'effective_future.id');
                                                });
                                        });
                                });

                            $this->applyCandidateClass($future, 'effective_future', 'future_class', $schoolLevel, $effectiveClassId, false);
                        });
                });
            }

            $terminalMethod = $activeYear === null ? 'where' : 'orWhere';
            $visibility->{$terminalMethod}(function (Builder $terminalStudent) use ($schoolLevel, $activeYear, $effectiveClassId): void {
                $terminalStudent->whereIn('students.status', $this->terminalStatuses());

                if ($activeYear !== null) {
                    $terminalStudent->whereNotExists(fn (QueryBuilder $current) => $this->currentEnrollmentQuery($current, $activeYear));
                }

                $terminalStudent->whereExists(function (QueryBuilder $attended) use ($schoolLevel, $activeYear, $effectiveClassId): void {
                    $attended
                        ->selectRaw('1')
                        ->from('student_academic_enrollments as effective_attended')
                        ->join('academic_years as attended_year', 'attended_year.id', '=', 'effective_attended.academic_year_id')
                        ->join('school_classes as attended_class', 'attended_class.id', '=', 'effective_attended.school_class_id')
                        ->whereColumn('effective_attended.student_id', 'students.id')
                        ->where('effective_attended.status', self::ENROLLMENT_ACTIVE)
                        ->when($activeYear !== null, fn (QueryBuilder $query) => $query
                            ->whereDate('attended_year.start_date', '<=', $activeYear->start_date))
                        ->whereNotExists(function (QueryBuilder $later) use ($activeYear): void {
                            $later
                                ->selectRaw('1')
                                ->from('student_academic_enrollments as later_attended')
                                ->join('academic_years as later_year', 'later_year.id', '=', 'later_attended.academic_year_id')
                                ->whereColumn('later_attended.student_id', 'effective_attended.student_id')
                                ->where('later_attended.status', self::ENROLLMENT_ACTIVE)
                                ->when($activeYear !== null, fn (QueryBuilder $query) => $query
                                    ->whereDate('later_year.start_date', '<=', $activeYear->start_date))
                                ->where(function (QueryBuilder $precedence): void {
                                    $precedence
                                        ->whereColumn('later_year.start_date', '>', 'attended_year.start_date')
                                        ->orWhere(function (QueryBuilder $sameYear): void {
                                            $sameYear
                                                ->whereColumn('later_year.start_date', 'attended_year.start_date')
                                                ->whereColumn('later_attended.id', '>', 'effective_attended.id');
                                        });
                                });
                        });

                    $this->applyCandidateClass($attended, 'effective_attended', 'attended_class', $schoolLevel, $effectiveClassId, false);
                });
            });

            $visibility->orWhere(function (Builder $legacyStudent) use ($schoolLevel, $activeYear, $effectiveClassId): void {
                if ($activeYear !== null) {
                    $legacyStudent->whereNotExists(fn (QueryBuilder $current) => $this->currentEnrollmentQuery($current, $activeYear));
                }

                $legacyStudent->where(function (Builder $withoutSuitableEnrollment) use ($activeYear): void {
                    $withoutSuitableEnrollment
                        ->where(function (Builder $activeStudent) use ($activeYear): void {
                            $activeStudent->where('students.status', StudentStatus::Active->value);

                            if ($activeYear !== null) {
                                $activeStudent->whereNotExists(function (QueryBuilder $future) use ($activeYear): void {
                                    $future
                                        ->selectRaw('1')
                                        ->from('student_academic_enrollments as any_future')
                                        ->join('academic_years as any_future_year', 'any_future_year.id', '=', 'any_future.academic_year_id')
                                        ->whereColumn('any_future.student_id', 'students.id')
                                        ->where('any_future.status', self::ENROLLMENT_ACTIVE)
                                        ->whereDate('any_future_year.start_date', '>', $activeYear->start_date);
                                });
                            }
                        })
                        ->orWhere(function (Builder $terminalStudent) use ($activeYear): void {
                            $terminalStudent
                                ->whereIn('students.status', $this->terminalStatuses())
                                ->whereNotExists(function (QueryBuilder $attended) use ($activeYear): void {
                                    $attended
                                        ->selectRaw('1')
                                        ->from('student_academic_enrollments as any_attended')
                                        ->join('academic_years as any_attended_year', 'any_attended_year.id', '=', 'any_attended.academic_year_id')
                                        ->whereColumn('any_attended.student_id', 'students.id')
                                        ->where('any_attended.status', self::ENROLLMENT_ACTIVE)
                                        ->when($activeYear !== null, fn (QueryBuilder $query) => $query
                                            ->whereDate('any_attended_year.start_date', '<=', $activeYear->start_date));
                                });
                        });
                });

                $legacyStudent->whereExists(function (QueryBuilder $storedClass) use ($schoolLevel, $effectiveClassId): void {
                    $storedClass
                        ->selectRaw('1')
                        ->from('school_classes as stored_class')
                        ->whereColumn('stored_class.id', 'students.class_id')
                        ->whereIn('stored_class.level', $schoolLevel->classLevels())
                        ->when($effectiveClassId !== null, fn (QueryBuilder $query) => $query
                            ->where('stored_class.id', $effectiveClassId));
                });
            });
        });
    }

    public function resolveEffectiveClassFromLoaded(Student $student, ?AcademicYear $activeYear = null): ?SchoolClass
    {
        $activeYear ??= AcademicYear::active();
        $enrollments = $student->enrollments;

        if ($activeYear !== null) {
            $current = $enrollments->first(
                fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->academic_year_id === $activeYear->id
                    && $enrollment->status === self::ENROLLMENT_ACTIVE
            );

            if ($current !== null) {
                return $current->schoolClass;
            }

            if (StudentStatus::tryFrom((string) $student->getRawOriginal('status')) === StudentStatus::Active) {
                $future = $enrollments
                    ->filter(fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->status === self::ENROLLMENT_ACTIVE
                        && $enrollment->academicYear?->start_date?->gt($activeYear->start_date))
                    ->sortBy(fn (StudentAcademicEnrollment $enrollment): string => ($enrollment->academicYear?->start_date?->toDateString() ?? '').'|'.str_pad((string) $enrollment->id, 20, '0', STR_PAD_LEFT))
                    ->first();

                if ($future !== null) {
                    return $future->schoolClass;
                }
            }
        }

        if (in_array(StudentStatus::tryFrom((string) $student->getRawOriginal('status')), [StudentStatus::Graduated, StudentStatus::Transferred, StudentStatus::Withdrawn], true)) {
            $attended = $enrollments
                ->filter(fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->status === self::ENROLLMENT_ACTIVE
                    && ($activeYear === null || $enrollment->academicYear?->start_date?->lte($activeYear->start_date)))
                ->sortByDesc(fn (StudentAcademicEnrollment $enrollment): string => ($enrollment->academicYear?->start_date?->toDateString() ?? '').'|'.str_pad((string) $enrollment->id, 20, '0', STR_PAD_LEFT))
                ->first();

            if ($attended !== null) {
                return $attended->schoolClass;
            }
        }

        return $student->schoolClass;
    }

    private function currentEnrollmentQuery(QueryBuilder $query, AcademicYear $activeYear): void
    {
        $query
            ->selectRaw('1')
            ->from('student_academic_enrollments as effective_current')
            ->whereColumn('effective_current.student_id', 'students.id')
            ->where('effective_current.academic_year_id', $activeYear->id)
            ->where('effective_current.status', self::ENROLLMENT_ACTIVE);
    }

    private function applyCandidateClass(
        QueryBuilder $query,
        string $enrollmentAlias,
        string $classAlias,
        SchoolLevel $schoolLevel,
        ?int $effectiveClassId,
        bool $joinClass = true,
    ): void {
        if ($joinClass) {
            $query->join("school_classes as {$classAlias}", "{$classAlias}.id", '=', "{$enrollmentAlias}.school_class_id");
        }

        $query
            ->whereIn("{$classAlias}.level", $schoolLevel->classLevels())
            ->when($effectiveClassId !== null, fn (QueryBuilder $candidate) => $candidate
                ->where("{$enrollmentAlias}.school_class_id", $effectiveClassId));
    }

    /** @return list<string> */
    private function terminalStatuses(): array
    {
        return [
            StudentStatus::Graduated->value,
            StudentStatus::Transferred->value,
            StudentStatus::Withdrawn->value,
        ];
    }
}
