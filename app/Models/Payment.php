<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const KIND_BILL = 'bill';

    public const KIND_MANUAL = 'manual';

    /** @use HasFactory<Factory<Payment>> */
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'payment_kind',
        'student_id',
        'bank_id',
        'payment_date',
        'total_amount',
        'payment_method',
        'receipt',
        'description',
        'status',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'total_amount' => 'decimal:2',
        'status' => 'string',
        'payment_kind' => 'string',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<PaymentDetail, $this> */
    public function details(): HasMany
    {
        return $this->hasMany(PaymentDetail::class);
    }

    /** @return HasMany<PaymentCorrectionLog, $this> */
    public function correctionLogs(): HasMany
    {
        return $this->hasMany(PaymentCorrectionLog::class);
    }

    public function isActive(): bool
    {
        return $this->status !== self::STATUS_CANCELLED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isBillPayment(): bool
    {
        return $this->payment_kind === self::KIND_BILL;
    }

    public function isManualPayment(): bool
    {
        return $this->payment_kind === self::KIND_MANUAL;
    }

    public function getKindLabelAttribute(): string
    {
        return $this->isManualPayment() ? 'Manual' : 'Tagihan';
    }

    /**
     * Status pelunasan pembayaran berdasarkan tagihan yang dirujuk detailnya:
     * "lunas" jika semua tagihan terkait sudah lunas, "tunggakan" jika ada
     * tagihan yang belum dibayar atau dibayar sebagian.
     */
    public function getSettlementStatusAttribute(): string
    {
        if ($this->isManualPayment()) {
            return 'tercatat';
        }

        $bills = $this->relationLoaded('details')
            ? $this->details->pluck('bill')->filter()
            : $this->details()->with('bill')->get()->pluck('bill')->filter();

        foreach ($bills as $bill) {
            if (! $bill->isSettled()) {
                return 'tunggakan';
            }
        }

        return 'lunas';
    }

    /**
     * Label status tampilan yang menggabungkan status transaksi dan
     * status pelunasan: "Dibatalkan", "Lunas", atau "Tunggakan".
     *
     * Gunakan ini di seluruh tampilan (Dashboard, Index, Show, Kwitansi)
     * sebagai satu-satunya sumber kebenaran label status.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->isCancelled()) {
            return 'Dibatalkan';
        }

        return match ($this->settlement_status) {
            'tunggakan' => 'Tunggakan',
            'tercatat' => 'Tercatat',
            default => 'Lunas',
        };
    }

    /**
     * Tampilan "Detail Pembayaran" untuk kolom Dashboard dan Payment Index.
     * Menggunakan payment.description sebagai satu-satunya sumber tampilan.
     * Jika description kosong/whitespace, tampilkan "-".
     */
    public function getDetailDisplayAttribute(): string
    {
        if ($this->isManualPayment()) {
            $descriptions = $this->relationLoaded('details')
                ? $this->details->pluck('description')
                : $this->details()->orderBy('id')->pluck('description');
            $descriptions = $descriptions
                ->map(fn (?string $description): string => trim((string) $description))
                ->filter()
                ->values();

            if ($descriptions->count() === 1) {
                return (string) $descriptions->first();
            }

            if ($descriptions->count() === 2) {
                return $descriptions->implode(' + ');
            }

            if ($descriptions->count() > 2) {
                return $descriptions->first().' + '.($descriptions->count() - 1).' lainnya';
            }
        }

        $desc = trim((string) $this->description);

        return $desc !== '' ? $desc : '-';
    }
}
