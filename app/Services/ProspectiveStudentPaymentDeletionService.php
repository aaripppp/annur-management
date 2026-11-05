<?php

namespace App\Services;

use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProspectiveStudentPaymentDeletionService
{
    public function delete(int $paymentId, ?int $expectedProspectiveStudentId = null): void
    {
        $receiptPath = DB::transaction(function () use ($paymentId, $expectedProspectiveStudentId): ?string {
            $query = ProspectiveStudentPayment::query()->lockForUpdate();

            if ($expectedProspectiveStudentId !== null) {
                $query->where('prospective_student_id', $expectedProspectiveStudentId);
            }

            $payment = $query->findOrFail($paymentId);

            $details = $payment->details()
                ->lockForUpdate()
                ->get();

            $billIds = $details->pluck('prospective_student_bill_id')->filter()->unique()->values();

            if ($billIds->isNotEmpty()) {
                ProspectiveStudentBill::query()
                    ->whereKey($billIds)
                    ->lockForUpdate()
                    ->get();
            }

            $receiptPath = $payment->receipt;

            $payment->details()->delete();
            $payment->delete();

            return $receiptPath;
        });

        if ($receiptPath) {
            Storage::disk('public')->delete($receiptPath);
        }
    }
}
