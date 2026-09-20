<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Support\SchoolReportDocument;
use App\Support\SchoolReportLevel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class StudentTargetArrearsReportService
{
    public const MODE_MONTHLY = 'monthly';

    public const MODE_YEARLY = 'yearly';

    public const MODE_ONE_TIME = 'one_time';

    public const MODE_ALL = 'all';

    /** @return list<string> */
    public static function modes(): array
    {
        return [self::MODE_MONTHLY, self::MODE_YEARLY, self::MODE_ONE_TIME, self::MODE_ALL];
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(
        string $mode,
        int $month,
        int $year,
        string $academicYear,
        ?SchoolLevel $schoolLevel = null,
    ): array {
        if ($mode === self::MODE_ALL) {
            return $this->generateAll($month, $year, $academicYear, $schoolLevel);
        }

        return $this->build($mode, $month, $year, $academicYear, $schoolLevel, true);
    }

    /** @return array<string, mixed> */
    public function generateMonthlySummary(int $month, int $year, ?SchoolLevel $schoolLevel = null): array
    {
        return $this->build(self::MODE_MONTHLY, $month, $year, '', $schoolLevel, false);
    }

    /** @return array<string, mixed> */
    private function generateAll(
        int $month,
        int $year,
        string $academicYear,
        ?SchoolLevel $schoolLevel,
    ): array {
        $sections = [
            self::MODE_MONTHLY => $this->build(self::MODE_MONTHLY, $month, $year, $academicYear, $schoolLevel, true),
            self::MODE_YEARLY => $this->build(self::MODE_YEARLY, $month, $year, $academicYear, $schoolLevel, true),
            self::MODE_ONE_TIME => $this->build(self::MODE_ONE_TIME, $month, $year, $academicYear, $schoolLevel, true),
        ];
        $target = array_sum(array_column(array_column($sections, 'totals'), 'target'));
        $paid = array_sum(array_column(array_column($sections, 'totals'), 'paid'));
        $outstanding = array_sum(array_column(array_column($sections, 'totals'), 'outstanding'));
        $achievement = $this->achievement($target, $paid);

        return [
            'mode' => self::MODE_ALL,
            'mode_label' => $this->modeLabel(self::MODE_ALL),
            'month' => $month,
            'year' => $year,
            'academic_year' => $academicYear,
            'period_academic_year' => $sections[self::MODE_MONTHLY]['period_academic_year'],
            'period_label' => 'Bulanan: '.$sections[self::MODE_MONTHLY]['period_label']
                .' • Tahunan/Sekali Bayar: Tahun Ajaran '.$academicYear,
            'school_level' => $sections[self::MODE_MONTHLY]['school_level'],
            'school_level_label' => $sections[self::MODE_MONTHLY]['school_level_label'],
            'unit_name' => $sections[self::MODE_MONTHLY]['unit_name'],
            'rows' => array_merge(...array_column($sections, 'rows')),
            'totals' => [
                'target' => $target,
                'paid' => $paid,
                'outstanding' => $outstanding,
                'achievement_percentage' => $achievement,
                'achievement_label' => $this->achievementLabel($achievement),
            ],
            'bill_count' => array_sum(array_column($sections, 'bill_count')),
            'sections' => $sections,
        ];
    }

    /** @return array<string, mixed> */
    private function build(
        string $mode,
        int $month,
        int $year,
        string $academicYear,
        ?SchoolLevel $schoolLevel,
        bool $includeDetails,
    ): array {
        $frequency = BillFrequency::from($mode);
        $monthlyDate = CarbonImmutable::create($year, $month, 1);
        $periodAcademicYear = $mode === self::MODE_MONTHLY
            ? $this->academicYearForDate($monthlyDate)?->year
            : $academicYear;
        $requiresEnrollment = $includeDetails || $schoolLevel !== null;

        $bills = StudentBill::query()
            ->where('billing_frequency', $frequency->value)
            ->when(
                $mode === self::MODE_MONTHLY,
                fn (Builder $query) => $query
                    ->where('period_month', $month)
                    ->where('period_year', $year),
                fn (Builder $query) => $query->where('academic_year', $academicYear)
            )
            ->with('paymentType')
            ->when($requiresEnrollment, fn (Builder $query) => $query->with([
                'student.enrollments' => function ($enrollmentQuery) use ($periodAcademicYear): void {
                    $enrollmentQuery->when(
                        $periodAcademicYear !== null,
                        fn ($filteredQuery) => $filteredQuery->whereHas(
                            'academicYear',
                            fn ($academicYearQuery) => $academicYearQuery->where('year', $periodAcademicYear)
                        )
                    )->with(['academicYear', 'schoolClass']);
                },
            ]))
            ->withSum('adjustments as report_adjustments_total', 'amount')
            ->withSum([
                'paymentDetails as report_paid_total' => fn ($query) => $query->whereHas(
                    'payment',
                    fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_ACTIVE)
                ),
            ], 'amount')
            ->orderBy('payment_type_id')
            ->orderBy('student_id')
            ->orderBy('id')
            ->get();

        $rows = [];
        $billCount = 0;

        foreach ($bills as $bill) {
            $enrollment = $requiresEnrollment ? $this->periodEnrollment($bill, $periodAcademicYear) : null;

            if ($schoolLevel !== null && $this->enrollmentLevel($enrollment) !== $schoolLevel) {
                continue;
            }

            $billCount++;

            $target = max(0.0, round((float) $bill->amount + (float) ($bill->report_adjustments_total ?? 0), 2));
            $allocated = max(0.0, round((float) ($bill->report_paid_total ?? 0), 2));
            $paid = min($target, $allocated);
            $outstanding = max(0.0, round($target - $paid, 2));
            $paymentTypeId = $bill->payment_type_id;
            $paymentType = $bill->getRelation('paymentType');

            if (! $paymentType instanceof PaymentType) {
                continue;
            }

            $rows[$paymentTypeId] ??= $this->emptyPaymentTypeRow($paymentTypeId, $paymentType->name);

            $rows[$paymentTypeId]['target'] += $target;
            $rows[$paymentTypeId]['paid'] += $paid;
            $rows[$paymentTypeId]['outstanding'] += $outstanding;

            if ($includeDetails && $outstanding > 0) {
                $rows[$paymentTypeId]['details'][] = [
                    'bill_id' => $bill->id,
                    'student_id' => $bill->student_id,
                    'student_name' => $this->studentName($bill),
                    'class_name' => $this->enrollmentClassName($enrollment),
                    'target' => $target,
                    'paid' => $paid,
                    'outstanding' => $outstanding,
                ];
            }
        }

        $rows = array_values($rows);
        usort($rows, fn (array $left, array $right): int => strcasecmp($left['payment_type_name'], $right['payment_type_name']));

        foreach ($rows as &$row) {
            usort($row['details'], fn (array $left, array $right): int => strcasecmp($left['student_name'], $right['student_name']));
            $row['achievement_percentage'] = $this->achievement($row['target'], $row['paid']);
            $row['achievement_label'] = $this->achievementLabel($row['achievement_percentage']);
        }
        unset($row);

        $totals = [
            'target' => array_sum(array_column($rows, 'target')),
            'paid' => array_sum(array_column($rows, 'paid')),
            'outstanding' => array_sum(array_column($rows, 'outstanding')),
            'achievement_percentage' => 0.0,
        ];
        $totals['achievement_percentage'] = $this->achievement($totals['target'], $totals['paid']);
        $totals['achievement_label'] = $this->achievementLabel($totals['achievement_percentage']);

        $periodLabel = $mode === self::MODE_MONTHLY
            ? $monthlyDate->settings(['locale' => 'id'])->translatedFormat('F Y')
            : 'Tahun Ajaran '.$academicYear;

        return [
            'mode' => $mode,
            'mode_label' => $this->modeLabel($mode),
            'month' => $month,
            'year' => $year,
            'academic_year' => $academicYear,
            'period_academic_year' => $periodAcademicYear,
            'period_label' => $periodLabel,
            'school_level' => $schoolLevel === null ? SchoolReportLevel::OPTION_ALL : $schoolLevel->value,
            'school_level_label' => SchoolReportLevel::label($schoolLevel),
            'unit_name' => SchoolReportDocument::unitName($schoolLevel),
            'rows' => $rows,
            'totals' => $totals,
            'bill_count' => $billCount,
        ];
    }

    private function academicYearForDate(CarbonImmutable $date): ?AcademicYear
    {
        return AcademicYear::query()
            ->whereDate('start_date', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->orderByDesc('start_date')
            ->first();
    }

    private function periodEnrollment(StudentBill $bill, ?string $academicYear): ?StudentAcademicEnrollment
    {
        if ($academicYear === null) {
            return null;
        }

        $student = $bill->getRelation('student');

        if (! $student instanceof Student) {
            return null;
        }

        return $student->enrollments->first(
            fn (StudentAcademicEnrollment $enrollment): bool => $enrollment->academicYear?->year === $academicYear
        );
    }

    /**
     * @return array{
     *     payment_type_id: int,
     *     payment_type_name: string,
     *     target: float,
     *     paid: float,
     *     outstanding: float,
     *     achievement_percentage: float,
     *     details: list<array{bill_id: int, student_id: int, student_name: string, class_name: string, target: float, paid: float, outstanding: float}>
     * }
     */
    private function emptyPaymentTypeRow(int $paymentTypeId, string $paymentTypeName): array
    {
        return [
            'payment_type_id' => $paymentTypeId,
            'payment_type_name' => $paymentTypeName,
            'target' => 0.0,
            'paid' => 0.0,
            'outstanding' => 0.0,
            'achievement_percentage' => 0.0,
            'details' => [],
        ];
    }

    private function enrollmentLevel(?StudentAcademicEnrollment $enrollment): ?SchoolLevel
    {
        $schoolClass = $enrollment?->getRelation('schoolClass');

        return $schoolClass instanceof SchoolClass ? $schoolClass->school_level : null;
    }

    private function enrollmentClassName(?StudentAcademicEnrollment $enrollment): string
    {
        $schoolClass = $enrollment?->getRelation('schoolClass');

        return $schoolClass instanceof SchoolClass ? $schoolClass->name : '—';
    }

    private function studentName(StudentBill $bill): string
    {
        $student = $bill->getRelation('student');

        return $student instanceof Student ? $student->nama_lengkap : 'Siswa tidak tersedia';
    }

    private function achievement(float $target, float $paid): float
    {
        return $target > 0 ? min(100.0, round(($paid / $target) * 100, 1)) : 0.0;
    }

    private function achievementLabel(float $percentage): string
    {
        $formatted = rtrim(rtrim(number_format($percentage, 1, ',', ''), '0'), ',');

        return $formatted.'%';
    }

    private function modeLabel(string $mode): string
    {
        return match ($mode) {
            self::MODE_MONTHLY => 'Bulanan',
            self::MODE_YEARLY => 'Tahunan',
            self::MODE_ONE_TIME => 'Sekali Bayar',
            self::MODE_ALL => 'Semua',
            default => throw new InvalidArgumentException('Mode laporan target tidak valid.'),
        };
    }
}
