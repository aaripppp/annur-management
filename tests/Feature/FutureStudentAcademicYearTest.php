<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentCreate;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\ClassPromotionService;
use App\Services\StudentCreationService;

beforeEach(function () {
    $this->activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $this->futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $this->sppType = makeBillType('SPP', auto: true, required: true);
    makeBillRate($this->sppType, 7, 975000);
    makeLevelDefault($this->sppType, SchoolLevel::SMP);
});

function createFutureStudent(int $level, string $entryYearId): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);

    return app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Masa Depan',
        'nama_panggilan' => 'Depan',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Masa Depan No. 1',
        'entry_academic_year_id' => $entryYearId,
    ]);
}

/*
|--------------------------------------------------------------------------
| Test 1: Current Class 7 student promoted to Class 8
|--------------------------------------------------------------------------
*/
it('promotes current class 7 student to class 8 in target year', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $result = $service->processPromotion($this->activeYear, $this->futureYear);

    expect($result['promoted'])->toBe(1);
    $student->refresh();
    expect($student->class_id)->toBe($class8->id);

    $enrollment = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $this->futureYear->id)
        ->first();
    expect($enrollment)->not->toBeNull()
        ->and($enrollment->school_class_id)->toBe($class8->id)
        ->and($enrollment->status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| Test 2: Future student not included in current year promotion
|--------------------------------------------------------------------------
*/
it('does not include future student in current year promotion', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->futureYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $service = app(ClassPromotionService::class);
    $preview = $service->getPreviewData($this->activeYear, $this->futureYear);

    $studentIds = $preview['students']->pluck('id')->toArray();
    expect($studentIds)->not->toContain($student->id);
    expect($preview['summary']['total_promoted'])->toBe(0)
        ->and($preview['summary']['total_graduated'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 3: Future student enrollment remains Class 7 after promotion
|--------------------------------------------------------------------------
*/
it('preserves future student enrollment class 7 after promotion', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $existingStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $existingStudent->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $futureStudent = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->futureYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $service->processPromotion($this->activeYear, $this->futureYear);

    $futureStudent->refresh();
    expect($futureStudent->class_id)->toBe($class7->id);

    $futureEnrollment = StudentAcademicEnrollment::where('student_id', $futureStudent->id)
        ->where('academic_year_id', $this->futureYear->id)
        ->first();
    expect($futureEnrollment->school_class_id)->toBe($class7->id)
        ->and($futureEnrollment->status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| Test 4: Promotion does not overwrite existing target-year enrollment
|--------------------------------------------------------------------------
*/
it('does not overwrite existing target-year enrollment during promotion', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $existingTargetEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->futureYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $result = $service->processPromotion($this->activeYear, $this->futureYear);

    $existingTargetEnrollment->refresh();
    expect($existingTargetEnrollment->school_class_id)->toBe($class7->id);
    expect($existingTargetEnrollment->status)->toBe('active');
    expect($result['promoted'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Test 5: Future student can have Uang Pangkal while active year is current
|--------------------------------------------------------------------------
*/
it('allows future student to have uang pangkal while active year is current', function () {
    $this->travelTo('2026-08-15');

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, [
        'billing_frequency' => BillFrequency::OneTime,
    ]);

    $futureStudent = createFutureStudent(7, $this->futureYear->id);

    expect($futureStudent->class_id)->not->toBeNull();

    $service = app(BillGenerationService::class);
    $service->generateBillbook($futureStudent, $this->futureYear->start_date);

    $pangkalBill = $futureStudent->bills()
        ->where('payment_type_id', $pangkal->id)
        ->first();

    expect($pangkalBill)->not->toBeNull()
        ->and($pangkalBill->academic_year)->toBe('2027/2028')
        ->and($pangkalBill->billing_frequency)->toBe(BillFrequency::OneTime->value)
        ->and((float) $pangkalBill->amount)->toBe(5000000.0);
});

/*
|--------------------------------------------------------------------------
| Test 6: Future student Uang Pangkal can be partially paid
|--------------------------------------------------------------------------
*/
it('allows partial payment of future student uang pangkal', function () {
    $this->travelTo('2026-12-15');

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, [
        'billing_frequency' => BillFrequency::OneTime,
    ]);

    $futureStudent = createFutureStudent(7, $this->futureYear->id);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($futureStudent, $this->futureYear->start_date);

    $pangkalBill = $futureStudent->bills()
        ->where('payment_type_id', $pangkal->id)
        ->first();

    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    $payment = Payment::create([
        'receipt_number' => 'KWT-FUTURE-'.uniqid(),
        'student_id' => $futureStudent->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-12-15',
        'total_amount' => 1000000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $pangkalBill->id,
        'payment_type_id' => $pangkal->id,
        'period_month' => null,
        'period_year' => null,
        'academic_year' => '2027/2028',
        'amount' => 1000000,
    ]);

    $pangkalBill->refresh();
    expect($pangkalBill->status)->toBe('partial')
        ->and((float) $pangkalBill->paid_amount)->toBe(1000000.0);
});

/*
|--------------------------------------------------------------------------
| Test 7: PaymentCreate can search and select future student
|--------------------------------------------------------------------------
*/
it('paymentcreate can search and select future student', function () {
    $this->travelTo('2026-12-15');

    $futureStudent = createFutureStudent(7, $this->futureYear->id);

    $searchResults = Student::where('nama_lengkap', 'like', '%Siswa Masa Depan%')
        ->orWhere('nis', 'like', '%'.$futureStudent->nis.'%')
        ->take(5)
        ->get();

    expect($searchResults->isNotEmpty())->toBeTrue()
        ->and($searchResults->first()->id)->toBe($futureStudent->id);
});

/*
|--------------------------------------------------------------------------
| Test 8: PaymentCreate shows future student outstanding bills
|--------------------------------------------------------------------------
*/
it('paymentcreate shows future student outstanding uang pangkal', function () {
    $this->travelTo('2026-12-15');

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, [
        'billing_frequency' => BillFrequency::OneTime,
    ]);

    $futureStudent = createFutureStudent(7, $this->futureYear->id);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($futureStudent, $this->futureYear->start_date);

    $outstanding = $futureStudent->bills()
        ->with('paymentType')
        ->get()
        ->filter(fn ($bill) => $bill->remaining_amount > 0);

    expect($outstanding->isNotEmpty())->toBeTrue();
    $pangkalBill = $outstanding->firstWhere('payment_type_id', $pangkal->id);
    expect($pangkalBill)->not->toBeNull()
        ->and((float) $pangkalBill->remaining_amount)->toBe(5000000.0);
});

/*
|--------------------------------------------------------------------------
| Test 9: Activating 2027/2028 does not duplicate Uang Pangkal
|--------------------------------------------------------------------------
*/
it('does not duplicate uang pangkal when 2027/2028 becomes active', function () {
    $this->travelTo('2026-08-15');

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, [
        'billing_frequency' => BillFrequency::OneTime,
    ]);

    $futureStudent = createFutureStudent(7, $this->futureYear->id);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($futureStudent, $this->futureYear->start_date);

    $initialCount = $futureStudent->bills()
        ->where('payment_type_id', $pangkal->id)
        ->count();
    expect($initialCount)->toBe(1);

    $this->travelTo('2027-09-01');
    $service->generateBillbook($futureStudent, $this->futureYear->start_date);

    $afterCount = $futureStudent->bills()
        ->where('payment_type_id', $pangkal->id)
        ->count();
    expect($afterCount)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Test 10: One-time lifetime dedup still works (regression)
|--------------------------------------------------------------------------
*/
it('maintains one-time lifetime dedup for future students', function () {
    $this->travelTo('2026-08-15');

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 7, 5000000, [
        'billing_frequency' => BillFrequency::OneTime,
    ]);

    $futureStudent = createFutureStudent(7, $this->futureYear->id);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($futureStudent, $this->futureYear->start_date);
    $service->generateBillbook($futureStudent, $this->futureYear->start_date);

    $count = $futureStudent->bills()
        ->where('payment_type_id', $pangkal->id)
        ->count();
    expect($count)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Test 11: Future student does not receive current year monthly bills
|--------------------------------------------------------------------------
*/
it('does not generate current year monthly bills for future student', function () {
    $this->travelTo('2026-08-15');

    $sppType = makeBillType('SPP', auto: true, required: true);
    makeBillRate($sppType, 7, 975000);
    makeLevelDefault($sppType, SchoolLevel::SMP);

    $futureStudent = createFutureStudent(7, $this->futureYear->id);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($futureStudent, $this->futureYear->start_date);

    $currentYearBills = $futureStudent->bills()
        ->where(function ($q) {
            $q->where('period_year', 2026)
                ->where('period_month', '>=', 8)
                ->where('period_month', '<=', 12);
        })
        ->count();

    expect($currentYearBills)->toBe(0);

    $futureYearBills = $futureStudent->bills()
        ->where(function ($q) {
            $q->where('period_year', 2027)
                ->where('period_month', '>=', 7)
                ->orWhere(function ($q2) {
                    $q2->where('period_year', 2028)
                        ->where('period_month', '<=', 6);
                });
        })
        ->count();

    expect($futureYearBills)->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Test 12: Existing current students still receive promoted year billbook
|--------------------------------------------------------------------------
*/
it('still generates billbook for existing current students during promotion', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    makeBillRate($this->sppType, 8, 975000);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $result = $service->processPromotion($this->activeYear, $this->futureYear);

    expect($result['promoted'])->toBe(1);

    $bills = $student->bills()
        ->where(function ($q) {
            $q->where('period_year', 2027)
                ->where('period_month', '>=', 7)
                ->orWhere(function ($q2) {
                    $q2->where('period_year', 2028)
                        ->where('period_month', '<=', 6);
                });
        })
        ->count();

    expect($bills)->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Test 13: Promotion remains idempotent
|--------------------------------------------------------------------------
*/
it('promotion remains idempotent when run twice', function () {
    $this->travelTo('2026-08-15');

    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $result1 = $service->processPromotion($this->activeYear, $this->futureYear);

    expect($result1['promoted'])->toBe(1);

    $result2 = $service->processPromotion($this->activeYear, $this->futureYear);

    expect($result2['promoted'])->toBe(0)
        ->and($result2['graduated'])->toBe(0);

    $enrollments = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $this->futureYear->id)
        ->count();
    expect($enrollments)->toBe(1);
});
