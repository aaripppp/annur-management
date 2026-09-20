<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\StudentCreationService;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function makeMonthlyGenerationTypes(int $level): array
{
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, $level, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, $level, 52000);
    makeLevelDefault($ekskul, SchoolLevel::SMP);

    $osis = makeBillType('OSIS', auto: true, required: true);
    makeBillRate($osis, $level, 5000);
    makeLevelDefault($osis, SchoolLevel::SMP);

    return compact('spp', 'ekskul', 'osis');
}

function makeYearlyAndOneTimeTypes(int $level): array
{
    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, $level, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $kegiatan = makeBillType('Uang Kegiatan');
    makeBillRate($kegiatan, $level, 100000, ['billing_frequency' => BillFrequency::Yearly]);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, $level, 5000000, ['billing_frequency' => BillFrequency::OneTime]);

    return compact('buku', 'kegiatan', 'pangkal');
}

function makeFutureStudentEnrollment(AcademicYear $year, int $level): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

/*
|--------------------------------------------------------------------------
 | 1. Future student creation creates only initial July monthly components
|--------------------------------------------------------------------------
*/
it('future student creation creates only initial July monthly components', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);
    makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);

    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $monthlyBills = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->get();

    expect($monthlyBills)->toHaveCount(3)
        ->and($monthlyBills->pluck('paymentType.name')->sort()->values()->all())->toBe(['Ekskul', 'OSIS', 'SPP'])
        ->and($monthlyBills->pluck('period_month')->unique()->all())->toBe([7])
        ->and($monthlyBills->pluck('period_year')->unique()->all())->toBe([2027]);
});

/*
|--------------------------------------------------------------------------
| 2. Future student creation creates valid yearly bills
|--------------------------------------------------------------------------
*/
it('future student creation creates valid yearly bills', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $types = makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);

    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $yearlyBills = $student->bills()
        ->where('billing_frequency', BillFrequency::Yearly->value)
        ->get();

    expect($yearlyBills->count())->toBeGreaterThanOrEqual(2);

    $bukuBill = $yearlyBills->firstWhere('payment_type_id', $types['buku']->id);
    expect($bukuBill)->not->toBeNull()
        ->and($bukuBill->academic_year)->toBe('2027/2028')
        ->and((float) $bukuBill->amount)->toBe(500000.0);

    $kegiatanBill = $yearlyBills->firstWhere('payment_type_id', $types['kegiatan']->id);
    expect($kegiatanBill)->not->toBeNull()
        ->and((float) $kegiatanBill->amount)->toBe(100000.0);
});

/*
|--------------------------------------------------------------------------
| 3. Future student creation creates Uang Pangkal one-time
|--------------------------------------------------------------------------
*/
it('future student creation creates uang pangkal one-time', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $types = makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);

    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $pangkalBill = $student->bills()
        ->where('payment_type_id', $types['pangkal']->id)
        ->first();

    expect($pangkalBill)->not->toBeNull()
        ->and($pangkalBill->billing_frequency)->toBe(BillFrequency::OneTime->value)
        ->and($pangkalBill->academic_year)->toBe('2027/2028')
        ->and((float) $pangkalBill->amount)->toBe(5000000.0);
});

