<?php

namespace App\Support;

use App\Models\StudentBill;
use Illuminate\Support\Collection;

/**
 * Urutkan tagihan untuk tampilan tabel tagihan siswa.
 *
 * Hanya memengaruhi tampilan: SPP, Ekskul, lalu OSIS selalu tampil paling atas,
 * sedangkan jenis pembayaran lain mengikuti posisi relatifnya semula (stable
 * sort). Tidak ada jenis pembayaran baru yang dimunculkan — koleksi hanya
 * diurutkan ulang.
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
    ];

    public static function sort(Collection $bills): Collection
    {
        return $bills->sortBy(
            fn (StudentBill $bill) => self::PRIORITY[self::normalize($bill->paymentType?->name)] ?? 3,
            SORT_NUMERIC,
        )->values();
    }

    private static function normalize(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }
}
