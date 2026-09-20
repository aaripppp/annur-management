<?php

use App\Enums\SchoolLevel;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPaymentSetting;

it('mengaktifkan otomatis default jenjang SMP (SPP, Ekskul, OSIS) saat siswa baru dibuat', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    $osis = makeBillType('OSIS', auto: true, required: true);
    $jemputan = makeBillType('Jemputan', auto: false, required: false);

    makeLevelDefault($spp, SchoolLevel::SMP);
    makeLevelDefault($ekskul, SchoolLevel::SMP);
    makeLevelDefault($osis, SchoolLevel::SMP);
    makeBillRate($spp, 8, 970000);
    makeBillRate($ekskul, 8, 52000);
    makeBillRate($osis, 8, 5000);

    $student = makeBillStudent(8);

    $settings = $student->paymentSettings()->with('paymentType')->get();

    expect($settings->where('payment_type_id', $spp->id)->first()?->is_active)->toBeTrue()
        ->and($settings->where('payment_type_id', $ekskul->id)->first()?->is_active)->toBeTrue()
        ->and($settings->where('payment_type_id', $osis->id)->first()?->is_active)->toBeTrue()
        ->and($settings->where('payment_type_id', $jemputan->id)->first())->toBeNull();
});

it('tidak membuat setting otomatis untuk jenis pembayaran opsional', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeBillRate($spp, 8, 970000);

    $jemputan = makeBillType('Jemputan', auto: false);

    $student = makeBillStudent(8);

    expect($student->paymentSettings()->where('payment_type_id', $jemputan->id)->exists())->toBeFalse();
});

it('tidak menduplikasi setting ketika siswa disimpan ulang', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeBillRate($spp, 8, 970000);

    $student = makeBillStudent(8);

    expect($student->paymentSettings()->count())->toBe(1);

    $student->paymentSettings()->firstOrCreate(
        ['payment_type_id' => $spp->id],
        ['is_active' => true]
    );

    expect($student->paymentSettings()->count())->toBe(1);
});

it('tidak membuat OSIS otomatis untuk siswa TK meski is_auto_enrolled global aktif', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::TK);
    makeBillRate($spp, -2, 970000);

    $osis = makeBillType('OSIS', auto: true, required: true);
    makeLevelDefault($osis, SchoolLevel::SMP);

    $student = makeBillStudent(-2);

    expect(StudentPaymentSetting::where('student_id', $student->id)->where('payment_type_id', $osis->id)->exists())->toBeFalse()
        ->and(StudentPaymentSetting::where('student_id', $student->id)->where('payment_type_id', $spp->id)->exists())->toBeTrue()
        ->and(PaymentTypeSchoolLevel::where('school_level', SchoolLevel::TK)->where('payment_type_id', $osis->id)->exists())->toBeFalse();
});

it('siswa dengan jenjang tak dikenal tidak mendapatkan auto-enrollment', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $class = SchoolClass::factory()->create(['level' => 99]);

    $student = Student::factory()->create(['class_id' => $class->id]);

    expect($student->schoolLevel)->toBeNull()
        ->and($student->paymentSettings()->count())->toBe(0);
});
