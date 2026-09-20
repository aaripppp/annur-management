<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use App\Services\BillGenerationService;
use Carbon\Carbon;
use Livewire\Livewire;

function oneTimeStudent(int $level): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);

    return Student::factory()->create(['class_id' => $class->id]);
}

function setupOneTimePangkal(int $level = 8): PaymentType
{
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, $level, 5000000, [
        'billing_frequency' => BillFrequency::OneTime,
    ]);

    return $pangkal;
}

function otPayBill(StudentBill $bill, int $amount): Payment
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-OT-'.uniqid(),
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-15',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);

    return $payment;
}

function promoteStudent(Student $student, AcademicYear $fromYear, AcademicYear $toYear): void
{
    $nextLevel = $student->schoolClass->level + 1;
    $nextClass = SchoolClass::factory()->create(['level' => $nextLevel]);

    $student->update(['class_id' => $nextClass->id]);

    StudentAcademicEnrollment::updateOrCreate(
        ['student_id' => $student->id, 'academic_year_id' => $toYear->id],
        ['school_class_id' => $nextClass->id, 'status' => 'active']
    );
}

/*
|--------------------------------------------------------------------------
| CASE A: Unpaid one-time survives promotion without duplicate
|--------------------------------------------------------------------------
*/
it('unpaid one-time bill survives promotion without duplicate', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(7);
    $student = oneTimeStudent(7);
    makeActiveSetting($student, $pangkal);

    $fromYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $toYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $service = app(BillGenerationService::class);
    $created = $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $billId = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->value('id');

    expect($billId)->not->toBeNull();

    promoteStudent($student, $fromYear, $toYear);
    $service->generateBillbook($student, Carbon::parse('2027-09-15'));

    $count = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();

    expect($count)->toBe(1)
        ->and(StudentBill::find($billId)->id)->toBe($billId);
});

/*
|--------------------------------------------------------------------------
| CASE B: Partial one-time survives promotion without duplicate
|--------------------------------------------------------------------------
*/
it('partial one-time bill survives promotion without duplicate', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(7);
    $student = oneTimeStudent(7);
    makeActiveSetting($student, $pangkal);

    $fromYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $toYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    otPayBill($bill, 3000000);

    expect($bill->refresh()->paid_amount)->toBe(3000000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_PARTIAL);

    promoteStudent($student, $fromYear, $toYear);
    $service->generateBillbook($student, Carbon::parse('2027-09-15'));

    $count = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();

    expect($count)->toBe(1)
        ->and($bill->refresh()->paid_amount)->toBe(3000000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_PARTIAL);
});

/*
|--------------------------------------------------------------------------
| CASE C: Fully paid one-time survives promotion without duplicate
|--------------------------------------------------------------------------
*/
it('fully paid one-time bill survives promotion without duplicate', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(7);
    $student = oneTimeStudent(7);
    makeActiveSetting($student, $pangkal);

    $fromYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $toYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    $billId = $bill->id;

    otPayBill($bill, 5000000);

    expect($bill->refresh()->status)->toBe(StudentBill::STATUS_PAID);

    promoteStudent($student, $fromYear, $toYear);
    $service->generateBillbook($student, Carbon::parse('2027-09-15'));

    $count = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();

    expect($count)->toBe(1)
        ->and(StudentBill::find($billId))->not->toBeNull()
        ->and(StudentBill::find($billId)->status)->toBe(StudentBill::STATUS_PAID)
        ->and(StudentBill::find($billId)->id)->toBe($billId);
});

/*
|--------------------------------------------------------------------------
| CASE D: generateBillbook twice does not duplicate one-time
|--------------------------------------------------------------------------
*/
it('generateBillbook twice does not duplicate one-time', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(8);
    $student = oneTimeStudent(8);
    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $firstBill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    $firstBillId = $firstBill->id;

    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $count = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();

    expect($count)->toBe(1)
        ->and(StudentBill::find($firstBillId))->not->toBeNull()
        ->and(StudentBill::find($firstBillId)->id)->toBe($firstBillId);
});

/*
|--------------------------------------------------------------------------
| CASE E: Multiple academic years do not duplicate one-time
|--------------------------------------------------------------------------
*/
it('multiple academic years do not duplicate one-time', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(7);
    $student = oneTimeStudent(7);
    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $firstBill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    $firstBillId = $firstBill->id;

    $this->travelTo('2027-09-01');
    $service->generateBillbook($student, Carbon::parse('2027-09-15'));

    $countAfterYear2 = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();

    expect($countAfterYear2)->toBe(1)
        ->and(StudentBill::find($firstBillId)->id)->toBe($firstBillId);

    $this->travelTo('2028-09-01');
    $service->generateBillbook($student, Carbon::parse('2028-09-15'));

    $countAfterYear3 = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();

    expect($countAfterYear3)->toBe(1)
        ->and(StudentBill::find($firstBillId)->id)->toBe($firstBillId);
});

/*
|--------------------------------------------------------------------------
| CASE F: Different one-time payment types can each exist once
|--------------------------------------------------------------------------
*/
it('different one-time payment types can each exist once', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(8);

    $pendaftaran = makeBillType('Biaya Pendaftaran', auto: true, required: true);
    makeBillRate($pendaftaran, 8, 1000000, [
        'billing_frequency' => BillFrequency::OneTime,
    ]);
    makeLevelDefault($pendaftaran, SchoolLevel::SMP);

    $student = oneTimeStudent(8);
    StudentPaymentSetting::firstOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $pangkal->id],
        ['is_active' => true, 'started_at' => '2026-01-01']
    );
    StudentPaymentSetting::firstOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $pendaftaran->id],
        ['is_active' => true, 'started_at' => '2026-01-01']
    );

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $pangkalCount = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();

    $pendaftaranCount = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pendaftaran->id)
        ->count();

    expect($pangkalCount)->toBe(1)
        ->and($pendaftaranCount)->toBe(1);

    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count())->toBe(1)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $pendaftaran->id)
            ->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| CASE G: Fully paid one-time survives generateBillbook twice
