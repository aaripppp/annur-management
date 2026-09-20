<?php

namespace Database\Seeders;

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Services\BillGenerationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class SchoolDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedPaymentTypes();
        $this->seedSchoolLevelDefaults();
        $this->seedPaymentRates();
        $this->seedBanks();
        $this->seedClasses();

        $students = $this->seedStudents();
        $this->seedPaymentSettings($students);
        $this->generateBills($students);
        $this->seedAcademicEnrollments();
    }

    /**
     * Payment types global. SPP/Ekskul/OSIS otomatis & wajib; sisanya opsional.
     */
    protected function seedPaymentTypes(): void
    {
        $types = [
            'SPP' => ['is_auto_enrolled' => true, 'is_required' => true],
            'Ekskul' => ['is_auto_enrolled' => true, 'is_required' => true],
            'OSIS' => ['is_auto_enrolled' => true, 'is_required' => true],
            'Jemputan' => ['is_auto_enrolled' => false, 'is_required' => false],
            'Uang Pangkal' => ['is_auto_enrolled' => false, 'is_required' => false],
            'Uang Buku' => ['is_auto_enrolled' => false, 'is_required' => false],
            'Uang Kegiatan' => ['is_auto_enrolled' => false, 'is_required' => false],
            'Lain-lain' => ['is_auto_enrolled' => false, 'is_required' => false],
        ];

        foreach ($types as $name => $flags) {
            PaymentType::updateOrCreate(
                ['name' => $name],
                ['is_active' => true] + $flags
            );
        }
    }

    /**
     * Jenis pembayaran otomatis per jenjang. Biaya standar tahunan dan sekali
     * bayar berlaku di semua jenjang; jenis lainnya tetap manual.
     */
    protected function seedSchoolLevelDefaults(): void
    {
        $defaults = [
            'TK' => ['SPP', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
            'SD' => ['SPP', 'Ekskul', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
            'SMP' => ['SPP', 'Ekskul', 'OSIS', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
            'SMA' => ['SPP', 'Ekskul', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
        ];
        $requiredTypeNames = ['SPP', 'Ekskul', 'OSIS'];

        foreach ($defaults as $level => $typeNames) {
            foreach ($typeNames as $typeName) {
                $type = PaymentType::where('name', $typeName)->firstOrFail();

                PaymentTypeSchoolLevel::updateOrCreate(
                    ['payment_type_id' => $type->id, 'school_level' => SchoolLevel::from($level)],
                    [
                        'is_required' => in_array($typeName, $requiredTypeNames, true),
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * Tarif baseline untuk level lama. Tarif KB dikonfigurasi melalui UI tarif.
     */
    protected function seedPaymentRates(): void
    {
        $monthly = ['SPP', 'Ekskul', 'OSIS', 'Jemputan'];
        $yearly = ['Uang Buku', 'Uang Kegiatan'];

        $rates = [
            'SPP' => array_replace(
                $this->expandLevels([0 => 300000, 1 => 450000, 7 => 970000, 10 => 1000000]),
                [2 => 450000, 3 => 475000, 4 => 475000, 5 => 500000, 6 => 500000, 8 => 975000, 9 => 970000, 11 => 1050000, 12 => 1100000]
            ),
            'Ekskul' => array_replace(
                $this->expandLevels([0 => 30000, 1 => 40000, 7 => 60000, 10 => 70000]),
                [2 => 40000, 3 => 45000, 4 => 45000, 5 => 50000, 6 => 50000, 8 => 52000, 9 => 53000, 11 => 75000, 12 => 75000]
            ),
            'OSIS' => $this->expandLevels([0 => 3000, 1 => 4000, 7 => 5000, 10 => 7000]),
            'Jemputan' => $this->expandLevels([0 => 200000, 1 => 300000, 7 => 550000, 10 => 600000]),
            'Uang Pangkal' => $this->expandLevels([0 => 2000000, 1 => 3000000, 7 => 5000000, 10 => 6000000]),
            'Uang Buku' => $this->expandLevels([0 => 250000, 1 => 300000, 7 => 500000, 10 => 750000]),
            'Uang Kegiatan' => $this->expandLevels([0 => 50000, 1 => 75000, 7 => 100000, 10 => 150000]),
            'Lain-lain' => $this->expandLevels([0 => 50000, 1 => 50000, 7 => 100000, 10 => 100000]),
        ];

        foreach ($rates as $typeName => $amounts) {
            $type = PaymentType::where('name', $typeName)->firstOrFail();

            $billingFrequency = in_array($typeName, $monthly, true)
                ? BillFrequency::Monthly
                : (in_array($typeName, $yearly, true) ? BillFrequency::Yearly : BillFrequency::OneTime);

            foreach ($amounts as $level => $amount) {
                PaymentRate::updateOrCreate(
                    ['payment_type_id' => $type->id, 'class_level' => $level, 'effective_from' => Carbon::parse('2026-08-01')],
                    [
                        'amount' => $amount,
                        'billing_frequency' => $billingFrequency,
                        'effective_until' => null,
                    ]
                );
            }
        }
    }

    /**
     * Perluas rate per kelompok level menjadi per level (0-12).
     *
     * @param  array<int, int>  $groupAmounts  level mewakili kelompok: 0=TK, 1=SD, 7=SMP, 10=SMA
     * @return array<int, int>
     */
    protected function expandLevels(array $groupAmounts): array
    {
        $ranges = [
            0 => [-2, -1],
            1 => [1, 2, 3, 4, 5, 6],
            7 => [7, 8, 9],
            10 => [10, 11, 12],
        ];

        $result = [];

        foreach ($groupAmounts as $level => $amount) {
            foreach ($ranges[$level] ?? [$level] as $lvl) {
                $result[$lvl] = $amount;
            }
        }

        return $result;
    }

    /**
     * Bank penerima pembayaran (dibutuhkan form pembayaran).
     */
    protected function seedBanks(): void
    {
        $banks = [
            ['name' => 'BSI', 'account_number' => '7123456789', 'account_name' => 'Yayasan Sekolah'],
            ['name' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'Yayasan Sekolah'],
            ['name' => 'Mandiri', 'account_number' => '9876543210', 'account_name' => 'Yayasan Sekolah'],
            ['name' => 'BRI', 'account_number' => '1122334455', 'account_name' => 'Yayasan Sekolah'],
            ['name' => 'BNI', 'account_number' => '5566778899', 'account_name' => 'Yayasan Sekolah'],
        ];

        foreach ($banks as $bank) {
            Bank::firstOrCreate(['name' => $bank['name']], $bank);
        }
    }

    /**
     * 15 kelas: 3 TK, 6 SD, 3 SMP, 3 SMA.
     */
    protected function seedClasses(): void
    {
        foreach ($this->classDefinitions() as $definition) {
            SchoolClass::updateOrCreate(['name' => $definition['name']], $definition);
        }
    }

    /**
     * @return array<int, array{name: string, level: int}>
     */
    protected function classDefinitions(): array
    {
        return [
            ['name' => 'KB', 'level' => -3],
            ['name' => 'TK A', 'level' => -2],
            ['name' => 'TK B', 'level' => -1],
            ['name' => 'I A', 'level' => 1],
            ['name' => 'II A', 'level' => 2],
            ['name' => 'III A', 'level' => 3],
            ['name' => 'IV A', 'level' => 4],
            ['name' => 'V A', 'level' => 5],
            ['name' => 'VI A', 'level' => 6],
            ['name' => 'VII A', 'level' => 7],
            ['name' => 'VIII B', 'level' => 8],
            ['name' => 'IX A', 'level' => 9],
            ['name' => 'X A', 'level' => 10],
            ['name' => 'XI A', 'level' => 11],
            ['name' => 'XII A', 'level' => 12],
        ];
    }

    /**
     * 150 siswa (10 per kelas).
     *
     * @return Collection<int, Student>
     */
    protected function seedStudents(): Collection
    {
        $classes = SchoolClass::all()->keyBy('name');
        $students = collect();
        $nisCounter = 1;

        foreach ($this->classDefinitions() as $definition) {
            $class = $classes[$definition['name']];

            for ($i = 1; $i <= 10; $i++) {
                $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $name = 'Siswa '.$definition['name'].' '.$number;
                $nis = '2026'.str_pad((string) $nisCounter++, 4, '0', STR_PAD_LEFT);

                $student = Student::firstOrCreate(
                    ['nis' => $nis],
                    [
                        'nama_lengkap' => $name,
                        'nama_panggilan' => 'Siswa',
                        'class_id' => $class->id,
                        'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                        'alamat' => 'Jl. '.$definition['name'].' No. '.$i,
                    ]
                );

                $students->push($student);
            }
        }

        return $students;
    }

    /**
     * Backfill pengaturan pembayaran mengikuti default jenjang (idempotent).
     *
     * - Default yang berlaku untuk jenjang dipastikan aktif (tanpa
     *   mengubah started_at/ended_at yang sudah ada).
     * - Jenis yang sebelumnya auto-enrolled global (is_auto_enrolled) tapi
     *   BUKAN default untuk jenjang siswa (mis. OSIS pada siswa TK)
     *   dinonaktifkan. Ini hanya mengoreksi konfigurasi; bill dan histori
     *   pembayaran tidak dihapus.
     * - Jenis opsional yang aktif manual tidak disentuh.
     *
     * @param  Collection<int, Student>  $students
     */
    protected function seedPaymentSettings(Collection $students): void
    {
        $autoTypeIds = PaymentType::where('is_auto_enrolled', true)
            ->where('is_active', true)
            ->where('audience', PaymentTypeAudience::Student)
            ->pluck('id');

        foreach ($students as $student) {
            $level = $student->schoolLevel;

            if ($level === null) {
                continue;
            }

            $defaultTypeIds = PaymentTypeSchoolLevel::query()
                ->where('school_level', $level)
                ->where('is_active', true)
                ->whereHas('paymentType', fn ($query) => $query
                    ->where('audience', PaymentTypeAudience::Student))
                ->pluck('payment_type_id');

            foreach ($defaultTypeIds as $typeId) {
                $setting = $student->paymentSettings()->firstOrCreate(
                    ['payment_type_id' => $typeId],
                    ['is_active' => true, 'started_at' => '2026-08-01', 'ended_at' => null]
                );

                if (! $setting->wasRecentlyCreated && ! $setting->is_active) {
                    $setting->update(['is_active' => true]);
                }
            }

            $student->paymentSettings()
                ->whereIn('payment_type_id', $autoTypeIds->diff($defaultTypeIds))
                ->update(['is_active' => false]);
        }
    }

    /**
     * Generate tagihan Agustus 2026 lewat BillGenerationService (idempotent).
     * Mengikuti StudentPaymentSettings aktif per jenjang (mis. TK hanya SPP;
     * SMP: SPP + Ekskul + OSIS).
     *
     * @param  Collection<int, Student>  $students
     */
    protected function generateBills(Collection $students): void
    {
        $target = Carbon::create(2026, 8, 15);
        $service = app(BillGenerationService::class);

        foreach ($students as $student) {
            $service->generateForStudent($student, $target);
        }
    }

    /**
     * Backfill student_academic_enrollments untuk semua siswa existing
     * di tahun ajaran yang sedang aktif. Idempotent — tidak membuat
     * duplikat jika sudah ada.
     */
    protected function seedAcademicEnrollments(): void
    {
        $activeYear = AcademicYear::active();

        if (! $activeYear) {
            return;
        }

        $students = Student::all();

        foreach ($students as $student) {
            StudentAcademicEnrollment::firstOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $activeYear->id],
                [
                    'school_class_id' => $student->class_id,
                    'status' => $student->status->value,
                ]
            );
        }
    }
}
