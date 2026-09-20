<?php

namespace App\Support;

use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\Student;

/**
 * Sumber tunggal data kartu "Riwayat Pendaftaran" untuk siswa hasil konversi.
 *
 * Dipakai bersama oleh Student Detail dan Student Payment Workspace agar
 * perhitungan total/terbayar/sisa identik. Kartu hanya relevan selama masih
 * ada sisa tagihan calon siswa; begitu seluruh tagihan lunas, keduanya
 * dikembalikan null sehingga tidak ada wrapper/jarak kosong di view.
 *
 * Sisa dihitung dari total remaining seluruh tagihan (bukan hanya satu
 * tagihan), dan accessor paid_amount sudah mengabaikan pembayaran yang
 * dibatalkan, sehingga pembayaran batal otomatis memunculkan kartu kembali.
 */
final class StudentRegistrationHistory
{
    /**
     * @return array{prospect: ProspectiveStudent|null, summary: array{total: float, paid: float, remaining: float}|null}
     */
    public static function forStudent(Student $student): array
    {
        $prospect = ProspectiveStudent::query()
            ->with(['academicYear', 'bills.paymentDetails.payment'])
            ->where('converted_student_id', $student->id)
            ->first();

        if ($prospect === null) {
            return ['prospect' => null, 'summary' => null];
        }

        $summary = [
            'total' => $prospect->bills->sum(fn (ProspectiveStudentBill $bill): float => (float) $bill->amount),
            'paid' => $prospect->bills->sum(fn (ProspectiveStudentBill $bill): float => (float) $bill->paid_amount),
            'remaining' => $prospect->bills->sum(fn (ProspectiveStudentBill $bill): float => (float) $bill->remaining_amount),
        ];

        if ($summary['remaining'] <= 0) {
            return ['prospect' => null, 'summary' => null];
        }

        return ['prospect' => $prospect, 'summary' => $summary];
    }
}
