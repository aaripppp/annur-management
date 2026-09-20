<?php

namespace App\Services;

use App\Models\Payment;

class StudentReceiptNumberGenerator
{
    public function next(int $year): string
    {
        $prefix = 'KWT-'.$year.'-';
        $lastNumber = Payment::query()
            ->where('receipt_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('receipt_number')
            ->map(function (string $receiptNumber) use ($prefix): int {
                $number = substr($receiptNumber, strlen($prefix));

                return ctype_digit($number) ? (int) $number : 0;
            })
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }
}
