<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class JemputanDriverSalaryService
{
    public const EXPORT_CHUNK_SIZE = 200;

    public const PAYMENT_STATUS_NO_BILL = 'no_bill';

    public const PAYMENT_STATUS_UNPAID = StudentBill::STATUS_UNPAID;

    public const PAYMENT_STATUS_PARTIAL = StudentBill::STATUS_PARTIAL;

    public const PAYMENT_STATUS_PAID = StudentBill::STATUS_PAID;

    private const PAYMENT_TYPE_NAME = 'Jemputan';

    public function __construct(
        private readonly JemputanDriverDeductionRateResolver $deductionRates,
    ) {}

    /**
     * Generate a read-only, whole-filter salary summary with one paginated student page.
     *
     * The summary always covers every student that matches the filters, not just the
     * returned page. Single-driver students keep their full bill amount. Multi-driver
     * students use a stored allocation only when it is valid for the current bill and
     * membership; missing, stale, and orphan allocations fall back to the full bill
     * and are flagged as not final. No amount is ever split automatically.
     *
     * @return array<string, mixed>
     */
    public function generate(
        int $academicYearId,
        int $driverId,
        ?string $monthKey = null,
        int $perPage = 25,
        int $page = 1,
    ): array {
        Gate::authorize('viewAny', JemputanDriver::class);

        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $driver = JemputanDriver::query()->findOrFail($driverId);
        $months = $this->academicYearMonths($academicYear);
        $selectedMonth = $this->selectedMonth($months, $monthKey);
        $paymentTypeIds = PaymentType::query()
            ->where('name', self::PAYMENT_TYPE_NAME)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $students = $this->students($academicYear, $driver, $perPage, $page);
        $allStudentIds = $this->allStudentIds($academicYear, $driver);
        $billsByStudent = $this->bills($allStudentIds, $paymentTypeIds, $selectedMonth)
            ->groupBy('student_id');
        $driverMemberships = $this->currentDriverMemberships($allStudentIds, $academicYear);
        $allocationsByStudent = $this->allocationsForPeriod($allStudentIds, $academicYear, $selectedMonth)
            ->groupBy('student_id');
        $deductionRate = $this->deductionRates->resolve(
            $driver,
            (int) $selectedMonth['year'],
            (int) $selectedMonth['month'],
        );
        $percentage = $deductionRate['percentage'];
        $totals = $this->salaryTotals(
            $driver->id,
            $allStudentIds,
            $billsByStudent,
            $driverMemberships,
            $allocationsByStudent,
        );
        $summary = $this->summaryWithAllocationMetadata($totals, $percentage, $deductionRate);
        $rows = [];

        foreach ($students->getCollection() as $student) {
            $studentBills = $billsByStudent->get($student->id, collect());
            $billAmount = $this->effectiveBillTotal($studentBills);
            $currentDriverIds = $driverMemberships->get($student->id, []);
            $driverCount = count($currentDriverIds);
            $resolution = $studentBills->isEmpty()
                ? [
                    'allocation_status' => JemputanDriverAllocationService::STATUS_NOT_REQUIRED,
                    'salary_amount' => 0.0,
                ]
                : $this->resolveSalaryAmount(
                    $driver->id,
                    $billAmount,
                    $currentDriverIds,
                    $allocationsByStudent->get($student->id, collect()),
                );
            $paymentStatus = $this->paymentStatus($studentBills, $billAmount);

            $rows[] = [
                'student_id' => (int) $student->id,
                'student_name' => $student->nama_lengkap,
                'historical_class_id' => $student->historical_class_id === null ? null : (int) $student->historical_class_id,
                'historical_class' => $student->historical_class_name,
                'bill_amount' => $billAmount,
                'salary_amount' => $resolution['salary_amount'],
                'allocation_status' => $resolution['allocation_status'],
                'bill_count' => $studentBills->count(),
                'has_bill' => $studentBills->isNotEmpty(),
                'current_driver_count' => $driverCount,
                'is_multi_driver' => $driverCount > 1,
                'payment_status' => $paymentStatus['status'],
                'payment_status_label' => $paymentStatus['label'],
                'paid_amount' => $paymentStatus['paid_amount'],
                'remaining_amount' => $paymentStatus['remaining_amount'],
            ];
        }

        $students->setCollection(collect($rows));

        return [
            'academic_year' => [
                'id' => $academicYear->id,
                'label' => $academicYear->year,
                'start_date' => $academicYear->start_date->toDateString(),
                'end_date' => $academicYear->end_date->toDateString(),
            ],
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'is_active' => $driver->is_active,
                'foundation_deduction_percentage' => $percentage,
            ],
            'month' => $selectedMonth,
            'months' => $months,
            'rows' => $rows,
            'summary' => $summary,
            'multi_driver_student_count' => $totals['multi_driver_student_count'],
            'has_multi_driver' => $totals['multi_driver_student_count'] > 0,
            'pagination' => $this->pagination($students),
        ];
    }

    /**
     * Generate every filtered salary row for a one-driver, one-month export.
     *
     * Students are read in chunks so the export is independent from the active
     * page. Latest active payment metadata is included for display only. Salary
     * amounts use the same allocation resolver as the paginated report.
     *
     * @return array<string, mixed>
     */
    public function exportData(
        int $academicYearId,
        int $driverId,
        ?string $monthKey = null,
    ): array {
        Gate::authorize('viewAny', JemputanDriver::class);

        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $driver = JemputanDriver::query()->findOrFail($driverId);
        $months = $this->academicYearMonths($academicYear);
        $selectedMonth = $this->selectedMonth($months, $monthKey);
        $paymentTypeIds = PaymentType::query()
            ->where('name', self::PAYMENT_TYPE_NAME)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $deductionRate = $this->deductionRates->resolve(
            $driver,
            (int) $selectedMonth['year'],
            (int) $selectedMonth['month'],
        );
        $percentage = $deductionRate['percentage'];
        $rows = [];
        $totalRevenue = 0.0;
        $multiDriverStudentCount = 0;
        $unallocatedMultiDriverCount = 0;
        $staleAllocationCount = 0;

        $this->studentsQuery($academicYear, $driver)
            ->orderBy('students.nama_lengkap')
            ->orderBy('students.id')
            ->chunk(self::EXPORT_CHUNK_SIZE, function (Collection $students) use (
                $academicYear,
                $paymentTypeIds,
                $selectedMonth,
                $driver,
                &$rows,
                &$totalRevenue,
                &$multiDriverStudentCount,
                &$unallocatedMultiDriverCount,
                &$staleAllocationCount,
            ): void {
                $studentIds = $students->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $chunkBills = $this->bills($studentIds, $paymentTypeIds, $selectedMonth)
                    ->groupBy('student_id');
                $billIds = $chunkBills
                    ->flatten(1)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
                $driverMemberships = $this->currentDriverMemberships($studentIds, $academicYear);
                $allocationsByStudent = $this->allocationsForPeriod($studentIds, $academicYear, $selectedMonth)
                    ->groupBy('student_id');
                $paymentMetadataByBill = $this->activePaymentMetadata($billIds)->groupBy('bill_id');

                foreach ($students as $student) {
                    $studentBills = $chunkBills->get($student->id, collect());
                    $billAmount = $this->effectiveBillTotal($studentBills);
                    $currentDriverIds = $driverMemberships->get($student->id, []);
                    $driverCount = count($currentDriverIds);
                    $resolution = $this->resolveSalaryAmount(
                        $driver->id,
                        $billAmount,
                        $currentDriverIds,
                        $allocationsByStudent->get($student->id, collect()),
                    );
                    $latestPayment = $studentBills
                        ->flatMap(fn (StudentBill $bill): Collection => $paymentMetadataByBill->get($bill->id, collect()))
                        ->sortByDesc(fn ($metadata): string => sprintf(
                            '%s|%020d|%020d',
                            (string) $metadata->payment_date,
                            (int) $metadata->payment_id,
                            (int) $metadata->detail_id,
                        ))
                        ->first();

                    $rows[] = [
                        'student_id' => (int) $student->id,
                        'student_name' => $student->nama_lengkap,
                        'historical_class_id' => $student->historical_class_id === null ? null : (int) $student->historical_class_id,
                        'historical_class' => $student->historical_class_name,
                        'bill_amount' => $billAmount,
                        'salary_amount' => $resolution['salary_amount'],
                        'allocation_status' => $resolution['allocation_status'],
                        'bill_count' => $studentBills->count(),
                        'has_bill' => $studentBills->isNotEmpty(),
                        'current_driver_count' => $driverCount,
                        'is_multi_driver' => $driverCount > 1,
                        'payment_date' => $latestPayment === null
                            ? null
                            : CarbonImmutable::parse($latestPayment->payment_date)->toDateString(),
                        'payment_method' => $latestPayment?->payment_method,
                        'bank_display' => $latestPayment === null
                            ? null
                            : $this->bankDisplay(
                                $latestPayment->bank_name,
                                $latestPayment->bank_type,
                                $latestPayment->bank_account_number,
                            ),
                    ];

                    $totalRevenue += $resolution['salary_amount'];

                    if ($driverCount > 1) {
                        $multiDriverStudentCount++;
                    }

                    if ($resolution['allocation_status'] === JemputanDriverAllocationService::STATUS_MISSING) {
                        $unallocatedMultiDriverCount++;
                    } elseif (in_array($resolution['allocation_status'], [
                        JemputanDriverAllocationService::STATUS_STALE,
                        JemputanDriverAllocationService::STATUS_ORPHAN,
                    ], true)) {
                        $staleAllocationCount++;
                    }
                }
            });

        $totals = [
            'revenue' => $totalRevenue,
            'multi_driver_student_count' => $multiDriverStudentCount,
            'unallocated_multi_driver_count' => $unallocatedMultiDriverCount,
            'stale_allocation_count' => $staleAllocationCount,
        ];

        return [
            'academic_year' => [
                'id' => $academicYear->id,
                'label' => $academicYear->year,
                'start_date' => $academicYear->start_date->toDateString(),
                'end_date' => $academicYear->end_date->toDateString(),
            ],
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'is_active' => $driver->is_active,
                'foundation_deduction_percentage' => $percentage,
            ],
            'month' => $selectedMonth,
            'months' => $months,
            'rows' => $rows,
            'summary' => $this->summaryWithAllocationMetadata($totals, $percentage, $deductionRate),
            'multi_driver_student_count' => $multiDriverStudentCount,
            'has_multi_driver' => $multiDriverStudentCount > 0,
        ];
    }

    /** @return list<array{key: string, month: int, year: int, label: string}> */
    private function academicYearMonths(AcademicYear $academicYear): array
    {
        $months = [];
        $start = CarbonImmutable::parse($academicYear->start_date)->startOfMonth();

        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->addMonths($offset);
            $months[] = [
                'key' => $month->format('Y-m'),
                'month' => $month->month,
                'year' => $month->year,
                'label' => $month->locale('id')->translatedFormat('F Y'),
            ];
        }

        return $months;
    }

    /** @param list<array{key: string, month: int, year: int, label: string}> $months */
    private function selectedMonth(array $months, ?string $monthKey): array
    {
        foreach ($months as $month) {
            if ($month['key'] === $monthKey) {
                return $month;
            }
        }

        return $months[0];
    }

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    private function students(
        AcademicYear $academicYear,
        JemputanDriver $driver,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return $this->studentsQuery($academicYear, $driver)
            ->orderBy('students.nama_lengkap')
            ->orderBy('students.id')
            ->paginate(max(1, min($perPage, 500)), ['*'], 'salaryPage', max(1, $page));
    }

    /** @return Builder<Student> */
    private function studentsQuery(AcademicYear $academicYear, JemputanDriver $driver): Builder
    {
        return Student::query()
            ->leftJoin('student_academic_enrollments as salary_enrollments', function ($join) use ($academicYear): void {
                $join->on('salary_enrollments.student_id', '=', 'students.id')
                    ->where('salary_enrollments.academic_year_id', '=', $academicYear->id);
            })
            ->leftJoin('school_classes as salary_classes', 'salary_classes.id', '=', 'salary_enrollments.school_class_id')
            ->select([
                'students.id',
                'students.nama_lengkap',
                'salary_classes.id as historical_class_id',
                'salary_classes.name as historical_class_name',
            ])
            ->whereHas('jemputanDriverAssignments', fn (Builder $query) => $query
                ->where('jemputan_driver_id', $driver->id)
                ->where('academic_year_id', $academicYear->id)
                ->currentMembership());
    }

    /** @return list<int> */
    private function allStudentIds(AcademicYear $academicYear, JemputanDriver $driver): array
    {
        return $this->studentsQuery($academicYear, $driver)
            ->pluck('students.id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $studentIds
     * @param  Collection<int, Collection<int, StudentBill>>  $billsByStudent
     * @param  Collection<int, list<int>>  $driverMemberships
     * @param  Collection<int, Collection<int, JemputanDriverSalaryAllocation>>  $allocationsByStudent
     * @return array{revenue: float, multi_driver_student_count: int, unallocated_multi_driver_count: int, stale_allocation_count: int}
     */
    private function salaryTotals(
        int $driverId,
        array $studentIds,
        Collection $billsByStudent,
        Collection $driverMemberships,
        Collection $allocationsByStudent,
    ): array {
        $revenue = 0.0;
        $multiDriverStudentCount = 0;
        $unallocatedMultiDriverCount = 0;
        $staleAllocationCount = 0;

        foreach ($studentIds as $studentId) {
            $studentBills = $billsByStudent->get($studentId, collect());
            $billAmount = $this->effectiveBillTotal($studentBills);
            $currentDriverIds = $driverMemberships->get($studentId, []);
            $driverCount = count($currentDriverIds);
            $resolution = $studentBills->isEmpty()
                ? [
                    'allocation_status' => JemputanDriverAllocationService::STATUS_NOT_REQUIRED,
                    'salary_amount' => 0.0,
                ]
                : $this->resolveSalaryAmount(
                    $driverId,
                    $billAmount,
                    $currentDriverIds,
                    $allocationsByStudent->get($studentId, collect()),
                );

            $revenue += $resolution['salary_amount'];

            if ($driverCount > 1) {
                $multiDriverStudentCount++;
            }

            if ($resolution['allocation_status'] === JemputanDriverAllocationService::STATUS_MISSING) {
                $unallocatedMultiDriverCount++;
            } elseif (in_array($resolution['allocation_status'], [
                JemputanDriverAllocationService::STATUS_STALE,
                JemputanDriverAllocationService::STATUS_ORPHAN,
            ], true)) {
                $staleAllocationCount++;
            }
        }

        return [
            'revenue' => round($revenue, 2),
            'multi_driver_student_count' => $multiDriverStudentCount,
            'unallocated_multi_driver_count' => $unallocatedMultiDriverCount,
            'stale_allocation_count' => $staleAllocationCount,
        ];
    }

    /**
     * Resolve the amount that belongs to one driver for one student row.
     *
     * A single-driver student always uses the full effective bill. Multi-driver
     * students use their stored allocation only when every current member has
     * exactly one allocation and the total matches the live effective bill.
     * Missing, stale, and orphan allocations fall back to the full bill so the
     * report stays usable while clearly flagged as not final.
     *
     * @param  list<int>  $currentDriverIds
     * @param  Collection<int, JemputanDriverSalaryAllocation>  $allocations
     * @return array{allocation_status: string, salary_amount: float}
     */
    public function resolveSalaryAmount(
        int $driverId,
        float $billAmount,
        array $currentDriverIds,
        Collection $allocations,
    ): array {
        $driverCount = count($currentDriverIds);

        if ($allocations->isEmpty()) {
            return [
                'allocation_status' => $driverCount <= 1
                    ? JemputanDriverAllocationService::STATUS_NOT_REQUIRED
                    : JemputanDriverAllocationService::STATUS_MISSING,
                'salary_amount' => $billAmount,
            ];
        }

        $allocationDriverIds = $allocations
            ->pluck('jemputan_driver_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $uniqueAllocationDriverIds = collect($allocationDriverIds)->unique()->values()->all();

        if (array_diff($uniqueAllocationDriverIds, $currentDriverIds) !== []) {
            return [
                'allocation_status' => JemputanDriverAllocationService::STATUS_ORPHAN,
                'salary_amount' => $billAmount,
            ];
        }

        if ($driverCount <= 1) {
            return [
                'allocation_status' => JemputanDriverAllocationService::STATUS_NOT_REQUIRED,
                'salary_amount' => $billAmount,
            ];
        }

        $totalAllocatedCents = $allocations->sum(
            fn (JemputanDriverSalaryAllocation $allocation): int => (int) round(
                (float) $allocation->allocated_amount * 100,
                0,
                PHP_ROUND_HALF_UP,
            )
        );

        if (
            count($allocationDriverIds) !== count($uniqueAllocationDriverIds)
            || $uniqueAllocationDriverIds !== $currentDriverIds
            || $totalAllocatedCents !== (int) round($billAmount * 100, 0, PHP_ROUND_HALF_UP)
        ) {
            return [
                'allocation_status' => JemputanDriverAllocationService::STATUS_STALE,
                'salary_amount' => $billAmount,
            ];
        }

        $allocation = $allocations->firstWhere('jemputan_driver_id', $driverId);

        if (! $allocation instanceof JemputanDriverSalaryAllocation) {
            return [
                'allocation_status' => JemputanDriverAllocationService::STATUS_STALE,
                'salary_amount' => $billAmount,
            ];
        }

        return [
            'allocation_status' => JemputanDriverAllocationService::STATUS_VALID,
            'salary_amount' => round((float) $allocation->allocated_amount, 2),
        ];
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<int>  $paymentTypeIds
     * @param  array{key: string, month: int, year: int, label: string}  $month
     * @return Collection<int, StudentBill>
     */
    private function bills(array $studentIds, array $paymentTypeIds, array $month): Collection
    {
        if ($studentIds === [] || $paymentTypeIds === []) {
            return collect();
        }

        return StudentBill::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('payment_type_id', $paymentTypeIds)
            ->where('billing_frequency', BillFrequency::Monthly->value)
            ->where('period_month', $month['month'])
            ->where('period_year', $month['year'])
            ->withSum('adjustments as salary_adjustment_total', 'amount')
            ->withSum([
                'paymentDetails as salary_paid_total' => fn (Builder $query) => $query
                    ->whereHas('payment', fn (Builder $paymentQuery) => $paymentQuery
                        ->where('status', Payment::STATUS_ACTIVE)),
            ], 'amount')
            ->orderBy('student_id')
            ->orderBy('id')
            ->get();
    }

    /** @param Collection<int, StudentBill> $bills */
    public function effectiveBillTotal(Collection $bills): float
    {
        $total = 0.0;

        foreach ($bills as $bill) {
            $base = round((float) $bill->amount, 2);
            $adjustments = round((float) ($bill->salary_adjustment_total ?? 0), 2);
            $total += max(0.0, round($base + $adjustments, 2));
        }

        return round($total, 2);
    }

    /**
     * @param  Collection<int, StudentBill>  $bills
     * @return array{status: string, label: string, paid_amount: float, remaining_amount: float}
     */
    private function paymentStatus(Collection $bills, float $effectiveBillTotal): array
    {
        if ($bills->isEmpty()) {
            return [
                'status' => self::PAYMENT_STATUS_NO_BILL,
                'label' => 'Belum Ada Tagihan',
                'paid_amount' => 0.0,
                'remaining_amount' => 0.0,
            ];
        }

        $paidAmount = round((float) $bills->sum(
            fn (StudentBill $bill): float => (float) ($bill->salary_paid_total ?? 0),
        ), 2);
        $remainingAmount = max(0.0, round($effectiveBillTotal - $paidAmount, 2));
        $status = match (true) {
            $remainingAmount <= 0 => self::PAYMENT_STATUS_PAID,
            $paidAmount <= 0 => self::PAYMENT_STATUS_UNPAID,
            default => self::PAYMENT_STATUS_PARTIAL,
        };

        return [
            'status' => $status,
            'label' => match ($status) {
                self::PAYMENT_STATUS_PAID => 'Lunas',
                self::PAYMENT_STATUS_PARTIAL => 'Sebagian',
                default => 'Belum Lunas',
            },
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
        ];
    }

    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, list<int>>
     */
    private function currentDriverMemberships(array $studentIds, AcademicYear $academicYear): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return JemputanDriverAssignment::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_year_id', $academicYear->id)
            ->currentMembership()
            ->orderBy('student_id')
            ->orderBy('jemputan_driver_id')
            ->get(['student_id', 'jemputan_driver_id'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows): array => $rows
                ->pluck('jemputan_driver_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all());
    }

    /**
     * @param  list<int>  $studentIds
     * @param  array{key: string, month: int, year: int, label: string}  $month
     * @return Collection<int, JemputanDriverSalaryAllocation>
     */
    private function allocationsForPeriod(
        array $studentIds,
        AcademicYear $academicYear,
        array $month,
    ): Collection {
        if ($studentIds === []) {
            return collect();
        }

        return JemputanDriverSalaryAllocation::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_year_id', $academicYear->id)
            ->where('period_year', $month['year'])
            ->where('period_month', $month['month'])
            ->get(['id', 'student_id', 'jemputan_driver_id', 'allocated_amount']);
    }

    /**
     * @param  list<int>  $billIds
     * @return Collection<int, PaymentDetail>
     */
    private function activePaymentMetadata(array $billIds): Collection
    {
        if ($billIds === []) {
            return collect();
        }

        return PaymentDetail::query()
            ->join('payments', 'payments.id', '=', 'payment_details.payment_id')
            ->leftJoin('banks', 'banks.id', '=', 'payments.bank_id')
            ->whereIn('payment_details.bill_id', $billIds)
            ->whereNotNull('payment_details.bill_id')
            ->where('payments.status', Payment::STATUS_ACTIVE)
            ->orderByDesc('payments.payment_date')
            ->orderByDesc('payments.id')
            ->orderByDesc('payment_details.id')
            ->get([
                'payment_details.id as detail_id',
                'payment_details.bill_id',
                'payment_details.payment_id',
                'payments.payment_date',
                'payments.payment_method',
                'banks.name as bank_name',
                'banks.type as bank_type',
                'banks.account_number as bank_account_number',
            ]);
    }

    private function bankDisplay(?string $bankName, ?string $bankType, ?string $accountNumber): ?string
    {
        if ($bankName === null || trim($bankName) === '') {
            return null;
        }

        $displayAccountNumber = $this->bankAccountNumber($bankType, $accountNumber);

        return $displayAccountNumber === null
            ? $bankName
            : $bankName.' · '.$displayAccountNumber;
    }

    private function bankAccountNumber(?string $bankType, ?string $accountNumber): ?string
    {
        if ($bankType === Bank::TYPE_CASH) {
            return null;
        }

        $accountNumber = trim((string) $accountNumber);

        return $accountNumber === '' ? null : $accountNumber;
    }

    /** @return array<string, mixed> */
    public function summary(float $revenue, ?float $percentage): array
    {
        $roundedRevenue = (int) round($revenue, 0, PHP_ROUND_HALF_UP);

        if ($percentage === null) {
            return [
                'revenue' => $roundedRevenue,
                'percentage' => null,
                'deduction' => null,
                'net_salary' => null,
                'percentage_configured' => false,
            ];
        }

        $deduction = (int) round($roundedRevenue * $percentage / 100, 0, PHP_ROUND_HALF_UP);

        return [
            'revenue' => $roundedRevenue,
            'percentage' => $percentage,
            'deduction' => $deduction,
            'net_salary' => $roundedRevenue - $deduction,
            'percentage_configured' => true,
        ];
    }

    /**
     * @param  array{revenue: float, multi_driver_student_count: int, unallocated_multi_driver_count: int, stale_allocation_count: int}  $totals
     * @param  array{percentage: float|null, configured: bool, source: string, effective_year: int|null, effective_month: int|null}  $deductionRate
     * @return array<string, mixed>
     */
    private function summaryWithAllocationMetadata(
        array $totals,
        ?float $percentage,
        array $deductionRate,
    ): array {
        $unallocatedCount = (int) $totals['unallocated_multi_driver_count'];
        $staleCount = (int) $totals['stale_allocation_count'];

        return array_merge($this->summary($totals['revenue'], $percentage), [
            'unallocated_multi_driver_count' => $unallocatedCount,
            'stale_allocation_count' => $staleCount,
            'has_allocation_warning' => ($unallocatedCount + $staleCount) > 0,
            'percentage_source' => $deductionRate['source'],
            'percentage_is_legacy' => $deductionRate['source'] === JemputanDriverDeductionRateResolver::SOURCE_LEGACY,
            'percentage_effective_year' => $deductionRate['effective_year'],
            'percentage_effective_month' => $deductionRate['effective_month'],
        ]);
    }

    /** @return array<string, int|null> */
    private function pagination(LengthAwarePaginator $students): array
    {
        return [
            'current_page' => $students->currentPage(),
            'per_page' => $students->perPage(),
            'last_page' => $students->lastPage(),
            'total' => $students->total(),
            'from' => $students->firstItem(),
            'to' => $students->lastItem(),
        ];
    }
}
