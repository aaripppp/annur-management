<?php

namespace App\Services;

use App\Models\DaycarePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DaycarePaymentDeletionService
{
    public function delete(int $paymentId): void
    {
        $proofPath = DB::transaction(function () use ($paymentId): ?string {
            $payment = DaycarePayment::query()->lockForUpdate()->findOrFail($paymentId);
            $proofPath = $payment->proof_path;

            $payment->details()->delete();
            $payment->delete();

            return $proofPath;
        });

        if ($proofPath !== null) {
            Storage::disk('public')->delete($proofPath);
        }
    }
}
