<?php

namespace App\Console\Commands;

use App\Enums\SchoolLevel;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:backfill-legacy-applicability')]
#[Description('Backfill konfigurasi jenjang untuk biaya standar legacy tanpa membuat tagihan')]
class BackfillLegacyBillingApplicability extends Command
{
    /** @var array<int, string> */
    private const LEGACY_AUTOMATIC_TYPES = ['Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'];

    public function handle(): int
    {
        $created = 0;

        PaymentType::query()
            ->whereIn('name', self::LEGACY_AUTOMATIC_TYPES)
            ->get()
            ->each(function (PaymentType $paymentType) use (&$created): void {
                foreach (SchoolLevel::cases() as $schoolLevel) {
                    $mapping = PaymentTypeSchoolLevel::query()->firstOrCreate(
                        [
                            'payment_type_id' => $paymentType->id,
                            'school_level' => $schoolLevel,
                        ],
                        [
                            'is_required' => $paymentType->is_required,
                            'is_active' => true,
                        ]
                    );

                    if ($mapping->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        $this->info("{$created} mapping applicability legacy dibuat. Mapping existing tidak diubah.");

        return self::SUCCESS;
    }
}
