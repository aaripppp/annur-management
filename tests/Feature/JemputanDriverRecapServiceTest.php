<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverRecapService;
use App\Services\PaymentDeletionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

function recapManager(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function recapAcademicYear(): AcademicYear
{
    return AcademicYear::query()->firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
}

/** @return array{Student, SchoolClass} */
function recapStudent(AcademicYear $academicYear, string $className = '8A', int $classLevel = 8): array
{
    $schoolClass = SchoolClass::query()->create(['name' => $className, 'level' => $classLevel]);
    $student = Student::factory()->create(['class_id' => $schoolClass->id]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return [$student, $schoolClass];
}

function recapPaymentType(string $name): PaymentType
{
    return PaymentType::factory()->create(['name' => $name]);
}

function recapBill(Student $student, PaymentType $type, int $month, int $year, float $amount): StudentBill
{
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
        'period_month' => $month,
        'period_year' => $year,
        'academic_year' => null,
        'billing_frequency' => BillFrequency::Monthly->value,
    ]);
}

/** @param array<int, float> $allocations */
function recapPayment(
    Student $student,
    Bank $bank,
    string $date,
    array $allocations,
    string $status = Payment::STATUS_ACTIVE,
    string $method = 'transfer',
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'JRC-'.str_pad((string) (Payment::query()->count() + 1), 5, '0', STR_PAD_LEFT),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => array_sum($allocations),
        'payment_method' => $method,
        'status' => $status,
        'created_by' => User::factory()->create()->id,
    ]);

    foreach ($allocations as $billId => $amount) {
        $bill = StudentBill::query()->findOrFail($billId);
        PaymentDetail::query()->create([
            'payment_id' => $payment->id,
            'bill_id' => $bill->id,
            'payment_type_id' => $bill->payment_type_id,
            'period_month' => $bill->period_month,
            'period_year' => $bill->period_year,
            'academic_year' => $bill->academic_year,
            'amount' => $amount,
        ]);
    }

    return $payment;
}

function recapAssign(
    JemputanDriver $driver,
    Student $student,
    AcademicYear $academicYear,
    string $start = '2026-07-01',
    ?string $end = null,
): JemputanDriverAssignment {
    return JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'effective_from' => $start,
        'effective_until' => $end,
    ]);
}

beforeEach(function () {
    $this->actingAs(recapManager());
});

it('generates the twelve July through June bill periods', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear);

    $report = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id);

    expect($report['months'])->toHaveCount(12)
        ->and($report['months'][0]['key'])->toBe('2026-07')
        ->and($report['months'][11]['key'])->toBe('2027-06')
        ->and($report['summary_basis'])->toBe('bill_period');
});

it('uses only exact monthly Jemputan bills and only their mixed-payment allocation', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear);
    $jemputan = recapPaymentType('Jemputan');
    $admJemputan = recapPaymentType('Adm Jemputan');
    $spp = recapPaymentType('SPP');
    $jemputanBill = recapBill($student, $jemputan, 7, 2026, 500000);
    $admBill = recapBill($student, $admJemputan, 7, 2026, 50000);
    $sppBill = recapBill($student, $spp, 7, 2026, 900000);
    $bank = Bank::factory()->create();
    recapPayment($student, $bank, '2026-07-10', [
        $jemputanBill->id => 500000,
        $admBill->id => 50000,
        $sppBill->id => 900000,
    ]);

    $july = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id)['rows'][0]['months']['2026-07'];

    expect($july['bill_ids'])->toBe([$jemputanBill->id])
        ->and($july['effective_billed_amount'])->toBe(500000.0)
        ->and($july['paid_amount'])->toBe(500000.0)
        ->and($july['status'])->toBe(JemputanDriverRecapService::STATUS_PAID);
});

