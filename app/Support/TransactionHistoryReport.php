<?php

namespace App\Support;

final class TransactionHistoryReport
{
    /**
     * Group detail rows by bank (L1) and payment type (L2) for the history PDF.
     *
     * @param  list<array<string, mixed>>  $detailRows
     * @return array{
     *   groups: list<array{header: string, bank_name: string, is_cash: bool, total: float, empty: bool, categories: list<array{name: string, total: float, rows: list<array<string, mixed>>}>}>,
     *   summary: array{cash: float, transfer: float, total: float},
     *   grand_total: float,
     *   transaction_count: int,
     *   detail_count: int,
     * }
     */
    public static function groupDaily(array $detailRows): array
    {
        $bankGroups = [];
        $cashTotal = 0.0;
        $transferTotal = 0.0;

        foreach ($detailRows as $row) {
            $bankId = $row['bank_id'];
            $bankName = $row['bank_name'];
            $bankLabel = $row['bank_label'];
            $isCash = $row['channel_key'] === 'cash';
            $categoryKey = $row['category_key'];
            $categoryName = $row['category_name'];
            $amount = (float) $row['amount'];

            $key = $isCash ? 'cash' : 'bank-'.$bankId;

            if ($isCash) {
                $cashTotal += $amount;
            } else {
                $transferTotal += $amount;
            }

            $bankGroups[$key] ??= [
                'bank_name' => $bankName,
                'bank_label' => $bankLabel,
                'is_cash' => $isCash,
                'categories' => [],
                'total' => 0.0,
            ];

            $bankGroups[$key]['categories'][$categoryKey] ??= [
                'name' => $categoryName,
                'total' => 0.0,
                'rows' => [],
            ];

            $bankGroups[$key]['categories'][$categoryKey]['rows'][] = $row;
            $bankGroups[$key]['categories'][$categoryKey]['total'] += $amount;
            $bankGroups[$key]['total'] += $amount;
        }

        $sortedGroups = [];

        // Transfer banks sorted by label, then bank_id for deterministic order
        $transferGroups = array_filter($bankGroups, fn (array $group): bool => ! $group['is_cash']);
        uasort($transferGroups, static function (array $a, array $b): int {
            $cmp = strnatcasecmp($a['bank_label'], $b['bank_label']);

            return $cmp !== 0 ? $cmp : $a['bank_label'] <=> $b['bank_label'];
        });

        foreach ($transferGroups as $group) {
            uasort($group['categories'], fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
            $group['categories'] = array_values($group['categories']);
            $group['header'] = 'BANK: '.$group['bank_label'];
            $group['empty'] = false;
            $sortedGroups[] = $group;
        }

        // Cash group always last, even if empty
        $cashGroup = $bankGroups['cash'] ?? [
            'bank_name' => 'TUNAI',
            'bank_label' => 'TUNAI',
            'is_cash' => true,
            'categories' => [],
            'total' => 0.0,
        ];
        uasort($cashGroup['categories'], fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $cashGroup['categories'] = array_values($cashGroup['categories']);
        $cashGroup['header'] = 'TUNAI / CASH';
        $cashGroup['empty'] = $cashGroup['total'] <= 0;
        $sortedGroups[] = $cashGroup;

        $transactionCount = count(array_unique(array_column($detailRows, 'payment_id')));

        return [
            'groups' => $sortedGroups,
            'summary' => [
                'cash' => $cashTotal,
                'transfer' => $transferTotal,
                'total' => $cashTotal + $transferTotal,
            ],
            'grand_total' => $cashTotal + $transferTotal,
            'transaction_count' => $transactionCount,
            'detail_count' => count($detailRows),
        ];
    }
}
