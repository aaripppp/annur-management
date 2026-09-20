<?php

namespace App\Models;

use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use Database\Factories\ProspectiveStudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $registration_number
 * @property string $nama_lengkap
 * @property string|null $nama_panggilan
 * @property string|null $jenis_kelamin
 * @property string|null $nama_orang_tua
 * @property string|null $no_telp_orang_tua
 * @property string|null $alamat
 * @property int $academic_year_id
 * @property int|null $school_class_id
 * @property ProspectiveStudentStatus $status
 * @property int|null $converted_student_id
 * @property string|null $notes
 * @property int|null $created_by
 */
class ProspectiveStudent extends Model
{
    /** @use HasFactory<ProspectiveStudentFactory> */
    use HasFactory;

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'registration_number',
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'nama_orang_tua',
        'no_telp_orang_tua',
        'alamat',
        'academic_year_id',
        'school_class_id',
        'status',
        'converted_student_id',
        'converted_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProspectiveStudentStatus::class,
            'converted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function convertedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'converted_student_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function isConverted(): bool
    {
        return $this->status === ProspectiveStudentStatus::Converted;
    }

    /**
     * Calon siswa tetap boleh membayar tagihan pendaftaran yang sudah ada
     * setelah dikonversi menjadi siswa. Status lain (mis. dibatalkan) tidak
     * boleh menerima pembayaran pendaftaran.
     */
    public function canReceiveRegistrationPayment(): bool
    {
        return $this->status === ProspectiveStudentStatus::Registered
            || $this->status === ProspectiveStudentStatus::Converted;
    }

    /**
     * Target level derived from the target class, never a separate column.
     */
    public function getTargetLevelAttribute(): ?SchoolLevel
    {
        return $this->schoolClass?->schoolLevel;
    }

    /** @return HasMany<ProspectiveStudentBill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(ProspectiveStudentBill::class);
    }

    /**
     * Status tagihan agregat untuk tampilan daftar: "none", "unpaid", "partial",
     * atau "paid". Selalu didelegasikan ke status komputasi tiap tagihan
     * (ProspectiveStudentBill) sehingga menggunakan sumber kebenaran yang sama.
     */
    public function getBillStatusAttribute(): string
    {
        $bills = $this->bills;

        if ($bills->isEmpty()) {
            return 'none';
        }

        if ($bills->every(fn (ProspectiveStudentBill $bill) => $bill->isSettled())) {
            return ProspectiveStudentBill::STATUS_PAID;
        }

        if ($bills->contains(fn (ProspectiveStudentBill $bill) => $bill->status === ProspectiveStudentBill::STATUS_PARTIAL)) {
            return ProspectiveStudentBill::STATUS_PARTIAL;
        }

        return ProspectiveStudentBill::STATUS_UNPAID;
    }

    public function getBillStatusLabelAttribute(): string
    {
        return match ($this->bill_status) {
            'none' => 'Belum Ada Tagihan',
            ProspectiveStudentBill::STATUS_UNPAID => 'Belum Bayar',
            ProspectiveStudentBill::STATUS_PARTIAL => 'Sebagian',
            ProspectiveStudentBill::STATUS_PAID => 'Lunas',
            default => ucfirst($this->bill_status),
        };
    }

    /** @return HasMany<ProspectiveStudentPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ProspectiveStudentPayment::class);
    }

    public function getTargetLevelLabelAttribute(): ?string
    {
        return $this->schoolClass?->schoolLevel?->value;
    }

    /**
     * Aturan validasi profil calon siswa. Dipakai bersama oleh komponen
     * manajemen data ataupun workspace pembayaran calon siswa.
     *
     * @return array<string, string>
     */
    public static function profileRules(): array
    {
        return [
            'nama_lengkap' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'nama_orang_tua' => 'nullable|string|max:255',
            'no_telp_orang_tua' => 'nullable|string|max:50',
            'alamat' => 'nullable|string',
            'academic_year_id' => 'required|exists:academic_years,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Pesan validasi profil calon siswa.
     *
     * @return array<string, string>
     */
    public static function profileMessages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max' => 'Nama lengkap maksimal 255 karakter.',
            'jenis_kelamin.in' => 'Jenis kelamin tidak valid.',
            'academic_year_id.required' => 'Tahun ajaran tujuan wajib dipilih.',
            'academic_year_id.exists' => 'Tahun ajaran tujuan tidak valid.',
            'school_class_id.required' => 'Kelas tujuan wajib dipilih.',
            'school_class_id.exists' => 'Kelas tujuan tidak valid.',
        ];
    }
}
