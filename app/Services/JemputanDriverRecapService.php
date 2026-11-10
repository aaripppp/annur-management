<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\JemputanDriver;
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

class JemputanDriverRecapService
{
    public const STATUS_NO_ASSIGNMENT = 'no_assignment';

    public const STATUS_NO_BILL = 'no_bill';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    private const PAYMENT_TYPE_NAME = 'Jemputan';

    /**
     * Generate one read-only page/chunk of a driver's bill-period recap.
     *
     * Summary values cover only the returned page/chunk. Consumers exporting
     * multiple chunks must add the summaries from every chunk.
     *
     * @return array<string, mixed>
     */
    public function generate(
        int $academicYearId,
        int $driverId,
        ?SchoolLevel $schoolLevel = null,
        ?int $schoolClassId = null,
        int $perPage = 25,
        int $page = 1,
        bool $includeUnassignedBillExceptionCount = true,
    ): array {
        Gate::authorize('viewAny', JemputanDriver::class);

        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $driver = JemputanDriver::query()->findOrFail($driverId);
        $periods = $this->academicYearPeriods($academicYear);
        $paymentTypeIds = PaymentType::query()
            ->where('name', self::PAYMENT_TYPE_NAME)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $students = $this->students(
            $academicYear,
            $driver,
            $schoolLevel,
            $schoolClassId,
            $perPage,
            $page,
        );
        $studentIds = $students->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $bills = $this->bills($studentIds, $paymentTypeIds, $periods);
        $billsByStudentAndPeriod = $bills->groupBy(
            fn (StudentBill $bill): string => $bill->student_id.'|'.sprintf('%04d-%02d', $bill->period_year, $bill->period_month)
        );
        $billIds = $bills->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $allocationsByBill = $this->activeAllocationAggregates($billIds);
        $paymentMetadataByBill = $this->activePaymentMetadata($billIds)->groupBy('bill_id');
        $rows = [];

        foreach ($students->getCollection() as $student) {
            $months = [];

            foreach ($periods as $period) {
                $periodBills = $billsByStudentAndPeriod->get($student->id.'|'.$period['key'], collect());
                $months[$period['key']] = $periodBills->isEmpty()
                    ? $this->emptyMonth(self::STATUS_NO_BILL)
                    : $this->billMonth($periodBills, $allocationsByBill, $paymentMetadataByBill);
            }

            $rows[] = [
                'student_id' => (int) $student->id,
                'student_name' => $student->nama_lengkap,
                'historical_class_id' => $student->historical_class_id === null ? null : (int) $student->historical_class_id,
                'historical_class' => $student->historical_class_name,
                'historical_class_level' => $student->historical_class_level === null ? null : (int) $student->historical_class_level,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'academic_year_id' => $academicYear->id,
                'academic_year' => $academicYear->year,
                'months' => $months,
                'totals' => $this->summary([$months], $periods)['overall'],
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
            ],
            'filters' => [
                'school_level' => $schoolLevel?->value,
                'school_class_id' => $schoolClassId,
            ],
            'summary_basis' => 'bill_period',
            'summary_scope' => 'current_page',
            'months' => $periods,
            'rows' => $rows,
            'summary' => $this->summary(array_column($rows, 'months'), $periods),
            'pagination' => $this->pagination($students),
            'unassigned_bill_exception_count' => $includeUnassignedBillExceptionCount
                ? $this->unassignedBillExceptionCount(
                    $academicYear,
                    $paymentTypeIds,
                    $periods,
                    $schoolLevel,
                    $schoolClassId,
                )
                : 0,
        ];
    }

