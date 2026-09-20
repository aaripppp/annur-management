<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\DaycarePayment;
use Carbon\CarbonImmutable;

class DaycareMonthlyReportService
{
    public const CATEGORY_KEY = 'daycare';

    public const CATEGORY_LABEL = 'DAYCARE';

    /**
     * Generate laporan bulanan Daycare per tanggal.
     *
     * Tanggal operasional memakai kolom payment_date pada DaycarePayment
     * (aturan kanonikal laporan Daycare), bukan created_at.
     *
     * @return array<string, mixed>
     */
    public function generate(int $year, int $month): array
    {
        $firstDay = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $lastDay = $firstDay->endOfMonth();

        $payments = DaycarePayment::query()
            ->with('bank')
            ->whereDate('payment_date', '>=', $firstDay->toDateString())
            ->whereDate('payment_date', '<=', $lastDay->toDateString())
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $detailRows = [];
        $reportBankIds = [];

        foreach ($payments as $payment) {
            $bank = $payment->bank;
            $date = $payment->payment_date->toImmutable();
            $detailRows[] = [
                'payment_id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'recorded_at' => $date,
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'bank_label' => $bank->optionLabel(),
                'bank_type' => $bank->type,
                'category_key' => self::CATEGORY_KEY,
                'amount' => (float) $payment->total_amount,
            ];
            $reportBankIds[$bank->id] = $bank->id;
        }

        $categories = [['key' => self::CATEGORY_KEY, 'name' => self::CATEGORY_LABEL]];
        $reportBanks = Bank::query()
            ->where('type', Bank::TYPE_BANK)
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', array_values($reportBankIds)))
            ->orderBy('name')
            ->orderBy('account_number')
            ->orderBy('id')
            ->get()
            ->values()
            ->all();

        $bankDateGroups = $this->buildBankDateGroups($detailRows, $categories, $reportBanks);
        $cashDateRows = $this->buildCashDateRows($detailRows, $categories);
        $categoryKeys = array_column($categories, 'key');
        $bankCategoryTotals = array_fill_keys($categoryKeys, 0.0);
        $cashCategoryTotals = array_fill_keys($categoryKeys, 0.0);

        foreach ($bankDateGroups as $bankDateGroup) {
            foreach ($categoryKeys as $categoryKey) {
                $bankCategoryTotals[$categoryKey] += $bankDateGroup['category_totals'][$categoryKey];
            }
        }

        foreach ($cashDateRows as $cashDateRow) {
            foreach ($categoryKeys as $categoryKey) {
                $cashCategoryTotals[$categoryKey] += $cashDateRow['amounts'][$categoryKey];
            }
        }

        $activeBankDates = array_values(array_filter(
            $bankDateGroups,
            fn (array $dateGroup): bool => $dateGroup['total'] > 0
        ));
        $activeCashDates = array_values(array_filter(
            $cashDateRows,
            fn (array $cashDate): bool => $cashDate['total'] > 0
        ));

        $bankTotal = array_sum($bankCategoryTotals);
        $cashTotal = array_sum($cashCategoryTotals);
        $grandTotal = $bankTotal + $cashTotal;
        $transactionCount = collect($detailRows)->pluck('payment_id')->unique()->count();

        return [
            'year' => $year,
            'month' => $month,
            'first_day' => $firstDay,
            'last_day' => $lastDay,
            'month_label' => $firstDay->settings(['locale' => 'id'])->translatedFormat('F Y'),
            'month_label_upper' => strtoupper($firstDay->settings(['locale' => 'id'])->translatedFormat('F Y')),
            'category' => self::CATEGORY_LABEL,
            'categories' => $categories,
            'bank' => [
                'dates' => $activeBankDates,
                'category_totals' => $bankCategoryTotals,
                'total' => $bankTotal,
                'account_count' => count($reportBanks),
            ],
            'cash' => [
                'dates' => $activeCashDates,
                'category_totals' => $cashCategoryTotals,
                'total' => $cashTotal,
            ],
            'grand_total' => $grandTotal,
            'transaction_count' => $transactionCount,
            'detail_count' => count($detailRows),
            'has_payments' => $transactionCount > 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $detailRows
     * @param  list<array{key: string, name: string}>  $categories
     * @param  array<int, Bank>  $reportBanks
     * @return list<array<string, mixed>>
     */
    private function buildBankDateGroups(array $detailRows, array $categories, array $reportBanks): array
    {
        $emptyAmounts = array_fill_keys(array_column($categories, 'key'), 0.0);
        $emptyBankRows = [];

        foreach ($reportBanks as $bank) {
            $emptyBankRows[$bank->id] = [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'bank_label' => $bank->optionLabel(),
                'bank_type' => $bank->type,
                'amounts' => $emptyAmounts,
                'total' => 0.0,
            ];
        }

        $dateGroups = [];

        foreach ($detailRows as $row) {
            $date = $row['recorded_at']->startOfDay();
            $dateKey = $date->toDateString();
            $bankKey = $row['bank_id'];

            $dateGroups[$dateKey] ??= [
                'date_key' => $dateKey,
                'date' => $date,
                'date_label' => $date->settings(['locale' => 'id'])->translatedFormat('l, d F Y'),
                'banks' => $emptyBankRows,
                'category_totals' => $emptyAmounts,
                'total' => 0.0,
            ];

            if ($row['bank_type'] !== Bank::TYPE_BANK) {
                continue;
            }

            if (! isset($dateGroups[$dateKey]['banks'][$bankKey])) {
                $dateGroups[$dateKey]['banks'][$bankKey] = [
                    'bank_id' => $row['bank_id'],
                    'bank_name' => $row['bank_name'],
                    'bank_label' => $row['bank_label'],
                    'bank_type' => $row['bank_type'],
                    'amounts' => $emptyAmounts,
                    'total' => 0.0,
                ];
            }

            $categoryKey = $row['category_key'];
            $dateGroups[$dateKey]['banks'][$bankKey]['amounts'][$categoryKey] += $row['amount'];
            $dateGroups[$dateKey]['banks'][$bankKey]['total'] += $row['amount'];
            $dateGroups[$dateKey]['category_totals'][$categoryKey] += $row['amount'];
            $dateGroups[$dateKey]['total'] += $row['amount'];
        }

        ksort($dateGroups);

        foreach ($dateGroups as &$dateGroup) {
            $dateGroup['banks'] = array_values($dateGroup['banks']);
            $dateGroup['bank_count'] = count($dateGroup['banks']);
        }
        unset($dateGroup);

        return array_values($dateGroups);
    }

    /**
     * @param  list<array<string, mixed>>  $detailRows
     * @param  list<array{key: string, name: string}>  $categories
     * @return list<array<string, mixed>>
     */
    private function buildCashDateRows(array $detailRows, array $categories): array
    {
        $emptyAmounts = array_fill_keys(array_column($categories, 'key'), 0.0);
        $dateRows = [];

        foreach ($detailRows as $detailRow) {
            $date = $detailRow['recorded_at']->startOfDay();
            $dateKey = $date->toDateString();
            $dateRows[$dateKey] ??= [
                'date_key' => $dateKey,
                'date' => $date,
                'date_label' => $date->settings(['locale' => 'id'])->translatedFormat('l, d F Y'),
                'amounts' => $emptyAmounts,
                'total' => 0.0,
            ];

            if ($detailRow['bank_type'] !== Bank::TYPE_CASH) {
                continue;
            }

            $categoryKey = $detailRow['category_key'];
            $dateRows[$dateKey]['amounts'][$categoryKey] += $detailRow['amount'];
            $dateRows[$dateKey]['total'] += $detailRow['amount'];
        }

        ksort($dateRows);

        return array_values($dateRows);
    }
}
