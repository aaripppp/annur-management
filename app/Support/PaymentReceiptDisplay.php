<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Baris rincian untuk kwitansi pembayaran siswa.
 *
 * Murni tampilan: detail SPP, Ekskul, dan OSIS pada periode bulan yang sama
 * dikelompokkan menjadi satu baris berlabel "SPP (Juni 2026)" sejumlah total
 * ketiganya. Data tersimpan (payment_details, tagihan, perhitungan, laporan)
 * tidak diubah; hanya cara baris rincian disajikan di detail web & PDF.
 */
final class PaymentReceiptDisplay
{
    /** Nama jenis pembayaran dinormalisasi (lowercase) yang digabung per bulan. */
    private const MONTHLY_GROUPABLE = [
        'spp' => true,
        'ekskul' => true,
        'osis' => true,
    ];

    /**
     * Baris rincian kwitansi siswa untuk ditampilkan.
     *
     * Urutan item semula dipertahankan dan baris gabungan muncul pada posisi
     * item pertama kelompoknya. Label baris gabungan memakai nama dengan
     * prioritas SPP > Ekskul > OSIS (bila SPP ada, pastilah "SPP (Bulan Tahun)").
     *
     * @return Collection<int, array{name: string, description: string|null, amount: float}>
     */
    public static function rows(Payment $payment): Collection
    {
        $rows = collect();

        if ($payment->isManualPayment()) {
            foreach ($payment->details as $detail) {
                $rows->push([
                    'name' => $detail->description ?: 'Pembayaran Manual',
                    'description' => null,
                    'amount' => (float) $detail->amount,
                ]);
            }

            return $rows;
        }

        /** @var array<string, float> $groupTotal */
        $groupTotal = [];
        /** @var array<string, PaymentDetail> $groupAnchor */
        $groupAnchor = [];
        /** @var array<string, int> $groupPriority */
        $groupPriority = [];
        /** @var array<int, true> $groupedDetailIds */
        $groupedDetailIds = [];

        foreach ($payment->details as $detail) {
            $key = self::monthlyGroupKey($detail);

            if ($key === null) {
                continue;
            }

            $groupedDetailIds[$detail->id] = true;
            $groupTotal[$key] = ($groupTotal[$key] ?? 0) + (float) $detail->amount;

            $paymentType = $detail->paymentType;
            $priority = self::priorityOf($paymentType?->name);

            if (! isset($groupPriority[$key]) || $priority < $groupPriority[$key]) {
                $groupPriority[$key] = $priority;
                $groupAnchor[$key] = $detail;
            }
        }

        $emitted = [];

        foreach ($payment->details as $detail) {
            $key = self::monthlyGroupKey($detail);

            if ($key !== null && isset($groupedDetailIds[$detail->id])) {
                if (isset($emitted[$key])) {
                    continue;
                }

                $emitted[$key] = true;

                $rows->push([
                    'name' => self::groupLabel($groupAnchor[$key]),
                    'description' => null,
                    'amount' => round($groupTotal[$key], 2),
                ]);

                continue;
            }

            $paymentType = $detail->paymentType;

            $rows->push([
                'name' => $paymentType instanceof PaymentType ? $paymentType->name : 'Item Pembayaran',
                'description' => $detail->description,
                'amount' => (float) $detail->amount,
            ]);
        }

        return $rows;
    }

    private static function monthlyGroupKey(PaymentDetail $detail): ?string
    {
        if (
            $detail->period_month === null
            || $detail->period_year === null
            || ! self::isGroupable($detail->paymentType?->name)
        ) {
            return null;
        }

        return (int) $detail->period_year.'-'.(int) $detail->period_month;
    }

    private static function isGroupable(?string $name): bool
    {
        return isset(self::MONTHLY_GROUPABLE[mb_strtolower(trim((string) $name))]);
    }

    private static function priorityOf(?string $name): int
    {
        return match (mb_strtolower(trim((string) $name))) {
            'spp' => 0,
            'ekskul' => 1,
            'osis' => 2,
            default => 3,
        };
    }

    private static function groupLabel(PaymentDetail $anchor): string
    {
        $paymentType = $anchor->paymentType;
        $month = Carbon::createFromDate(
            (int) $anchor->period_year,
            (int) $anchor->period_month,
            1
        )->locale('id')->translatedFormat('F Y');

        return ($paymentType instanceof PaymentType ? $paymentType->name : 'Pembayaran').' ('.$month.')';
    }
}
