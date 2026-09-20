<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Tahun ajaran sekolah, contoh: 2026/2027.
 *
 * Tidak ada konsep konfigurasi tahun ajaran di proyek ini sebelumnya, sehingga
 * dipakai default yang terdokumentasi: tahun ajaran dimulai bulan Juli.
 * Bulan dapat diubah melalui konstanta START_MONTH apabila kebijakan sekolah
 * berubah.
 */
final class AcademicYear
{
    /** Bulan awal tahun ajaran (1-12). Default Juli. */
    public const START_MONTH = 7;

    public function __construct(public readonly int $startYear) {}

    /**
     * Membuat tahun ajaran dari sebuah tanggal.
     *
     * Contoh:
     * - Juni 2026 -> 2025/2026
     * - Juli 2026 -> 2026/2027
     * - Agustus 2026 -> 2026/2027
     */
    public static function fromDate(CarbonInterface|string $date): self
    {
        $date = Carbon::parse($date);

        return new self($date->month >= self::START_MONTH ? $date->year : $date->year - 1);
    }

    public function startYear(): int
    {
        return $this->startYear;
    }

    public function endYear(): int
    {
        return $this->startYear + 1;
    }

    /**
     * Tanggal awal tahun ajaran (mis. 2026-07-01).
     */
    public function startDate(): CarbonInterface
    {
        return Carbon::create($this->startYear, self::START_MONTH, 1);
    }

    /**
     * Tanggal akhir tahun ajaran (mis. 2027-06-30, satu hari sebelum awal
     * tahun ajaran berikutnya).
     */
    public function endDate(): CarbonInterface
    {
        return $this->startDate()->addYear()->subDay();
    }

    /**
     * Label "2026/2027".
     */
    public function label(): string
    {
        return $this->startYear.'/'.$this->endYear();
    }

    public function __toString(): string
    {
        return $this->label();
    }
}
