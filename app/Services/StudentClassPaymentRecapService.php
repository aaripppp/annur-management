<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Support\StudentBillbook;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StudentClassPaymentRecapService
{
    /**
     * @return array{
     *     academic_year_id: int,
     *     academic_year: string,
     *     school_level: string,
     *     school_class_id: int,
     *     school_class: string,
     *     months: list<array{key: string, month: int, year: int, label: string}>,
     *     payment_types: array{
     *         monthly: list<array{id: int, name: string}>,
     *         yearly: list<array{id: int, name: string}>,
     *         one_time: list<array{id: int, name: string}>
     *     },
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     student_count: int
     * }
     */
    public function generate(int $academicYearId, SchoolLevel $schoolLevel, int $schoolClassId): array
    {
        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $schoolClass = SchoolClass::query()->findOrFail($schoolClassId);

        if ($schoolClass->school_level !== $schoolLevel) {
            throw new InvalidArgumentException('Kelas tidak sesuai dengan jenjang yang dipilih.');
        }

        $periods = $this->academicYearPeriods($academicYear);
        $paymentTypes = $this->configuredPaymentTypes($schoolLevel);
        $monthlyTypeIds = array_column($paymentTypes['monthly'], 'id');
        $yearlyTypeIds = array_column($paymentTypes['yearly'], 'id');
        $oneTimeTypeIds = array_column($paymentTypes['one_time'], 'id');

        $enrollments = StudentAcademicEnrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('status', 'active')
            ->with(['student.enrollments.academicYear'])
            ->get()
            ->filter(fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->student instanceof Student)
            ->sortBy(fn (StudentAcademicEnrollment $enrollment): string => Str::lower($enrollment->student->nama_lengkap))
            ->values();

        $studentIds = $enrollments->pluck('student_id')->map(fn ($id): int => (int) $id)->all();
        $billsByStudent = $this->billsForStudents($studentIds, $academicYear, $periods)->groupBy('student_id');
        $rows = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;

            if (! $student instanceof Student) {
                continue;
            }

            $row = $this->emptyRow($student, $periods, $monthlyTypeIds, $yearlyTypeIds, $oneTimeTypeIds);

            foreach ($billsByStudent->get($student->id, collect()) as $bill) {
                $this->applyBill(
                    $row,
                    $bill,
                    $academicYear,
                    $student->finalEnrollmentYearLabel(),
                    $monthlyTypeIds,
                    $yearlyTypeIds,
                    $oneTimeTypeIds,
                );
            }

            foreach ($monthlyTypeIds as $typeId) {
                // Ringkasan is one expected monthly amount, taken from the latest persisted bill period.
                foreach ($row['monthly_targets'] as $targets) {
                    if ($targets[$typeId] !== null) {
                        $row['monthly_summary'][$typeId] = $targets[$typeId];
                    }
                }
            }

            $row['monthly_summary']['total'] = array_sum($row['monthly_summary']);
            unset($row['monthly_targets']);
            $rows[] = $row;
        }

        return [
            'academic_year_id' => $academicYear->id,
            'academic_year' => $academicYear->year,
            'school_level' => $schoolLevel->value,
            'school_class_id' => $schoolClass->id,
            'school_class' => $schoolClass->name,
            'months' => $periods,
            'payment_types' => $paymentTypes,
            'rows' => $rows,
            'totals' => $this->totals($rows, $periods, $monthlyTypeIds, $yearlyTypeIds, $oneTimeTypeIds),
            'student_count' => count($rows),
        ];
    }

    /**
     * Payment type columns come from the active jenjang applicability
     * (payment_type_school_levels) plus the billing frequency each type is
     * configured with through its PaymentRate rows. No payment type is
     * identified by name.
     *
     * @return array{
     *     monthly: list<array{id: int, name: string}>,
     *     yearly: list<array{id: int, name: string}>,
     *     one_time: list<array{id: int, name: string}>
     * }
     */
    private function configuredPaymentTypes(SchoolLevel $schoolLevel): array
    {
        $types = PaymentTypeSchoolLevel::query()
            ->where('school_level', $schoolLevel)
            ->where('is_active', true)
            ->with(['paymentType.rates'])
            ->get()
            ->pluck('paymentType')
            ->filter(fn ($type): bool => $type instanceof PaymentType)
            ->unique('id')
            ->sortBy('id')
            ->values();

        $groups = ['monthly' => [], 'yearly' => [], 'one_time' => []];

        foreach ($types as $type) {
            $frequencies = $type->rates
                ->map(fn (PaymentRate $rate): ?string => $rate->billing_frequency?->value)
                ->filter()
                ->unique()
                ->values();

            foreach ($frequencies as $frequency) {
                $groups[$frequency][] = ['id' => (int) $type->id, 'name' => $type->name];
            }
        }

        return $groups;
    }

    /** @return list<array{key: string, month: int, year: int, label: string}> */
    private function academicYearPeriods(AcademicYear $academicYear): array
    {
        $periods = [];
        $start = CarbonImmutable::parse($academicYear->start_date)->startOfMonth();

        for ($offset = 0; $offset < 12; $offset++) {
            $date = $start->addMonths($offset);
            $periods[] = [
                'key' => $date->format('Y-m'),
                'month' => (int) $date->format('n'),
                'year' => (int) $date->format('Y'),
                'label' => $date->settings(['locale' => 'id'])->translatedFormat('F'),
            ];
        }

        return $periods;
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<array{key: string, month: int, year: int, label: string}>  $periods
     * @return Collection<int, StudentBill>
     */
    private function billsForStudents(array $studentIds, AcademicYear $academicYear, array $periods): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return StudentBill::query()
            ->whereIn('student_id', $studentIds)
            ->where(function (Builder $query) use ($academicYear, $periods): void {
                $query->where(function (Builder $monthlyQuery) use ($periods): void {
                    $monthlyQuery->where('billing_frequency', BillFrequency::Monthly->value)
                        ->where(function (Builder $periodQuery) use ($periods): void {
                            foreach ($periods as $period) {
                                $periodQuery->orWhere(function (Builder $monthQuery) use ($period): void {
                                    $monthQuery->where('period_month', $period['month'])
                                        ->where('period_year', $period['year']);
                                });
                            }
                        });
                })->orWhere(function (Builder $yearlyQuery) use ($academicYear): void {
                    $yearlyQuery->where('billing_frequency', BillFrequency::Yearly->value)
                        ->where('academic_year', $academicYear->year);
                })->orWhere('billing_frequency', BillFrequency::OneTime->value);
            })
            ->withSum('adjustments as recap_adjustments_total', 'amount')
            ->withSum([
                'paymentDetails as recap_paid_total' => fn (Builder $query) => $query->whereHas(
                    'payment',
                    fn (Builder $paymentQuery) => $paymentQuery->where('status', Payment::STATUS_ACTIVE)
                ),
            ], 'amount')
            ->orderBy('student_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array{key: string, month: int, year: int, label: string}>  $periods
     * @param  list<int>  $monthlyTypeIds
     * @param  list<int>  $yearlyTypeIds
     * @param  list<int>  $oneTimeTypeIds
     * @return array<string, mixed>
     */
    private function emptyRow(Student $student, array $periods, array $monthlyTypeIds, array $yearlyTypeIds, array $oneTimeTypeIds): array
    {
        $monthlyAmounts = [];
        $monthlyTargets = [];

        foreach ($periods as $period) {
            $monthlyAmounts[$period['key']] = array_fill_keys($monthlyTypeIds, 0.0);
            $monthlyTargets[$period['key']] = array_fill_keys($monthlyTypeIds, null);
        }

        $yearly = [];
        foreach ($yearlyTypeIds as $typeId) {
            $yearly[$typeId] = $this->emptyBalance();
        }

        $oneTime = [];
        foreach ($oneTimeTypeIds as $typeId) {
            $oneTime[$typeId] = $this->emptyBalance();
        }

        $monthlySummary = array_fill_keys($monthlyTypeIds, 0.0);
        $monthlySummary['total'] = 0.0;

        return [
            'student_id' => $student->id,
            'student_name' => $student->nama_lengkap,
            'monthly_summary' => $monthlySummary,
            'monthly_targets' => $monthlyTargets,
            'monthly_paid' => $monthlyAmounts,
            'yearly' => $yearly,
            'one_time' => $oneTime,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<int>  $monthlyTypeIds
     * @param  list<int>  $yearlyTypeIds
     * @param  list<int>  $oneTimeTypeIds
     */
    private function applyBill(
        array &$row,
        StudentBill $bill,
        AcademicYear $academicYear,
        ?string $enrollmentBoundaryYear,
        array $monthlyTypeIds,
        array $yearlyTypeIds,
        array $oneTimeTypeIds,
    ): void {
        $target = max(0.0, round((float) $bill->amount + (float) ($bill->recap_adjustments_total ?? 0), 2));
        $allocated = max(0.0, round((float) ($bill->recap_paid_total ?? 0), 2));
        $paid = min($target, $allocated);
        $frequency = BillFrequency::tryFrom((string) $bill->billing_frequency);
        $typeId = (int) $bill->payment_type_id;

        if ($frequency === BillFrequency::Monthly) {
            $periodKey = sprintf('%04d-%02d', $bill->period_year, $bill->period_month);

            if (in_array($typeId, $monthlyTypeIds, true) && isset($row['monthly_paid'][$periodKey])) {
                $row['monthly_targets'][$periodKey][$typeId] = ($row['monthly_targets'][$periodKey][$typeId] ?? 0.0) + $target;
                $row['monthly_paid'][$periodKey][$typeId] += $paid;
            }

            return;
        }

        if ($frequency === BillFrequency::Yearly) {
            if (in_array($typeId, $yearlyTypeIds, true) && $bill->academic_year === $academicYear->year) {
                $this->addBalance($row['yearly'][$typeId], $target, $paid);
            }

            return;
        }

        if ($frequency === BillFrequency::OneTime
            && in_array($typeId, $oneTimeTypeIds, true)
            && StudentBillbook::oneTimeBillVisibleForAcademicYear($bill, $academicYear->year, $enrollmentBoundaryYear)) {
            $this->addBalance($row['one_time'][$typeId], $target, $paid);
        }
    }

    /** @return array{target: float, paid: float, remaining: float} */
    private function emptyBalance(): array
    {
        return ['target' => 0.0, 'paid' => 0.0, 'remaining' => 0.0];
    }

    /** @param array{target: float, paid: float, remaining: float} $balance */
    private function addBalance(array &$balance, float $target, float $paid): void
    {
        $balance['target'] += $target;
        $balance['paid'] += $paid;
        $balance['remaining'] += max(0.0, round($target - $paid, 2));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key: string, month: int, year: int, label: string}>  $periods
     * @param  list<int>  $monthlyTypeIds
     * @param  list<int>  $yearlyTypeIds
     * @param  list<int>  $oneTimeTypeIds
     * @return array<string, mixed>
     */
    private function totals(array $rows, array $periods, array $monthlyTypeIds, array $yearlyTypeIds, array $oneTimeTypeIds): array
    {
        $monthlySummary = array_fill_keys($monthlyTypeIds, 0.0);
        $monthlySummary['total'] = 0.0;

        $totals = [
            'monthly_summary' => $monthlySummary,
            'monthly_paid' => [],
            'yearly' => [],
            'one_time' => [],
        ];

        foreach ($periods as $period) {
            $totals['monthly_paid'][$period['key']] = array_fill_keys($monthlyTypeIds, 0.0);
        }

        foreach ($yearlyTypeIds as $typeId) {
            $totals['yearly'][$typeId] = $this->emptyBalance();
        }

        foreach ($oneTimeTypeIds as $typeId) {
            $totals['one_time'][$typeId] = $this->emptyBalance();
        }

        foreach ($rows as $row) {
            foreach ($totals['monthly_summary'] as $key => $amount) {
                $totals['monthly_summary'][$key] = $amount + $row['monthly_summary'][$key];
            }

            foreach ($totals['monthly_paid'] as $periodKey => $types) {
                foreach ($types as $typeId => $amount) {
                    $totals['monthly_paid'][$periodKey][$typeId] = $amount + $row['monthly_paid'][$periodKey][$typeId];
                }
            }

            foreach ($yearlyTypeIds as $typeId) {
                foreach ($totals['yearly'][$typeId] as $key => $amount) {
                    $totals['yearly'][$typeId][$key] = $amount + $row['yearly'][$typeId][$key];
                }
            }

            foreach ($oneTimeTypeIds as $typeId) {
                foreach ($totals['one_time'][$typeId] as $key => $amount) {
                    $totals['one_time'][$typeId][$key] = $amount + $row['one_time'][$typeId][$key];
                }
            }
        }

        return $totals;
    }
}
