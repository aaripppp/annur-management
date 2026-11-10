<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JemputanDriverAssignmentService
{
    public function assign(int $driverId, int $studentId, int $academicYearId): JemputanDriverAssignment
    {
        return DB::transaction(function () use ($driverId, $studentId, $academicYearId): JemputanDriverAssignment {
            $student = Student::query()->lockForUpdate()->findOrFail($studentId);
            $driver = JemputanDriver::query()->lockForUpdate()->findOrFail($driverId);
            $academicYear = AcademicYear::query()->findOrFail($academicYearId);

            if (! $driver->is_active) {
                throw ValidationException::withMessages(['assignmentDriverId' => 'Sopir tidak aktif tidak dapat menerima siswa.']);
            }

            $this->lockAssignments($student->id, $academicYear->id);
            $this->assertNoCurrentMembership($student->id, $academicYear->id);

            return JemputanDriverAssignment::query()->create([
                'jemputan_driver_id' => $driver->id,
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'effective_from' => $this->operationDate($academicYear),
                'effective_until' => null,
            ]);
        });
    }

    public function remove(int $assignmentId): JemputanDriverAssignment
    {
        return DB::transaction(function () use ($assignmentId): JemputanDriverAssignment {
            $studentId = JemputanDriverAssignment::query()->whereKey($assignmentId)->value('student_id');
            Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $assignment = JemputanDriverAssignment::query()->lockForUpdate()->findOrFail($assignmentId);
            $academicYear = AcademicYear::query()->findOrFail($assignment->academic_year_id);

            if ($assignment->effective_until !== null) {
                throw ValidationException::withMessages(['closingAssignmentId' => 'Siswa sudah dikeluarkan dari kelompok sopir.']);
            }

            $this->lockAssignments($assignment->student_id, $assignment->academic_year_id);
            $assignment->update([
                'effective_until' => $this->operationDate($academicYear, $assignment->effective_from->toImmutable()),
            ]);

            return $assignment->refresh();
        });
    }

    public function move(int $assignmentId, int $targetDriverId): JemputanDriverAssignment
    {
        return DB::transaction(function () use ($assignmentId, $targetDriverId): JemputanDriverAssignment {
            $studentId = JemputanDriverAssignment::query()->whereKey($assignmentId)->value('student_id');
            Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $assignment = JemputanDriverAssignment::query()->lockForUpdate()->findOrFail($assignmentId);
            $targetDriver = JemputanDriver::query()->lockForUpdate()->findOrFail($targetDriverId);
            $academicYear = AcademicYear::query()->findOrFail($assignment->academic_year_id);

            if ($assignment->effective_until !== null) {
                throw ValidationException::withMessages(['movingAssignmentId' => 'Hanya anggota aktif yang dapat dipindahkan.']);
            }

            if (! $targetDriver->is_active) {
                throw ValidationException::withMessages(['moveDriverId' => 'Sopir tujuan harus berstatus aktif.']);
            }

            if ($targetDriver->id === $assignment->jemputan_driver_id) {
                throw ValidationException::withMessages(['moveDriverId' => 'Pilih sopir tujuan yang berbeda.']);
            }

            $this->lockAssignments($assignment->student_id, $assignment->academic_year_id);
            $this->assertNoCurrentMembership(
                $assignment->student_id,
                $assignment->academic_year_id,
                $assignment->id,
            );
            $operationDate = $this->operationDate($academicYear, $assignment->effective_from->toImmutable());
            $assignment->update(['effective_until' => $operationDate]);

            return JemputanDriverAssignment::query()->create([
                'jemputan_driver_id' => $targetDriver->id,
                'student_id' => $assignment->student_id,
                'academic_year_id' => $assignment->academic_year_id,
                'effective_from' => $operationDate,
                'effective_until' => null,
            ]);
        });
    }

    private function lockAssignments(int $studentId, int $academicYearId): void
    {
        JemputanDriverAssignment::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->lockForUpdate()
            ->get(['id']);
    }

    private function assertNoCurrentMembership(
        int $studentId,
        int $academicYearId,
        ?int $ignoreAssignmentId = null,
    ): void {
        $currentMembershipExists = JemputanDriverAssignment::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->currentMembership()
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->exists();

        if ($currentMembershipExists) {
            throw ValidationException::withMessages([
                'selectedStudentId' => 'Siswa sudah menjadi anggota kelompok sopir pada tahun ajaran ini.',
            ]);
        }
    }

    private function operationDate(AcademicYear $academicYear, ?CarbonImmutable $minimum = null): CarbonImmutable
    {
        $yearStart = $academicYear->start_date->toImmutable()->startOfDay();
        $yearEnd = $academicYear->end_date->toImmutable()->startOfDay();
        $date = CarbonImmutable::today()->max($yearStart)->min($yearEnd);

        return $minimum === null ? $date : $date->max($minimum->startOfDay());
    }
}
