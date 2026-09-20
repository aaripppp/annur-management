<?php

namespace App\Support;

use App\Enums\SchoolLevel;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter jenjang (TK/SD/SMP/SMA) untuk laporan sekolah (Harian & Bulanan).
 *
 * Klasifikasi memakai riwayat enrollment per tahun ajaran
 * (StudentAcademicEnrollment); enrollment dengan start_date terbesar yang
 * masih lebih kecil dari tanggal pembayaran menandakan jenjang siswa saat itu.
 * Bila tidak ada enrollment yang mencakup tanggal pembayaran (mis. calon siswa
 * yang belum terdaftar, atau pembayaran sebelum tahun ajaran mulai), jenjang
 * diturunkan dari kelas siswa saat ini (students.class_id). Nilai class saat
 * ini tidak dipakai ketika enrollment historis tersedia supaya kenaikan kelas
 * tidak memindahkan pembayaran lama ke jenjang yang salah.
 */
final class SchoolReportLevel
{
    public const OPTION_ALL = 'all';

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [self::OPTION_ALL => 'Semua Jenjang'];

        foreach (SchoolLevel::cases() as $level) {
            $options[$level->value] = $level->value;
        }

        return $options;
    }

    public static function label(?SchoolLevel $level): string
    {
        return $level->value ?? 'Semua Jenjang';
    }

    /**
     * Ubah nilai query/URL menjadi scope jenjang laporan.
     * Nilai "all" (atau kosong) berarti tanpa batasan jenjang.
     */
    public static function fromValue(string $value): ?SchoolLevel
    {
        if ($value === self::OPTION_ALL) {
            return null;
        }

        return SchoolLevel::tryFrom($value);
    }

    /**
     * Jenjang historis siswa pada tanggal pembayaran.
     *
     * Menggunakan jenjang dari enrollment terbaru yang mencakup tanggal
     * pembayaran. Bila tidak ada enrollment yang mencakupnya, jatuh kembali ke
     * jenjang kelas siswa saat ini (class_id). Mengembalikan null hanya ketika
     * kelas tidak ditemukan atau level kelas tidak valid, sehingga pembayaran
     * tersebut tidak diklaim masuk jenjang manapun.
     */
    public static function historicalLevelForDate(Payment $payment): ?SchoolLevel
    {
        $student = $payment->student;

        if ($student === null) {
            return null;
        }

        $bestStart = null;
        $bestLevel = null;

        foreach ($student->enrollments as $enrollment) {
            $startDate = $enrollment->academicYear?->start_date;
            $schoolClass = $enrollment->schoolClass;

            if ($startDate === null || $schoolClass === null || ! $startDate->lte($payment->payment_date)) {
                continue;
            }

            if ($bestStart === null || $startDate->gt($bestStart)) {
                $bestStart = $startDate;
                $bestLevel = $schoolClass->school_level;
            }
        }

        return $bestLevel ?? $student->schoolClass?->school_level;
    }

    /**
     * Apply the same historical-level rule in SQL for aggregate payment queries.
     */
    public static function scopeHistoricalLevel(Builder $payments, SchoolLevel $schoolLevel): Builder
    {
        return $payments->whereExists(function ($enrollments) use ($schoolLevel): void {
            $enrollments
                ->selectRaw('1')
                ->from('student_academic_enrollments as report_enrollments')
                ->join('academic_years as report_years', 'report_years.id', '=', 'report_enrollments.academic_year_id')
                ->join('school_classes as report_classes', 'report_classes.id', '=', 'report_enrollments.school_class_id')
                ->whereColumn('report_enrollments.student_id', 'payments.student_id')
                ->whereColumn('report_years.start_date', '<=', 'payments.payment_date')
                ->whereIn('report_classes.level', $schoolLevel->classLevels())
                ->whereNotExists(function ($laterEnrollment): void {
                    $laterEnrollment
                        ->selectRaw('1')
                        ->from('student_academic_enrollments as report_later_enrollments')
                        ->join('academic_years as report_later_years', 'report_later_years.id', '=', 'report_later_enrollments.academic_year_id')
                        ->join('school_classes as report_later_classes', 'report_later_classes.id', '=', 'report_later_enrollments.school_class_id')
                        ->whereColumn('report_later_enrollments.student_id', 'payments.student_id')
                        ->whereColumn('report_later_years.start_date', '<=', 'payments.payment_date')
                        ->whereColumn('report_later_years.start_date', '>', 'report_years.start_date');
                });
        });
    }
}
