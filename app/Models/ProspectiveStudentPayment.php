<?php

namespace App\Models;

use Database\Factories\ProspectiveStudentPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $receipt_number
 * @property int $prospective_student_id
 * @property int $bank_id
 * @property string $payment_date
 * @property string $total_amount
 * @property string|null $receipt
 * @property string|null $description
 * @property string $status
 * @property int|null $cancelled_by
 * @property string|null $cancelled_at
 * @property string|null $cancellation_reason
 */
class ProspectiveStudentPayment extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<ProspectiveStudentPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'prospective_student_id',
        'bank_id',
        'payment_date',
        'total_amount',
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
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<ProspectiveStudent, $this> */
    public function prospectiveStudent(): BelongsTo
    {
        return $this->belongsTo(ProspectiveStudent::class);
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<ProspectiveStudentPaymentDetail, $this> */
    public function details(): HasMany
    {
        return $this->hasMany(ProspectiveStudentPaymentDetail::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Status pelunasan pembayaran berdasarkan tagihan yang dirujuk detailnya:
     * "lunas" jika semua tagihan terkait sudah lunas, "tunggakan" jika ada
     * tagihan yang belum dibayar atau dibayar sebagian.
     */
    public function getSettlementStatusAttribute(): string
    {
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
     * Label status tampilan: "Dibatalkan" untuk transaksi yang dibatalkan,
     * "Lunas" atau "Sebagian" mengikuti status pelunasan tagihan terkait.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->isCancelled()) {
            return 'Dibatalkan';
        }

        return match ($this->settlement_status) {
            'tunggakan' => 'Sebagian',
            default => 'Lunas',
        };
    }
}
