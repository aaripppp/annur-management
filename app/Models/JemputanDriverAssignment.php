<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\JemputanDriverAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class JemputanDriverAssignment extends Model
{
    /** @use HasFactory<JemputanDriverAssignmentFactory> */
    use HasFactory;

    public const SERVICE_TYPE_ROUND_TRIP = 'PP';

    public const SERVICE_TYPE_RETURN = 'Pulang';

    public const SERVICE_TYPE_DEPARTURE = 'Pergi';

    public const SERVICE_TYPES = [
        self::SERVICE_TYPE_ROUND_TRIP,
        self::SERVICE_TYPE_RETURN,
        self::SERVICE_TYPE_DEPARTURE,
    ];

    protected $fillable = [
        'jemputan_driver_id',
        'student_id',
        'academic_year_id',
        'effective_from',
        'effective_until',
        'destination',
        'service_type',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /** @param Builder<JemputanDriverAssignment> $query */
    public function scopeCurrentMembership(Builder $query): Builder
    {
        return $query->whereNull('effective_until');
    }

    /** @param Builder<JemputanDriverAssignment> $query */
    public function scopeForAcademicYear(Builder $query, int $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /**
     * Resolve memberships overlapping one calendar month using [from, until) semantics.
     *
     * @param  Builder<JemputanDriverAssignment>  $query
     */
    public function scopeActiveDuringMonth(
        Builder $query,
        int $academicYearId,
        int $year,
        int $month,
    ): Builder {
        if ($year < 1 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Tahun dan bulan keanggotaan tidak valid.');
        }

        $monthStart = CarbonImmutable::createFromDate($year, $month, 1)->startOfDay();

        return $query
            ->forAcademicYear($academicYearId)
            ->overlappingPeriod($monthStart, $monthStart->addMonth());
    }

    /**
     * Resolve assignments overlapping a half-open [from, until) period.
     *
     * @param  Builder<JemputanDriverAssignment>  $query
     */
    public function scopeOverlappingPeriod(
        Builder $query,
        CarbonInterface $effectiveFrom,
        ?CarbonInterface $effectiveUntil,
    ): Builder {
        $from = CarbonImmutable::parse($effectiveFrom->toDateString())->startOfDay()->toDateTimeString();
        $until = $effectiveUntil === null
            ? null
            : CarbonImmutable::parse($effectiveUntil->toDateString())->startOfDay()->toDateTimeString();

        return $query
            ->when(
                $until !== null,
                fn (Builder $periodQuery): Builder => $periodQuery
                    ->where('effective_from', '<', $until),
            )
            ->where(function (Builder $periodQuery) use ($from): void {
                $periodQuery
                    ->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $from);
            });
    }

    /** @return BelongsTo<JemputanDriver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(JemputanDriver::class, 'jemputan_driver_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
