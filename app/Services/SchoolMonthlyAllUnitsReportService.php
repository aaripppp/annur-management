<?php

namespace App\Services;

use App\Models\Bank;

/**
 * @phpstan-type Amounts array<string, float>
 * @phpstan-type BankRow array{bank_id: int, bank_name: string, bank_label: string, amounts: Amounts, total: float}
 */
class SchoolMonthlyAllUnitsReportService
{
    public function __construct(private SchoolMonthlyReportService $monthlyReportService) {}

    /** @return array<string, mixed> */
    public function generate(int $year, int $month): array
    {
        $monthlyReport = $this->monthlyReportService->generate($year, $month);
        $detailRows = $monthlyReport['detail_rows'];
        $categoryKeys = array_column($monthlyReport['categories'], 'key');
        $emptyAmounts = array_fill_keys($categoryKeys, 0.0);
        $reportBankIds = [];

        foreach ($detailRows as $detailRow) {
            if ($detailRow['bank_type'] === Bank::TYPE_BANK) {
                $reportBankIds[(int) $detailRow['bank_id']] = (int) $detailRow['bank_id'];
            }
        }

        $reportBanks = Bank::query()
            ->where('type', Bank::TYPE_BANK)
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', array_values($reportBankIds)))
            ->orderBy('name')
            ->orderBy('account_number')
            ->orderBy('id')
            ->get();
        $bankRows = [];

        foreach ($reportBanks as $bank) {
            $bankId = (int) $bank->id;
            $bankRows[$bankId] = [
                'bank_id' => $bankId,
                'bank_name' => (string) $bank->name,
                'bank_label' => (string) $bank->optionLabel(),
                'amounts' => $emptyAmounts,
                'total' => 0.0,
            ];
        }

        $bankCategoryTotals = $emptyAmounts;
        $cashAmounts = $emptyAmounts;
        $bankTotal = 0.0;
        $cashTotal = 0.0;

        foreach ($detailRows as $detailRow) {
            $categoryKey = (string) $detailRow['category_key'];
            $amount = (float) $detailRow['amount'];

            if ($detailRow['bank_type'] === Bank::TYPE_CASH) {
                $cashAmounts[$categoryKey] += $amount;
                $cashTotal += $amount;

                continue;
            }

            if ($detailRow['bank_type'] !== Bank::TYPE_BANK) {
                continue;
            }

            $bankId = (int) $detailRow['bank_id'];

            if (! isset($bankRows[$bankId])) {
                $bankRows[$bankId] = [
                    'bank_id' => $bankId,
                    'bank_name' => (string) $detailRow['bank_name'],
                    'bank_label' => (string) $detailRow['bank_label'],
                    'amounts' => $emptyAmounts,
                    'total' => 0.0,
                ];
            }

            $bankRows[$bankId]['amounts'][$categoryKey] += $amount;
            $bankRows[$bankId]['total'] += $amount;
            $bankCategoryTotals[$categoryKey] += $amount;
            $bankTotal += $amount;
        }

        $grandCategoryTotals = [];

        foreach ($categoryKeys as $categoryKey) {
            $grandCategoryTotals[$categoryKey] = $bankCategoryTotals[$categoryKey] + $cashAmounts[$categoryKey];
        }

        return [
            'year' => $monthlyReport['year'],
            'month' => $monthlyReport['month'],
            'first_day' => $monthlyReport['first_day'],
            'last_day' => $monthlyReport['last_day'],
            'month_label' => $monthlyReport['month_label'],
            'month_label_upper' => $monthlyReport['month_label_upper'],
            'unit_name' => $monthlyReport['unit_name'],
            'categories' => $monthlyReport['categories'],
            'bank' => [
                'rows' => $bankTotal > 0 ? array_values($bankRows) : [],
                'category_totals' => $bankCategoryTotals,
                'total' => $bankTotal,
                'account_count' => $reportBanks->count(),
            ],
            'cash' => [
                'amounts' => $cashAmounts,
                'total' => $cashTotal,
            ],
            'grand_category_totals' => $grandCategoryTotals,
            'grand_total' => $bankTotal + $cashTotal,
            'transaction_count' => $monthlyReport['transaction_count'],
            'detail_count' => $monthlyReport['detail_count'],
            'detail_rows' => $detailRows,
        ];
    }
}
