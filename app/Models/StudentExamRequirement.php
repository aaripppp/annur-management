<?php

namespace App\Models;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use Database\Factories\StudentExamRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StudentExamRequirement extends Model
{
    /** @use HasFactory<StudentExamRequirementFactory> */
    use HasFactory;

    protected $fillable = [
        'student_exam_id',
        'student_eligibility_config_id',
        'source_student_exam_requirement_id',
        'school_level',
        'payment_type_id',
        'billing_frequency',
        'start_month',
        'end_month',
        'required_percentage',
        'is_active',
    ];

    protected $casts = [
        'school_level' => SchoolLevel::class,
        'billing_frequency' => BillFrequency::class,
        'start_month' => 'date',
        'end_month' => 'date',
        'required_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<StudentExam, $this> */
    public function studentExam(): BelongsTo
    {
        return $this->belongsTo(StudentExam::class);
    }

    /** @return BelongsTo<StudentEligibilityConfig, $this> */
    public function eligibilityConfig(): BelongsTo
    {
        return $this->belongsTo(StudentEligibilityConfig::class, 'student_eligibility_config_id');
    }

    /** @return BelongsTo<PaymentType, $this> */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    /** @return BelongsToMany<PaymentType, $this> */
    public function pooledPaymentTypes(): BelongsToMany
    {
        return $this->belongsToMany(PaymentType::class, 'student_exam_requirement_payment_types')
            ->withTimestamps();
    }
}