it('returns partial and multiple-payment totals with deterministic latest metadata', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear);
    $bill = recapBill($student, recapPaymentType('Jemputan'), 8, 2026, 500000);
    $firstBank = Bank::factory()->create(['name' => 'Bank Awal', 'account_number' => '111111']);
    $latestBank = Bank::factory()->create(['name' => 'Bank Terbaru', 'account_number' => '222222']);
    $firstPayment = recapPayment($student, $firstBank, '2026-08-15', [$bill->id => 100000]);
    $latestPayment = recapPayment($student, $latestBank, '2026-08-15', [$bill->id => 200000]);

    $august = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id)['rows'][0]['months']['2026-08'];

    expect($august['paid_amount'])->toBe(300000.0)
        ->and($august['remaining_amount'])->toBe(200000.0)
        ->and($august['status'])->toBe(JemputanDriverRecapService::STATUS_PARTIAL)
        ->and($august['payment_date'])->toBe('2026-08-15')
        ->and($august['latest_payment_id'])->toBe($latestPayment->id)
        ->and($august['latest_payment_id'])->toBeGreaterThan($firstPayment->id)
        ->and($august['bank_name'])->toBe('Bank Terbaru')
        ->and($august['bank_account_number'])->toBe('222222')
        ->and($august['bank_display'])->toBe('Bank Terbaru · 222222')
        ->and($august['payment_method'])->toBe('transfer')
        ->and($august['contributing_payment_count'])->toBe(2);
});

it('excludes cancelled payments and null bill details', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear);
    $type = recapPaymentType('Jemputan');
    $bill = recapBill($student, $type, 9, 2026, 400000);
    $bank = Bank::factory()->create();
    recapPayment($student, $bank, '2026-09-05', [$bill->id => 400000], Payment::STATUS_CANCELLED);
    $unlinkedPayment = Payment::query()->create([
        'receipt_number' => 'JRC-NULL',
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-06',
        'total_amount' => 999000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => auth()->id(),
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $unlinkedPayment->id,
        'bill_id' => null,
        'payment_type_id' => $type->id,
        'period_month' => 9,
        'period_year' => 2026,
        'amount' => 999000,
    ]);

    $september = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id)['rows'][0]['months']['2026-09'];

    expect($september['paid_amount'])->toBe(0.0)
        ->and($september['status'])->toBe(JemputanDriverRecapService::STATUS_UNPAID)
        ->and($september['payment_date'])->toBeNull()
        ->and($september['bank_name'])->toBeNull()
        ->and($september['bank_account_number'])->toBeNull()
        ->and($september['bank_display'])->toBeNull()
        ->and($september['contributing_payment_count'])->toBe(0);
});

it('reflects live payment correction and deletion state', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear);
    $bill = recapBill($student, recapPaymentType('Jemputan'), 10, 2026, 500000);
    $bank = Bank::factory()->create();
    $payment = recapPayment($student, $bank, '2026-10-01', [$bill->id => 100000]);
    $service = app(JemputanDriverRecapService::class);

    expect($service->generate($academicYear->id, $driver->id)['rows'][0]['months']['2026-10']['paid_amount'])->toBe(100000.0);

    $payment->details()->firstOrFail()->update(['amount' => 350000]);
    $payment->update(['total_amount' => 350000, 'payment_date' => '2026-10-20']);
    $corrected = $service->generate($academicYear->id, $driver->id)['rows'][0]['months']['2026-10'];

    expect($corrected['paid_amount'])->toBe(350000.0)
        ->and($corrected['payment_date'])->toBe('2026-10-20');

    app(PaymentDeletionService::class)->delete($payment->id);
    $deleted = $service->generate($academicYear->id, $driver->id)['rows'][0]['months']['2026-10'];

    expect($deleted['paid_amount'])->toBe(0.0)
        ->and($deleted['status'])->toBe(JemputanDriverRecapService::STATUS_UNPAID);
});

