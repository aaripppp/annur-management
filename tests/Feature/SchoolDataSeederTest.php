<?php

use App\Enums\SchoolLevel;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use Database\Seeders\SchoolDataSeeder;

it('seeder membuat data master lengkap', function () {
    $this->seed(SchoolDataSeeder::class);

    expect(PaymentType::where('name', 'SPP')->exists())->toBeTrue()
        ->and(PaymentRate::count())->toBeGreaterThan(0)
        ->and(StudentPaymentSetting::count())->toBeGreaterThan(0)
        ->and(StudentBill::count())->toBeGreaterThan(0);

    $spp = PaymentType::where('name', 'SPP')->first();

    expect($spp->is_auto_enrolled)->toBeTrue()
        ->and($spp->is_required)->toBeTrue()
        ->and(SchoolClass::query()->where('name', 'KB')->where('level', -3)->count())->toBe(1)
        ->and(PaymentRate::query()->where('class_level', -3)->count())->toBe(0);
});

it('seeder membuat default jenis pembayaran per jenjang', function () {
    $this->seed(SchoolDataSeeder::class);

    $expected = [
        'TK' => ['SPP', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
        'SD' => ['SPP', 'Ekskul', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
        'SMP' => ['SPP', 'Ekskul', 'OSIS', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
        'SMA' => ['SPP', 'Ekskul', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal'],
    ];

    foreach ($expected as $level => $typeNames) {
        foreach ($typeNames as $typeName) {
            expect(PaymentTypeSchoolLevel::where('school_level', SchoolLevel::from($level))
                ->whereHas('paymentType', fn ($q) => $q->where('name', $typeName))
                ->exists())->toBeTrue();
        }
    }
});

it('seeder hanya mengaktifkan setting yang berlaku untuk jenjang siswa', function () {
    $this->seed(SchoolDataSeeder::class);

    $tk = Student::whereHas('schoolClass', fn ($q) => $q->whereIn('level', SchoolLevel::TK->classLevels()))->first();
    $sd = Student::whereHas('schoolClass', fn ($q) => $q->where('level', 3))->first();
    $smp = Student::whereHas('schoolClass', fn ($q) => $q->where('level', 8))->first();
    $sma = Student::whereHas('schoolClass', fn ($q) => $q->where('level', 10))->first();

    expect(StudentPaymentSetting::where('student_id', $tk->id)->where('is_active', true)->count())->toBe(4)
        ->and(StudentPaymentSetting::where('student_id', $sd->id)->where('is_active', true)->count())->toBe(5)
        ->and(StudentPaymentSetting::where('student_id', $smp->id)->where('is_active', true)->count())->toBe(6)
        ->and(StudentPaymentSetting::where('student_id', $sma->id)->where('is_active', true)->count())->toBe(5);
});

it('seeder idempotent ketika dijalankan dua kali', function () {
    $this->seed(SchoolDataSeeder::class);

    $students = Student::count();
    $settings = StudentPaymentSetting::count();
    $bills = StudentBill::count();
    $rates = PaymentRate::count();

    $this->seed(SchoolDataSeeder::class);

    expect(Student::count())->toBe($students)
        ->and(StudentPaymentSetting::count())->toBe($settings)
        ->and(StudentBill::count())->toBe($bills)
        ->and(PaymentRate::count())->toBe($rates);
});
