<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\ProspectiveStudentPayment;
use App\Models\Student;
use App\Support\SchoolReportLevel;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DashboardOperationalMetricsService
{
    public const UNIT_ALL = 'all';

    public const UNIT_DAYCARE = 'daycare';

    /** @return array<string, string> */
    public static function unitOptions(): array
    {
        return [
            self::UNIT_ALL => 'Semua',
            SchoolLevel::TK->value => SchoolLevel::TK->value,
            SchoolLevel::SD->value => SchoolLevel::SD->value,
            SchoolLevel::SMP->value => SchoolLevel::SMP->value,
            SchoolLevel::SMA->value => SchoolLevel::SMA->value,
            self::UNIT_DAYCARE => 'Daycare',
        ];
    }

    /** @return array<string, mixed> */
    public function generate(
        string $unit,
        string|DateTimeInterface|null $startDate,
        string|DateTimeInterface|null $endDate,
    ): array {
        if (! array_key_exists($unit, self::unitOptions())) {
            throw new InvalidArgumentException('Unit dashboard tidak valid.');
        }

        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $start = $startDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($startDate)->setTimezone($timezone)->startOfDay()
            : ($startDate === null ? null : CarbonImmutable::parse($startDate, $timezone)->startOfDay());
        $end = $endDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($endDate)->setTimezone($timezone)->endOfDay()
            : ($endDate === null ? null : CarbonImmutable::parse($endDate, $timezone)->endOfDay());

        if (($start === null) !== ($end === null)) {
            throw new InvalidArgumentException('Rentang tanggal dashboard harus lengkap.');
        }

        if ($start !== null && $end !== null && $end->isBefore($start)) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        $schoolLevel = SchoolLevel::tryFrom($unit);
        $studentAggregates = $unit === self::UNIT_DAYCARE
            ? collect()
            : $this->studentAggregates($start, $end, $schoolLevel);
        $daycareAggregates = in_array($unit, [self::UNIT_ALL, self::UNIT_DAYCARE], true)
            ? $this->daycareAggregates($start, $end)
            : collect();
        $prospectiveAggregates = $unit === self::UNIT_DAYCARE
            ? collect()
            : $this->prospectiveAggregates($start, $end, $schoolLevel);
        $aggregateBankIds = $studentAggregates->keys()
            ->merge($daycareAggregates->keys())
            ->merge($prospectiveAggregates->keys())
            ->unique()
            ->values();

        $banks = Bank::query()
            ->where(function (Builder $query) use ($aggregateBankIds): void {
                $query->where('is_active', true);

                if ($aggregateBankIds->isNotEmpty()) {
                    $query->orWhereIn('id', $aggregateBankIds);
                }
            })
            ->orderBy('id')
            ->get();

        $bankTotals = $banks->mapWithKeys(function (Bank $bank) use ($studentAggregates, $daycareAggregates, $prospectiveAggregates): array {
            $student = $studentAggregates->get($bank->id, ['total' => 0.0, 'transactions' => 0]);
            $daycare = $daycareAggregates->get($bank->id, ['total' => 0.0, 'transactions' => 0]);
            $prospective = $prospectiveAggregates->get($bank->id, ['total' => 0.0, 'transactions' => 0]);

            return [$bank->id => [
                'student_total' => $student['total'],
                'daycare_total' => $daycare['total'],
                'prospective_total' => $prospective['total'],
                'combined_total' => $student['total'] + $daycare['total'] + $prospective['total'],
            ]];
        });

        return [
            'start_date' => $start,
            'end_date' => $end,
            'total_income' => (float) $bankTotals->sum('combined_total'),
            'transaction_count' => (int) $studentAggregates->sum('transactions')
                + (int) $daycareAggregates->sum('transactions')
                + (int) $prospectiveAggregates->sum('transactions'),
            'student_transaction_count' => (int) $studentAggregates->sum('transactions'),
            'daycare_transaction_count' => (int) $daycareAggregates->sum('transactions'),
            'prospective_transaction_count' => (int) $prospectiveAggregates->sum('transactions'),
            'active_students' => $unit === self::UNIT_DAYCARE ? 0 : $this->activeStudentCount($schoolLevel),
            'active_daycare_children' => in_array($unit, [self::UNIT_ALL, self::UNIT_DAYCARE], true)
                ? DaycareChild::query()->where('is_active', true)->count()
                : 0,
            'banks' => $banks,
            'bank_totals' => $bankTotals,
        ];
    }

    /** @return Collection<int, array{total: float, transactions: int}> */
    private function studentAggregates(
        ?CarbonImmutable $start,
        ?CarbonImmutable $end,
        ?SchoolLevel $schoolLevel,
    ): Collection {
        $query = Payment::query()->where('status', Payment::STATUS_ACTIVE);

        if ($start !== null && $end !== null) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($schoolLevel !== null) {
            SchoolReportLevel::scopeHistoricalLevel($query, $schoolLevel);
        }

        return $this->aggregateByBank($query);
    }

    /** @return Collection<int, array{total: float, transactions: int}> */
    private function daycareAggregates(?CarbonImmutable $start, ?CarbonImmutable $end): Collection
    {
        $query = DaycarePayment::query();

        if ($start !== null && $end !== null) {
            $query
                ->whereDate('payment_date', '>=', $start->toDateString())
                ->whereDate('payment_date', '<=', $end->toDateString());
        }

        return $this->aggregateByBank($query);
    }

    /** @return Collection<int, array{total: float, transactions: int}> */
    private function prospectiveAggregates(
        ?CarbonImmutable $start,
        ?CarbonImmutable $end,
        ?SchoolLevel $schoolLevel,
    ): Collection {
        $query = ProspectiveStudentPayment::query()
            ->where('status', ProspectiveStudentPayment::STATUS_ACTIVE);

        if ($start !== null && $end !== null) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($schoolLevel !== null) {
            $query->whereHas(
                'prospectiveStudent.schoolClass',
                fn (Builder $classes) => $classes->whereIn('level', $schoolLevel->classLevels())
            );
        }

        return $this->aggregateByBank($query);
    }

    /** @return Collection<int, array{total: float, transactions: int}> */
    private function aggregateByBank(Builder $query): Collection
    {
        return $query
            ->selectRaw('bank_id, SUM(total_amount) as aggregate_total, COUNT(*) as aggregate_transactions')
            ->groupBy('bank_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->bank_id => [
                'total' => (float) $row->aggregate_total,
                'transactions' => (int) $row->aggregate_transactions,
            ]]);
    }

    private function activeStudentCount(?SchoolLevel $schoolLevel): int
    {
        $activeAcademicYear = AcademicYear::active();
        $query = Student::query();

        if ($activeAcademicYear !== null) {
            $query->whereHas('enrollments', function (Builder $enrollments) use ($activeAcademicYear, $schoolLevel): void {
                $enrollments
                    ->where('academic_year_id', $activeAcademicYear->id)
                    ->where('status', 'active');

                if ($schoolLevel !== null) {
                    $enrollments->whereHas(
                        'schoolClass',
                        fn (Builder $classes) => $classes->whereIn('level', $schoolLevel->classLevels())
                    );
                }
            });
        } elseif ($schoolLevel !== null) {
            $query->whereHas(
                'schoolClass',
                fn (Builder $classes) => $classes->whereIn('level', $schoolLevel->classLevels())
            );
        }

        return $query->count();
    }
}