it('applies bill adjustments and preserves different persisted tariffs per student', function () {
    $academicYear = recapAcademicYear();
    [$firstStudent] = recapStudent($academicYear, '8A');
    [$secondStudent] = recapStudent($academicYear, '8B');
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $firstStudent, $academicYear);
    recapAssign($driver, $secondStudent, $academicYear);
    $type = recapPaymentType('Jemputan');
    $firstBill = recapBill($firstStudent, $type, 11, 2026, 500000);
    $secondBill = recapBill($secondStudent, $type, 11, 2026, 700000);
    BillAdjustment::factory()->create(['bill_id' => $firstBill->id, 'type' => BillAdjustment::TYPE_DISCOUNT, 'amount' => -100000]);
    $bank = Bank::factory()->create();
    recapPayment($firstStudent, $bank, '2026-11-02', [$firstBill->id => 400000]);
    recapPayment($secondStudent, $bank, '2026-11-03', [$secondBill->id => 200000]);

    $report = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id);
    $rows = collect($report['rows'])->keyBy('student_id');
    $first = $rows[$firstStudent->id]['months']['2026-11'];
    $second = $rows[$secondStudent->id]['months']['2026-11'];

    expect($first['bill_amount'])->toBe(500000.0)
        ->and($first['adjustment_total'])->toBe(-100000.0)
        ->and($first['effective_billed_amount'])->toBe(400000.0)
        ->and($second['effective_billed_amount'])->toBe(700000.0)
        ->and($report['summary']['overall']['effective_billed_amount'])->toBe(1100000.0)
        ->and($report['summary']['overall']['paid_amount'])->toBe(600000.0)
        ->and($report['summary']['overall']['remaining_amount'])->toBe(500000.0)
        ->and($report['summary']['overall']['paid_count'])->toBe(1)
        ->and($report['summary']['overall']['partial_count'])->toBe(1);
});

it('preserves raw over-allocation for anomaly detection', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear);
    $bill = recapBill($student, recapPaymentType('Jemputan'), 12, 2026, 500000);
    recapPayment($student, Bank::factory()->create(), '2026-12-10', [$bill->id => 600000]);

    $december = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id)['rows'][0]['months']['2026-12'];

    expect($december['paid_amount'])->toBe(600000.0)
        ->and($december['remaining_amount'])->toBe(0.0)
        ->and($december['status'])->toBe(JemputanDriverRecapService::STATUS_PAID)
        ->and($december['is_overallocated'])->toBeTrue();
});

it('shows all academic-year months for a moved current member regardless of legacy dates', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $oldDriver = JemputanDriver::factory()->create(['name' => 'Sopir Lama']);
    $newDriver = JemputanDriver::factory()->create(['name' => 'Sopir Baru']);
    recapAssign($oldDriver, $student, $academicYear, '2026-07-01', '2026-09-30');
    recapAssign($newDriver, $student, $academicYear, '2026-10-01');
    $type = recapPaymentType('Jemputan');
    $septemberBill = recapBill($student, $type, 9, 2026, 500000);
    $octoberBill = recapBill($student, $type, 10, 2026, 600000);
    $bank = Bank::factory()->create();
    recapPayment($student, $bank, '2026-10-05', [$septemberBill->id => 500000, $octoberBill->id => 600000]);
    $service = app(JemputanDriverRecapService::class);

    $oldReport = $service->generate($academicYear->id, $oldDriver->id);
    $newRow = $service->generate($academicYear->id, $newDriver->id)['rows'][0];

    expect($oldReport['rows'])->toBe([])
        ->and($newRow['months']['2026-09']['paid_amount'])->toBe(500000.0)
        ->and($newRow['months']['2026-10']['paid_amount'])->toBe(600000.0);
});

it('shows July through June payment values regardless of the current legacy start date', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear, '2027-06-01');
    $type = recapPaymentType('Jemputan');
    $julyBill = recapBill($student, $type, 7, 2026, 410000);
    $juneBill = recapBill($student, $type, 6, 2027, 730000);
    $julyBank = Bank::factory()->create(['name' => 'Bank Juli', 'account_number' => '1234567890']);
    $juneBank = Bank::factory()->create(['name' => 'Bank Juni', 'account_number' => null]);
    recapPayment($student, $julyBank, '2026-07-15', [$julyBill->id => 410000]);
    recapPayment($student, $juneBank, '2027-06-20', [$juneBill->id => 730000]);

    $row = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id)['rows'][0];

    expect($row['months'])->toHaveCount(12)
        ->and($row['months']['2026-07']['paid_amount'])->toBe(410000.0)
        ->and($row['months']['2026-07']['payment_date'])->toBe('2026-07-15')
        ->and($row['months']['2026-07']['bank_name'])->toBe('Bank Juli')
        ->and($row['months']['2026-07']['bank_display'])->toBe('Bank Juli · 1234567890')
        ->and($row['months']['2027-06']['paid_amount'])->toBe(730000.0)
        ->and($row['months']['2027-06']['payment_date'])->toBe('2027-06-20')
        ->and($row['months']['2027-06']['bank_name'])->toBe('Bank Juni')
        ->and($row['months']['2027-06']['bank_account_number'])->toBeNull()
        ->and($row['months']['2027-06']['bank_display'])->toBe('Bank Juni');
});

