<?php

use App\Enums\SchoolLevel;
use App\Livewire\StudentPaymentSettings;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use Livewire\Livewire;

it('menampilkan LOCKED untuk tipe wajib dan toggle untuk tipe opsional', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $osis = makeBillType('OSIS', auto: false, required: false);
    makeBillRate($osis, 8, 50000);

    $student = makeBillStudent(8);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->assertSee('SPP')
        ->assertSee('LOCKED')
        ->assertSee('Wajib')
        ->assertSee('OSIS')
        ->assertSee('Opsional');
});

it('mengaktifkan dan menonaktifkan keikutsertaan tipe opsional', function () {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    $component = Livewire::test(StudentPaymentSettings::class, ['student' => $student]);

    $component->call('confirmActivate', $jemputan->id)
        ->set('activateStartMonth', now()->format('Y-m'))
        ->call('activate');

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->where('is_active', true)
        ->exists())->toBeTrue();

    $component->call('toggle', $jemputan->id);

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->where('is_active', false)
        ->exists())->toBeTrue();
});

it('menolak menonaktifkan tipe wajib melalui UI', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('toggle', $spp->id);

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('is_active', true)
        ->exists())->toBeTrue();
});

it('mengaktifkan setting opsional tidak membuat tagihan otomatis', function () {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('confirmActivate', $jemputan->id)
        ->set('activateStartMonth', now()->format('Y-m'))
        ->call('activate')
        ->assertDispatched('student-payment-settings-updated', studentId: $student->id);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0);
});

it('menonaktifkan setting opsional tidak menghapus tagihan historis', function () {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);
    $historyBill = makeMonthlyBill($student, $jemputan, 550000, month: 7);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('confirmActivate', $jemputan->id)
        ->set('activateStartMonth', now()->format('Y-m'))
        ->call('activate')
        ->call('toggle', $jemputan->id);

    $bills = StudentBill::where('student_id', $student->id)->get();

    expect($bills)->toHaveCount(1)
        ->and(StudentBill::find($historyBill->id))->not->toBeNull();

    $futureCreated = app(BillGenerationService::class)
        ->generateForStudent($student, now()->addMonth());

    expect($futureCreated)->toHaveCount(0);
});
