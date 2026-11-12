<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class JemputanDriverAllocationService
{
    public const STATUS_NOT_REQUIRED = 'not_required';

    public const STATUS_MISSING = 'missing';

    public const STATUS_VALID = 'valid';

    public const STATUS_STALE = 'stale';

    public const STATUS_ORPHAN = 'orphan';

    private const PAYMENT_TYPE_NAME = 'Jemputan';

    /**
     * Read the allocations that already exist for one student period.
     *
     * @return Collection<int, JemputanDriverSalaryAllocation>
     */
    public function allocations(
        int $studentId,
        int $academicYearId,
        int $periodYear,
        int $periodMonth,
    ): Collection {
        return JemputanDriverSalaryAllocation::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth)
            ->with('driver')
            ->get()
            ->sortBy(fn (JemputanDriverSalaryAllocation $allocation): string => $allocation->driver?->name ?? '')
            ->values();
    }

    /**
     * Read every driver that is still an active member of the student in the year.
     *
     * Inactive drivers are deliberately included: they remain members until the
     * assignment is closed, so their allocation still belongs to the period.
     *
     * @return Collection<int, JemputanDriver>
     */
    public function currentDrivers(int $studentId, int $academicYearId): Collection
    {
        return JemputanDriverAssignment::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->currentMembership()
            ->with('driver')
            ->get()
            ->pluck('driver')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Sum the effective Jemputan bills for one student period.
     *
     * Each bill contributes MAX(0, amount + total adjustments). Multiple
     * Jemputan bills in the same month are added together.
     */
    public function effectiveBillAmount(
        int $studentId,
        int $academicYearId,
        int $periodYear,
        int $periodMonth,
    ): float {
        return $this->billTotalCents(
            $this->periodBills($studentId, $periodYear, $periodMonth)
        ) / 100;
    }

    /**
     * List the twelve periods covered by an academic year.
     *
     * @return list<array{key: string, year: int, month: int, label: string}>
     */
    public function periodsForAcademicYear(int $academicYearId): array
    {
        $academicYear = AcademicYear::query()->findOrFail($academicYearId);

        return $this->academicYearMonths($academicYear);
    }

    /**
     * Build the read-only data needed by the allocation modal.
     *
     * @return array{
     *     student_id: int,
     *     student_name: string,
     *     academic_year: array{id: int, label: string},
     *     period: array{key: string, year: int, month: int, label: string},
     *     bill_count: int,
     *     has_bill: bool,
     *     bill_amount: float,
     *     driver_count: int,
     *     is_multi_driver: bool,
     *     drivers: list<array{id: int, name: string, is_active: bool, allocated_amount: float|null}>,
     *     allocations: list<array{jemputan_driver_id: int, allocated_amount: float}>,
     *     total_allocated: float,
     *     difference: float,
     *     status: string
     * }
     */
    public function preview(
        int $studentId,
        int $academicYearId,
        int $periodYear,
        int $periodMonth,
    ): array {
        $student = Student::query()->findOrFail($studentId);
        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $bills = $this->periodBills($studentId, $periodYear, $periodMonth);
        $drivers = $this->currentDrivers($studentId, $academicYearId);
        $allocations = $this->allocations($studentId, $academicYearId, $periodYear, $periodMonth);
        $billCents = $this->billTotalCents($bills);
        $totalAllocatedCents = $allocations->sum(
            fn (JemputanDriverSalaryAllocation $allocation): int => $this->decimalToCents($allocation->allocated_amount)
        );
        $status = $this->resolveStatus(
            $drivers,
            $allocations,
            $billCents,
            $bills->isNotEmpty(),
        );
        $allocationsByDriver = $allocations->keyBy('jemputan_driver_id');
        $driverRows = $drivers
            ->map(function (JemputanDriver $driver) use ($allocationsByDriver): array {
                $allocation = $allocationsByDriver->get($driver->id);

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'is_active' => $driver->is_active,
                    'allocated_amount' => $allocation === null
                        ? null
                        : (float) $allocation->allocated_amount,
                ];
            })
            ->values()
            ->all();

        return [
            'student_id' => $student->id,
            'student_name' => $student->nama_lengkap,
            'academic_year' => [
                'id' => $academicYear->id,
                'label' => $academicYear->year,
            ],
            'period' => $this->periodPayload($periodYear, $periodMonth),
            'bill_count' => $bills->count(),
            'has_bill' => $bills->isNotEmpty(),
            'bill_amount' => (float) ($billCents / 100),
            'driver_count' => $drivers->count(),
            'is_multi_driver' => $drivers->count() > 1,
            'drivers' => $driverRows,
            'allocations' => $allocations
                ->map(fn (JemputanDriverSalaryAllocation $allocation): array => [
                    'jemputan_driver_id' => (int) $allocation->jemputan_driver_id,
                    'allocated_amount' => (float) $allocation->allocated_amount,
                ])
                ->values()
                ->all(),
            'total_allocated' => (float) ($totalAllocatedCents / 100),
            'difference' => (float) (($billCents - $totalAllocatedCents) / 100),
            'status' => $status,
        ];
    }

    /**
     * Store manual allocations for one or more periods in a single transaction.
     *
     * Input is keyed by period ("2026-07"). Each period contains a list of
     * driver rows so duplicate driver IDs can be rejected instead of silently
     * collapsing into an associative array.
     *
     * Example:
     * [
     *     '2026-07' => [
     *         ['jemputan_driver_id' => 1, 'allocated_amount' => '450000'],
     *         ['jemputan_driver_id' => 2, 'allocated_amount' => '250000'],
     *     ],
     * ]
     *
     * @param  array<string, list<array{jemputan_driver_id: int|string, allocated_amount: int|float|string}>>  $allocationsByPeriod
     * @return Collection<int, JemputanDriverSalaryAllocation>
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function save(
        int $studentId,
        int $academicYearId,
        array $allocationsByPeriod,
        ?User $actor = null,
    ): Collection {
        $actor ??= Auth::user();

        if ($actor === null) {
            throw new AuthorizationException('Pengguna tidak terautentikasi.');
        }

        Gate::forUser($actor)->authorize('create', JemputanDriver::class);

        return DB::transaction(function () use ($studentId, $academicYearId, $allocationsByPeriod, $actor): Collection {
            $student = Student::query()->lockForUpdate()->findOrFail($studentId);
            $academicYear = AcademicYear::query()->findOrFail($academicYearId);
            $periods = $this->normalizePeriods($allocationsByPeriod, $this->academicYearMonths($academicYear));

            $assignments = JemputanDriverAssignment::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->currentMembership()
                ->lockForUpdate()
                ->get(['id', 'jemputan_driver_id']);
            $currentDriverIds = $assignments
                ->pluck('jemputan_driver_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $billsByPeriod = [];

            foreach ($periods as $key => $period) {
                $billsByPeriod[$key] = $this->periodBills(
                    $student->id,
                    $period['year'],
                    $period['month'],
                    lock: true,
                );
            }

            $existingByPeriod = [];

            foreach ($periods as $key => $period) {
                $existingByPeriod[$key] = JemputanDriverSalaryAllocation::query()
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->where('period_year', $period['year'])
                    ->where('period_month', $period['month'])
                    ->lockForUpdate()
                    ->get();
            }

            foreach ($periods as $key => $period) {
                $this->validatePeriod(
                    $period,
                    $billsByPeriod[$key],
                    $currentDriverIds,
                );
            }

            $saved = collect();

            foreach ($periods as $key => $period) {
                $existingByDriver = $existingByPeriod[$key]->keyBy('jemputan_driver_id');

                foreach ($period['allocations'] as $driverId => $amountCents) {
                    $allocation = $existingByDriver->get($driverId);

                    if ($allocation instanceof JemputanDriverSalaryAllocation) {
                        $allocation->update([
                            'allocated_amount' => $this->formatCents($amountCents),
                            'updated_by' => $actor->id,
                        ]);
                        $saved->push($allocation->refresh());
                    } else {
                        $saved->push(JemputanDriverSalaryAllocation::query()->create([
                            'student_id' => $student->id,
                            'academic_year_id' => $academicYear->id,
                            'period_year' => $period['year'],
                            'period_month' => $period['month'],
                            'jemputan_driver_id' => $driverId,
                            'allocated_amount' => $this->formatCents($amountCents),
                            'created_by' => $actor->id,
                            'updated_by' => $actor->id,
                        ]));
                    }
                }

                JemputanDriverSalaryAllocation::query()
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->where('period_year', $period['year'])
                    ->where('period_month', $period['month'])
                    ->whereNotIn('jemputan_driver_id', $currentDriverIds)
                    ->delete();
            }

            return $saved->values();
        });
    }

    /**
     * @param  array<string, list<array{jemputan_driver_id: int|string, allocated_amount: int|float|string}>>  $allocationsByPeriod
     * @param  list<array{key: string, year: int, month: int, label: string}>  $academicYearMonths
     * @return array<string, array{year: int, month: int, allocations: array<int, int>}>
     */
    private function normalizePeriods(array $allocationsByPeriod, array $academicYearMonths): array
    {
        if ($allocationsByPeriod === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Pilih minimal satu periode alokasi.',
            ]);
        }

        $monthsByKey = collect($academicYearMonths)->keyBy('key');
        $periods = [];

        foreach ($allocationsByPeriod as $key => $rows) {
            $key = (string) $key;
            $month = $monthsByKey->get($key);

            if ($month === null) {
                throw ValidationException::withMessages([
                    'allocations' => 'Periode alokasi harus berada di dalam tahun ajaran yang dipilih.',
                ]);
            }

            if (! is_array($rows) || $rows === []) {
                throw ValidationException::withMessages([
                    'allocations.'.$key => 'Isi nominal alokasi untuk setiap sopir.',
                ]);
            }

            $amounts = [];

            foreach ($rows as $row) {
                if (! is_array($row) || ! array_key_exists('jemputan_driver_id', $row)) {
                    throw ValidationException::withMessages([
                        'allocations.'.$key => 'Data sopir alokasi tidak valid.',
                    ]);
                }

                $driverId = filter_var($row['jemputan_driver_id'], FILTER_VALIDATE_INT);

                if ($driverId === false || $driverId <= 0) {
                    throw ValidationException::withMessages([
                        'allocations.'.$key => 'Sopir alokasi tidak valid.',
                    ]);
                }

                if (array_key_exists($driverId, $amounts)) {
                    throw ValidationException::withMessages([
                        'allocations.'.$key => 'Sopir tidak boleh duplikat dalam satu periode.',
                    ]);
                }

                $amounts[$driverId] = $this->normalizeAmount(
                    $row['allocated_amount'] ?? null,
                    'allocations.'.$key.'.'.$driverId,
                );
            }

            ksort($amounts);
            $periods[$key] = [
                'year' => (int) $month['year'],
                'month' => (int) $month['month'],
                'allocations' => $amounts,
            ];
        }

        ksort($periods);

        return $periods;
    }

    /**
     * @param  array{year: int, month: int, allocations: array<int, int>}  $period
     * @param  Collection<int, StudentBill>  $bills
     * @param  list<int>  $currentDriverIds
     */
    private function validatePeriod(
        array $period,
        Collection $bills,
        array $currentDriverIds,
    ): void {
        if (count($currentDriverIds) < 2) {
            throw ValidationException::withMessages([
                'allocations' => 'Siswa dengan satu sopir tidak memerlukan alokasi.',
            ]);
        }

        if ($bills->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Tagihan Jemputan untuk periode ini tidak ditemukan.',
            ]);
        }

        $submittedDriverIds = array_keys($period['allocations']);
        sort($submittedDriverIds);

        if ($submittedDriverIds !== $currentDriverIds) {
            throw ValidationException::withMessages([
                'allocations' => 'Daftar sopir alokasi harus sama dengan anggota aktif siswa.',
            ]);
        }

        $totalAllocatedCents = array_sum($period['allocations']);
        $billCents = $this->billTotalCents($bills);

        if ($totalAllocatedCents !== $billCents) {
            throw ValidationException::withMessages([
                'allocations' => 'Total alokasi harus sama dengan nominal efektif tagihan.',
            ]);
        }
    }

    /**
     * @return Collection<int, StudentBill>
     */
    private function periodBills(
        int $studentId,
        int $periodYear,
        int $periodMonth,
        bool $lock = false,
    ): Collection {
        $paymentTypeIds = PaymentType::query()
            ->where('name', self::PAYMENT_TYPE_NAME)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($paymentTypeIds === []) {
            return collect();
        }

        return StudentBill::query()
            ->where('student_id', $studentId)
            ->whereIn('payment_type_id', $paymentTypeIds)
            ->where('billing_frequency', BillFrequency::Monthly->value)
            ->where('period_month', $periodMonth)
            ->where('period_year', $periodYear)
            ->withSum('adjustments as allocation_adjustment_total', 'amount')
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    /** @param Collection<int, StudentBill> $bills */
    private function billTotalCents(Collection $bills): int
    {
        $total = 0;

        foreach ($bills as $bill) {
            $base = $this->decimalToCents($bill->amount);
            $adjustments = $this->decimalToCents($bill->allocation_adjustment_total ?? 0);
            $total += max(0, $base + $adjustments);
        }

        return $total;
    }

    /**
     * @param  Collection<int, JemputanDriver>  $drivers
     * @param  Collection<int, JemputanDriverSalaryAllocation>  $allocations
     */
    private function resolveStatus(
        Collection $drivers,
        Collection $allocations,
        int $billCents,
        bool $hasBill,
    ): string {
        if ($allocations->isEmpty()) {
            return $drivers->count() <= 1
                ? self::STATUS_NOT_REQUIRED
                : self::STATUS_MISSING;
        }

        $memberDriverIds = $drivers
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $allocationDriverIds = $allocations
            ->pluck('jemputan_driver_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (array_diff($allocationDriverIds, $memberDriverIds) !== []) {
            return self::STATUS_ORPHAN;
        }

        if ($drivers->count() <= 1) {
            return self::STATUS_NOT_REQUIRED;
        }

        $totalAllocatedCents = $allocations->sum(
            fn (JemputanDriverSalaryAllocation $allocation): int => $this->decimalToCents($allocation->allocated_amount)
        );

        if (! $hasBill || $allocationDriverIds !== $memberDriverIds || $totalAllocatedCents !== $billCents) {
            return self::STATUS_STALE;
        }

        return self::STATUS_VALID;
    }

    private function normalizeAmount(int|float|string|null $amount, string $errorKey): int
    {
        if ($amount === null || (! is_int($amount) && ! is_float($amount) && ! is_string($amount))) {
            throw ValidationException::withMessages([
                $errorKey => 'Nominal alokasi wajib berupa angka.',
            ]);
        }

        $value = trim((string) $amount);

        if ($value === '' || ! is_numeric($value)) {
            throw ValidationException::withMessages([
                $errorKey => 'Nominal alokasi wajib berupa angka.',
            ]);
        }

        if ((float) $value < 0) {
            throw ValidationException::withMessages([
                $errorKey => 'Nominal alokasi tidak boleh negatif.',
            ]);
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            throw ValidationException::withMessages([
                $errorKey => 'Nominal alokasi maksimal dua angka desimal.',
            ]);
        }

        return (int) round((float) $value * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function decimalToCents(int|float|string|null $amount): int
    {
        return (int) round((float) ($amount ?? 0) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** @return array{key: string, year: int, month: int, label: string} */
    private function periodPayload(int $year, int $month): array
    {
        $date = CarbonImmutable::createFromDate($year, $month, 1);

        return [
            'key' => $date->format('Y-m'),
            'year' => $year,
            'month' => $month,
            'label' => $date->locale('id')->translatedFormat('F Y'),
        ];
    }

    /** @return list<array{key: string, year: int, month: int, label: string}> */
    private function academicYearMonths(AcademicYear $academicYear): array
    {
        $months = [];
        $start = CarbonImmutable::parse($academicYear->start_date)->startOfMonth();

        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->addMonths($offset);
            $months[] = [
                'key' => $month->format('Y-m'),
                'year' => $month->year,
                'month' => $month->month,
                'label' => $month->locale('id')->translatedFormat('F Y'),
            ];
        }

        return $months;
    }
}
