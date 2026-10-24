<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Support\Collection;
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
     * Find conflicts for multiple NIS values in a single academic year.
     * Returns a map keyed by "normalizedNis|schoolLevelValue" => StudentAcademicEnrollment.
     *
     * @param  array<int, string>  $nisList
     * @return Collection<string, StudentAcademicEnrollment>
     */
    public function findConflicts(
        array $nisList,
        AcademicYear $academicYear,
        ?int $ignoreStudentId = null,
    ): Collection {
        $normalized = collect($nisList)
            ->map(fn (mixed $v): string => trim((string) $v))
            ->filter(fn (string $v): bool => $v !== '')
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            return collect();
        }

        $enrollments = StudentAcademicEnrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('status', ['active', 'planned'])
            ->whereHas('student', function ($query) use ($normalized, $ignoreStudentId): void {
                $query->whereIn('nis', $normalized)
                    ->when($ignoreStudentId !== null, fn ($query) => $query->whereKeyNot($ignoreStudentId));
            })
            ->with(['student', 'schoolClass'])
            ->get();

        $map = collect();

        foreach ($enrollments as $enrollment) {
            $studentNis = $enrollment->student?->nis;
            if (! is_string($studentNis) || $studentNis === '') {
                continue;
            }
            $norm = trim($studentNis);
            $schoolLevel = $enrollment->schoolClass?->schoolLevel?->value;
            if ($schoolLevel === null || $schoolLevel === '') {
                continue;
            }
            $key = $norm.'|'.$schoolLevel;
            if (! $map->has($key)) {
                $map->put($key, $enrollment);
            }
        }

        return $map;
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

    /**
     * Get previous school levels for multiple NIS values in one query.
     * Returns a map keyed by normalized NIS => array of unique school level values.
     *
     * @param  array<int, string>  $nisList
     * @return array<string, array<int, string>>
     */
    public function getPreviousSchoolLevelsForNisList(array $nisList): array
    {
        $normalized = collect($nisList)
            ->map(fn (mixed $v): string => trim((string) $v))
            ->filter(fn (string $v): bool => $v !== '')
            ->unique()
            ->values()
            ->all();

        $result = [];

        foreach ($normalized as $nis) {
            $result[$nis] = [];
        }

        if ($normalized === []) {
            return $result;
        }

        $students = Student::query()
            ->whereIn('nis', $normalized)
            ->with('schoolClass')
            ->get();

        foreach ($students as $student) {
            $nisRaw = $student->nis;
            if (! is_string($nisRaw) || $nisRaw === '') {
                continue;
            }
            $nisNorm = trim($nisRaw);
            $level = $student->schoolLevel?->value;
            if ($level === null || $level === '') {
                continue;
            }
            if (! isset($result[$nisNorm])) {
                $result[$nisNorm] = [];
            }
            if (! in_array($level, $result[$nisNorm], true)) {
                $result[$nisNorm][] = $level;
            }
        }

        return $result;
    }
}
