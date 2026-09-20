<?php

namespace App\Models;

use Database\Factories\DaycarePaymentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $receipt_number
 * @property int $daycare_child_id
 * @property int $bank_id
 * @property Carbon $payment_date
 * @property string $total_amount
 * @property string|null $proof_path
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read DaycareChild $child
 * @property-read Bank $bank
 * @property-read User|null $creator
 * @property-read Collection<int, DaycarePaymentDetail> $details
 * @property-read string $detail_summary
 */
class DaycarePayment extends Model
{
    /** @use HasFactory<DaycarePaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'daycare_child_id',
        'bank_id',
        'payment_date',
        'total_amount',
        'proof_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<DaycareChild, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(DaycareChild::class, 'daycare_child_id');
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

    /** @return HasMany<DaycarePaymentDetail, $this> */
    public function details(): HasMany
    {
        return $this->hasMany(DaycarePaymentDetail::class);
    }

    public function getDetailSummaryAttribute(): string
    {
        $descriptions = $this->details
            ->pluck('description')
            ->map(fn (mixed $description): string => trim((string) $description))
            ->filter()
            ->values();

        if ($descriptions->isEmpty()) {
            return '-';
        }

        if ($descriptions->count() <= 2) {
            return $descriptions->implode(' + ');
        }

        return $descriptions->first().' + '.($descriptions->count() - 1).' lainnya';
    }
}
