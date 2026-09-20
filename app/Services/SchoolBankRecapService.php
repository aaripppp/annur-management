<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\ProspectiveStudentPayment;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class SchoolBankRecapService
{
    public const SOURCE_STUDENT = 'student';

    public const SOURCE_DAYCARE = 'daycare';

    public const SOURCE_PROSPECTIVE = 'prospective';

    /** @return array<string, mixed> */
    public function generate(
        string|DateTimeInterface $startDate,
        string|DateTimeInterface|null $endDate = null,
        string|int|null $bankFilter = 'all',
    ): array {
        $reportStartDate = $startDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($startDate)->startOfDay()
            : CarbonImmutable::parse($startDate)->startOfDay();
        $reportEndDate = $endDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($endDate)->startOfDay()
            : CarbonImmutable::parse($endDate ?? $startDate)->startOfDay();

        if ($reportEndDate->isBefore($reportStartDate)) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        $filter = $this->resolveBankFilter($bankFilter);

        $payments = $this->applyBankFilter(
            Payment::query()
                ->with(['student', 'bank'])
                ->where('status', Payment::STATUS_ACTIVE)
                ->whereDate('payment_date', '>=', $reportStartDate->toDateString())
                ->whereDate('payment_date', '<=', $reportEndDate->toDateString()),
            $filter,
        )->get();

        $daycarePayments = $this->applyBankFilter(
            DaycarePayment::query()
                ->with(['child', 'bank'])
                ->whereDate('payment_date', '>=', $reportStartDate->toDateString())
                ->whereDate('payment_date', '<=', $reportEndDate->toDateString()),
            $filter,
        )->get();

        $prospectivePayments = $this->applyBankFilter(
            ProspectiveStudentPayment::query()
                ->with(['prospectiveStudent.schoolClass', 'bank'])
                ->where('status', ProspectiveStudentPayment::STATUS_ACTIVE)
                ->whereDate('payment_date', '>=', $reportStartDate->toDateString())
                ->whereDate('payment_date', '<=', $reportEndDate->toDateString()),
            $filter,
        )->get();

        $detailRows = collect()
            ->concat($payments->map(fn (Payment $payment): array => [
                'payment_id' => $payment->id,
                'source' => self::SOURCE_STUDENT,
                'name' => $payment->student->nama_lengkap,
                'payment_date' => $payment->payment_date->toImmutable(),
                'recorded_at' => $payment->created_at->toImmutable(),
                'receipt_number' => $payment->receipt_number,
                'bank_id' => $payment->bank_id,
                'bank_name' => $payment->bank->name,
                'bank_label' => $payment->bank->optionLabel(),
                'bank_type' => $payment->bank->type,
                'amount' => (float) $payment->total_amount,
            ]))
            ->concat($daycarePayments->map(fn (DaycarePayment $payment): array => [
                'payment_id' => $payment->id,
                'source' => self::SOURCE_DAYCARE,
                'name' => $payment->child->nama_lengkap,
                'payment_date' => $payment->payment_date->toImmutable(),
                'recorded_at' => $payment->created_at->toImmutable(),
                'receipt_number' => $payment->receipt_number,
                'bank_id' => $payment->bank_id,
                'bank_name' => $payment->bank->name,
                'bank_label' => $payment->bank->optionLabel(),
                'bank_type' => $payment->bank->type,
                'amount' => (float) $payment->total_amount,
            ]))
            ->concat($prospectivePayments->map(fn (ProspectiveStudentPayment $payment): array => [
                'payment_id' => $payment->id,
                'source' => self::SOURCE_PROSPECTIVE,
                'name' => $payment->prospectiveStudent->nama_lengkap,
                'payment_date' => $payment->payment_date->toImmutable(),
                'recorded_at' => $payment->created_at->toImmutable(),
                'receipt_number' => $payment->receipt_number,
                'bank_id' => $payment->bank_id,
                'bank_name' => $payment->bank->name,
                'bank_label' => $payment->bank->optionLabel(),
                'bank_type' => $payment->bank->type,
                'amount' => (float) $payment->total_amount,
            ]))
            ->all();

        usort($detailRows, function (array $left, array $right): int {
            return [
                $left['payment_date']->toDateString(),
                $left['recorded_at']->getTimestamp(),
                $left['payment_id'],
            ] <=> [
                $right['payment_date']->toDateString(),
                $right['recorded_at']->getTimestamp(),
                $right['payment_id'],
            ];
        });

        $cashDetails = array_values(array_filter(
            $detailRows,
            fn (array $row): bool => $row['bank_type'] === Bank::TYPE_CASH,
        ));
        $bankDetails = collect($detailRows)
            ->filter(fn (array $row): bool => $row['bank_type'] === Bank::TYPE_BANK)
            ->groupBy('bank_id');

        $cashSection = $this->section(null, 'TUNAI / CASH', 'TUNAI / CASH', $cashDetails);
        $bankSections = [];

        foreach ($bankDetails as $bankId => $rows) {
            $firstRow = $rows->first();
            $bankSections[] = $this->section(
                (int) $bankId,
                $firstRow['bank_name'],
                $firstRow['bank_label'],
                array_values($rows->all()),
            );
        }

        usort($bankSections, fn (array $first, array $second): int => strnatcasecmp(
            $first['bank_label'].'-'.$first['bank_id'],
            $second['bank_label'].'-'.$second['bank_id'],
        ));

        return [
            'start_date' => $reportStartDate,
            'end_date' => $reportEndDate,
            'is_single_day' => $reportStartDate->isSameDay($reportEndDate),
            'period_label' => $this->periodLabel($reportStartDate, $reportEndDate),
            'bank_filter_kind' => $filter['kind'],
            'bank_filter_label' => $filter['label'],
            'cash_section' => $cashSection,
            'bank_sections' => $bankSections,
            'sections' => array_merge([$cashSection], $bankSections),
            'detail_rows' => $detailRows,
            'transaction_count' => count($detailRows),
            'cash_total' => $cashSection['total'],
            'bank_total' => array_sum(array_column($bankSections, 'total')),
            'grand_total' => array_sum(array_column($detailRows, 'amount')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return array<string, mixed>
     */
    private function section(?int $bankId, string $bankName, string $bankLabel, array $details): array
    {
        $rowsByDate = [];

        foreach ($details as $detail) {
            $dateKey = $detail['payment_date']->toDateString();
            $rowsByDate[$dateKey] ??= [
                'date' => $detail['payment_date'],
                'details' => [],
                'transaction_count' => 0,
                'total' => 0.0,
            ];
            $rowsByDate[$dateKey]['details'][] = $detail;
            $rowsByDate[$dateKey]['transaction_count']++;
            $rowsByDate[$dateKey]['total'] += $detail['amount'];
        }

        ksort($rowsByDate);

        return [
            'bank_id' => $bankId,
            'bank_name' => $bankName,
            'bank_label' => $bankLabel,
            'rows' => array_values($rowsByDate),
            'transaction_count' => count($details),
            'total' => array_sum(array_column($details, 'amount')),
        ];
    }

    private function periodLabel(CarbonImmutable $startDate, CarbonImmutable $endDate): string
    {
        $startLabel = $startDate->settings(['locale' => 'id'])->translatedFormat('d F Y');

        return $startDate->isSameDay($endDate)
            ? $startLabel
            : $startLabel.' s.d. '.$endDate->settings(['locale' => 'id'])->translatedFormat('d F Y');
    }

    /**
     * @return array{kind: string, bank_id: int|null, label: string}
     */
    private function resolveBankFilter(string|int|null $bankFilter): array
    {
        if ($bankFilter === 'cash') {
            return ['kind' => 'cash', 'bank_id' => null, 'label' => 'Tunai / Cash'];
        }

        if (is_numeric($bankFilter)) {
            $bankId = (int) $bankFilter;
            $bank = Bank::query()->find($bankId);

            if ($bank !== null) {
                return ['kind' => 'bank', 'bank_id' => $bankId, 'label' => $bank->displayLabel()];
            }
        }

        return ['kind' => 'all', 'bank_id' => null, 'label' => 'Semua Bank'];
    }

    /**
     * @param  array{kind: string, bank_id: int|null, label: string}  $filter
     */
    private function applyBankFilter(Builder $query, array $filter): Builder
    {
        if ($filter['kind'] === 'cash') {
            return $query->whereHas('bank', fn (Builder $bankQuery): Builder => $bankQuery->where('type', Bank::TYPE_CASH));
        }

        if ($filter['kind'] === 'bank') {
            return $query->where('bank_id', $filter['bank_id']);
        }

        return $query;
    }
}
