<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\JemputanDriverAssignment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class JemputanDriverMembershipPeriodValidator
{
    public function assertValid(
        int $studentId,
        int $driverId,
        int $academicYearId,
        CarbonInterface $effectiveFrom,
        ?CarbonInterface $effectiveUntil,
        ?int $ignoreAssignmentId = null,
    ): void {
        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $from = CarbonImmutable::parse($effectiveFrom->toDateString())->startOfDay();
        $until = $effectiveUntil === null
            ? null
            : CarbonImmutable::parse($effectiveUntil->toDateString())->startOfDay();
        $this->assertWithinAcademicYear($academicYear, $from, $until);

        if ($this->overlapExists(
            $studentId,
            $driverId,
            $academicYearId,
            $from,
            $until,
            $ignoreAssignmentId,
        )) {
            throw ValidationException::withMessages([
                'assignmentPeriod' => 'Periode keanggotaan siswa pada sopir ini tumpang tindih dengan periode yang sudah ada.',
            ]);
        }
    }

    /**
     * Validate a proposed assignment batch with bounded database queries.
     *
     * @param  list<array{
     *     assignment_id: int,
     *     student_id: int,
     *     driver_id: int,
     *     academic_year_id: int,
     *     effective_from: CarbonInterface,
     *     effective_until: CarbonInterface|null
     * }>  $periods
     */
    public function assertValidBatch(array $periods): void
    {
        if ($periods === []) {
            return;
        }

        $academicYears = AcademicYear::query()
            ->whereKey(collect($periods)->pluck('academic_year_id')->unique())
            ->get()
            ->keyBy('id');
        $assignments = JemputanDriverAssignment::query()
            ->whereIn('academic_year_id', collect($periods)->pluck('academic_year_id')->unique())
            ->whereIn('student_id', collect($periods)->pluck('student_id')->unique())
            ->get();
        $proposals = collect($periods)->keyBy('assignment_id');

        foreach ($periods as $period) {
            $academicYear = $academicYears->get($period['academic_year_id']);

            if (! $academicYear instanceof AcademicYear) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Tahun ajaran assignment tidak ditemukan.',
                ]);
            }

            $from = CarbonImmutable::parse($period['effective_from']->toDateString())->startOfDay();
            $until = $period['effective_until'] === null
                ? null
                : CarbonImmutable::parse($period['effective_until']->toDateString())->startOfDay();
            $this->assertWithinAcademicYear($academicYear, $from, $until);

            foreach ($assignments as $assignment) {
                if (
                    $assignment->id === $period['assignment_id']
                    || $assignment->student_id !== $period['student_id']
                    || $assignment->jemputan_driver_id !== $period['driver_id']
                    || $assignment->academic_year_id !== $period['academic_year_id']
                ) {
                    continue;
                }

                $otherProposal = $proposals->get($assignment->id);
                $otherFrom = $otherProposal === null
                    ? $assignment->effective_from->toImmutable()->startOfDay()
                    : CarbonImmutable::parse($otherProposal['effective_from']->toDateString())->startOfDay();
                $otherUntil = $otherProposal === null
                    ? $assignment->effective_until?->toImmutable()->startOfDay()
                    : ($otherProposal['effective_until'] === null
                        ? null
                        : CarbonImmutable::parse($otherProposal['effective_until']->toDateString())->startOfDay());

                if (
                    ($otherUntil === null || $from->lt($otherUntil))
                    && ($until === null || $otherFrom->lt($until))
                ) {
                    throw ValidationException::withMessages([
                        'assignmentPeriod' => 'Periode keanggotaan siswa pada sopir ini tumpang tindih dengan periode yang sudah ada.',
                    ]);
                }
            }
        }
    }

    public function overlapExists(
        int $studentId,
        int $driverId,
        int $academicYearId,
        CarbonInterface $effectiveFrom,
        ?CarbonInterface $effectiveUntil,
        ?int $ignoreAssignmentId = null,
    ): bool {
        return JemputanDriverAssignment::query()
            ->where('student_id', $studentId)
            ->where('jemputan_driver_id', $driverId)
            ->forAcademicYear($academicYearId)
            ->overlappingPeriod($effectiveFrom, $effectiveUntil)
            ->when(
                $ignoreAssignmentId !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($ignoreAssignmentId),
            )
            ->exists();
    }

    private function assertWithinAcademicYear(
        AcademicYear $academicYear,
        CarbonInterface $effectiveFrom,
        ?CarbonInterface $effectiveUntil,
    ): void {
        $yearStart = $academicYear->start_date->toImmutable()->startOfDay();
        $yearEndExclusive = $academicYear->end_date->toImmutable()->addDay()->startOfDay();

        if ($effectiveUntil !== null && ! $effectiveFrom->lt($effectiveUntil)) {
            throw ValidationException::withMessages([
                'effective_until' => 'Tanggal selesai harus setelah tanggal mulai karena periode menggunakan batas akhir eksklusif.',
            ]);
        }

        if ($effectiveFrom->lt($yearStart) || ! $effectiveFrom->lt($yearEndExclusive)) {
            throw ValidationException::withMessages([
                'effective_from' => 'Tanggal mulai harus berada di dalam tahun ajaran yang dipilih.',
            ]);
        }

        if ($effectiveUntil !== null && $effectiveUntil->gt($yearEndExclusive)) {
            throw ValidationException::withMessages([
                'effective_until' => 'Tanggal selesai tidak boleh melewati hari pertama setelah tahun ajaran berakhir.',
            ]);
        }
    }
}
