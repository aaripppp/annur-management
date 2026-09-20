<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\BillbookPeriod;
use DomainException;
use Illuminate\Support\Facades\DB;

class StudentContinuationService
{
    public function __construct(
        private readonly StudentCreationService $studentCreationService,
        private readonly StudentGraduationMarkerService $studentGraduationMarkerService,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public function continue(Student $student, array $profile, AcademicYear $academicYear, bool $prospective): Student
    {
        return DB::transaction(function () use ($student, $profile, $academicYear, $prospective): Student {
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);
            $importClass = SchoolClass::query()->findOrFail((int) $profile['class_id']);
            $marker = $this->studentGraduationMarkerService->assess(
                $student,
                $academicYear,
                $importClass,
                lockForUpdate: true,
            );

            if ($marker['target']) {
                if (! $marker['reusable'] || ! $marker['source']) {
                    $message = $marker['financial_conflict']
                        ? "Enrollment target NIS {$student->nis} memiliki tagihan atau pembayaran dan membutuhkan pemeriksaan manual."
                        : "Siswa {$student->nis} sudah terdaftar di tahun ajaran {$academicYear->year}.";

                    throw new DomainException($message);
                }
            }

            $latestEnrollment = $student->enrollments()
                ->with('schoolClass')
                ->join('academic_years', 'academic_years.id', '=', 'student_academic_enrollments.academic_year_id')
                ->orderByDesc('academic_years.start_date')
                ->select('student_academic_enrollments.*')
                ->first();

            $sourceClass = $marker['source']?->schoolClass ?? $latestEnrollment?->schoolClass;

            if ($sourceClass !== null && $sourceClass->school_level !== $importClass->school_level) {
                throw new DomainException("Siswa {$student->nis} harus didaftarkan sebagai siswa baru saat berpindah jenjang.");
            }

            if ($marker['target']) {
                $marker['source']->update(['status' => 'lulus']);
                $marker['target']->delete();
            }

            if ($latestEnrollment?->status !== 'lulus') {
                throw new DomainException("NIS {$student->nis} masih digunakan siswa aktif.");
            }

            $student->update([
                ...$profile,
                'status' => StudentStatus::Active,
            ]);
            $student->refresh();

            $start = $prospective ? $academicYear->start_date : BillbookPeriod::startDate();
            $this->studentCreationService->initializeAcademicEntry(
                $student,
                $academicYear,
                $start,
                $prospective,
            );

            return $student;
        });
    }
}
