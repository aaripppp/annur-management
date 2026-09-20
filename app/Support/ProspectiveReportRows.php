<?php

namespace App\Support;

use App\Enums\SchoolLevel;
use App\Models\Bank;
use App\Models\ProspectiveStudentPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helper baris detail laporan keuangan sekolah untuk pembayaran calon siswa.
 *
 * Menyediakan baris detail dengan bentuk yang sama persis dengan baris detail
 * pembayaran siswa (SchoolDailyReportService / SchoolMonthlyReportService)
 * sehingga ia mengalir otomatis ke seluruh konsumen: All Units, By Level,
 * PDF Harian/Bulanan, XLSX, dan riwayat harian.
 *
 * Kunci khusus:
 * - payment_id / detail_id dinamespace dengan awalan "prospect-" agar tidak
 *   bertabrakan dengan id pembayaran siswa pada perhitungan transaksi unik.
 * - report_level membawa jenjang kelas tujuan calon siswa untuk agregasi
 *   per jenjang (tanpa perlu me-lead Payment model).
 */
final class ProspectiveReportRows
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function detailRows(
        CarbonImmutable $start,
        CarbonImmutable $end,
        bool $monthly = false,
        ?SchoolLevel $schoolLevel = null,
    ): array {
        $query = ProspectiveStudentPayment::query()
            ->with([
                'prospectiveStudent.schoolClass',
                'bank',
                'details' => fn ($query) => $query
                    ->with(['paymentType', 'bill.paymentType'])
                    ->orderBy('id'),
            ])
            ->where('status', ProspectiveStudentPayment::STATUS_ACTIVE)
            ->whereBetween('created_at', [$start, $end]);

        if ($schoolLevel !== null) {
            $query->whereHas(
                'prospectiveStudent.schoolClass',
                fn (Builder $classes) => $classes->whereIn('level', $schoolLevel->classLevels())
            );
        }

        $payments = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($payments as $payment) {
            $bank = $payment->bank;
            $prospect = $payment->prospectiveStudent;
            $class = $prospect?->schoolClass;

            foreach ($payment->details as $detail) {
                $category = SchoolReportCategory::categoryForProspectiveDetail($detail);

                $row = [
                    'detail_id' => 'prospect-'.$detail->id,
                    'payment_id' => 'prospect-'.$payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'payment_date' => $payment->payment_date?->toImmutable(),
                    'recorded_at' => $payment->created_at->toImmutable(),
                    'student_name' => $prospect?->nama_lengkap ?? '—',
                    'class_name' => $class?->name ?? '—',
                    'detail_label' => $category,
                    'category_key' => SchoolReportCategory::normalizationKey($category),
                    'category_name' => $category,
                    'bank_id' => $bank->id,
                    'bank_name' => $bank->name,
                    'bank_label' => $bank->optionLabel(),
                    'channel_key' => $bank->type === Bank::TYPE_CASH ? 'cash' : 'transfer',
                    'channel_label' => $bank->reportingTypeLabel(),
                    'amount' => (float) $detail->amount,
                    'source' => 'prospective',
                    'report_level' => $class?->schoolLevel?->value,
                ];

                if ($monthly) {
                    $row['payment_kind'] = 'bill';
                    $row['bank_type'] = $bank->type;
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }
}
