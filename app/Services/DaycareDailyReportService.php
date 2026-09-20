<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\DaycarePayment;
use App\Support\SchoolReportCategory;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class DaycareDailyReportService
{
    public const CATEGORY_LABEL = 'SPP Daycare';

    /** @return array<string, mixed> */
    public function generate(
        string|DateTimeInterface $startDate,
        string|DateTimeInterface|null $endDate = null,
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

        $payments = DaycarePayment::query()
            ->with('bank')
            ->whereDate('payment_date', '>=', $reportStartDate->toDateString())
            ->whereDate('payment_date', '<=', $reportEndDate->toDateString())
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $channels = [
            'cash' => $this->summarizeChannel($payments, Bank::TYPE_CASH, 'TUNAI / CASH'),
            'transfer' => $this->summarizeChannel($payments, Bank::TYPE_BANK, 'TRANSFER / DEBET'),
        ];
        $transactionCount = $channels['cash']['transaction_count'] + $channels['transfer']['transaction_count'];

        return [
            'date' => $reportStartDate,
            'start_date' => $reportStartDate,
            'end_date' => $reportEndDate,
            'is_single_day' => $reportStartDate->isSameDay($reportEndDate),
            'period_title' => $reportStartDate->isSameDay($reportEndDate) ? 'Tanggal' : 'Periode',
            'period_label' => $this->periodLabel($reportStartDate, $reportEndDate),
            'category' => self::CATEGORY_LABEL,
            'channels' => $channels,
            'transaction_count' => $transactionCount,
            'total_cash' => $channels['cash']['total'],
            'total_transfer' => $channels['transfer']['total'],
            'grand_total' => $channels['cash']['total'] + $channels['transfer']['total'],
            'has_payments' => $transactionCount > 0,
        ];
    }

    /**
     * Build one row per payment detail for the history PDF.
     *
     * @return list<array{detail_id: int|null, payment_id: int, receipt_number: string, payment_date: CarbonImmutable, child_name: string, class_name: string, detail_label: string, category_key: string, category_name: string, bank_id: int, bank_name: string, bank_label: string, channel_key: string, channel_label: string, amount: float}>
     */
    public function detailRows(
        string|DateTimeInterface $startDate,
        string|DateTimeInterface|null $endDate = null,
    ): array {
        $reportStartDate = $startDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($startDate)->startOfDay()
            : CarbonImmutable::parse($startDate)->startOfDay();
        $reportEndDate = $endDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($endDate)->startOfDay()
            : CarbonImmutable::parse($endDate ?? $startDate)->startOfDay();

        $payments = DaycarePayment::query()
            ->with(['bank', 'child', 'details'])
            ->whereDate('payment_date', '>=', $reportStartDate->toDateString())
            ->whereDate('payment_date', '<=', $reportEndDate->toDateString())
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $detailRows = [];

        foreach ($payments as $payment) {
            $bank = $payment->bank;

            $details = $payment->details->isEmpty()
                ? collect([(object) ['id' => null, 'description' => self::CATEGORY_LABEL, 'amount' => $payment->total_amount]])
                : $payment->details;

            foreach ($details as $detail) {
                $categoryName = SchoolReportCategory::normalizedLabel(
                    $detail->description ?? self::CATEGORY_LABEL,
                    self::CATEGORY_LABEL
                );

                $detailRows[] = [
                    'detail_id' => $detail->id,
                    'payment_id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'payment_date' => $payment->payment_date->toImmutable(),
                    'child_name' => $payment->child?->nama_lengkap ?? '-',
                    'class_name' => $payment->child?->kelas ?? '-',
                    'detail_label' => $categoryName,
                    'category_key' => SchoolReportCategory::normalizationKey($categoryName),
                    'category_name' => $categoryName,
                    'bank_id' => $bank->id,
                    'bank_name' => $bank->name,
                    'bank_label' => $bank->optionLabel(),
                    'channel_key' => $bank->type === Bank::TYPE_CASH ? 'cash' : 'transfer',
                    'channel_label' => $bank->reportingTypeLabel(),
                    'amount' => (float) $detail->amount,
                ];
            }
        }

        return $detailRows;
    }

    private function periodLabel(CarbonImmutable $startDate, CarbonImmutable $endDate): string
    {
        $startLabel = $startDate->locale('id')->translatedFormat('d F Y');

        if ($startDate->isSameDay($endDate)) {
            return $startLabel;
        }

        return $startLabel.' s.d. '.$endDate->locale('id')->translatedFormat('d F Y');
    }

    /**
     * @param  iterable<int, DaycarePayment>  $payments
     * @return array<string, mixed>
     */
    private function summarizeChannel(iterable $payments, string $bankType, string $label): array
    {
        $bankGroups = [];

        foreach ($payments as $payment) {
            $bank = $payment->bank;

            if ($bank->type !== $bankType) {
                continue;
            }

            $bankGroups[$bank->id] ??= [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'bank_label' => $bank->optionLabel(),
                'transaction_count' => 0,
                'total' => 0.0,
            ];
            $bankGroups[$bank->id]['transaction_count']++;
            $bankGroups[$bank->id]['total'] += (float) $payment->total_amount;
        }

        foreach ($bankGroups as &$bankGroup) {
            $bankGroup['categories'] = [[
                'key' => 'spp-daycare',
                'name' => self::CATEGORY_LABEL,
                'count' => $bankGroup['transaction_count'],
                'total' => $bankGroup['total'],
            ]];
        }
        unset($bankGroup);

        uasort($bankGroups, function (array $first, array $second): int {
            $labelComparison = strnatcasecmp($first['bank_label'], $second['bank_label']);

            return $labelComparison !== 0
                ? $labelComparison
                : $first['bank_id'] <=> $second['bank_id'];
        });

        return [
            'key' => $bankType === Bank::TYPE_CASH ? 'cash' : 'transfer',
            'label' => $label,
            'banks' => array_values($bankGroups),
            'transaction_count' => array_sum(array_column($bankGroups, 'transaction_count')),
            'total' => array_sum(array_column($bankGroups, 'total')),
        ];
    }
}
