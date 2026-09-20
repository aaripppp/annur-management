<?php

namespace App\Services;

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Support\AcademicYear;
use Illuminate\Support\Collection;

class ProspectiveStudentBillGenerationService
{
    public function __construct(private readonly BillGenerationService $billGenerationService) {}

    /**
     * Buat tagihan pendaftaran yang belum ada untuk calon siswa.
     *
     * Tanpa target jenjang atau tahun ajaran → tidak ada tagihan.
     * Hanya tipe `prospective_student` yang aktif + mapping aktif + frequency
     * one_time yang dihasilkan. Tidak ada duplikat berkat unique DB index.
     *
     * @return Collection<int, ProspectiveStudentBill>
     */
    public function generateFor(ProspectiveStudent $prospectiveStudent): Collection
    {
        $prospectiveStudent->unsetRelation('academicYear')->unsetRelation('schoolClass');

        $academicYear = $prospectiveStudent->academicYear;
        $level = $prospectiveStudent->target_level;
        $classLevel = $prospectiveStudent->schoolClass?->level;

        if ($academicYear === null || $level === null || $classLevel === null) {
            return collect();
        }

        $targetDate = AcademicYear::fromDate($academicYear->start_date)->startDate();

        $typeIds = PaymentTypeSchoolLevel::query()
            ->where('school_level', $level)
            ->where('is_active', true)
            ->whereHas('paymentType', fn ($query) => $query
                ->where('audience', PaymentTypeAudience::ProspectiveStudent)
                ->where('is_active', true))
            ->pluck('payment_type_id');

        $created = [];

        foreach ($typeIds as $typeId) {
            $type = PaymentType::query()->find($typeId);

            if ($type === null) {
                continue;
            }

            $rate = $this->billGenerationService->resolveRate($type, $classLevel, $targetDate);

            if ($rate === null || $rate->billing_frequency !== BillFrequency::OneTime) {
                continue;
            }

            $bill = ProspectiveStudentBill::query()->firstOrCreate(
                [
                    'prospective_student_id' => $prospectiveStudent->id,
                    'payment_type_id' => $type->id,
                    'academic_year' => $academicYear->year,
                ],
                [
                    'amount' => (float) $rate->amount,
                    'billing_frequency' => BillFrequency::OneTime->value,
                    'due_date' => null,
                    'created_by' => auth()->id(),
                ]
            );

            if ($bill->wasRecentlyCreated) {
                $created[] = $bill;
            }
        }

        return collect($created);
    }

    /**
     * Setelah jenjang/tahun ajaran berubah, perbarui nominal untuk tagihan
     * yang belum dibayar berdasarkan tarif saat ini.
     *
     * - Jika tarif valid ditemukan → update amount jika nominal berubah.
     * - Jika tidak ada tarif → biarkan nominal lama (bukan error).
     * - Tagihan yang nominalnya diatur manual (is_manual_override) atau
     *   sudah memiliki pembayaran tidak pernah diubah nominalnya.
     * - Tidak pernah menghapus tagihan yang sudah ada.
     *
     * @return Collection<int, ProspectiveStudentBill> tagihan baru yang dibuat
     */
    public function sync(ProspectiveStudent $prospectiveStudent): Collection
    {
        $newBills = $this->generateFor($prospectiveStudent);

        $prospectiveStudent->unsetRelation('schoolClass');

        $academicYear = $prospectiveStudent->academicYear;
        $classLevel = $prospectiveStudent->schoolClass?->level;

        if ($academicYear === null || $classLevel === null) {
            return $newBills;
        }

        $targetDate = AcademicYear::fromDate($academicYear->start_date)->startDate();

        foreach ($prospectiveStudent->bills()->with('paymentType', 'paymentDetails.payment')->get() as $bill) {
            $rate = $this->billGenerationService->resolveRate($bill->paymentType, $classLevel, $targetDate);

            if ($rate === null || $rate->billing_frequency !== BillFrequency::OneTime) {
                continue;
            }

            $newAmount = (float) $rate->amount;

            if ($newAmount === (float) $bill->amount) {
                continue;
            }

            if ($bill->is_manual_override || (float) $bill->paid_amount > 0) {
                continue;
            }

            $bill->forceFill(['amount' => $newAmount])->save();
        }

        return $newBills;
    }
}
