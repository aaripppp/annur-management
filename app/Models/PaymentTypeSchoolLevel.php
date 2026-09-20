<?php

namespace App\Models;

use App\Enums\SchoolLevel;
use Database\Factories\PaymentTypeSchoolLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $payment_type_id
 * @property SchoolLevel $school_level
 * @property bool $is_required
 * @property bool $is_active
 */
class PaymentTypeSchoolLevel extends Model
{
    /** @use HasFactory<PaymentTypeSchoolLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'payment_type_id',
        'school_level',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'school_level' => SchoolLevel::class,
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<PaymentType, $this>
     */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }
}
