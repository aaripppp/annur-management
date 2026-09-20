<?php

namespace App\Models;

use Database\Factories\DaycarePaymentDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $daycare_payment_id
 * @property string $description
 * @property string $amount
 * @property-read DaycarePayment $payment
 */
class DaycarePaymentDetail extends Model
{
    /** @use HasFactory<DaycarePaymentDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<DaycarePayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(DaycarePayment::class, 'daycare_payment_id');
    }
}
