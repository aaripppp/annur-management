<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Validation\ValidationException;

class StudentNisConflictService
{
    public function findConflict(
        string $nis,
        AcademicYear $academicYear,
        SchoolClass $schoolClass,
        ?int $ignoreStudentId = null,
    ): ?StudentAcademicEnrollment {
        $targetSchoolLevel = $schoolClass->schoolLevel;

        if ($targetSchoolLevel === null) {
            return null;
        }

        return StudentAcademicEnrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('status', ['active', 'planned'])
            ->whereHas('student', function ($query) use ($nis, $ignoreStudentId): void {
                $query->where('nis', trim($nis))
                    ->when($ignoreStudentId !== null, fn ($query) => $query->whereKeyNot($ignoreStudentId));
            })
            ->with(['student', 'schoolClass'])
            ->get()
            ->first(fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->schoolClass?->schoolLevel === $targetSchoolLevel);
    }

    /**
     * @throws ValidationException
     */
    public function ensureNoConflict(
        string $nis,
        AcademicYear $academicYear,
        SchoolClass $schoolClass,
        ?int $ignoreStudentId = null,
    ): void {
        if ($this->findConflict($nis, $academicYear, $schoolClass, $ignoreStudentId) === null) {
            return;
        }

        throw ValidationException::withMessages([
            'nis' => "NIS {$nis} sudah terdaftar pada jenjang {$schoolClass->schoolLevel?->value} di tahun ajaran {$academicYear->year}.",
        ]);
    }

    /** @return array<int, string> */
    public function previousSchoolLevels(string $nis): array
    {
        return Student::query()
            ->where('nis', trim($nis))
            ->with('schoolClass')
            ->get()
            ->map(fn (Student $student): ?string => $student->schoolLevel?->value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
