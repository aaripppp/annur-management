<?php

use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentRate;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;

it('adds KB once to an existing installation without mutating financial or enrollment data', function () {
    $class = SchoolClass::query()->create(['name' => 'A-1', 'level' => -2]);
    $student = Student::factory()->create(['class_id' => $class->id]);
    $academicYear = AcademicYear::query()->updateOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);
    $type = makeBillType('KB Migration Safety');
    makeBillRate($type, -2, 500000);
    $bill = makeMonthlyBill($student, $type, 500000);
    $payment = Payment::query()->create([
        'receipt_number' => 'KB-MIGRATION-SAFETY',
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-01',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'created_by' => User::factory()->create()->id,
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 100000,
    ]);
    $before = [
        'rates' => PaymentRate::query()->orderBy('id')->get()->toArray(),
        'bills' => StudentBill::query()->orderBy('id')->get()->toArray(),
        'payments' => Payment::query()->orderBy('id')->get()->toArray(),
        'details' => PaymentDetail::query()->orderBy('id')->get()->toArray(),
        'enrollments' => StudentAcademicEnrollment::query()->orderBy('id')->get()->toArray(),
    ];

    $migration = require database_path('migrations/2026_09_09_142816_add_kb_school_class.php');
    $migration->up();
    $migration->up();

    expect(SchoolClass::query()->where('name', 'KB')->where('level', -3)->count())->toBe(1)
        ->and(PaymentRate::query()->where('class_level', -3)->count())->toBe(0)
        ->and(PaymentRate::query()->orderBy('id')->get()->toArray())->toBe($before['rates'])
        ->and(StudentBill::query()->orderBy('id')->get()->toArray())->toBe($before['bills'])
        ->and(Payment::query()->orderBy('id')->get()->toArray())->toBe($before['payments'])
        ->and(PaymentDetail::query()->orderBy('id')->get()->toArray())->toBe($before['details'])
        ->and(StudentAcademicEnrollment::query()->orderBy('id')->get()->toArray())->toBe($before['enrollments']);
});
