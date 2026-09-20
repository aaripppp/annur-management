<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'bill_id',
        'payment_type_id',
        'period_month',
        'period_year',
        'academic_year',
        'amount',
        'description',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'period_year' => 'integer',
        'academic_year' => 'string',
        'amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(StudentBill::class, 'bill_id');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    /**
     * Label manusiawi untuk satu detail pembayaran:
     * - Bulanan: "SPP Agustus"
     * - Tahunan: "Uang Buku 2026/2027"
     * - Sekali bayar: "Uang Pangkal"
     */
    public function getDetailLabelAttribute(): string
    {
        $name = $this->paymentType->name ?? 'Pembayaran';

        if ($this->academic_year !== null) {
            return $name.' '.$this->academic_year;
        }

        if ($this->period_month !== null && $this->period_year !== null) {
            $month = Carbon::createFromDate($this->period_year, $this->period_month, 1)
                ->locale('id')
                ->translatedFormat('F');

            return $name.' '.$month;
        }

        return $name;
    }
}
