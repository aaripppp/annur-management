<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class JemputanDriverSemesterRecapService
{
    private const PAYMENT_TYPE_NAME = 'Jemputan';

    public function __construct(
        private readonly JemputanDriverDeductionRateResolver $deductionRates,
        private readonly JemputanDriverSalaryService $salaryCalculations,
    ) {}

    /** @return array<string, mixed> */
    public function generate(
        int $academicYearId,
        ?SchoolLevel $schoolLevel,
        int $semester,
    ): array {
        Gate::authorize('viewAny', JemputanDriver::class);

        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $periods = $this->semesterPeriods($academicYear, $semester);
        $studentIds = $this->studentIds($academicYear, $schoolLevel);
        $memberships = $this->memberships($academicYear, $studentIds);
        $drivers = $this->drivers($memberships);
        $paymentTypeIds = PaymentType::query()
            ->where('name', self::PAYMENT_TYPE_NAME)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $bills = $this->bills($studentIds, $paymentTypeIds, $periods);
        $billsByStudentAndPeriod = $bills->groupBy(
            fn (StudentBill $bill): string => $this->studentPeriodKey(
                (int) $bill->student_id,
                (int) $bill->period_year,
                (int) $bill->period_month,
            ),
        );
        $allocationsByStudentAndPeriod = $this->allocations($academicYear, $studentIds, $periods)
            ->groupBy(fn (JemputanDriverSalaryAllocation $allocation): string => $this->studentPeriodKey(
                (int) $allocation->student_id,
                (int) $allocation->period_year,
                (int) $allocation->period_month,
            ));
        $ratesByDriverAndPeriod = $this->deductionRates->resolveMany($drivers, $periods);
        $revenues = [];
        $warningCounts = [
            JemputanDriverAllocationService::STATUS_MISSING => 0,
            JemputanDriverAllocationService::STATUS_STALE => 0,
            JemputanDriverAllocationService::STATUS_ORPHAN => 0,
        ];

        foreach ($studentIds as $studentId) {
            $driverIds = $memberships->get($studentId, []);

            foreach ($periods as $period) {
                $key = $this->studentPeriodKey($studentId, $period['year'], $period['month']);
                $periodBills = $billsByStudentAndPeriod->get($key, collect());
                $periodAllocations = $allocationsByStudentAndPeriod->get($key, collect());

                if ($periodBills->isEmpty() && $periodAllocations->isEmpty()) {
                    continue;
                }

                $effectiveBill = $this->salaryCalculations->effectiveBillTotal($periodBills);
                $resolution = $this->resolveAmountsUsingSalaryService(
                    $effectiveBill,
                    $driverIds,
                    $periodAllocations,
                );

                if (array_key_exists($resolution['status'], $warningCounts)) {
                    $warningCounts[$resolution['status']]++;
                }

                foreach ($resolution['amounts'] as $driverId => $amount) {
                    $revenues[$driverId][$period['key']] = round(
                        (float) ($revenues[$driverId][$period['key']] ?? 0) + $amount,
                        2,
                    );
                }
            }
        }

        $rows = [];
        $missingRateCount = 0;
        $legacyRateCount = 0;

        foreach ($drivers as $driver) {
            $months = [];

            foreach ($periods as $period) {
                $revenue = (int) round((float) ($revenues[$driver->id][$period['key']] ?? 0), 0, PHP_ROUND_HALF_UP);
                $rate = $ratesByDriverAndPeriod[$driver->id][$period['key']];
                $calculation = $this->monthlyCalculation($revenue, $rate['percentage']);
                $months[$period['key']] = array_merge($calculation, [
                    'percentage' => $rate['percentage'],
                    'percentage_configured' => $rate['configured'],
                    'percentage_source' => $rate['source'],
                    'percentage_effective_year' => $rate['effective_year'],
                    'percentage_effective_month' => $rate['effective_month'],
                ]);

                if (! $rate['configured']) {
                    $missingRateCount++;
                } elseif ($rate['source'] === JemputanDriverDeductionRateResolver::SOURCE_LEGACY) {
                    $legacyRateCount++;
                }
            }

            $rows[] = [
                'driver_id' => (int) $driver->id,
                'driver_name' => $driver->name,
                'is_active' => (bool) $driver->is_active,
                'months' => $months,
                'totals' => $this->sumMonthCalculations($months),
            ];
        }

        $allocationWarningCount = array_sum($warningCounts);

        return [
            'academic_year' => [
                'id' => (int) $academicYear->id,
                'label' => $academicYear->year,
            ],
            'filters' => [
                'school_level' => $schoolLevel?->value,
                'semester' => $semester,
            ],
            'months' => $periods,
            'rows' => $rows,
            'recap' => $this->recap($rows, $periods),
            'warnings' => [
                'allocation' => [
                    'missing_count' => $warningCounts[JemputanDriverAllocationService::STATUS_MISSING],
                    'stale_count' => $warningCounts[JemputanDriverAllocationService::STATUS_STALE],
                    'orphan_count' => $warningCounts[JemputanDriverAllocationService::STATUS_ORPHAN],
                    'total_count' => $allocationWarningCount,
                ],
                'missing_rate_count' => $missingRateCount,
                'legacy_rate_count' => $legacyRateCount,
                'historical_membership_limited' => true,
            ],
            'is_final' => $allocationWarningCount === 0 && $missingRateCount === 0 && $legacyRateCount === 0,
        ];
    }

    /** @return list<array{key: string, year: int, month: int, label: string}> */
    private function semesterPeriods(AcademicYear $academicYear, int $semester): array
    {
        $semester = in_array($semester, [1, 2], true) ? $semester : 1;
        $start = CarbonImmutable::parse($academicYear->start_date)->startOfMonth()
            ->addMonths($semester === 1 ? 0 : 6);
        $periods = [];

        for ($offset = 0; $offset < 6; $offset++) {
            $month = $start->addMonths($offset);
            $periods[] = [
                'key' => $month->format('Y-m'),
                'year' => $month->year,
                'month' => $month->month,
                'label' => $month->locale('id')->translatedFormat('F Y'),
            ];
        }

        return $periods;
    }

    /** @return list<int> */
    private function studentIds(AcademicYear $academicYear, ?SchoolLevel $schoolLevel): array
    {
        return Student::query()
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->where('academic_year_id', $academicYear->id)
                ->when($schoolLevel !== null, fn (Builder $levelQuery) => $levelQuery->whereHas(
                    'schoolClass',
                    fn (Builder $classQuery) => $classQuery->whereIn('level', $schoolLevel->classLevels()),
                )))
            ->whereHas('jemputanDriverAssignments', fn (Builder $query) => $query
                ->where('academic_year_id', $academicYear->id)
                ->currentMembership())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<int> $studentIds
     * @return Collection<int, list<int>>
     */
    private function memberships(AcademicYear $academicYear, array $studentIds): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return JemputanDriverAssignment::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('student_id', $studentIds)
            ->currentMembership()
            ->get(['student_id', 'jemputan_driver_id'])
            ->groupBy('student_id')
            ->map(fn (Collection $assignments): array => $assignments
                ->pluck('jemputan_driver_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all());
    }

    /** @param Collection<int, list<int>> $memberships
     * @return Collection<int, JemputanDriver>
     */
    private function drivers(Collection $memberships): Collection
    {
        $driverIds = $memberships->flatten()->unique()->values()->all();

        if ($driverIds === []) {
            return collect();
        }

        return JemputanDriver::query()
            ->whereIn('id', $driverIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'is_active', 'foundation_deduction_percentage']);
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<int>  $paymentTypeIds
     * @param  list<array{key: string, year: int, month: int, label: string}>  $periods
     * @return Collection<int, StudentBill>
     */
    private function bills(array $studentIds, array $paymentTypeIds, array $periods): Collection
    {
        if ($studentIds === [] || $paymentTypeIds === []) {
            return collect();
        }

        return StudentBill::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('payment_type_id', $paymentTypeIds)
            ->where('billing_frequency', BillFrequency::Monthly->value)
            ->where(function (Builder $query) use ($periods): void {
                foreach ($periods as $period) {
                    $query->orWhere(fn (Builder $monthQuery) => $monthQuery
                        ->where('period_year', $period['year'])
                        ->where('period_month', $period['month']));
                }
            })
            ->withSum('adjustments as salary_adjustment_total', 'amount')
            ->get();
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<array{key: string, year: int, month: int, label: string}>  $periods
     * @return Collection<int, JemputanDriverSalaryAllocation>
     */
    private function allocations(AcademicYear $academicYear, array $studentIds, array $periods): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return JemputanDriverSalaryAllocation::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('student_id', $studentIds)
            ->where(function (Builder $query) use ($periods): void {
                foreach ($periods as $period) {
                    $query->orWhere(fn (Builder $monthQuery) => $monthQuery
                        ->where('period_year', $period['year'])
                        ->where('period_month', $period['month']));
                }
            })
            ->get(['id', 'student_id', 'period_year', 'period_month', 'jemputan_driver_id', 'allocated_amount']);
    }

    /**
     * @param  list<int>  $driverIds
     * @param  Collection<int, JemputanDriverSalaryAllocation>  $allocations
     * @return array{status: string, amounts: array<int, float>}
     */
    private function resolveAmountsUsingSalaryService(
        float $billAmount,
        array $driverIds,
        Collection $allocations,
    ): array {
        $status = JemputanDriverAllocationService::STATUS_NOT_REQUIRED;
        $amounts = [];

        foreach ($driverIds as $driverId) {
            $resolution = $this->salaryCalculations->resolveSalaryAmount(
                $driverId,
                $billAmount,
                $driverIds,
                $allocations,
            );
            $status = $resolution['allocation_status'];
            $amounts[$driverId] = $resolution['salary_amount'];
        }

        return ['status' => $status, 'amounts' => $amounts];
    }

    /** @return array{revenue: int, driver_income: int|null, foundation_fee: int|null, total: int|null} */
    private function monthlyCalculation(int $revenue, ?float $percentage): array
    {
        $summary = $this->salaryCalculations->summary($revenue, $percentage);

        return [
            'revenue' => $summary['revenue'],
            'driver_income' => $summary['net_salary'],
            'foundation_fee' => $summary['deduction'],
            'total' => $summary['percentage_configured'] ? $summary['net_salary'] + $summary['deduction'] : null,
        ];
    }

    /** @param array<string, array<string, mixed>> $months
     * @return array{revenue: int, driver_income: int|null, foundation_fee: int|null, total: int|null}
     */
    private function sumMonthCalculations(array $months): array
    {
        $hasNull = collect($months)->contains(fn (array $month): bool => $month['total'] === null);

        return [
            'revenue' => (int) collect($months)->sum('revenue'),
            'driver_income' => $hasNull ? null : (int) collect($months)->sum('driver_income'),
            'foundation_fee' => $hasNull ? null : (int) collect($months)->sum('foundation_fee'),
            'total' => $hasNull ? null : (int) collect($months)->sum('total'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, year: int, month: int, label: string}>  $periods
     * @return array{months: array<string, array<string, int|null>>, totals: array<string, int|null>}
     */
    private function recap(array $rows, array $periods): array
    {
        $months = [];

        foreach ($periods as $period) {
            $driverMonths = collect($rows)->pluck('months.'.$period['key']);
            $hasNull = $driverMonths->contains(fn (array $month): bool => $month['total'] === null);
            $months[$period['key']] = [
                'driver_income' => $hasNull ? null : (int) $driverMonths->sum('driver_income'),
                'foundation_fee' => $hasNull ? null : (int) $driverMonths->sum('foundation_fee'),
                'total' => $hasNull ? null : (int) $driverMonths->sum('total'),
            ];
        }

        $hasNull = collect($months)->contains(fn (array $month): bool => $month['total'] === null);

        return [
            'months' => $months,
            'totals' => [
                'driver_income' => $hasNull ? null : (int) collect($months)->sum('driver_income'),
                'foundation_fee' => $hasNull ? null : (int) collect($months)->sum('foundation_fee'),
                'total' => $hasNull ? null : (int) collect($months)->sum('total'),
            ],
        ];
    }

    private function studentPeriodKey(int $studentId, int $year, int $month): string
    {
        return $studentId.'|'.sprintf('%04d-%02d', $year, $month);
    }
}