/*
|--------------------------------------------------------------------------
| 4. Future student can pay those bills early
|--------------------------------------------------------------------------
*/
it('future student can pay those bills early', function () {
    $this->travelTo('2026-12-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $types = makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $pangkalBill = $student->bills()
        ->where('payment_type_id', $types['pangkal']->id)
        ->first();

    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    $payment = Payment::create([
        'receipt_number' => 'KWT-PRE-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-12-15',
        'total_amount' => 1000000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $pangkalBill->id,
        'payment_type_id' => $types['pangkal']->id,
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
| 5. Future student has no current-year monthly bills
|--------------------------------------------------------------------------
*/
it('future student has no current year monthly bills', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $currentYearBills = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->where('period_year', 2026)
        ->count();

    expect($currentYearBills)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 6. Monthly generation preview does not mutate DB
|--------------------------------------------------------------------------
*/
it('monthly generation preview does not mutate database', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $billCountBefore = StudentBill::count();

    $service = app(BillGenerationService::class);
    $preview = $service->getMonthlyGenerationPreview($futureYear);

    $billCountAfter = StudentBill::count();

    expect($billCountAfter)->toBe($billCountBefore)
        ->and($preview['eligible_students'])->toBe(1)
        ->and($preview['will_create'])->toBeGreaterThan(0)
        ->and($preview['already_existing'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 7. Monthly generation targets selected academic year enrollments only
|--------------------------------------------------------------------------
*/
it('monthly generation targets only selected academic year enrollments', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);

    $currentStudent = makeFutureStudentEnrollment($activeYear, 7);
    $futureStudent = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);
    $result = $service->generateMonthlyForAcademicYear($futureYear);

    $futureStudentBills = $futureStudent->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->count();
    $currentStudentBills = $currentStudent->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->count();

    expect($futureStudentBills)->toBeGreaterThan(0)
        ->and($currentStudentBills)->toBe(0)
        ->and($result['created'])->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| 8. Current PaymentRate is used during generation (snapshot)
|--------------------------------------------------------------------------
*/
it('uses current payment rate during generation as snapshot', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);
    $service->generateMonthlyForAcademicYear($futureYear);

    $bill = $student->bills()
        ->where('payment_type_id', $spp->id)
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->first();

    expect($bill)->not->toBeNull()
        ->and((float) $bill->amount)->toBe(970000.0);
});

/*
|--------------------------------------------------------------------------
| 9. Old StudentBill amount remains unchanged after PaymentRate edit
|--------------------------------------------------------------------------
*/
it('old student bill amount remains unchanged after payment rate edit', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);
    $service->generateMonthlyForAcademicYear($futureYear);

    $billBefore = $student->bills()
        ->where('payment_type_id', $spp->id)
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->first();

    expect((float) $billBefore->amount)->toBe(970000.0);

    // Change master tariff
    $spp->rates()->update(['amount' => 975000, 'effective_from' => '2027-01-01']);

    $billAfter = $billBefore->fresh();

    expect((float) $billAfter->amount)->toBe(970000.0);
});

/*
|--------------------------------------------------------------------------
| 10. New StudentBill uses updated PaymentRate
|--------------------------------------------------------------------------
*/
it('new student bill uses updated payment rate', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);

    // First generation with 970000
    $service->generateMonthlyForAcademicYear($futureYear);

    $billBefore = $student->bills()
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 7)
        ->where('period_year', 2027)
        ->first();

    expect((float) $billBefore->amount)->toBe(970000.0);

    // Delete the bill so the new generation creates it fresh
    $billBefore->delete();

    // Update master tariff
    $spp->rates()->update(['amount' => 975000, 'effective_from' => '2027-01-01']);

    // Generate again — should use new rate
    $service->generateMonthlyForAcademicYear($futureYear);

    $billAfter = $student->bills()
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 7)
        ->where('period_year', 2027)
        ->first();

    expect((float) $billAfter->amount)->toBe(975000.0);
});

/*
|--------------------------------------------------------------------------
| 11. Re-running generator skips existing bills (idempotent)
|--------------------------------------------------------------------------
*/
it('re running generator skips existing bills', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);

    $result1 = $service->generateMonthlyForAcademicYear($futureYear);
    $countAfterFirst = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->count();

    $result2 = $service->generateMonthlyForAcademicYear($futureYear);
    $countAfterSecond = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->count();

    expect($countAfterSecond)->toBe($countAfterFirst)
        ->and($result2['created'])->toBe(0)
        ->and($result2['skipped'])->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| 12. Existing bill amount is not overwritten
|--------------------------------------------------------------------------
*/
it('existing bill amount is not overwritten on re generation', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);
    $service->generateMonthlyForAcademicYear($futureYear);

    $bill = $student->bills()
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 7)
        ->where('period_year', 2027)
        ->first();

    // Manually edit the bill amount
    $bill->update(['amount' => 999999]);

    // Re-run generator
    $service->generateMonthlyForAcademicYear($futureYear);

    $billRefreshed = $bill->fresh();

    expect((float) $billRefreshed->amount)->toBe(999999.0);
});

