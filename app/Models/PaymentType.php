<?php

namespace App\Models;

use App\Enums\PaymentTypeAudience;
use Database\Factories\PaymentTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentType extends Model
{
    /** @use HasFactory<PaymentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'audience',
        'is_active',
        'is_auto_enrolled',
        'is_required',
    ];

    protected $casts = [
        'audience' => PaymentTypeAudience::class,
        'is_active' => 'boolean',
        'is_auto_enrolled' => 'boolean',
        'is_required' => 'boolean',
    ];

    public function scopeForStudents(Builder $query): Builder
    {
        return $query->where('audience', PaymentTypeAudience::Student);
    }

    public function scopeForProspectiveStudents(Builder $query): Builder
    {
        return $query->where('audience', PaymentTypeAudience::ProspectiveStudent);
    }

    public function getAudienceLabelAttribute(): string
    {
        return ($this->audience ?? PaymentTypeAudience::Student)->label();
    }

    public function paymentDetails(): HasMany
    {
        return $this->hasMany(PaymentDetail::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(PaymentRate::class);
    }

    public function studentSettings(): HasMany
    {
        return $this->hasMany(StudentPaymentSetting::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(StudentBill::class);
    }

    public function paymentTypeSchoolLevels(): HasMany
    {
        return $this->hasMany(PaymentTypeSchoolLevel::class);
    }

    /** @return HasMany<ProspectiveStudentBill, $this> */
    public function prospectiveBills(): HasMany
    {
        return $this->hasMany(ProspectiveStudentBill::class);
    }
}
