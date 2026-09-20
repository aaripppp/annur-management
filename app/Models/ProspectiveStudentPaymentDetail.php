<?php

namespace App\Models;

use Database\Factories\ProspectiveStudentPaymentDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectiveStudentPaymentDetail extends Model
{
    /** @use HasFactory<ProspectiveStudentPaymentDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'prospective_student_payment_id',
        'prospective_student_bill_id',
        'payment_type_id',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<ProspectiveStudentPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(ProspectiveStudentPayment::class, 'prospective_student_payment_id');
    }

    /** @return BelongsTo<ProspectiveStudentBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(ProspectiveStudentBill::class, 'prospective_student_bill_id');
    }

    /** @return BelongsTo<PaymentType, $this> */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }
}
