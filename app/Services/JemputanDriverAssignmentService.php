<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JemputanDriverAssignmentService
{
    public function __construct(
        private readonly JemputanDriverMembershipPeriodValidator $periodValidator,
    ) {}

    public function assign(
        int $driverId,
        int $studentId,
        int $academicYearId,
        ?string $effectiveMonth = null,
        ?string $destination = null,
        ?string $serviceType = null,
    ): JemputanDriverAssignment {
        return DB::transaction(function () use ($driverId, $studentId, $academicYearId, $effectiveMonth, $destination, $serviceType): JemputanDriverAssignment {
            $student = Student::query()->lockForUpdate()->findOrFail($studentId);
            $driver = $this->lockDrivers([$driverId])->firstWhere('id', $driverId);

            if (! $driver instanceof JemputanDriver) {
                throw (new ModelNotFoundException)->setModel(JemputanDriver::class, [$driverId]);
            }

            $academicYear = AcademicYear::query()->findOrFail($academicYearId);
            $effectiveFrom = $this->effectiveMonth($academicYear, $effectiveMonth);
            $details = $this->validatedDetails($destination, $serviceType);

            if (! $driver->is_active) {
                throw ValidationException::withMessages(['assignmentDriverId' => 'Sopir tidak aktif tidak dapat menerima siswa.']);
            }

            $this->lockAssignments($student->id, $academicYear->id);
            $this->assertNoCurrentMembership($student->id, $academicYear->id, $driver->id);
            $this->periodValidator->assertValid(
                $student->id,
                $driver->id,
                $academicYear->id,
                $effectiveFrom,
                null,
            );

            return JemputanDriverAssignment::query()->create([
                'jemputan_driver_id' => $driver->id,
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'effective_from' => $effectiveFrom,
                'effective_until' => null,
                ...$details,
            ]);
        });
    }

    public function remove(int $assignmentId, ?string $effectiveMonth = null): JemputanDriverAssignment
    {
        return DB::transaction(function () use ($assignmentId, $effectiveMonth): JemputanDriverAssignment {
            $assignmentSnapshot = JemputanDriverAssignment::query()->findOrFail($assignmentId);
            Student::query()->whereKey($assignmentSnapshot->student_id)->lockForUpdate()->firstOrFail();
            $this->lockDrivers([(int) $assignmentSnapshot->jemputan_driver_id]);
            $this->lockAssignments($assignmentSnapshot->student_id, $assignmentSnapshot->academic_year_id);
            $assignment = JemputanDriverAssignment::query()->findOrFail($assignmentId);
            $academicYear = AcademicYear::query()->findOrFail($assignment->academic_year_id);
            $effectiveUntil = $this->effectiveMonth($academicYear, $effectiveMonth, allowYearEndBoundary: true);

            if ($assignment->effective_until !== null) {
                throw ValidationException::withMessages(['closingAssignmentId' => 'Siswa sudah dikeluarkan dari kelompok sopir.']);
            }

            $this->periodValidator->assertValid(
                $assignment->student_id,
                $assignment->jemputan_driver_id,
                $assignment->academic_year_id,
                $assignment->effective_from->toImmutable(),
                $effectiveUntil,
                $assignment->id,
            );
            $assignment->update(['effective_until' => $effectiveUntil]);

            return $assignment->refresh();
        });
    }

    public function move(
        int $assignmentId,
        int $targetDriverId,
        ?string $effectiveMonth = null,
    ): JemputanDriverAssignment {
        return DB::transaction(function () use ($assignmentId, $targetDriverId, $effectiveMonth): JemputanDriverAssignment {
            $assignmentSnapshot = JemputanDriverAssignment::query()->findOrFail($assignmentId);
            Student::query()->whereKey($assignmentSnapshot->student_id)->lockForUpdate()->firstOrFail();
            $drivers = $this->lockDrivers([
                (int) $assignmentSnapshot->jemputan_driver_id,
                $targetDriverId,
            ]);
            $this->lockAssignments($assignmentSnapshot->student_id, $assignmentSnapshot->academic_year_id);
            $assignment = JemputanDriverAssignment::query()->findOrFail($assignmentId);
            $targetDriver = $drivers->firstWhere('id', $targetDriverId);

            if (! $targetDriver instanceof JemputanDriver) {
                throw (new ModelNotFoundException)->setModel(JemputanDriver::class, [$targetDriverId]);
            }

            $academicYear = AcademicYear::query()->findOrFail($assignment->academic_year_id);
            $moveDate = $this->effectiveMonth($academicYear, $effectiveMonth);

            if ($assignment->effective_until !== null) {
                throw ValidationException::withMessages(['movingAssignmentId' => 'Hanya anggota aktif yang dapat dipindahkan.']);
            }

            if (! $targetDriver->is_active) {
                throw ValidationException::withMessages(['moveDriverId' => 'Sopir tujuan harus berstatus aktif.']);
            }

            if ($targetDriver->id === $assignment->jemputan_driver_id) {
                throw ValidationException::withMessages(['moveDriverId' => 'Pilih sopir tujuan yang berbeda.']);
            }

            $this->assertNoCurrentMembership(
                $assignment->student_id,
                $assignment->academic_year_id,
                $targetDriver->id,
                $assignment->id,
            );
            $this->periodValidator->assertValid(
                $assignment->student_id,
                $assignment->jemputan_driver_id,
                $assignment->academic_year_id,
                $assignment->effective_from->toImmutable(),
                $moveDate,
                $assignment->id,
            );
            $this->periodValidator->assertValid(
                $assignment->student_id,
                $targetDriver->id,
                $assignment->academic_year_id,
                $moveDate,
                null,
            );
            $assignment->update(['effective_until' => $moveDate]);

            return JemputanDriverAssignment::query()->create([
                'jemputan_driver_id' => $targetDriver->id,
                'student_id' => $assignment->student_id,
                'academic_year_id' => $assignment->academic_year_id,
                'effective_from' => $moveDate,
                'effective_until' => null,
                'destination' => $assignment->destination,
                'service_type' => $assignment->service_type,
            ]);
        });
    }

    public function updateDetails(
        int $assignmentId,
        ?string $destination,
        ?string $serviceType,
    ): JemputanDriverAssignment {
        return DB::transaction(function () use ($assignmentId, $destination, $serviceType): JemputanDriverAssignment {
            $assignment = JemputanDriverAssignment::query()->lockForUpdate()->findOrFail($assignmentId);
            $assignment->update($this->validatedDetails($destination, $serviceType));

            return $assignment->refresh();
        });
    }

    public function updatePeriod(
        int $assignmentId,
        string $effectiveFromMonth,
        ?string $effectiveUntilMonth,
    ): JemputanDriverAssignment {
        return DB::transaction(function () use ($assignmentId, $effectiveFromMonth, $effectiveUntilMonth): JemputanDriverAssignment {
            $assignmentSnapshot = JemputanDriverAssignment::query()->findOrFail($assignmentId);
            Student::query()->whereKey($assignmentSnapshot->student_id)->lockForUpdate()->firstOrFail();
            $this->lockDrivers([(int) $assignmentSnapshot->jemputan_driver_id]);
            $this->lockAssignments($assignmentSnapshot->student_id, $assignmentSnapshot->academic_year_id);
            $assignment = JemputanDriverAssignment::query()->findOrFail($assignmentId);
            $academicYear = AcademicYear::query()->findOrFail($assignment->academic_year_id);
            $effectiveFrom = $this->effectiveMonth($academicYear, $effectiveFromMonth);
            $effectiveUntil = $effectiveUntilMonth === null
                ? null
                : $this->effectiveMonth($academicYear, $effectiveUntilMonth, allowYearEndBoundary: true);

            $this->periodValidator->assertValid(
                $assignment->student_id,
                $assignment->jemputan_driver_id,
                $assignment->academic_year_id,
                $effectiveFrom,
                $effectiveUntil,
                $assignment->id,
            );
            $assignment->update([
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
            ]);

            return $assignment->refresh();
        });
    }

    private function lockAssignments(int $studentId, int $academicYearId): void
    {
        JemputanDriverAssignment::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /** @param list<int> $driverIds
     * @return Collection<int, JemputanDriver>
     */
    private function lockDrivers(array $driverIds): Collection
    {
        return JemputanDriver::query()
            ->whereKey(array_values(array_unique($driverIds)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function assertNoCurrentMembership(
        int $studentId,
        int $academicYearId,
        int $driverId,
        ?int $ignoreAssignmentId = null,
    ): void {
        $currentMembershipExists = JemputanDriverAssignment::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('jemputan_driver_id', $driverId)
            ->currentMembership()
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->exists();

        if ($currentMembershipExists) {
            throw ValidationException::withMessages([
                'selectedStudentId' => 'Siswa sudah menjadi anggota sopir ini pada tahun ajaran yang dipilih.',
            ]);
        }
    }

    private function effectiveMonth(
        AcademicYear $academicYear,
        ?string $effectiveMonth,
        bool $allowYearEndBoundary = false,
    ): CarbonImmutable {
        $yearStart = $academicYear->start_date->toImmutable()->startOfMonth();
        $yearEnd = $academicYear->end_date->toImmutable()->startOfMonth();
        $latestAllowed = $allowYearEndBoundary ? $yearEnd->addMonth() : $yearEnd;

        if ($effectiveMonth === null) {
            return CarbonImmutable::today()->startOfMonth()->max($yearStart)->min($latestAllowed);
        }

        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $effectiveMonth, $matches) !== 1) {
            throw ValidationException::withMessages([
                'effectiveMonth' => 'Bulan efektif harus menggunakan format YYYY-MM.',
            ]);
        }

        $date = CarbonImmutable::createFromDate((int) $matches[1], (int) $matches[2], 1)->startOfDay();

        if ($date->lt($yearStart) || $date->gt($latestAllowed)) {
            throw ValidationException::withMessages([
                'effectiveMonth' => $allowYearEndBoundary
                    ? 'Bulan berhenti harus berada dalam tahun ajaran atau tepat pada bulan setelah tahun ajaran berakhir.'
                    : 'Bulan mulai harus berada di dalam tahun ajaran yang dipilih.',
            ]);
        }

        return $date;
    }

    /** @return array{destination: string|null, service_type: string|null} */
    private function validatedDetails(?string $destination, ?string $serviceType): array
    {
        $destination = filled($destination) ? trim($destination) : null;
        $serviceType = filled($serviceType) ? trim($serviceType) : null;

        if ($destination !== null && mb_strlen($destination) > 255) {
            throw ValidationException::withMessages([
                'destination' => 'Tujuan maksimal 255 karakter.',
            ]);
        }

        if ($serviceType !== null && ! in_array($serviceType, JemputanDriverAssignment::SERVICE_TYPES, true)) {
            throw ValidationException::withMessages([
                'service_type' => 'Keterangan layanan harus PP, Pulang, atau Pergi.',
            ]);
        }

        return [
            'destination' => $destination,
            'service_type' => $serviceType,
        ];
    }
}
