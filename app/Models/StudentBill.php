<?php

namespace App\Models;

use Database\Factories\StudentBillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $payment_type_id
 * @property string $amount
 * @property int|null $period_month
 * @property int|null $period_year
 * @property string|null $academic_year
 * @property Carbon|null $due_date
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $period_label
 * @property-read float $paid_amount
 * @property-read float $effective_amount
 * @property-read float $discount_amount
 * @property-read float $remaining_amount
 * @property-read string $status
 */
class StudentBill extends Model
{
    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    /** @use HasFactory<StudentBillFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'payment_type_id',
        'amount',
        'period_month',
        'period_year',
        'academic_year',
        'billing_frequency',
        'due_date',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_month' => 'integer',
        'period_year' => 'integer',
        'academic_year' => 'string',
        'billing_frequency' => 'string',
        'due_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function paymentDetails(): HasMany
    {
        return $this->hasMany(PaymentDetail::class, 'bill_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BillAdjustment::class, 'bill_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Jumlah seluruh penyesuaian tagihan (menggunakan relasi yang sudah
     * dimuat apabila tersedia untuk menghindari query tambahan).
     */
    protected function adjustmentsTotal(): float
    {
        $adjustments = $this->relationLoaded('adjustments')
            ? $this->adjustments
            : $this->adjustments()->get();

        return round((float) $adjustments->sum('amount'), 2);
    }

    /**
     * Batasi query ke bill yang payment type-nya sedang aktif untuk siswa.
     *
     * Bill tetap tersimpan di database (tidak dihapus) saat tipe dinonaktifkan;
     * filter ini hanya menyembunyikannya dari daftar tagihan aktif.
     *
     * @param  Builder<StudentBill>  $query
     * @return Builder<StudentBill>
     */
    public function scopeActivePaymentType(Builder $query, int $studentId): Builder
    {
        return $query->whereExists(function ($subquery) use ($studentId) {
            $subquery->selectRaw('1')
                ->from('student_payment_settings')
                ->whereColumn('student_payment_settings.student_id', 'student_bills.student_id')
                ->whereColumn('student_payment_settings.payment_type_id', 'student_bills.payment_type_id')
                ->where('student_payment_settings.student_id', $studentId)
                ->where('student_payment_settings.is_active', true);
        });
    }

    /**
     * Jumlah yang sudah dibayar = jumlah seluruh detail pembayaran AKTIF
     * (payment yang tidak dibatalkan). Detail dari pembayaran yang dibatalkan
     * tidak dihitung, sehingga saldo tagihan otomatis kembali pulih.
     */
    public function getPaidAmountAttribute(): float
    {
        $details = $this->relationLoaded('paymentDetails')
            ? $this->paymentDetails
            : $this->paymentDetails()->with('payment')->get();

        return (float) $details
            ->reject(fn (PaymentDetail $detail) => $detail->payment !== null && $detail->payment->isCancelled())
            ->sum('amount');
    }

    /**
     * Tagihan efektif = nominal awal + total penyesuaian.
     *
     * Diskon disimpan sebagai nilai negatif, sehingga tagihan efektif bisa
     * lebih kecil dari nominal awal. Dibulatkan ke 0 agar tidak pernah negatif.
     */
    public function getEffectiveAmountAttribute(): float
    {
        return max(0.0, round((float) $this->amount + $this->adjustmentsTotal(), 2));
    }

    /**
     * Total diskon (nilai negatif) yang diterapkan pada tagihan.
     */
    public function getDiscountAmountAttribute(): float
    {
        $adjustments = $this->relationLoaded('adjustments')
            ? $this->adjustments
            : $this->adjustments()->get();

        return round((float) $adjustments->where('type', BillAdjustment::TYPE_DISCOUNT)->sum('amount'), 2);
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0.0, round($this->effective_amount - $this->paid_amount, 2));
    }

    public function getStatusAttribute(): string
    {
        $paid = $this->paid_amount;

        if ($paid <= 0) {
            return self::STATUS_UNPAID;
        }

        if ($paid >= $this->effective_amount) {
            return self::STATUS_PAID;
        }

        return self::STATUS_PARTIAL;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Label periode tagihan:
     * - Bulanan: "Agustus 2026"
     * - Tahunan: "Tahun Ajaran 2026/2027"
     * - Sekali bayar: "Tahun Ajaran 2026/2027" (atau "—" jika tanpa academic_year)
     */
    public function getPeriodLabelAttribute(): string
    {
        if ($this->academic_year !== null) {
            return 'Tahun Ajaran '.$this->academic_year;
        }

        if ($this->period_month !== null && $this->period_year !== null) {
            return Carbon::createFromDate($this->period_year, $this->period_month, 1)
                ->locale('id')
                ->translatedFormat('F Y');
        }

        return '—';
    }
}
