<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\Bank;
use App\Models\Payment;
use App\Support\SchoolReportDocument;
use App\Support\SchoolReportLevel;

/**
 * @phpstan-type Amounts array<string, float>
 * @phpstan-type BankRow array{bank_id: int, bank_name: string, bank_label: string, amounts: Amounts, total: float}
 * @phpstan-type LevelRow array{level_key: string, level_label: string, banks: array<int, BankRow>, bank_category_totals: Amounts, bank_total: float, cash_amounts: Amounts, cash_total: float}
 */
class SchoolMonthlyByLevelReportService
{
    private const UNCLASSIFIED_LEVEL = 'unclassified';

    public function __construct(private SchoolMonthlyReportService $monthlyReportService) {}

    /** @return array<string, mixed> */
    public function generate(int $year, int $month): array
    {
        $monthlyReport = $this->monthlyReportService->generate($year, $month);
        $detailRows = $monthlyReport['detail_rows'];
        $categoryKeys = array_column($monthlyReport['categories'], 'key');
        $emptyAmounts = array_fill_keys($categoryKeys, 0.0);
        $paymentIds = [];

        foreach ($detailRows as $detailRow) {
            if (($detailRow['source'] ?? 'student') === 'prospective') {
                continue;
            }

            $paymentIds[(int) $detailRow['payment_id']] = (int) $detailRow['payment_id'];
        }

        $payments = Payment::query()
            ->with(['student.schoolClass', 'student.enrollments.schoolClass', 'student.enrollments.academicYear'])
            ->whereIn('id', array_values($paymentIds))
            ->get()
            ->keyBy('id');
        $paymentLevels = [];

        foreach ($payments as $payment) {
            $historicalLevel = SchoolReportLevel::historicalLevelForDate($payment);
            $paymentLevels[$payment->id] = $historicalLevel instanceof SchoolLevel
                ? $historicalLevel->value
                : self::UNCLASSIFIED_LEVEL;
        }

        $reportBankIds = [];

        foreach ($detailRows as $detailRow) {
            if ($detailRow['bank_type'] === Bank::TYPE_BANK) {
                $reportBankIds[(int) $detailRow['bank_id']] = (int) $detailRow['bank_id'];
            }
        }

        $reportBanks = Bank::query()
            ->where('type', Bank::TYPE_BANK)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', array_values($reportBankIds)))
            ->orderBy('name')
            ->orderBy('account_number')
            ->orderBy('id')
            ->get();
        $emptyBankRows = [];

        foreach ($reportBanks as $bank) {
            $bankId = (int) $bank->id;
            $emptyBankRows[$bankId] = [
                'bank_id' => $bankId,
                'bank_name' => (string) $bank->name,
                'bank_label' => (string) $bank->optionLabel(),
                'amounts' => $emptyAmounts,
                'total' => 0.0,
            ];
        }

        $levels = $this->initializeLevels($emptyBankRows, $emptyAmounts);

        foreach ($detailRows as $detailRow) {
            if (($detailRow['source'] ?? 'student') === 'prospective') {
                $levelKey = $detailRow['report_level'] ?? self::UNCLASSIFIED_LEVEL;
            } else {
                $levelKey = $paymentLevels[$detailRow['payment_id']] ?? self::UNCLASSIFIED_LEVEL;
            }

            $categoryKey = $detailRow['category_key'];
            $amount = (float) $detailRow['amount'];

            if (! isset($levels[$levelKey])) {
                $levelKey = self::UNCLASSIFIED_LEVEL;
            }

            $level = $levels[$levelKey];

            if ($detailRow['bank_type'] === Bank::TYPE_CASH) {
                $level['cash_amounts'][$categoryKey] += $amount;
                $level['cash_total'] += $amount;
                $levels[$levelKey] = $level;

                continue;
            }

            $bankId = (int) $detailRow['bank_id'];

            if (! isset($level['banks'][$bankId])) {
                $level['banks'][$bankId] = [
                    'bank_id' => $bankId,
                    'bank_name' => (string) $detailRow['bank_name'],
                    'bank_label' => (string) $detailRow['bank_label'],
                    'amounts' => $emptyAmounts,
                    'total' => 0.0,
                ];
            }

            $level['banks'][$bankId]['amounts'][$categoryKey] += $amount;
            $level['banks'][$bankId]['total'] += $amount;
            $level['bank_category_totals'][$categoryKey] += $amount;
            $level['bank_total'] += $amount;
            $levels[$levelKey] = $level;
        }

        $bankLevels = [];
        $cashLevels = [];
        $bankCategoryTotals = $emptyAmounts;
        $cashCategoryTotals = $emptyAmounts;

        foreach ($levels as $level) {
            foreach ($categoryKeys as $categoryKey) {
                $bankCategoryTotals[$categoryKey] += $level['bank_category_totals'][$categoryKey];
                $cashCategoryTotals[$categoryKey] += $level['cash_amounts'][$categoryKey];
            }

            if ($level['bank_total'] > 0) {
                $level['banks'] = array_values($level['banks']);
                $level['bank_count'] = count($level['banks']);
                $bankLevels[] = $level;
            }

            if ($level['cash_total'] > 0) {
                $cashLevels[] = $level;
            }
        }

        $bankTotal = array_sum($bankCategoryTotals);
        $cashTotal = array_sum($cashCategoryTotals);

        return [
            'year' => $year,
            'month' => $month,
            'first_day' => $monthlyReport['first_day'],
            'last_day' => $monthlyReport['last_day'],
            'month_label' => $monthlyReport['month_label'],
            'month_label_upper' => $monthlyReport['month_label_upper'],
            'unit_name' => SchoolReportDocument::unitName(null),
            'categories' => $monthlyReport['categories'],
            'bank' => [
                'levels' => $bankLevels,
                'category_totals' => $bankCategoryTotals,
                'total' => $bankTotal,
                'account_count' => $reportBanks->count(),
            ],
            'cash' => [
                'levels' => $cashLevels,
                'category_totals' => $cashCategoryTotals,
                'total' => $cashTotal,
            ],
            'grand_total' => $bankTotal + $cashTotal,
            'transaction_count' => $monthlyReport['transaction_count'],
            'detail_count' => $monthlyReport['detail_count'],
            'detail_rows' => $detailRows,
        ];
    }

    /** @return array<string, string> */
    private function levelDefinitions(): array
    {
        $levels = [];

        foreach (SchoolLevel::cases() as $level) {
            $levels[$level->value] = $level->value;
        }

        $levels[self::UNCLASSIFIED_LEVEL] = 'Tidak Terklasifikasi';

        return $levels;
    }

    /**
     * @param  array<int, BankRow>  $emptyBankRows
     * @param  Amounts  $emptyAmounts
     * @return array<string, LevelRow>
     */
    private function initializeLevels(array $emptyBankRows, array $emptyAmounts): array
    {
        $levels = [];

        foreach ($this->levelDefinitions() as $levelKey => $levelLabel) {
            $levels[$levelKey] = [
                'level_key' => $levelKey,
                'level_label' => $levelLabel,
                'banks' => $emptyBankRows,
                'bank_category_totals' => $emptyAmounts,
                'bank_total' => 0.0,
                'cash_amounts' => $emptyAmounts,
                'cash_total' => 0.0,
            ];
        }

        return $levels;
    }
}
