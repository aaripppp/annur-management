<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Periode buku tagihan siswa.
 *
 * Buku tagihan memiliki awal (start month) yang dikonfigurasi admin dan
 * selalu berakhir pada bulan Juni. Jumlah tagihan bulanan bergantung pada
 * bulan awal yang dipilih (mis. mulai September 2026 -> September 2026 sampai
 * Juni 2027).
 */
final class BillbookPeriod
{
    /** Bulan akhir siklus penagihan saat ini selalu Juni. */
    public const END_MONTH = 6;

    /** Kunci penyimpanan bulan awal buku tagihan (format "Y-m"). */
    public const SETTING_START_MONTH = 'billbook.start_month';

    /**
     * Bulan awal yang berlaku: nilai tersimpan di settings jika ada, selain
     * itu bulan berjalan. Nilai default tidak dikunci ke Juli/Agustus.
     */
    public static function startDate(?CarbonInterface $preferred = null): CarbonInterface
    {
        if ($preferred !== null) {
            return $preferred->copy()->startOfMonth();
        }

        $stored = Setting::get(self::SETTING_START_MONTH);

        if (is_string($stored) && $stored !== '') {
            return Carbon::parse($stored)->startOfMonth();
        }

        return Carbon::now()->startOfMonth();
    }

    public static function setStartMonth(CarbonInterface $start): void
    {
        Setting::set(self::SETTING_START_MONTH, $start->copy()->startOfMonth()->format('Y-m'));
    }

    /**
     * Tanggal akhir siklus: selalu Juni. Jika bulan awal berada di paruh
     * kedua tahun (Juli-Desember), Juni jatuh pada tahun berikutnya.
     * Jika bulan awal Januari-Juni, Juni jatuh pada tahun yang sama.
     */
    public static function endDateFor(CarbonInterface $start): CarbonInterface
    {
        $start = Carbon::parse($start->toDateString())->startOfMonth();

        $endYear = $start->month > self::END_MONTH
            ? $start->year + 1
            : $start->year;

        return Carbon::create($endYear, self::END_MONTH, 1);
    }

    /**
     * Daftar bulan (awal bulan) dari bulan awal sampai Juni (inklusif).
     *
     * @return array<int, CarbonInterface>
     */
    public static function months(CarbonInterface $start): array
    {
        $start = Carbon::parse($start->toDateString())->startOfMonth();
        $end = self::endDateFor($start);

        $months = [];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addMonth()) {
            $months[] = $cursor->copy();
        }

        return $months;
    }
}
