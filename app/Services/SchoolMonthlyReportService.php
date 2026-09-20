<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\Bank;
use App\Models\Payment;
use App\Support\ProspectiveReportRows;
use App\Support\SchoolReportCategory;
use App\Support\SchoolReportDocument;
use App\Support\SchoolReportLevel;
use Carbon\CarbonImmutable;

class SchoolMonthlyReportService
{
    /** @return array<string, mixed> */
    public function generate(int $year, int $month, ?SchoolLevel $schoolLevel = null): array
    {
        $firstDay = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $lastDay = $firstDay->endOfMonth();

        $query = Payment::query()
            ->with([
                'student.schoolClass',
                'bank',
                'details' => fn ($query) => $query
                    ->with(['paymentType', 'bill.paymentType'])
                    ->orderBy('id'),
            ])
            ->where('status', Payment::STATUS_ACTIVE)
            ->whereBetween('created_at', [$firstDay, $lastDay]);

        if ($schoolLevel !== null) {
            $query->with(['student.enrollments.schoolClass', 'student.enrollments.academicYear']);
        }

        $payments = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $detailRows = [];

        foreach ($payments as $payment) {
            if ($schoolLevel !== null && SchoolReportLevel::historicalLevelForDate($payment) !== $schoolLevel) {
                continue;
            }

            foreach ($payment->details as $detail) {
                $category = SchoolReportCategory::categoryFor($payment, $detail);
                $bank = $payment->bank;

                $detailRows[] = [
                    'detail_id' => $detail->id,
                    'payment_id' => $payment->id,
                    'payment_kind' => $payment->payment_kind,
                    'receipt_number' => $payment->receipt_number,
                    'payment_date' => $payment->payment_date->toImmutable(),
                    'recorded_at' => $payment->created_at->toImmutable(),
                    'student_name' => $payment->student->nama_lengkap,
                    'class_name' => $payment->student->schoolClass->name,
                    'detail_label' => SchoolReportCategory::detailLabel($payment, $detail, $category),
                    'category_key' => SchoolReportCategory::normalizationKey($category),
                    'category_name' => $category,
                    'bank_id' => $bank->id,
                    'bank_name' => $bank->name,
                    'bank_label' => $bank->optionLabel(),
                    'bank_type' => $bank->type,
                    'channel_key' => $bank->type === Bank::TYPE_CASH ? 'cash' : 'transfer',
                    'channel_label' => $bank->reportingTypeLabel(),
                    'amount' => (float) $detail->amount,
                ];
            }
        }

        $detailRows = [
            ...$detailRows,
            ...ProspectiveReportRows::detailRows($firstDay, $lastDay, true, $schoolLevel),
        ];

        $categories = SchoolReportCategory::categoryGrid(
            array_map(
                fn (array $row): array => ['key' => $row['category_key'], 'name' => $row['category_name']],
                $detailRows
            )
        );

        $channelDateRows = $this->buildChannelDateRows($detailRows);
        $channels = [
            'cash' => $this->channelSummary('cash', 'TUNAI / CASH', $channelDateRows['cash'] ?? []),
            'transfer' => $this->channelSummary('transfer', 'TRANSFER / DEBET', $channelDateRows['transfer'] ?? []),
        ];
        $reportBankIds = collect($detailRows)->pluck('bank_id')->unique()->all();
        $reportBanks = Bank::query()
            ->where('type', Bank::TYPE_BANK)
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', $reportBankIds))
            ->orderBy('name')
            ->orderBy('account_number')
            ->orderBy('id')
            ->get();
        $bankDateGroups = $this->buildBankDateGroups($detailRows, $categories, $reportBanks->values()->all());
        $cashDateRows = $this->buildCashDateRows($detailRows, $categories);
        $categoryKeys = array_column($categories, 'key');
        $bankCategoryTotals = array_fill_keys($categoryKeys, 0.0);
        $cashCategoryTotals = array_fill_keys($categoryKeys, 0.0);
        $grandCategoryTotals = array_fill_keys(array_column($categories, 'key'), 0.0);
        $overallDateRows = [];
        $cashDatesByKey = [];

        foreach ($cashDateRows as $cashDateRow) {
            $cashDatesByKey[$cashDateRow['date_key']] = $cashDateRow;

            foreach ($cashDateRow['amounts'] as $key => $amount) {
                $cashCategoryTotals[$key] += $amount;
            }
        }

        foreach ($bankDateGroups as $bankDateGroup) {
            $cashDateRow = $cashDatesByKey[$bankDateGroup['date_key']];
            $overallAmounts = [];

            foreach ($categoryKeys as $categoryKey) {
                $bankAmount = $bankDateGroup['category_totals'][$categoryKey];
                $cashAmount = $cashDateRow['amounts'][$categoryKey];
                $bankCategoryTotals[$categoryKey] += $bankAmount;
                $overallAmounts[$categoryKey] = $bankAmount + $cashAmount;
                $grandCategoryTotals[$categoryKey] += $overallAmounts[$categoryKey];
            }

            $overallDateRows[] = [
                'date_key' => $bankDateGroup['date_key'],
                'date' => $bankDateGroup['date'],
                'date_label' => $bankDateGroup['date_label'],
                'amounts' => $overallAmounts,
                'total' => $bankDateGroup['total'] + $cashDateRow['total'],
            ];
        }

        $activeBankDates = array_values(array_filter(
            $bankDateGroups,
            fn (array $dateGroup): bool => $dateGroup['total'] > 0
        ));
        $activeCashDates = array_values(array_filter(
            $cashDateRows,
            fn (array $cashDate): bool => $cashDate['total'] > 0
        ));

        $bankTotal = $channels['transfer']['total'];
        $cashTotal = $channels['cash']['total'];
        $grandTotal = $bankTotal + $cashTotal;

        return [
            'year' => $year,
            'month' => $month,
            'first_day' => $firstDay,
            'last_day' => $lastDay,
            'month_label' => $firstDay->settings(['locale' => 'id'])->translatedFormat('F Y'),
            'month_label_upper' => strtoupper($firstDay->settings(['locale' => 'id'])->translatedFormat('F Y')),
            'categories' => $categories,
            'channels' => $channels,
            'date_groups' => $bankDateGroups,
            'bank' => [
                'dates' => $activeBankDates,
                'category_totals' => $bankCategoryTotals,
                'total' => $bankTotal,
                'account_count' => $reportBanks->count(),
            ],
            'cash' => [
                'dates' => $activeCashDates,
                'category_totals' => $cashCategoryTotals,
                'total' => $cashTotal,
            ],
            'overall' => [
                'dates' => $overallDateRows,
                'category_totals' => $grandCategoryTotals,
                'total' => $grandTotal,
            ],
            'grand_category_totals' => $grandCategoryTotals,
            'grand_total' => $grandTotal,
            'transaction_count' => collect($detailRows)->pluck('payment_id')->unique()->count(),
            'detail_count' => count($detailRows),
            'detail_rows' => $detailRows,
            'school_level' => $schoolLevel,
            'school_level_label' => SchoolReportLevel::label($schoolLevel),
            'unit_name' => SchoolReportDocument::unitName($schoolLevel),
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
                'subtotal_label' => 'TOTAL '.strtoupper($date->settings(['locale' => 'id'])->translatedFormat('d F')),
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

    /**
     * @param  list<array<string, mixed>>  $detailRows
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function buildChannelDateRows(array $detailRows): array
    {
        $channelDateRows = [];

        foreach ($detailRows as $row) {
            $channelKey = $row['channel_key'];
            $dateKey = $row['recorded_at']->toDateString();
            $channelDateRows[$channelKey][$dateKey] ??= [
                'date' => $row['recorded_at']->startOfDay(),
                'amounts' => [],
            ];
            $channelDateRows[$channelKey][$dateKey]['amounts'][$row['category_key']] =
                ($channelDateRows[$channelKey][$dateKey]['amounts'][$row['category_key']] ?? 0.0)
                + $row['amount'];
        }

        return $channelDateRows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rowsByDate
     * @return array<string, mixed>
     */
    private function channelSummary(string $channelKey, string $label, array $rowsByDate): array
    {
        ksort($rowsByDate);
        $rows = [];

        foreach ($rowsByDate as $row) {
            $rows[] = [
                'date' => $row['date'],
                'day_name' => $row['date']->settings(['locale' => 'id'])->translatedFormat('l'),
                'amounts' => $row['amounts'],
                'total' => array_sum($row['amounts']),
            ];
        }

        $categoryTotals = [];

        foreach ($rows as $row) {
            foreach ($row['amounts'] as $key => $amount) {
                $categoryTotals[$key] = ($categoryTotals[$key] ?? 0.0) + $amount;
            }
        }

        return [
            'key' => $channelKey,
            'label' => $label,
            'rows' => $rows,
            'category_totals' => $categoryTotals,
            'total' => array_sum($categoryTotals),
        ];
    }
}