/*
|--------------------------------------------------------------------------
| 13. Existing payments remain intact after regeneration
|--------------------------------------------------------------------------
*/
it('existing payments remain intact after regeneration', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);
    $service->generateMonthlyForAcademicYear($futureYear);

    $bill = $student->bills()
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 7)
        ->where('period_year', 2027)
        ->first();

    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    $payment = Payment::create([
        'receipt_number' => 'KWT-REGEN-'.uniqid(),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-20',
        'total_amount' => 500000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $spp->id,
        'period_month' => 7,
        'period_year' => 2027,
        'amount' => 500000,
    ]);

    $service->generateMonthlyForAcademicYear($futureYear);

    $billRefreshed = $bill->fresh();

    expect((float) $billRefreshed->paid_amount)->toBe(500000.0);
});

/*
|--------------------------------------------------------------------------
| 14. Graduated student excluded from generation
|--------------------------------------------------------------------------
*/
it('graduated student is excluded from monthly generation', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    StudentAcademicEnrollment::where('student_id', $student->id)
        ->update(['status' => 'lulus']);

    $service = app(BillGenerationService::class);
    $result = $service->generateMonthlyForAcademicYear($futureYear);

    $studentBills = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->count();

    expect($studentBills)->toBe(0)
        ->and($result['created'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 15. Future student included when target year matches enrollment
|--------------------------------------------------------------------------
*/
it('future student is included when target year matches enrollment', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);
    $result = $service->generateMonthlyForAcademicYear($futureYear);

    $studentBills = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->count();

    expect($studentBills)->toBeGreaterThan(0)
        ->and($result['created'])->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| 16. One-time bill does not duplicate
|--------------------------------------------------------------------------
*/
it('one time bill does not duplicate on repeated generation', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $types = makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $service = app(BillGenerationService::class);
    $service->generateYearlyAndOneTimeOnly($student, $futureYear->start_date);
    $service->generateYearlyAndOneTimeOnly($student, $futureYear->start_date);

    $pangkalCount = $student->bills()
        ->where('payment_type_id', $types['pangkal']->id)
        ->count();

    expect($pangkalCount)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 17. Optional Jemputan/Lain-lain not auto-generated
|--------------------------------------------------------------------------
*/
it('optional jemputan and lain lain are not auto generated', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 7, 550000, ['billing_frequency' => BillFrequency::Monthly]);

    $lainlain = makeBillType('Lain-lain');
    makeBillRate($lainlain, 7, 100000, ['billing_frequency' => BillFrequency::OneTime]);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $jemputanBills = $student->bills()
        ->where('payment_type_id', $jemputan->id)
        ->count();
    $lainlainBills = $student->bills()
        ->where('payment_type_id', $lainlain->id)
        ->count();

    expect($jemputanBills)->toBe(0)
        ->and($lainlainBills)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 18. StudentDetail shows useful empty monthly state before generation
|--------------------------------------------------------------------------
*/
it('student detail shows empty monthly state for future student', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $types = makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $this->actingAs(User::factory()->create());

    $this->get(route('siswa.show', $student->id))
        ->assertOk()
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Tagihan bulanan belum digenerate untuk tahun ajaran ini');
});

/*
|--------------------------------------------------------------------------
| Bonus: Snapshot flow test (970k -> edit 975k -> old/new bill)
|--------------------------------------------------------------------------
*/
it('core snapshot rule: old bill stays 970k new bill gets 975k', function () {
    $this->travelTo('2026-08-15');

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );
    $nextYear = AcademicYear::firstOrCreate(
        ['year' => '2028/2029'],
        ['is_active' => false, 'start_date' => '2028-07-01', 'end_date' => '2029-06-30']
    );

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeFutureStudentEnrollment($futureYear, 7);

    $service = app(BillGenerationService::class);

    // Step 1: Generate 2027/2028 bills
    $service->generateMonthlyForAcademicYear($futureYear);

    $bill2027 = $student->bills()
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 7)
        ->where('period_year', 2027)
        ->first();

    expect((float) $bill2027->amount)->toBe(970000.0);

    // Step 2: Change tariff
    $spp->rates()->where('class_level', 7)->update([
        'amount' => 975000,
        'effective_from' => '2028-01-01',
    ]);

    // Old bill unchanged
    $bill2027Fresh = $bill2027->fresh();
    expect((float) $bill2027Fresh->amount)->toBe(970000.0);

    // Step 3: Create 2028/2029 enrollment and generate
    $student->update(['class_id' => SchoolClass::factory()->create(['level' => 8])->id]);
    $student->refresh();

    // For next year we need level 8 rate
    makeBillRate($spp, 8, 975000, ['effective_from' => '2028-01-01']);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $nextYear->id,
        'school_class_id' => $student->class_id,
        'status' => 'active',
    ]);

    $service->generateMonthlyForAcademicYear($nextYear);

    $bill2028 = $student->bills()
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 7)
        ->where('period_year', 2028)
        ->first();

    expect($bill2028)->not->toBeNull()
        ->and((float) $bill2028->amount)->toBe(975000.0);
});

