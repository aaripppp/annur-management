<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Log audit untuk koreksi dan pembatalan pembayaran.
 *
 * Catatan bersifat aditif: sebelum/ sesudah disimpan sebagai snapshot JSON
 * sehingga riwayat pembayaran tetap bisa ditelusuri walau detail live
 * diperbarui oleh koreksi.
 */
class PaymentCorrectionLog extends Model
{
    public const ACTION_CORRECT = 'correct';

    public const ACTION_CANCEL = 'cancel';

    use HasFactory;

    protected $fillable = [
        'payment_id',
        'action',
        'reason',
        'before_data',
        'after_data',
        'corrected_by',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /**
     * Snapshot kondisi pembayaran berikut rincian detail-nya untuk audit.
     *
     * @param  Collection<int, PaymentDetail>|iterable<int, PaymentDetail>  $details
     */
    public static function snapshot(Payment $payment, iterable $details): array
    {
        return [
            'receipt_number' => $payment->receipt_number,
            'payment_kind' => $payment->payment_kind,
            'student_id' => $payment->student_id,
            'status' => $payment->status,
            'bank_id' => $payment->bank_id,
            'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : null,
            'total_amount' => round((float) $payment->total_amount, 2),
            'details' => collect($details)
                ->map(fn (PaymentDetail $detail) => [
                    'id' => $detail->id,
                    'bill_id' => $detail->bill_id,
                    'payment_type_id' => $detail->payment_type_id,
                    'period_month' => $detail->period_month,
                    'period_year' => $detail->period_year,
                    'academic_year' => $detail->academic_year,
                    'amount' => round((float) $detail->amount, 2),
                    'description' => $detail->description,
                ])
                ->values()
                ->all(),
        ];
    }
}
