<?php

namespace App\Support;

use Carbon\CarbonInterface;

final readonly class RecentTransaction
{
    public function __construct(
        public string $type,
        public int $sourceId,
        public string $receiptNumber,
        public string $name,
        public string $secondaryInfo,
        public string $description,
        public string $bank,
        public ?string $bankAccountNumber,
        public float $amount,
        public CarbonInterface $paymentDate,
        public CarbonInterface $createdAt,
        public string $detailUrl,
        public string $status,
        public string $creator,
        public bool $isManual = false,
    ) {}

    public function typeLabel(): string
    {
        return match ($this->type) {
            'daycare' => 'Daycare',
            'prospective' => 'Calon Siswa',
            default => 'Siswa',
        };
    }
}
