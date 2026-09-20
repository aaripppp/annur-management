<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\PaymentRate;
use App\Models\StudentBill;
use App\Services\BillGenerationService;
use Carbon\Carbon;

it('menggunakan ulang tagihan Agustus yang sudah ada saat generate until', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    makeMonthlyBill($student, $spp, 1750000, 8, 2026);

    $created = app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2026-12-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(5)
        ->and($created)->toHaveCount(4);
});

it('membuat tagihan September sampai Desember yang belum ada', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2026-12-15'));

    foreach ([8, 9, 10, 11, 12] as $month) {
        expect(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $spp->id)
            ->where('period_month', $month)
            ->where('period_year', 2026)
            ->exists())->toBeTrue();
    }
});

it('menjalankan generate until dua kali tidak membuat duplikat', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);
    $service = app(BillGenerationService::class);

    $service->generateUntil($student, Carbon::parse('2026-12-15'));
    $service->generateUntil($student, Carbon::parse('2026-12-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->count())->toBe(5);
});

it('tidak membuat tagihan untuk tipe opsional yang tidak aktif', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2026-12-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0);
});

it('tidak membuat tagihan untuk tipe opsional yang aktif (manual saja)', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    makeActiveSetting($student, $jemputan);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2026-12-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0);
});

it('tidak membuat duplikat tagihan non-monthly saat generate until', function () {
    $this->travelTo('2026-08-15');

    $pangkal = makeBillType('Uang Pangkal', auto: false, required: false);
    PaymentRate::factory()->create([
        'payment_type_id' => $pangkal->id,
        'class_level' => 8,
        'amount' => 5000000,
        'is_monthly' => false,
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);
    makeLevelDefault($pangkal, SchoolLevel::SMP, required: false);

    $student = makeBillStudent(8);

    makeActiveSetting($student, $pangkal);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2026-12-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count())->toBe(1);
});

it('menggunakan rate yang benar untuk tiap periode saat generate until', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000, ['effective_from' => '2026-01-01', 'effective_until' => '2026-09-30']);
    makeBillRate($spp, 8, 1750000, ['effective_from' => '2026-10-01', 'effective_until' => null]);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2026-12-15'));

    $august = StudentBill::where('student_id', $student->id)
        ->where('period_month', 8)
        ->first();

    $october = StudentBill::where('student_id', $student->id)
        ->where('period_month', 10)
        ->first();

    expect((int) $august->amount)->toBe(1500000)
        ->and((int) $october->amount)->toBe(1750000);
});

it('tidak membuat tagihan untuk periode yang belum ada rate yang berlaku', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000, ['effective_from' => '2026-11-01', 'effective_until' => null]);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2026-12-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 8)
        ->exists())->toBeFalse()
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $spp->id)
            ->where('period_month', 11)
            ->exists())->toBeTrue();
});
