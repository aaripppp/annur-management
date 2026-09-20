<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\StudentBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentDeletionService
{
    public function delete(int $paymentId): void
    {
        $receiptPath = DB::transaction(function () use ($paymentId): ?string {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($paymentId);

            $details = $payment->details()
                ->lockForUpdate()
                ->get();

            $billIds = $details->pluck('bill_id')->filter()->unique()->values();

            if ($billIds->isNotEmpty()) {
                StudentBill::query()
                    ->whereKey($billIds)
                    ->lockForUpdate()
                    ->get();
            }

            $receiptPath = $payment->receipt;

            $payment->correctionLogs()->delete();
            $payment->details()->delete();
            $payment->delete();

            return $receiptPath;
        });

        if ($receiptPath) {
            Storage::disk('public')->delete($receiptPath);
        }
    }
}
