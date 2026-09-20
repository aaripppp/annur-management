<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\ProspectiveStudentPayment;

/**
 * Sumber tunggal pilihan tahun kalender untuk filter laporan (Bulan / Tahun
 * ajaran) pada laporan keuangan sekolah.
 *
 * Tahun adalah CALENDAR YEAR (bukan label tahun ajaran). Sumber pilihan:
 *  1. Tahun kalender yang benar-benar tercatat pada transaksi siswa
 *     (created_at pembayaran aktif) - mengikuti canonical date report bulanan.
 *  2. Tahun kalender saat ini.
 *  3. Tahun kalender dari tahun ajaran aktif (mis. 2030/2031 -> 2030 & 2031).
 */
class ReportYearOptionsService
{
    /** @return list<int> */
    public function options(): array
    {
        $years = [];

        foreach ($this->transactionYears() as $transactionYear) {
            $years[] = $transactionYear;
        }

        foreach ($this->prospectiveTransactionYears() as $prospectiveYear) {
            $years[] = $prospectiveYear;
        }

        $years[] = (int) now()->format('Y');

        $activeAcademicYear = AcademicYear::active();

        if ($activeAcademicYear !== null) {
            foreach ($this->academicYearCalendarYears($activeAcademicYear->year) as $academicYearCalendarYear) {
                $years[] = $academicYearCalendarYear;
            }
        }

        $years = array_values(array_unique($years));
        sort($years);

        return array_values(array_filter(
            $years,
            fn (int $year): bool => $year >= 2000 && $year <= 2100
        ));
    }

    /** @return list<int> */
    private function transactionYears(): array
    {
        return Payment::query()
            ->where('status', Payment::STATUS_ACTIVE)
            ->whereNotNull('created_at')
            ->distinct()
            ->orderBy('created_at')
            ->pluck('created_at')
            ->map(fn (mixed $date): int => (int) $date->format('Y'))
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function prospectiveTransactionYears(): array
    {
        return ProspectiveStudentPayment::query()
            ->where('status', ProspectiveStudentPayment::STATUS_ACTIVE)
            ->whereNotNull('created_at')
            ->distinct()
            ->orderBy('created_at')
            ->pluck('created_at')
            ->map(fn (mixed $date): int => (int) $date->format('Y'))
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function academicYearCalendarYears(string $yearLabel): array
    {
        $parts = array_map('intval', explode('/', $yearLabel));
        $parts = array_values(array_filter($parts, fn (int $part): bool => $part > 0));

        return count($parts) === 2 ? $parts : [];
    }
}
