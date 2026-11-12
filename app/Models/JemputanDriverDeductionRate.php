<?php

namespace App\Models;

use Database\Factories\JemputanDriverDeductionRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JemputanDriverDeductionRate extends Model
{
    /** @use HasFactory<JemputanDriverDeductionRateFactory> */
    use HasFactory;

    protected $fillable = [
        'jemputan_driver_id',
        'effective_year',
        'effective_month',
        'percentage',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_year' => 'integer',
            'effective_month' => 'integer',
            'percentage' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<JemputanDriver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(JemputanDriver::class, 'jemputan_driver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
