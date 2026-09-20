<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentBill;
use App\Support\StudentBillbook;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun statement tagihan siswa untuk PDF "Daftar Tagihan".
 *
 * Populasi tagihan memakai StudentBillbook::build() yang sama dengan halaman
 * Billbook siswa sehingga cakupan akademik tahun (termasuk visibilitas sekali
 * bayar dan batas akhir enrollment) identik. Terbayar memakai alokasi
 * PaymentDetail valid pada bill_id (menolak pembayaran dibatalkan) lalu dibatasi
 * maksimal sebesar tagihan efektif supaya total selalu rekonsiliasi.
 *
 * Tagihan bulanan mempertahankan pengelompokan bulanan kanonik dari
 * StudentBillbook (satu grup per periode YYYY-MM, urutan kronologis, lalu
 * pengurutan jenis pembayaran di dalam grup). Setiap grup menyertakan subtotal
 * target/terbayar/sisa sendiri; total keseluruhan adalah agregasi seluruh grup.
 */
class StudentBillStatementService
{
    /**
     * @return array{
     *     student_name: string,
     *     nis: ?string,
     *     class_name: string,
     *     academic_year_label: string,
     *     sections: array{
     *         monthly: list<array{
     *             period_label: string,
     *             rows: list<array<string, mixed>>,
     *             totals: array{target: float, paid: float, remaining: float}
     *         }>,
     *         yearly: list<array<string, mixed>>,
     *         one_time: list<array<string, mixed>>
     *     },
     *     totals: array{target: float, paid: float, remaining: float}
     * }
     */
    public function generate(Student $student, string $academicYear): array
    {
        $billbook = StudentBillbook::build($student, $academicYear, 'all');

        $monthlyGroups = $billbook['groupedMonthlyBills']
            ->map(function (array $group): array {
                $rows = $group['bills']
                    ->map(fn (StudentBill $bill): array => $this->row($bill))
                    ->all();

                return [
                    'period_label' => Carbon::createFromDate(
                        (int) $group['period_year'],
                        (int) $group['period_month'],
                        1,
                    )->locale('id')->translatedFormat('F Y'),
                    'rows' => $rows,
                    'totals' => [
                        'target' => $this->sum($rows, 'target'),
                        'paid' => $this->sum($rows, 'paid'),
                        'remaining' => $this->sum($rows, 'remaining'),
                    ],
                ];
            })
            ->values()
            ->all();

        $yearlyRows = $billbook['groupedYearlyBills']
            ->flatMap(fn (array $group): array => $group['bills']
                ->map(fn (StudentBill $bill): array => $this->row($bill))
                ->all())
            ->all();

        $oneTimeRows = $this->sortOneTimeBills($billbook['oneTimeBills'])
            ->map(fn (StudentBill $bill): array => $this->row($bill))
            ->all();

        $allRows = array_merge(
            array_merge(...array_map(fn (array $group): array => $group['rows'], $monthlyGroups)),
            $yearlyRows,
            $oneTimeRows,
        );

        return [
            'student_name' => $student->nama_lengkap,
            'nis' => $student->nis,
            'class_name' => $this->classFor($student, $academicYear),
            'academic_year_label' => $academicYear === '' ? 'Semua Tahun Ajaran' : $academicYear,
            'sections' => [
                'monthly' => $monthlyGroups,
                'yearly' => $yearlyRows,
                'one_time' => $oneTimeRows,
            ],
            'totals' => [
                'target' => $this->sum($allRows, 'target'),
                'paid' => $this->sum($allRows, 'paid'),
                'remaining' => $this->sum($allRows, 'remaining'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(StudentBill $bill): array
    {
        $target = (float) $bill->effective_amount;
        $paid = min($target, (float) $bill->paid_amount);

        return [
            'bill_id' => $bill->id,
            'payment_type_name' => $bill->paymentType->name ?? '—',
            'period_label' => $bill->period_label,
            'target' => $target,
            'paid' => round($paid, 2),
            'remaining' => round(max(0.0, $target - $paid), 2),
            'status' => match ($bill->status) {
                StudentBill::STATUS_PAID => 'Lunas',
                StudentBill::STATUS_PARTIAL => 'Sebagian',
                default => 'Belum Bayar',
            },
        ];
    }

    private function sortOneTimeBills(Collection $oneTimeBills): Collection
    {
        return $oneTimeBills
            ->sortBy([
                fn (StudentBill $bill): string => $bill->academic_year ?? "\xFF",
                fn (StudentBill $bill): string => $bill->paymentType->name ?? '',
                fn (StudentBill $bill): int => $bill->id,
            ])
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sum(array $rows, string $key): float
    {
        return round((float) array_sum(array_column($rows, $key)), 2);
    }

    private function classFor(Student $student, string $academicYear): string
    {
        if ($academicYear !== '') {
            $enrollment = $student->enrollments()
                ->with('schoolClass')
                ->whereHas('academicYear', fn ($query) => $query->where('year', $academicYear))
                ->first();

            if ($enrollment?->schoolClass !== null) {
                return $enrollment->schoolClass->name;
            }
        }

        return $student->academicClassLabel();
    }
}