    /** @return list<array{key: string, month: int, year: int, label: string, start_date: string, end_date: string}> */
    private function academicYearPeriods(AcademicYear $academicYear): array
    {
        $periods = [];
        $start = CarbonImmutable::parse($academicYear->start_date)->startOfMonth();

        for ($offset = 0; $offset < 12; $offset++) {
            $month = $start->addMonths($offset);
            $periods[] = [
                'key' => $month->format('Y-m'),
                'month' => $month->month,
                'year' => $month->year,
                'label' => $month->locale('id')->translatedFormat('F'),
                'start_date' => $month->startOfMonth()->toDateString(),
                'end_date' => $month->endOfMonth()->toDateString(),
            ];
        }

        return $periods;
    }

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    private function students(
        AcademicYear $academicYear,
        JemputanDriver $driver,
        ?SchoolLevel $schoolLevel,
        ?int $schoolClassId,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return Student::query()
            ->leftJoin('student_academic_enrollments as recap_enrollments', function ($join) use ($academicYear): void {
                $join->on('recap_enrollments.student_id', '=', 'students.id')
                    ->where('recap_enrollments.academic_year_id', '=', $academicYear->id);
            })
            ->leftJoin('school_classes as recap_classes', 'recap_classes.id', '=', 'recap_enrollments.school_class_id')
            ->select([
                'students.id',
                'students.nama_lengkap',
                'recap_classes.id as historical_class_id',
                'recap_classes.name as historical_class_name',
                'recap_classes.level as historical_class_level',
            ])
            ->whereHas('jemputanDriverAssignments', fn (Builder $query) => $query
                ->where('jemputan_driver_id', $driver->id)
                ->where('academic_year_id', $academicYear->id)
                ->currentMembership())
            ->when($schoolLevel !== null, fn (Builder $query) => $query->whereIn('recap_classes.level', $schoolLevel->classLevels()))
            ->when($schoolClassId !== null, fn (Builder $query) => $query->where('recap_classes.id', $schoolClassId))
            ->orderBy('students.nama_lengkap')
            ->orderBy('students.id')
            ->paginate(max(1, min($perPage, 500)), ['*'], 'page', max(1, $page));
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<int>  $paymentTypeIds
     * @param  list<array<string, mixed>>  $periods
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
                    $query->orWhere(function (Builder $monthQuery) use ($period): void {
                        $monthQuery->where('period_month', $period['month'])
                            ->where('period_year', $period['year']);
                    });
                }
            })
            ->withSum('adjustments as recap_adjustment_total', 'amount')
            ->orderBy('student_id')
            ->orderBy('id')
            ->get();
    }

    /** @param list<int> $billIds */
    private function activeAllocationAggregates(array $billIds): Collection
    {
        if ($billIds === []) {
            return collect();
        }

        return PaymentDetail::query()
            ->join('payments', 'payments.id', '=', 'payment_details.payment_id')
            ->whereIn('payment_details.bill_id', $billIds)
            ->whereNotNull('payment_details.bill_id')
            ->where('payments.status', Payment::STATUS_ACTIVE)
            ->selectRaw('payment_details.bill_id, SUM(payment_details.amount) as paid_amount, COUNT(DISTINCT payment_details.payment_id) as payment_count')
            ->groupBy('payment_details.bill_id')
            ->get()
            ->keyBy('bill_id');
    }

    /** @param list<int> $billIds */
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
                'payment_details.bill_id',
                'payment_details.payment_id',
                'payments.payment_date',
                'payments.payment_method',
                'banks.name as bank_name',
                'banks.type as bank_type',
                'banks.account_number as bank_account_number',
            ]);
    }

    /**
     * @param  Collection<int, StudentBill>  $bills
     * @param  Collection<int, mixed>  $allocationsByBill
     * @param  Collection<int, Collection<int, mixed>>  $paymentMetadataByBill
     * @return array<string, mixed>
     */
    private function billMonth(Collection $bills, Collection $allocationsByBill, Collection $paymentMetadataByBill): array
    {
        $billAmount = 0.0;
        $adjustmentTotal = 0.0;
        $effectiveBilled = 0.0;
        $paidAmount = 0.0;
        $billIds = [];

        foreach ($bills as $bill) {
            $base = round((float) $bill->amount, 2);
            $adjustments = round((float) ($bill->recap_adjustment_total ?? 0), 2);
            $billAmount += $base;
            $adjustmentTotal += $adjustments;
            $effectiveBilled += max(0.0, round($base + $adjustments, 2));
            $paidAmount += round((float) ($allocationsByBill->get($bill->id)?->paid_amount ?? 0), 2);
            $billIds[] = (int) $bill->id;
        }

        $effectiveBilled = round($effectiveBilled, 2);
        $paidAmount = round($paidAmount, 2);
        $remaining = max(0.0, round($effectiveBilled - $paidAmount, 2));
        $metadataRows = collect($billIds)
            ->flatMap(fn (int $billId): Collection => $paymentMetadataByBill->get($billId, collect()))
            ->sortByDesc(fn ($metadata): string => sprintf('%s|%020d', $metadata->payment_date, $metadata->payment_id))
            ->values();
        $latest = $metadataRows->first();
        $status = match (true) {
            $remaining <= 0 => self::STATUS_PAID,
            $paidAmount <= 0 => self::STATUS_UNPAID,
            default => self::STATUS_PARTIAL,
        };

        return [
            'status' => $status,
            'bill_ids' => $billIds,
            'bill_count' => count($billIds),
            'bill_amount' => round($billAmount, 2),
            'adjustment_total' => round($adjustmentTotal, 2),
            'effective_billed_amount' => $effectiveBilled,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remaining,
            'is_overallocated' => $paidAmount > $effectiveBilled,
            'payment_date' => $latest === null ? null : CarbonImmutable::parse($latest->payment_date)->toDateString(),
            'latest_payment_id' => $latest === null ? null : (int) $latest->payment_id,
            'bank_name' => $latest?->bank_name,
            'bank_account_number' => $this->bankAccountNumber($latest?->bank_type, $latest?->bank_account_number),
            'bank_display' => $this->bankDisplay($latest?->bank_name, $latest?->bank_type, $latest?->bank_account_number),
            'payment_method' => $latest?->payment_method,
            'contributing_payment_count' => $metadataRows->pluck('payment_id')->unique()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyMonth(string $status): array
    {
        return [
            'status' => $status,
            'bill_ids' => [],
            'bill_count' => 0,
            'bill_amount' => 0.0,
            'adjustment_total' => 0.0,
            'effective_billed_amount' => 0.0,
            'paid_amount' => 0.0,
            'remaining_amount' => 0.0,
            'is_overallocated' => false,
            'payment_date' => null,
            'latest_payment_id' => null,
            'bank_name' => null,
            'bank_account_number' => null,
            'bank_display' => null,
            'payment_method' => null,
            'contributing_payment_count' => 0,
        ];
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

    /**
     * @param  list<array<string, array<string, mixed>>>  $rowMonths
     * @param  list<array<string, mixed>>  $periods
     * @return array<string, mixed>
     */
    private function summary(array $rowMonths, array $periods): array
    {
        $monthly = [];

        foreach ($periods as $period) {
            $monthly[$period['key']] = $this->emptySummaryBalance();
        }

        $overall = $this->emptySummaryBalance();

        foreach ($rowMonths as $months) {
            foreach ($periods as $period) {
                $month = $months[$period['key']];

                if ($month['status'] === self::STATUS_NO_ASSIGNMENT) {
                    continue;
                }

                $monthly[$period['key']]['assigned_count']++;
                $overall['assigned_count']++;

                if ($month['status'] === self::STATUS_NO_BILL) {
                    $monthly[$period['key']]['no_bill_count']++;
                    $overall['no_bill_count']++;

                    continue;
                }

                foreach (['effective_billed_amount', 'paid_amount', 'remaining_amount'] as $amountKey) {
                    $monthly[$period['key']][$amountKey] += $month[$amountKey];
                    $overall[$amountKey] += $month[$amountKey];
                }

                $statusCountKey = $month['status'].'_count';
                $monthly[$period['key']][$statusCountKey]++;
                $overall[$statusCountKey]++;

                if ($month['is_overallocated']) {
                    $monthly[$period['key']]['overallocated_count']++;
                    $overall['overallocated_count']++;
                }
            }
        }

        return ['overall' => $this->roundSummary($overall), 'monthly' => array_map($this->roundSummary(...), $monthly)];
    }

    /** @return array<string, float|int> */
    private function emptySummaryBalance(): array
    {
        return [
            'effective_billed_amount' => 0.0,
            'paid_amount' => 0.0,
            'remaining_amount' => 0.0,
            'paid_count' => 0,
            'partial_count' => 0,
            'unpaid_count' => 0,
            'no_bill_count' => 0,
            'assigned_count' => 0,
            'overallocated_count' => 0,
        ];
    }

    /** @param array<string, float|int> $summary */
    private function roundSummary(array $summary): array
    {
        foreach (['effective_billed_amount', 'paid_amount', 'remaining_amount'] as $key) {
            $summary[$key] = round((float) $summary[$key], 2);
        }

        return $summary;
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

    /**
     * @param  list<int>  $paymentTypeIds
     * @param  list<array<string, mixed>>  $periods
     */
    private function unassignedBillExceptionCount(
        AcademicYear $academicYear,
        array $paymentTypeIds,
        array $periods,
        ?SchoolLevel $schoolLevel,
        ?int $schoolClassId,
    ): int {
        if ($paymentTypeIds === []) {
            return 0;
        }

        return StudentBill::query()
            ->whereIn('payment_type_id', $paymentTypeIds)
            ->where('billing_frequency', BillFrequency::Monthly->value)
            ->when($schoolLevel !== null || $schoolClassId !== null, fn (Builder $query) => $query->whereHas(
                'student.enrollments',
                fn (Builder $enrollmentQuery) => $enrollmentQuery
                    ->where('academic_year_id', $academicYear->id)
                    ->when($schoolClassId !== null, fn (Builder $classQuery) => $classQuery->where('school_class_id', $schoolClassId))
                    ->when($schoolLevel !== null, fn (Builder $levelQuery) => $levelQuery->whereHas(
                        'schoolClass',
                        fn (Builder $schoolClassQuery) => $schoolClassQuery->whereIn('level', $schoolLevel->classLevels())
                    ))
            ))
            ->where(function (Builder $periodQuery) use ($periods): void {
                foreach ($periods as $period) {
                    $periodQuery->orWhere(function (Builder $monthQuery) use ($period): void {
                        $monthQuery->where('period_month', $period['month'])
                            ->where('period_year', $period['year']);
                    });
                }
            })
            ->whereDoesntHave('student.jemputanDriverAssignments', fn (Builder $assignmentQuery) => $assignmentQuery
                ->where('academic_year_id', $academicYear->id)
                ->currentMembership())
            ->count();
    }
}