|--------------------------------------------------------------------------
*/
it('fully paid one-time bill survives generateBillbook twice without duplicate', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(8);
    $student = oneTimeStudent(8);
    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    $billId = $bill->id;

    otPayBill($bill, 5000000);
    expect($bill->refresh()->status)->toBe(StudentBill::STATUS_PAID);

    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $count = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count();

    expect($count)->toBe(1)
        ->and(StudentBill::find($billId)->status)->toBe(StudentBill::STATUS_PAID);
});

/*
|--------------------------------------------------------------------------
| MANUAL ADD: Duplicate blocked even after full payment
|--------------------------------------------------------------------------
*/
it('manual add blocks one-time duplicate after fully paid', function () {
    $this->travelTo('2026-08-15');

    $student = oneTimeStudent(8);
    $catalog = manualAddCatalog(8);

    $pangkal = $catalog['Uang Pangkal'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $pangkal->id)
        ->set('addAmount', '5000000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    expect($bill)->not->toBeNull();

    otPayBill($bill, 5000000);
    expect($bill->refresh()->status)->toBe(StudentBill::STATUS_PAID);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $pangkal->id)
        ->set('addAmount', '5000000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| MANUAL ADD: Duplicate blocked with partially paid one-time
|--------------------------------------------------------------------------
*/
it('manual add blocks one-time duplicate with partially paid bill', function () {
    $this->travelTo('2026-08-15');

    $student = oneTimeStudent(8);
    $catalog = manualAddCatalog(8);

    $pangkal = $catalog['Uang Pangkal'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $pangkal->id)
        ->set('addAmount', '5000000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    otPayBill($bill, 2000000);
    expect($bill->refresh()->status)->toBe(StudentBill::STATUS_PARTIAL);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $pangkal->id)
        ->set('addAmount', '5000000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| MANUAL ADD: Duplicate blocked with unpaid one-time
|--------------------------------------------------------------------------
*/
it('manual add blocks one-time duplicate with unpaid bill', function () {
    $this->travelTo('2026-08-15');

    $student = oneTimeStudent(8);
    $catalog = manualAddCatalog(8);

    $pangkal = $catalog['Uang Pangkal'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $pangkal->id)
        ->set('addAmount', '5000000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $pangkal->id)
        ->set('addAmount', '5000000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| PAYMENT SAFETY: Old payments remain intact after bill stays single
|--------------------------------------------------------------------------
*/
it('existing payments remain intact when one-time bill survives dedup', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(8);
    $student = oneTimeStudent(8);
    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    $billId = $bill->id;
    $originalAmount = (float) $bill->amount;

    $payment = otPayBill($bill, 3000000);

    $detailId = PaymentDetail::where('bill_id', $bill->id)->first()->id;

    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::find($billId))->not->toBeNull()
        ->and((float) StudentBill::find($billId)->amount)->toBe($originalAmount)
        ->and(Payment::find($payment->id))->not->toBeNull()
        ->and(Payment::find($payment->id)->status)->toBe(Payment::STATUS_ACTIVE)
        ->and((float) Payment::find($payment->id)->total_amount)->toBe(3000000.0)
        ->and(PaymentDetail::find($detailId))->not->toBeNull()
        ->and((float) PaymentDetail::find($detailId)->amount)->toBe(3000000.0);
});

/*
|--------------------------------------------------------------------------
| generateForStudent: Fully paid one-time is not re-created
|--------------------------------------------------------------------------
*/
it('generateForStudent does not recreate fully paid one-time bill', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(8);
    $student = oneTimeStudent(8);
    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateForStudent($student, Carbon::parse('2026-09-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    $billId = $bill->id;

    otPayBill($bill, 5000000);
    expect($bill->refresh()->status)->toBe(StudentBill::STATUS_PAID);

    $created = $service->generateForStudent($student, Carbon::parse('2026-09-15'));

    expect($created)->toHaveCount(0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $pangkal->id)
            ->count())->toBe(1)
        ->and(StudentBill::find($billId)->status)->toBe(StudentBill::STATUS_PAID);
});

/*
|--------------------------------------------------------------------------
| generateUntil: Fully paid one-time is not re-created
|--------------------------------------------------------------------------
*/
it('generateUntil does not recreate fully paid one-time bill', function () {
    $this->travelTo('2026-08-15');

    $pangkal = setupOneTimePangkal(8);
    $student = oneTimeStudent(8);
    makeActiveSetting($student, $pangkal);

    $service = app(BillGenerationService::class);
    $service->generateUntil($student, Carbon::parse('2026-10-01'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $pangkal->id)
        ->first();

    $billId = $bill->id;

    otPayBill($bill, 5000000);
    expect($bill->refresh()->status)->toBe(StudentBill::STATUS_PAID);

    $this->travelTo('2026-11-01');
    $created = $service->generateUntil($student, Carbon::parse('2027-01-01'));

    $oneTimeCreated = array_filter($created, fn ($b) => $b->payment_type_id === $pangkal->id);

    expect($oneTimeCreated)->toHaveCount(0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $pangkal->id)
            ->count())->toBe(1)
        ->and(StudentBill::find($billId)->status)->toBe(StudentBill::STATUS_PAID);
});
