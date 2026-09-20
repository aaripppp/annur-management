<?php

namespace App\Models;

use Database\Factories\StudentPaymentSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $payment_type_id
 * @property bool $is_active
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property float|null $custom_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StudentPaymentSetting extends Model
{
    /** @use HasFactory<StudentPaymentSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'payment_type_id',
        'is_active',
        'started_at',
        'ended_at',
        'custom_amount',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'started_at' => 'date',
        'ended_at' => 'date',
        'custom_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (StudentPaymentSetting $setting) {
            if ($setting->is_active === false && $setting->isRequiredType()) {
                throw new \InvalidArgumentException(
                    'Jenis pembayaran "'.$setting->paymentType->name.'" bersifat wajib dan tidak boleh dinonaktifkan.'
                );
            }
        });

        static::deleting(function (StudentPaymentSetting $setting) {
            if ($setting->isRequiredType()) {
                throw new \InvalidArgumentException(
                    'Jenis pembayaran "'.$setting->paymentType->name.'" bersifat wajib dan tidak boleh dihapus.'
                );
            }
        });
    }

    public function isRequiredType(): bool
    {
        $schoolLevel = $this->student?->schoolClass?->schoolLevel;

        if ($schoolLevel !== null) {
            $default = PaymentTypeSchoolLevel::query()
                ->where('payment_type_id', $this->payment_type_id)
                ->where('school_level', $schoolLevel)
                ->first();

            return (bool) ($default?->is_required ?? false);
        }

        return (bool) ($this->paymentType?->is_required ?? false);
    }

    /**
     * Nominal yang berlaku untuk siswa ini: custom_amount jika diisi,
     * selain itu nominal dari payment rate default untuk level tertentu.
     *
     * Catatan: helper ini hanya untuk kebutuhan tampilan. Saat membuat
     * tagihan, BillGenerationService adalah pemilik resolusi rate dan
     * custom amount.
     */
    public function resolvedAmount(?int $classLevel = null): ?float
    {
        if ($this->custom_amount !== null) {
            return (float) $this->custom_amount;
        }

        if ($classLevel === null) {
            return null;
        }

        $rate = PaymentRate::query()
            ->where('payment_type_id', $this->payment_type_id)
            ->where('class_level', $classLevel)
            ->latest('effective_from')
            ->first();

        return $rate ? (float) $rate->amount : null;
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<PaymentType, $this>
     */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }
}
