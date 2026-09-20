<?php

namespace App\Models;

use App\Enums\BillFrequency;
use Database\Factories\PaymentRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_type_id
 * @property int $class_level
 * @property string $amount
 * @property bool $is_monthly
 * @property BillFrequency $billing_frequency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentRate extends Model
{
    /** @use HasFactory<PaymentRateFactory> */
    use HasFactory;

    protected $fillable = [
        'payment_type_id',
        'class_level',
        'amount',
        'is_monthly',
        'billing_frequency',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'billing_frequency' => BillFrequency::class,
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    /**
     * Kompatibilitas mundur: menulis is_monthly tetap mendukung kode lama
     * (termasuk mass assignment), dan otomatis memetakannya ke
     * billing_frequency (monthly / one_time).
     */
    public function setIsMonthlyAttribute(bool $value): void
    {
        $this->attributes['is_monthly'] = $value;
        $this->billing_frequency = $value ? BillFrequency::Monthly : BillFrequency::OneTime;
    }

    /**
     * Sumber utama frekuensi. Menjaga kolom is_monthly tetap sinkron agar
     * query/kode lama yang membaca kolom tersebut tidak rusak.
     */
    public function setBillingFrequencyAttribute(BillFrequency|string $value): void
    {
        $frequency = $value instanceof BillFrequency ? $value : BillFrequency::from($value);

        $this->attributes['billing_frequency'] = $frequency->value;
        $this->attributes['is_monthly'] = $frequency === BillFrequency::Monthly;
    }

    /**
     * Kompatibilitas mundur: is_monthly diturunkan dari billing_frequency.
     */
    public function getIsMonthlyAttribute(): bool
    {
        return $this->billing_frequency === BillFrequency::Monthly;
    }

    public function getLevelNameAttribute(): string
    {
        $labels = SchoolClass::levelLabels();

        return $labels[$this->class_level] ?? "Kelas {$this->class_level}";
    }
}
