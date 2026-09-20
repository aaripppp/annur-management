<?php

use App\Enums\SchoolLevel;
use App\Models\PaymentRate;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo('2026-08-15');
});

it('membuat tagihan SPP untuk siswa dengan tipe wajib', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    $created = app(BillGenerationService::class)
        ->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->first();

    expect($created)->toHaveCount(1)
        ->and($bill)->not->toBeNull()
        ->and((int) $bill->amount)->toBe(1750000)
        ->and($bill->period_month)->toBe(8)
        ->and($bill->period_year)->toBe(2026);
});

it('membuat tagihan Ekskul untuk siswa dengan tipe wajib', function () {
    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, 8, 100000);
    makeLevelDefault($ekskul, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $ekskul->id)
        ->exists())->toBeTrue();
});

it('tidak membuat tagihan untuk tipe opsional yang tidak aktif', function () {
    $opsional = makeBillType('OSIS', auto: false, required: false);
    makeBillRate($opsional, 8, 50000);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $opsional->id)
        ->exists())->toBeFalse();
});

it('tidak membuat tagihan untuk tipe opsional yang aktif (manual saja)', function () {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $jemputan->id,
        'is_active' => true,
    ]);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->exists())->toBeFalse();
});

it('tidak membuat tagihan duplikat ketika dijalankan dua kali', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);
    $service = app(BillGenerationService::class);

    $service->generateForStudent($student, Carbon::parse('2026-08-15'));
    $service->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(1);
});

it('membuat tagihan terpisah untuk periode yang berbeda', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);
    $service = app(BillGenerationService::class);

    $service->generateForStudent($student, Carbon::parse('2026-08-15'));
    $service->generateForStudent($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(2);
});

it('memilih rate berdasarkan class level siswa', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 1500000);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student7 = makeBillStudent(7);
    $student8 = makeBillStudent(8);
    $service = app(BillGenerationService::class);

    $service->generateForStudent($student7, Carbon::parse('2026-08-15'));
    $service->generateForStudent($student8, Carbon::parse('2026-08-15'));

    $bill7 = StudentBill::where('student_id', $student7->id)->where('payment_type_id', $spp->id)->first();
    $bill8 = StudentBill::where('student_id', $student8->id)->where('payment_type_id', $spp->id)->first();

    expect((int) $bill7->amount)->toBe(1500000)
        ->and((int) $bill8->amount)->toBe(1750000);
});

it('memilih rate paling baru yang masih berlaku', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000, ['effective_from' => '2026-01-01', 'effective_until' => '2026-06-30']);
    makeBillRate($spp, 8, 1750000, ['effective_from' => '2026-07-01', 'effective_until' => null]);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)->where('payment_type_id', $spp->id)->first();

    expect((int) $bill->amount)->toBe(1750000);
});

it('tidak membuat tagihan ketika rate belum efektif (effective_from di masa depan)', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000, ['effective_from' => '2026-09-01', 'effective_until' => null]);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(0);
});

it('tidak membuat tagihan ketika rate sudah lewat effective_until', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000, ['effective_from' => '2026-01-01', 'effective_until' => '2026-07-31']);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(0);
});

it('membuat tagihan saat tanggal target berada dalam rentang effective', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000, ['effective_from' => '2026-01-01', 'effective_until' => '2026-12-31']);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)->count())->toBe(1);
});

it('tidak membuat tagihan Uang Pangkal tanpa periode dan tidak duplikat', function () {
    $pangkal = makeBillType('Uang Pangkal', auto: false, required: false);
    PaymentRate::factory()->create([
        'payment_type_id' => $pangkal->id,
        'class_level' => 8,
        'amount' => 5000000,
        'is_monthly' => false,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);
    makeLevelDefault($pangkal, SchoolLevel::SMP, required: false);

    $student = makeBillStudent(8);

    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateForStudent($student, Carbon::parse('2026-08-15'));
    $service->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and((int) $bill->amount)->toBe(5000000)
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull()
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $pangkal->id)
            ->count())->toBe(1);
});