/*
|--------------------------------------------------------------------------
| REGRESSION 1: Future student gets ZERO bills for active year (2026/2027)
|--------------------------------------------------------------------------
*/
it('future student gets zero bills for the active year 2026/2027', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);
    makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $activeYearBills = $student->bills()
        ->where('academic_year', '2026/2027')
        ->count();

    $activeYearMonthlyBills = $student->bills()
        ->where('academic_year', '2026/2027')
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->count();

    expect($activeYearBills)->toBe(0)
        ->and($activeYearMonthlyBills)->toBe(0);
});

/*
|--------------------------------------------------------------------------
 | REGRESSION 2: Future student creation — July SPP + yearly+one-time
|--------------------------------------------------------------------------
*/
it('future student creation produces July monthly components plus yearly and one time bills for entry year', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $types = makeYearlyAndOneTimeTypes(7);
    makeMonthlyGenerationTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $allBills = $student->bills()->get();

    expect($allBills->count())->toBe(6);

    $yearlyCount = $allBills->where('billing_frequency', BillFrequency::Yearly->value)->count();
    $oneTimeCount = $allBills->where('billing_frequency', BillFrequency::OneTime->value)->count();
    $monthly = $allBills->where('billing_frequency', BillFrequency::Monthly->value);

    expect($yearlyCount)->toBe(2)
        ->and($oneTimeCount)->toBe(1)
        ->and($monthly)->toHaveCount(3)
        ->and($monthly->pluck('paymentType.name')->sort()->values()->all())->toBe(['Ekskul', 'OSIS', 'SPP'])
        ->and($monthly->pluck('period_month')->unique()->all())->toBe([7])
        ->and($monthly->pluck('period_year')->unique()->all())->toBe([2027]);
});

/*
|--------------------------------------------------------------------------
| REGRESSION 3: All bills for future student use entry year academic_year
|--------------------------------------------------------------------------
*/
it('all bills for future student use entry year academic year field', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $types = makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $pangkalBill = $student->bills()
        ->where('payment_type_id', $types['pangkal']->id)
        ->first();
    expect($pangkalBill->academic_year)->toBe('2027/2028');

    $bukuBill = $student->bills()
        ->where('payment_type_id', $types['buku']->id)
        ->first();
    expect($bukuBill->academic_year)->toBe('2027/2028');

    $kegiatanBill = $student->bills()
        ->where('payment_type_id', $types['kegiatan']->id)
        ->first();
    expect($kegiatanBill->academic_year)->toBe('2027/2028');
});

/*
|--------------------------------------------------------------------------
| REGRESSION 4: Manual monthly generation for future year uses only that year
|--------------------------------------------------------------------------
*/
it('manual monthly generation for 2027/2028 creates only 2027/2028 monthly bills', function () {
    $this->travelTo('2026-08-15');

    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    makeMonthlyGenerationTypes(7);
    $types = makeYearlyAndOneTimeTypes(7);

    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = app(StudentCreationService::class)->create([
        'nis' => (string) random_int(100000000, 999999999),
        'nama_lengkap' => 'Siswa Calon',
        'nama_panggilan' => 'Calon',
        'class_id' => $class->id,
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Test No. 1',
        'entry_academic_year_id' => $futureYear->id,
    ]);

    $monthlyBefore = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->count();
    expect($monthlyBefore)->toBe(3);

    app(BillGenerationService::class)->generateMonthlyForAcademicYear($futureYear);

    $monthlyAfter = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->get();

    expect($monthlyAfter->count())->toBeGreaterThan(0);

    foreach ($monthlyAfter as $bill) {
        expect($bill->period_year)->toBeGreaterThanOrEqual(2027)
            ->and($bill->period_year)->toBeLessThanOrEqual(2028);
    }

    $currentYearMonthly = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->where('period_year', 2026)
        ->count();
    expect($currentYearMonthly)->toBe(0);
});
