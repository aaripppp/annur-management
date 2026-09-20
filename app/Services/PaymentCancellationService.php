<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentCorrectionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentCancellationService
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

    public function cancel(int $paymentId, string $reason, int $actorId): Payment
    {
        return DB::transaction(function () use ($paymentId, $reason, $actorId): Payment {
            $payment = Payment::query()->lockForUpdate()->find($paymentId);

            if (! $payment || $payment->isCancelled()) {
                throw ValidationException::withMessages([
                    'cancelReason' => 'Pembayaran ini sudah dibatalkan.',
                ]);
            }

            $reason = trim($reason);
            $before = PaymentCorrectionLog::snapshot($payment, $payment->details()->get());

            $payment->update([
                'status' => Payment::STATUS_CANCELLED,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            PaymentCorrectionLog::create([
                'payment_id' => $payment->id,
                'action' => PaymentCorrectionLog::ACTION_CANCEL,
                'reason' => $reason,
                'before_data' => $before,
                'after_data' => $before,
                'corrected_by' => $actorId,
            ]);

            return $payment;
        });
    }
}
