<?php

namespace App\Services;

use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Menghapus siswa beserta seluruh data operasionalnya.
 *
 * Untuk siswa biasa, perilaku sama seperti alur hapus lama: detail pembayaran,
 * pembayaran, penyesuaian tagihan, tagihan, pengaturan pembayaran, lalu baris
 * siswa. Untuk siswa yang berasal dari konversi calon siswa
 * (ProspectiveStudent.converted_student_id menunjuk ke siswa ini), penghapusan
 * juga menghapus seluruh data calon siswa asal secara mendalam: detail
 * pembayaran calon siswa, pembayaran calon siswa, tagihan calon siswa, dan
 * baris calon siswa itu sendiri.
 *
 * Sequence nomor pendaftaran dan kwitansi TIDAK disentuh karena keduanya
 * bersifat global per tahun (monotonik) dan bukan milik baris manapun —
 * menghapusnya akan memungkinkan nomor terpakai ulang.
 *
 * Seluruh operasi database berjalan dalam satu transaksi. Baris siswa dan
 * calon siswa dikunci dengan FOR UPDATE. File kwitansi calon siswa dan foto
 * siswa baru dihapus setelah transaksi commit agar kegagalan tidak menghapus
 * berkas secara parsial.
 */
class StudentDeletionService
{
    public function __construct(
        private readonly StudentPhotoService $studentPhotoService,
        private readonly PpdbDocumentCleanupService $ppdbDocumentCleanupService,
        private readonly PaymentReceiptFileCleanupService $paymentReceiptFileCleanupService,
    ) {}

    public function delete(int $studentId, ?User $user = null): void
    {
        DB::transaction(function () use ($studentId, $user): void {
            $student = Student::query()
                ->with(['payments.details', 'bills.adjustments', 'paymentSettings'])
                ->lockForUpdate()
                ->findOrFail($studentId);
            $student->enrollments()->lockForUpdate()->get();

            if ($user !== null) {
                Gate::forUser($user)->authorize('delete', $student);
            }

            $linkedProspect = ProspectiveStudent::query()
                ->where('converted_student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($user !== null && $linkedProspect !== null) {
                Gate::forUser($user)->authorize('delete', $linkedProspect);
            }

            $prospectiveReceiptPaths = $linkedProspect !== null
                ? $this->deleteProspectiveData($linkedProspect)
                : [];

            $studentOwnedFiles = $this->deleteStudentOwnedData($student);

            $this->paymentReceiptFileCleanupService->scheduleAfterCommit([
                ...$prospectiveReceiptPaths,
                ...$studentOwnedFiles['receipts'],
            ]);
            $this->studentPhotoService->scheduleDeletionAfterCommit($studentOwnedFiles['photo']);
        });
    }

    /**
     * Hapus data calon siswa asal sesuai urutan dependensi FK:
     * payment_details → payments → bills → prospective_students.
     *
     * @return list<string>
     */
    private function deleteProspectiveData(ProspectiveStudent $prospect): array
    {
        $this->ppdbDocumentCleanupService->scheduleFor($prospect);

        $payments = $prospect->payments()->lockForUpdate()->get();
        $prospect->bills()->lockForUpdate()->get();

        $receiptPaths = $payments
            ->pluck('receipt')
            ->filter(fn (?string $path): bool => $path !== null && $path !== '')
            ->values()
            ->all();

        foreach ($payments as $payment) {
            $payment->details()->delete();
        }
        $prospect->payments()->delete();
        $prospect->bills()->delete();
        $prospect->delete();

        return $receiptPaths;
    }

    /**
     * Hapus data milik siswa dengan urutan yang sama seperti alur lama:
     * payment_details → payments → bill_adjustments → student_bills →
     * student_payment_settings → student.
     *
     * @return array{receipts: list<string>, photo: string|null}
     */
    private function deleteStudentOwnedData(Student $student): array
    {
        $receiptPaths = $student->payments
            ->pluck('receipt')
            ->filter(fn (?string $path): bool => $path !== null && $path !== '')
            ->values()
            ->all();

        foreach ($student->payments as $payment) {
            $payment->details()->delete();
        }
        $student->payments()->delete();

        foreach ($student->bills as $bill) {
            $bill->adjustments()->delete();
        }
        $student->bills()->delete();

        $student->paymentSettings()->delete();
        $photoPath = $student->foto;
        $student->delete();

        return [
            'receipts' => $receiptPaths,
            'photo' => $photoPath,
        ];
    }
}
