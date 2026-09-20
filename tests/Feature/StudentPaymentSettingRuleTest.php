<?php

use App\Enums\SchoolLevel;
use App\Models\StudentPaymentSetting;

it('menolak menonaktifkan setting pembayaran wajib sesuai jenjang', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    $setting = $student->paymentSettings()->where('payment_type_id', $spp->id)->first();

    expect($setting)->not->toBeNull();

    expect(fn () => $setting->update(['is_active' => false]))
        ->toThrow(InvalidArgumentException::class)
        ->and($setting->refresh()->is_active)->toBeTrue();
});

it('menolak menghapus setting pembayaran wajib sesuai jenjang', function () {
    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeLevelDefault($ekskul, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    $setting = $student->paymentSettings()->where('payment_type_id', $ekskul->id)->first();

    expect(fn () => $setting->delete())->toThrow(InvalidArgumentException::class)
        ->and(StudentPaymentSetting::where('payment_type_id', $ekskul->id)->exists())->toBeTrue();
});

it('mengizinkan menonaktifkan setting pembayaran opsional', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $opsional = makeBillType('Jemputan', auto: false, required: false);

    $student = makeBillStudent(8);

    $setting = StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $opsional->id,
        'is_active' => true,
    ]);

    $setting->update(['is_active' => false]);

    expect($setting->refresh()->is_active)->toBeFalse();
});

it('mengizinkan menonaktifkan tipe wajib global di jenjang yang tidak mewajibkannya', function () {
    $osis = makeBillType('OSIS', auto: true, required: true);
    makeLevelDefault($osis, SchoolLevel::SMP);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::TK);

    $student = makeBillStudent(-2);

    $setting = StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $osis->id,
        'is_active' => true,
    ]);

    $setting->update(['is_active' => false]);

    expect($setting->refresh()->is_active)->toBeFalse();
});