it('includes assignments without bills and counts unassigned Jemputan bill exceptions', function () {
    $academicYear = recapAcademicYear();
    [$assignedStudent] = recapStudent($academicYear, '8A');
    [$unassignedStudent] = recapStudent($academicYear, '8B');
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $assignedStudent, $academicYear);
    recapBill($unassignedStudent, recapPaymentType('Jemputan'), 7, 2026, 450000);

    $report = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id);

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0]['months']['2026-07']['status'])->toBe(JemputanDriverRecapService::STATUS_NO_BILL)
        ->and($report['unassigned_bill_exception_count'])->toBe(1);
});

it('uses historical enrollment class and supports school level and class filters', function () {
    $academicYear = recapAcademicYear();
    [$student, $historicalClass] = recapStudent($academicYear, '8 Historis', 8);
    $currentClass = SchoolClass::factory()->create(['name' => '11 Sekarang', 'level' => 11]);
    $student->update(['class_id' => $currentClass->id]);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear);
    $service = app(JemputanDriverRecapService::class);

    $matching = $service->generate($academicYear->id, $driver->id, SchoolLevel::SMP, $historicalClass->id);
    $wrongLevel = $service->generate($academicYear->id, $driver->id, SchoolLevel::SMA);

    expect($matching['rows'][0]['historical_class'])->toBe('8 Historis')
        ->and($matching['rows'][0]['historical_class_id'])->toBe($historicalClass->id)
        ->and($wrongLevel['rows'])->toBe([]);
});

it('performs no writes while generating the recap', function () {
    $academicYear = recapAcademicYear();
    [$student] = recapStudent($academicYear);
    $driver = JemputanDriver::factory()->create();
    recapAssign($driver, $student, $academicYear);
    recapBill($student, recapPaymentType('Jemputan'), 7, 2026, 500000);

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id);
    $writes = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query) === 1
    );
    DB::disableQueryLog();

    expect($writes)->toBeEmpty();
});

it('keeps SELECT queries constant as the student chunk grows', function (int $studentCount) {
    $academicYear = recapAcademicYear();
    $driver = JemputanDriver::factory()->create();
    $type = recapPaymentType('Jemputan');

    foreach (range(1, $studentCount) as $index) {
        [$student] = recapStudent($academicYear, 'Kelas '.$index);
        recapAssign($driver, $student, $academicYear);
        recapBill($student, $type, 7, 2026, 400000 + $index);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $report = app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id, perPage: 100);
    $selectCount = collect(DB::getQueryLog())->pluck('query')->filter(
        fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
    )->count();
    DB::disableQueryLog();

    expect($report['rows'])->toHaveCount($studentCount)
        ->and($selectCount)->toBe(9);
})->with([1, 10, 25, 50]);

it('allows active Admin and Superadmin users to generate a recap', function (string $role) {
    auth()->logout();
    $this->actingAs(recapManager($role));
    $academicYear = recapAcademicYear();
    $driver = JemputanDriver::factory()->create();

    expect(app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id)['driver']['id'])->toBe($driver->id);
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('denies recap generation to guests TU Unit and inactive administrators', function (?string $role, bool $active) {
    auth()->logout();

    if ($role !== null) {
        $this->actingAs(recapManager($role, $active));
    }

    $academicYear = recapAcademicYear();
    $driver = JemputanDriver::factory()->create();

    expect(fn () => app(JemputanDriverRecapService::class)->generate($academicYear->id, $driver->id))
        ->toThrow(AuthorizationException::class);
})->with([
    'guest' => [null, true],
    'TU Unit' => [User::ROLE_TU_UNIT, true],
    'inactive Admin' => [User::ROLE_ADMIN, false],
]);
