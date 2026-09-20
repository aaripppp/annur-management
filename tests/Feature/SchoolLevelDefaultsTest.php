<?php

use App\Enums\SchoolLevel;
use App\Livewire\StudentPaymentSettings;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-08-15');
});

it('siswa TK baru: hanya SPP yang aktif, Ekskul dan OSIS tidak', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::TK);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    $osis = makeBillType('OSIS', auto: true, required: true);

    $student = makeBillStudent(-2);

    $settings = $student->paymentSettings()->pluck('is_active', 'payment_type_id');

    expect($settings)->toHaveCount(1)
        ->and($settings->has($spp->id))->toBeTrue()
        ->and($settings[$spp->id])->toBeTrue()
        ->and($settings->has($ekskul->id))->toBeFalse()
        ->and($settings->has($osis->id))->toBeFalse();
});

it('siswa SD baru: SPP dan Ekskul aktif, OSIS tidak', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SD);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeLevelDefault($ekskul, SchoolLevel::SD);

    $osis = makeBillType('OSIS', auto: true, required: true);

    $student = makeBillStudent(3);

    $settings = $student->paymentSettings()->pluck('is_active', 'payment_type_id');

    expect($settings)->toHaveCount(2)
        ->and($settings[$spp->id])->toBeTrue()
        ->and($settings[$ekskul->id])->toBeTrue()
        ->and($settings->has($osis->id))->toBeFalse();
});

it('siswa SMP baru: SPP, Ekskul, dan OSIS aktif', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeLevelDefault($ekskul, SchoolLevel::SMP);

    $osis = makeBillType('OSIS', auto: true, required: true);
    makeLevelDefault($osis, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    $settings = $student->paymentSettings()->pluck('is_active', 'payment_type_id');

    expect($settings)->toHaveCount(3)
        ->and($settings[$spp->id])->toBeTrue()
        ->and($settings[$ekskul->id])->toBeTrue()
        ->and($settings[$osis->id])->toBeTrue();
});

it('siswa SMA baru: SPP dan Ekskul aktif, OSIS tidak', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMA);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeLevelDefault($ekskul, SchoolLevel::SMA);

    $osis = makeBillType('OSIS', auto: true, required: true);

    $student = makeBillStudent(10);

    $settings = $student->paymentSettings()->pluck('is_active', 'payment_type_id');

    expect($settings)->toHaveCount(2)
        ->and($settings[$spp->id])->toBeTrue()
        ->and($settings[$ekskul->id])->toBeTrue()
        ->and($settings->has($osis->id))->toBeFalse();
});

it('jenis opsional tidak ter-enroll otomatis karena jenjang', function (string $typeName) {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $optional = makeBillType($typeName, auto: false, required: false);

    $student = makeBillStudent(8);

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $optional->id)
        ->exists())->toBeFalse();
})->with(['Jemputan', 'Uang Buku', 'Uang Kegiatan', 'Uang Pangkal', 'Lain-lain']);

it('OSIS tidak dapat dinonaktifkan untuk siswa SMP', function () {
    $osis = makeBillType('OSIS', auto: true, required: true);
    makeLevelDefault($osis, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    $setting = StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $osis->id)
        ->first();

    expect($setting)->not->toBeNull();

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('toggle', $osis->id);

    expect(StudentPaymentSetting::find($setting->id)->is_active)->toBeTrue();
});

it('TK tidak mewarisi guard wajib OSIS', function () {
    $osis = makeBillType('OSIS', auto: true, required: true);
    makeLevelDefault($osis, SchoolLevel::SMP);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::TK);

    $student = makeBillStudent(-2);

    $osisSetting = StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $osis->id)
        ->first();

    expect($osisSetting)->toBeNull();

    makeActiveSetting($student, $osis);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('toggle', $osis->id);

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $osis->id)
        ->first()->is_active)->toBeFalse();
});

it('isRequiredType mengevaluasi jenjang, bukan flag global', function () {
    $osis = makeBillType('OSIS', auto: true, required: true);
    makeLevelDefault($osis, SchoolLevel::SMP);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::TK);

    $tkStudent = makeBillStudent(-2);
    $smpStudent = makeBillStudent(8);

    $tkSetting = makeActiveSetting($tkStudent, $osis);
    $smpSetting = StudentPaymentSetting::where('student_id', $smpStudent->id)
        ->where('payment_type_id', $osis->id)
        ->first();

    expect($tkSetting->isRequiredType())->toBeFalse()
        ->and($smpSetting->isRequiredType())->toBeTrue();
});

it('UI: status wajib mengikuti jenjang siswa', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, -2, 300000);
    makeLevelDefault($spp, SchoolLevel::TK);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, -2, 30000);

    $osis = makeBillType('OSIS', auto: true, required: true);
    makeBillRate($osis, -2, 3000);

    $jemputan = makeBillType('Jemputan', auto: false, required: false);

    $student = makeBillStudent(-2);

    $component = Livewire::test(StudentPaymentSettings::class, ['student' => $student]);

    $rows = collect($component->get('settings'))->keyBy('name');

    expect($rows['SPP']['is_required'])->toBeTrue()
        ->and($rows['SPP']['is_active'])->toBeTrue()
        ->and($rows['Ekskul']['is_required'])->toBeFalse()
        ->and($rows['Ekskul']['is_active'])->toBeFalse()
        ->and($rows['OSIS']['is_required'])->toBeFalse()
        ->and($rows['OSIS']['is_active'])->toBeFalse()
        ->and($rows['Jemputan']['is_required'])->toBeFalse();
});

it('generasi tagihan mengikuti default jenjang (TK hanya SPP)', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, -2, 300000);
    makeLevelDefault($spp, SchoolLevel::TK);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, -2, 30000);

    $osis = makeBillType('OSIS', auto: true, required: true);
    makeBillRate($osis, -2, 3000);

    $student = makeBillStudent(-2);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bills = StudentBill::where('student_id', $student->id)->get();

    expect($bills)->toHaveCount(1)
        ->and($bills->first()->payment_type_id)->toBe($spp->id);
});

it('default jenjang tersimpan sebagai data relasi payment_type_school_levels', function () {
    $spp = makeBillType('SPP');
    makeLevelDefault($spp, SchoolLevel::SMP);

    $row = PaymentTypeSchoolLevel::where('payment_type_id', $spp->id)
        ->where('school_level', SchoolLevel::SMP)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->school_level)->toBe(SchoolLevel::SMP)
        ->and($row->is_required)->toBeTrue()
        ->and($row->is_active)->toBeTrue()
        ->and($row->paymentType->is($spp))->toBeTrue();
});
