<?php

namespace App\Support;

use App\Enums\BillFrequency;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentBill;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StudentBillbook
{
    /**
     * Build the shared, read-only view data used by Student Detail and Payment Workspace.
     *
     * @return array{
     *     groupedMonthlyBills: Collection,
     *     groupedYearlyBills: Collection,
     *     oneTimeBills: Collection,
     *     oneTimeStatus: string,
     *     periodOptions: Collection,
     *     academicYearOptions: Collection,
     *     showOtherOption: bool,
     *     summaryPeriod: string,
     *     summaryPeriodEmpty: bool,
     *     totalTagihan: float|int,
     *     totalDibayar: float|int,
     *     totalTunggakan: float|int
     * }
     */
    public static function build(Student $student, string $selectedAcademicYear, string $summaryPeriod): array
    {
        $bills = $student->bills()
            ->with(['paymentType', 'paymentDetails.payment', 'adjustments.creator'])
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->get();

        $displayBills = self::filterByAcademicYear(
            $bills,
            $selectedAcademicYear,
            $student->finalEnrollmentYearLabel()
        );

        $groupedMonthlyBills = $displayBills
            ->filter(fn (StudentBill $bill) => $bill->period_month !== null && $bill->period_year !== null)
            ->groupBy(fn (StudentBill $bill) => $bill->period_year.'-'.str_pad((string) $bill->period_month, 2, '0', STR_PAD_LEFT))
            ->map(function (Collection $group): array {
                /** @var StudentBill $first */
                $first = $group->first();

                return [
                    'title' => 'Tagihan '.Carbon::createFromDate($first->period_year, $first->period_month, 1)->locale('id')->translatedFormat('F Y'),
                    'count' => $group->count(),
                    'total_remaining' => $group->sum(fn (StudentBill $bill) => (float) $bill->remaining_amount),
                    'status' => self::periodStatus($group),
                    'bills' => $group->sortBy(fn (StudentBill $bill) => $bill->paymentType->name ?? '')->values(),
                    'period_year' => (int) $first->period_year,
                    'period_month' => (int) $first->period_month,
                ];
            })
            ->sortKeys()
            ->values();

        $groupedYearlyBills = $displayBills
            ->filter(fn (StudentBill $bill) => $bill->academic_year !== null
                && $bill->billing_frequency !== BillFrequency::OneTime->value)
            ->groupBy('academic_year')
            ->map(function (Collection $group): array {
                /** @var StudentBill $first */
                $first = $group->first();

                return [
                    'title' => 'Tahun Ajaran '.$first->academic_year,
                    'count' => $group->count(),
                    'total_remaining' => $group->sum(fn (StudentBill $bill) => (float) $bill->remaining_amount),
                    'status' => self::periodStatus($group),
                    'bills' => $group->sortBy(fn (StudentBill $bill) => $bill->paymentType->name ?? '')->values(),
                    'academic_year' => $first->academic_year,
                ];
            })
            ->sortKeys()
            ->values();

        $oneTimeBills = $displayBills
            ->filter(fn (StudentBill $bill) => $bill->billing_frequency === BillFrequency::OneTime->value)
            ->values();

        $periodOptions = self::periodOptions($displayBills, $selectedAcademicYear);
        $academicYearOptions = $displayBills
            ->filter(fn (StudentBill $bill) => $bill->academic_year !== null)
            ->map(fn (StudentBill $bill) => 'academic-'.$bill->academic_year)
            ->unique()
            ->sort()
            ->values();

        if ($summaryPeriod === '') {
            $summaryPeriod = self::resolveDefaultPeriod($periodOptions);
        }

        $summaryBills = self::filterSummaryBills($displayBills, $summaryPeriod);

        return [
            'groupedMonthlyBills' => $groupedMonthlyBills,
            'groupedYearlyBills' => $groupedYearlyBills,
            'oneTimeBills' => $oneTimeBills,
            'oneTimeStatus' => self::periodStatus($oneTimeBills),
            'periodOptions' => $periodOptions->map(fn (string $key) => [
                'value' => $key,
                'label' => Carbon::createFromFormat('Y-m', $key)->locale('id')->translatedFormat('F Y'),
            ]),
            'academicYearOptions' => $academicYearOptions->map(fn (string $key) => [
                'value' => $key,
                'label' => 'Tahun Ajaran '.substr($key, strlen('academic-')),
            ]),
            'showOtherOption' => $displayBills->contains(fn (StudentBill $bill) => $bill->period_month === null
                && $bill->period_year === null
                && $bill->academic_year === null),
            'summaryPeriod' => $summaryPeriod,
            'summaryPeriodEmpty' => $summaryBills->isEmpty(),
            'totalTagihan' => $summaryBills->sum(fn (StudentBill $bill) => (float) $bill->effective_amount),
            'totalDibayar' => $summaryBills->sum(fn (StudentBill $bill) => (float) $bill->paid_amount),
            'totalTunggakan' => $summaryBills->sum(fn (StudentBill $bill) => (float) $bill->remaining_amount),
        ];
    }

    private static function periodOptions(Collection $displayBills, string $selectedAcademicYear): Collection
    {
        $yearRecord = $selectedAcademicYear === ''
            ? null
            : AcademicYear::query()->where('year', $selectedAcademicYear)->first();

        if (! $yearRecord) {
            return $displayBills
                ->filter(fn (StudentBill $bill) => $bill->period_month !== null && $bill->period_year !== null)
                ->map(fn (StudentBill $bill) => $bill->period_year.'-'.str_pad((string) $bill->period_month, 2, '0', STR_PAD_LEFT))
                ->unique()
                ->sort()
                ->values();
        }

        $periodOptions = collect();
        $periodStart = $yearRecord->start_date->copy()->startOfMonth();
        $periodEnd = $yearRecord->end_date
            ? $yearRecord->end_date->copy()->endOfMonth()
            : $periodStart->copy()->addYear()->subDay();

        for ($cursor = $periodStart->copy(); $cursor->lte($periodEnd); $cursor = $cursor->addMonth()) {
            $periodOptions->push($cursor->format('Y-m'));
        }

        return $periodOptions;
    }

    private static function filterSummaryBills(Collection $bills, string $summaryPeriod): Collection
    {
        if ($summaryPeriod === 'other') {
            return $bills->filter(
                fn (StudentBill $bill) => $bill->period_month === null && $bill->period_year === null
                    && $bill->academic_year === null
            );
        }

        if (str_starts_with($summaryPeriod, 'academic-')) {
            $academicYear = substr($summaryPeriod, strlen('academic-'));

            return $bills->filter(fn (StudentBill $bill) => $bill->academic_year === $academicYear);
        }

        if ($summaryPeriod === 'all') {
            return $bills;
        }

        [$periodYear, $periodMonth] = explode('-', $summaryPeriod);

        return $bills->filter(
            fn (StudentBill $bill) => (int) $bill->period_year === (int) $periodYear
                && (int) $bill->period_month === (int) $periodMonth
        );
    }

    private static function resolveDefaultPeriod(Collection $periodOptions): string
    {
        if ($periodOptions->isEmpty()) {
            return 'all';
        }

        $currentMonth = now()->format('Y-m');

        return $periodOptions->contains($currentMonth) ? $currentMonth : $periodOptions->first();
    }

    private static function filterByAcademicYear(Collection $bills, string $selectedAcademicYear, ?string $enrollmentBoundaryYear): Collection
    {
        if ($selectedAcademicYear === '') {
            return $bills;
        }

        $yearRecord = AcademicYear::query()->where('year', $selectedAcademicYear)->first();

        if (! $yearRecord) {
            return $bills;
        }

        $start = $yearRecord->start_date->copy()->startOfMonth();
        $end = $yearRecord->end_date
            ? $yearRecord->end_date->copy()->endOfMonth()
            : $start->copy()->addYear()->subDay();

        return $bills->filter(function (StudentBill $bill) use ($selectedAcademicYear, $start, $end, $enrollmentBoundaryYear): bool {
            if ($bill->billing_frequency === BillFrequency::OneTime->value) {
                return self::oneTimeBillVisibleForAcademicYear($bill, $selectedAcademicYear, $enrollmentBoundaryYear);
            }

            if ($bill->period_month !== null && $bill->period_year !== null) {
                $billDate = Carbon::createFromDate($bill->period_year, $bill->period_month, 1)->startOfMonth();

                return $billDate->gte($start) && $billDate->lte($end);
            }

            if ($bill->academic_year !== null) {
                return $bill->academic_year === $selectedAcademicYear;
            }

            return false;
        })->values();
    }

    public static function oneTimeBillVisibleForAcademicYear(
        StudentBill $bill,
        string $selectedAcademicYear,
        ?string $enrollmentBoundaryYear,
    ): bool {
        if ($bill->billing_frequency !== BillFrequency::OneTime->value) {
            return false;
        }

        if ($bill->academic_year === null) {
            return $enrollmentBoundaryYear === null || $selectedAcademicYear <= $enrollmentBoundaryYear;
        }

        return $selectedAcademicYear >= $bill->academic_year
            && ($enrollmentBoundaryYear === null || $selectedAcademicYear <= $enrollmentBoundaryYear);
    }

    private static function periodStatus(Collection $bills): string
    {
        if ($bills->isEmpty()) {
            return StudentBill::STATUS_UNPAID;
        }

        if ($bills->every(fn (StudentBill $bill) => (float) $bill->remaining_amount <= 0)) {
            return StudentBill::STATUS_PAID;
        }

        if ($bills->contains(fn (StudentBill $bill) => (float) $bill->paid_amount > 0)) {
            return StudentBill::STATUS_PARTIAL;
        }

        return StudentBill::STATUS_UNPAID;
    }
}
