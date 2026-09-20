<?php

namespace App\Models;

use Database\Factories\BillAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bill_id
 * @property string $type
 * @property string $amount
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudentBill $bill
 * @property-read User|null $creator
 */
class BillAdjustment extends Model
{
    public const TYPE_DISCOUNT = 'discount';

    /** @use HasFactory<BillAdjustmentFactory> */
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'type',
        'amount',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(StudentBill::class, 'bill_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
