<?php

use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Support\Carbon;

it('Juli 2026 masuk tahun ajaran 2026/2027', function () {
    expect(AcademicYear::fromDate('2026-07-01')->label())->toBe('2026/2027');
});

it('Agustus 2026 masuk tahun ajaran 2026/2027', function () {
    $academicYear = AcademicYear::fromDate('2026-08-15');

    expect($academicYear->label())->toBe('2026/2027')
        ->and($academicYear->startYear())->toBe(2026)
        ->and($academicYear->endYear())->toBe(2027);
});

it('Juni 2026 masuk tahun ajaran 2025/2026', function () {
    expect(AcademicYear::fromDate('2026-06-15')->label())->toBe('2025/2026');
});

it('Juni 2027 masuk tahun ajaran 2026/2027', function () {
    expect(AcademicYear::fromDate('2027-06-30')->label())->toBe('2026/2027');
});

it('Juli 2027 masuk tahun ajaran 2027/2028', function () {
    expect(AcademicYear::fromDate(Carbon::parse('2027-07-01'))->label())->toBe('2027/2028');
});

it('tagihan bulanan memiliki academic_year NULL', function () {
    $student = makeBillStudent();
    $type = makeBillType('SPP', auto: true, required: true);

    $bill = makeMonthlyBill($student, $type, 1500000, month: 8, year: 2026);

    $fresh = StudentBill::find($bill->id);

    expect($fresh->academic_year)->toBeNull()
        ->and($fresh->period_month)->toBe(8)
        ->and($fresh->period_year)->toBe(2026);
});

it('tagihan tahunan dapat menyimpan academic_year', function () {
    $student = makeBillStudent();
    $type = makeBillType('Uang Buku', auto: false, required: false);

    $bill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 250000,
        'period_month' => null,
        'period_year' => null,
        'academic_year' => '2026/2027',
        'due_date' => null,
    ]);

    $fresh = StudentBill::find($bill->id);

    expect($fresh->academic_year)->toBe('2026/2027')
        ->and($fresh->period_month)->toBeNull()
        ->and($fresh->period_year)->toBeNull();
});

it('tagihan sekali bayar memiliki academic_year NULL', function () {
    $student = makeBillStudent();
    $type = makeBillType('Uang Pangkal', auto: false, required: false);

    $bill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 5000000,
        'period_month' => null,
        'period_year' => null,
        'academic_year' => null,
        'due_date' => null,
    ]);

    $fresh = StudentBill::find($bill->id);

    expect($fresh->academic_year)->toBeNull()
        ->and($fresh->period_month)->toBeNull()
        ->and($fresh->period_year)->toBeNull();
});

it('label periode bulanan berupa nama bulan tahun', function () {
    $student = makeBillStudent();
    $type = makeBillType('SPP', auto: true, required: true);

    $bill = makeMonthlyBill($student, $type, 1500000, month: 8, year: 2026);

    expect($bill->period_label)->toBe('Agustus 2026');
});

it('label periode tahunan berupa tahun ajaran', function () {
    $student = makeBillStudent();
    $type = makeBillType('Uang Buku', auto: false, required: false);

    $bill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 250000,
        'period_month' => null,
        'period_year' => null,
        'academic_year' => '2026/2027',
        'due_date' => null,
    ]);

    expect($bill->period_label)->toBe('Tahun Ajaran 2026/2027');
});

it('label periode sekali bayar berupa garis datar', function () {
    $student = makeBillStudent();
    $type = makeBillType('Uang Pangkal', auto: false, required: false);

    $bill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => 5000000,
        'period_month' => null,
        'period_year' => null,
        'academic_year' => null,
        'due_date' => null,
    ]);

    expect($bill->period_label)->toBe('—');
});

it('payment detail dapat menyimpan academic_year tanpa merusak record bulanan', function () {
    $student = makeBillStudent();
    $type = makeBillType('SPP', auto: true, required: true);
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'RC-'.fake()->unique()->numberBetween(1000, 99999),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-15',
        'total_amount' => 1500000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $detail = PaymentDetail::create([
        'payment_id' => $payment->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'academic_year' => '2026/2027',
        'amount' => 1500000,
        'description' => null,
    ]);

    $fresh = PaymentDetail::find($detail->id);

    expect($fresh->period_month)->toBe(8)
        ->and($fresh->period_year)->toBe(2026)
        ->and($fresh->academic_year)->toBe('2026/2027')
        ->and((float) $fresh->amount)->toBe(1500000.0);
});
