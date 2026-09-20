<?php

namespace App\Services;

use App\Models\ProspectiveStudentPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectiveStudentPaymentCancellationService
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'cancelReason' => 'required|string|min:3|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancelReason.required' => 'Alasan pembatalan wajib diisi.',
            'cancelReason.min' => 'Alasan pembatalan minimal 3 karakter.',
        ];
    }

    public function cancel(int $paymentId, string $reason, int $actorId): ProspectiveStudentPayment
    {
        return DB::transaction(function () use ($paymentId, $reason, $actorId): ProspectiveStudentPayment {
            $payment = ProspectiveStudentPayment::query()->lockForUpdate()->find($paymentId);

            if (! $payment || $payment->isCancelled()) {
                throw ValidationException::withMessages([
                    'cancelReason' => 'Pembayaran ini sudah dibatalkan.',
                ]);
            }

            $reason = trim($reason);

            $payment->update([
                'status' => ProspectiveStudentPayment::STATUS_CANCELLED,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $payment;
        });
    }
}
