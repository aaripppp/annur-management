<?php

use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;

it('renders future student detail without error', function () {
    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $class = SchoolClass::factory()->create(['level' => 4]);
    $student = Student::factory()->create(['class_id' => $class->id, 'status' => 'aktif']);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $futureYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    $sppType = PaymentType::create(['name' => 'SPP', 'is_active' => true]);
    $ekskulType = PaymentType::create(['name' => 'Ekskul', 'is_active' => true]);

    for ($m = 7; $m <= 12; $m++) {
        StudentBill::create([
            'student_id' => $student->id, 'payment_type_id' => $sppType->id,
            'amount' => 475000,
            'period_month' => $m, 'period_year' => 2027,
            'academic_year' => null, 'billing_frequency' => 'monthly',
        ]);
    }
    for ($m = 1; $m <= 6; $m++) {
        StudentBill::create([
            'student_id' => $student->id, 'payment_type_id' => $sppType->id,
            'amount' => 475000,
            'period_month' => $m, 'period_year' => 2028,
            'academic_year' => null, 'billing_frequency' => 'monthly',
        ]);
    }

    StudentBill::create([
        'student_id' => $student->id, 'payment_type_id' => $sppType->id,
        'amount' => 75000,
        'period_month' => null, 'period_year' => null,
        'academic_year' => '2027/2028', 'billing_frequency' => 'yearly',
    ]);

    StudentBill::create([
        'student_id' => $student->id, 'payment_type_id' => $sppType->id,
        'amount' => 3000000,
        'period_month' => null, 'period_year' => null,
        'academic_year' => '2027/2028', 'billing_frequency' => 'one_time',
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee($student->nama_lengkap);
});
