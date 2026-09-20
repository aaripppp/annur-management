<?php

namespace App\Support;

use Carbon\CarbonInterface;

final readonly class TransactionHistoryRow
{
    public function __construct(
        public int|string $id,
        public string $receiptNumber,
        public string $name,
        public string $secondaryInfo,
        public string $detailDisplay,
        public string $bankName,
        public ?string $bankAccountNumber,
        public float $totalAmount,
        public ?CarbonInterface $paymentDate,
        public string $statusLabel,
        public string $creatorName,
        public string $badgeLabel,
        public string $badgeClasses,
        public string $detailUrl,
        public ?string $editUrl,
        public bool $isActive,
        public string $source = 'student',
    ) {}
}
