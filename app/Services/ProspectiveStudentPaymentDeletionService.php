<?php

namespace App\Services;

use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProspectiveStudentPaymentDeletionService
{
    public function delete(int $paymentId): void
    {
        $receiptPath = DB::transaction(function () use ($paymentId): ?string {
            $payment = ProspectiveStudentPayment::query()
                ->lockForUpdate()
                ->findOrFail($paymentId);

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
