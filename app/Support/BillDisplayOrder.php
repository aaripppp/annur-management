<?php

namespace App\Support;

use App\Models\StudentBill;
use Illuminate\Support\Collection;

/**
 * Urutkan tagihan untuk tampilan tabel tagihan siswa.
 *
 * Hanya memengaruhi tampilan tagihan bulanan: SPP, Ekskul, OSIS, lalu Jemputan
 * selalu tampil paling atas. Jenis lain diurutkan menurut nama yang
 * dinormalisasi dan ID tagihan sebagai tie-breaker stabil.
 *
 * @see resources/views/livewire/student/bill-table.blade.php
 */
final class BillDisplayOrder
{
    /** Nama pembayaran dinormalisasi (lowercase) -> prioritas tampil. */
    private const PRIORITY = [
        'spp' => 0,
        'ekskul' => 1,
        'osis' => 2,
        'jemputan' => 3,
    ];

    public static function sort(Collection $bills): Collection
    {
        return $bills
            ->sort(fn (StudentBill $left, StudentBill $right): int => self::compare(
                $left->paymentType?->name,
                (int) $left->id,
                $right->paymentType?->name,
                (int) $right->id,
            ))
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>|Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function sortRows(array|Collection $rows): Collection
    {
        return collect($rows)
            ->sort(fn (array $left, array $right): int => self::compare(
                (string) ($left['payment_type_name'] ?? ''),
                (int) ($left['id'] ?? 0),
                (string) ($right['payment_type_name'] ?? ''),
                (int) ($right['id'] ?? 0),
            ))
            ->values();
    }

    private static function normalize(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    private static function compare(?string $leftName, int $leftId, ?string $rightName, int $rightId): int
    {
        $leftNormalized = self::normalize($leftName);
        $rightNormalized = self::normalize($rightName);
        $fallbackPriority = count(self::PRIORITY);
        $priority = (self::PRIORITY[$leftNormalized] ?? $fallbackPriority)
            <=> (self::PRIORITY[$rightNormalized] ?? $fallbackPriority);

        return $priority
            ?: ($leftNormalized <=> $rightNormalized)
            ?: ($leftId <=> $rightId);
    }
}
