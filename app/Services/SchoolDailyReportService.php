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
use DateTimeInterface;
use InvalidArgumentException;

class SchoolDailyReportService
{
    /** @return array<string, mixed> */
    public function generate(
        string|DateTimeInterface $startDate,
        string|DateTimeInterface|SchoolLevel|null $endDate = null,
        ?SchoolLevel $schoolLevel = null,
    ): array {
        if ($endDate instanceof SchoolLevel) {
            $schoolLevel = $endDate;
            $endDate = null;
        }

        $reportStartDate = $startDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($startDate)->startOfDay()
            : CarbonImmutable::parse($startDate)->startOfDay();
        $reportEndDate = $endDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($endDate)->startOfDay()
            : CarbonImmutable::parse($endDate ?? $startDate)->startOfDay();

        if ($reportEndDate->isBefore($reportStartDate)) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        $query = Payment::query()
            ->with([
                'student.schoolClass',
                'bank',
                'details' => fn ($query) => $query
                    ->with(['paymentType', 'bill.paymentType'])
                    ->orderBy('id'),
            ])
            ->where('status', Payment::STATUS_ACTIVE)
            ->whereBetween('created_at', [$reportStartDate, $reportEndDate->endOfDay()]);

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
                    'channel_key' => $bank->type === Bank::TYPE_CASH ? 'cash' : 'transfer',
                    'channel_label' => $bank->reportingTypeLabel(),
                    'amount' => (float) $detail->amount,
                ];
            }
        }

        $detailRows = [
            ...$detailRows,
            ...ProspectiveReportRows::detailRows($reportStartDate, $reportEndDate->endOfDay(), false, $schoolLevel),
        ];

        $channels = [
            'cash' => $this->summarizeChannel($detailRows, 'cash', 'TUNAI / CASH'),
            'transfer' => $this->summarizeChannel($detailRows, 'transfer', 'TRANSFER / DEBET'),
        ];
        $formCategories = SchoolReportCategory::categoryGrid(
            array_map(
                fn (array $row): array => ['key' => $row['category_key'], 'name' => $row['category_name']],
                $detailRows
            )
        );
        $reportBankIds = collect($detailRows)->pluck('bank_id')->unique()->all();
        $formBanks = Bank::query()
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', $reportBankIds))
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [Bank::TYPE_CASH])
            ->orderBy('name')
            ->orderBy('account_number')
            ->orderBy('id')
            ->get();

        return [
            'date' => $reportStartDate,
            'start_date' => $reportStartDate,
            'end_date' => $reportEndDate,
            'is_single_day' => $reportStartDate->isSameDay($reportEndDate),
            'period_title' => $reportStartDate->isSameDay($reportEndDate) ? 'Tanggal' : 'Periode',
            'period_label' => $this->periodLabel($reportStartDate, $reportEndDate),
            'detail_rows' => $detailRows,
            'channels' => $channels,
            'form_categories' => $formCategories,
            'form_sections' => $this->formSections($formBanks, $formCategories, $detailRows),
            'transaction_count' => collect($detailRows)->pluck('payment_id')->unique()->count(),
            'detail_count' => count($detailRows),
            'grand_total' => $channels['cash']['total'] + $channels['transfer']['total'],
            'school_level' => $schoolLevel,
            'school_level_label' => SchoolReportLevel::label($schoolLevel),
            'unit_name' => SchoolReportDocument::unitName($schoolLevel),
        ];
    }

    private function periodLabel(CarbonImmutable $startDate, CarbonImmutable $endDate): string
    {
        $startLabel = $startDate->settings(['locale' => 'id'])->translatedFormat('d F Y');

        if ($startDate->isSameDay($endDate)) {
            return $startLabel;
        }

        return $startLabel.' s.d. '.$endDate->settings(['locale' => 'id'])->translatedFormat('d F Y');
    }

    /**
     * @param  iterable<int, Bank>  $banks
     * @param  list<array{key: string, name: string}>  $categories
     * @param  list<array<string, mixed>>  $detailRows
     * @return list<array<string, mixed>>
     */
    private function formSections(iterable $banks, array $categories, array $detailRows): array
    {
        $amountsByBank = [];
        $cashAmounts = [];

        foreach ($detailRows as $detailRow) {
            $bankId = $detailRow['bank_id'];
            $categoryKey = $detailRow['category_key'];
            $amountsByBank[$bankId][$categoryKey] = ($amountsByBank[$bankId][$categoryKey] ?? 0.0)
                + $detailRow['amount'];

            if ($detailRow['channel_key'] === 'cash') {
                $cashAmounts[$categoryKey] = ($cashAmounts[$categoryKey] ?? 0.0) + $detailRow['amount'];
            }
        }

        $sections = [[
            'key' => 'cash',
            'bank_id' => null,
            'name' => 'TUNAI',
            'type' => Bank::TYPE_CASH,
            'categories' => $this->formCategoryRows($categories, $cashAmounts),
            'total' => array_sum($cashAmounts),
        ]];

        foreach ($banks as $bank) {
            if ($bank->type !== Bank::TYPE_BANK) {
                continue;
            }

            $sections[] = [
                'key' => 'bank-'.$bank->id,
                'bank_id' => $bank->id,
                'name' => trim($bank->name.' '.(string) $bank->account_number),
                'type' => Bank::TYPE_BANK,
                'categories' => $this->formCategoryRows($categories, $amountsByBank[$bank->id] ?? []),
                'total' => array_sum($amountsByBank[$bank->id] ?? []),
            ];
        }

        return $sections;
    }

    /**
     * @param  list<array{key: string, name: string}>  $categories
     * @param  array<string, float>  $amounts
     * @return list<array{key: string, name: string, amount: float|null}>
     */
    private function formCategoryRows(array $categories, array $amounts): array
    {
        return array_map(fn (array $category): array => [
            'key' => $category['key'],
            'name' => $category['name'],
            'amount' => array_key_exists($category['key'], $amounts)
                ? $amounts[$category['key']]
                : null,
        ], $categories);
    }

    /**
     * @param  list<array<string, mixed>>  $detailRows
     * @return array<string, mixed>
     */
    private function summarizeChannel(array $detailRows, string $channelKey, string $label): array
    {
        $bankGroups = [];

        foreach ($detailRows as $row) {
            if ($row['channel_key'] !== $channelKey) {
                continue;
            }

            $bankId = $row['bank_id'];
            $categoryKey = $row['category_key'];

            $bankGroups[$bankId] ??= [
                'bank_id' => $bankId,
                'bank_name' => $row['bank_name'],
                'bank_label' => $row['bank_label'],
                'channel_label' => $row['channel_label'],
                'categories' => [],
                'total' => 0.0,
            ];

            $bankGroups[$bankId]['categories'][$categoryKey] ??= [
                'key' => $categoryKey,
                'name' => $row['category_name'],
                'details' => [],
                'total' => 0.0,
            ];

            $bankGroups[$bankId]['categories'][$categoryKey]['details'][] = $row;
            $bankGroups[$bankId]['categories'][$categoryKey]['total'] += $row['amount'];
            $bankGroups[$bankId]['total'] += $row['amount'];
        }

        foreach ($bankGroups as &$bankGroup) {
            uasort(
                $bankGroup['categories'],
                fn (array $first, array $second): int => strnatcasecmp($first['name'], $second['name'])
            );
            $bankGroup['categories'] = array_values($bankGroup['categories']);
        }
        unset($bankGroup);

        uasort(
            $bankGroups,
            fn (array $first, array $second): int => strnatcasecmp($first['bank_label'], $second['bank_label'])
        );

        return [
            'key' => $channelKey,
            'label' => $label,
            'banks' => array_values($bankGroups),
            'total' => array_sum(array_column($bankGroups, 'total')),
        ];
    }
}
