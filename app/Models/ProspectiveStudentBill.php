<?php

namespace App\Models;

use App\Enums\BillFrequency;
use Database\Factories\ProspectiveStudentBillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectiveStudentBill extends Model
{
    /** @use HasFactory<ProspectiveStudentBillFactory> */
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'prospective_student_id',
        'payment_type_id',
        'amount',
        'is_manual_override',
        'billing_frequency',
        'academic_year',
        'due_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_manual_override' => 'boolean',
            'billing_frequency' => BillFrequency::class,
            'due_date' => 'date',
        ];
    }

    public function prospectiveStudent(): BelongsTo
    {
        return $this->belongsTo(ProspectiveStudent::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ProspectiveStudentPaymentDetail, $this> */
    public function paymentDetails(): HasMany
    {
        return $this->hasMany(ProspectiveStudentPaymentDetail::class, 'prospective_student_bill_id');
    }

    /**
     * Jumlah yang sudah dibayar = jumlah seluruh detail pembayaran AKTIF
     * (pembayaran yang tidak dibatalkan). Detail dari pembayaran yang
     * dibatalkan tidak dihitung sehingga sisa tagihan otomatis pulih.
     */
    public function getPaidAmountAttribute(): float
    {
        $details = $this->relationLoaded('paymentDetails')
            ? $this->paymentDetails
            : $this->paymentDetails()->with('payment')->get();

        return (float) $details
            ->reject(fn (ProspectiveStudentPaymentDetail $detail) => $detail->payment !== null && $detail->payment->isCancelled())
            ->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0.0, round((float) $this->amount - $this->paid_amount, 2));
    }

    public function getStatusAttribute(): string
    {
        $paid = $this->paid_amount;

        if ($paid <= 0) {
            return self::STATUS_UNPAID;
        }

        if ($paid >= (float) $this->amount) {
            return self::STATUS_PAID;
        }

        return self::STATUS_PARTIAL;
    }

    /**
     * Label status tampilan: "Belum Bayar", "Sebagian", atau "Lunas".
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_UNPAID => 'Belum Bayar',
            self::STATUS_PARTIAL => 'Sebagian',
            self::STATUS_PAID => 'Lunas',
            default => ucfirst($this->status),
        };
    }

    public function isUnpaid(): bool
    {
        return $this->status === self::STATUS_UNPAID;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
