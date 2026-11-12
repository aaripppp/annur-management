<?php

use App\Enums\BillFrequency;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverAllocationService;
use App\Services\JemputanDriverSalaryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

function jemputanSalaryManager(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function jemputanSalaryYear(
    string $year = '2026/2027',
    string $startDate = '2026-07-01',
    string $endDate = '2027-06-30',
    bool $active = true,
): AcademicYear {
    return AcademicYear::query()->firstOrCreate(
        ['year' => $year],
        ['is_active' => $active, 'start_date' => $startDate, 'end_date' => $endDate],
    );
}

/** @return array{Student, SchoolClass} */
function jemputanSalaryStudent(
    AcademicYear $academicYear,
    string $name,
    string $className = 'VIII A',
    int $level = 8,
): array {
    $schoolClass = SchoolClass::query()->firstOrCreate(['name' => $className], ['level' => $level]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $schoolClass->id]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return [$student, $schoolClass];
}

function jemputanSalaryAssign(
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

function jemputanSalaryPaymentType(string $name = 'Jemputan'): PaymentType
{
    return PaymentType::factory()->create(['name' => $name]);
}

function jemputanSalaryBill(
    Student $student,
    PaymentType $type,
    int $month,
    int $year,
    float $amount,
): StudentBill {
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

function jemputanSalaryAllocation(
    Student $student,
    AcademicYear $academicYear,
    JemputanDriver $driver,
    int $month,
    int $year,
    float $amount,
): JemputanDriverSalaryAllocation {
    return JemputanDriverSalaryAllocation::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'period_year' => $year,
        'period_month' => $month,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => $amount,
    ]);
}

/** @return array{Student, JemputanDriver, JemputanDriver} */
function jemputanSalaryMultiDriverStudent(
    AcademicYear $academicYear,
    string $name,
    string $className = 'VIII Multi Alokasi',
): array {
    [$student] = jemputanSalaryStudent($academicYear, $name, $className);
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();

    jemputanSalaryAssign($firstDriver, $student, $academicYear);
    jemputanSalaryAssign($secondDriver, $student, $academicYear);

    return [$student, $firstDriver, $secondDriver];
}

function jemputanSalaryPayment(
    Student $student,
    StudentBill $bill,
    Bank $bank,
    string $date,
    float $amount,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'SAL-'.str_pad((string) (Payment::query()->count() + 1), 5, '0', STR_PAD_LEFT),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => auth()->id(),
    ]);

    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'amount' => $amount,
    ]);

    return $payment;
}

/** @return array<string, mixed> */
function jemputanSalaryReport(
    AcademicYear $academicYear,
    JemputanDriver $driver,
    ?string $month = null,
    int $perPage = 25,
    int $page = 1,
): array {
    return app(JemputanDriverSalaryService::class)->generate(
        $academicYear->id,
        $driver->id,
        $month,
        $perPage,
        $page,
    );
}

beforeEach(function () {
    $this->actingAs(jemputanSalaryManager());
});

it('counts paid partial and unpaid Jemputan bills at their full effective amount', function () {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create();
    $jemputan = jemputanSalaryPaymentType();
    $spp = jemputanSalaryPaymentType('SPP');
    $bank = Bank::factory()->create();

    [$paidStudent] = jemputanSalaryStudent($year, 'Siswa Lunas', 'VIII Lunas');
    [$partialStudent] = jemputanSalaryStudent($year, 'Siswa Sebagian', 'VIII Sebagian');
    [$unpaidStudent] = jemputanSalaryStudent($year, 'Siswa Belum', 'VIII Belum');

    foreach ([$paidStudent, $partialStudent, $unpaidStudent] as $student) {
        jemputanSalaryAssign($driver, $student, $year);
    }

    $paidBill = jemputanSalaryBill($paidStudent, $jemputan, 7, 2026, 500000);
    $partialBill = jemputanSalaryBill($partialStudent, $jemputan, 7, 2026, 400000);
    $unpaidBill = jemputanSalaryBill($unpaidStudent, $jemputan, 7, 2026, 300000);
    jemputanSalaryBill($paidStudent, $spp, 7, 2026, 900000);

    jemputanSalaryPayment($paidStudent, $paidBill, $bank, '2026-07-10', 500000);
    jemputanSalaryPayment($partialStudent, $partialBill, $bank, '2026-07-11', 150000);

    $report = jemputanSalaryReport($year, $driver, '2026-07');

    $rows = collect($report['rows'])->keyBy('student_id');

    expect($report['summary']['revenue'])->toBe(1200000)
        ->and($rows[$paidStudent->id]['bill_amount'])->toBe(500000.0)
        ->and($rows[$paidStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_PAID)
        ->and($rows[$paidStudent->id]['payment_status_label'])->toBe('Lunas')
        ->and($rows[$partialStudent->id]['bill_amount'])->toBe(400000.0)
        ->and($rows[$partialStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_PARTIAL)
        ->and($rows[$partialStudent->id]['payment_status_label'])->toBe('Sebagian')
        ->and($rows[$unpaidStudent->id]['bill_amount'])->toBe(300000.0)
        ->and($rows[$unpaidStudent->id]['has_bill'])->toBeTrue()
        ->and($rows[$unpaidStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_UNPAID)
        ->and($rows[$unpaidStudent->id]['payment_status_label'])->toBe('Belum Lunas')
        ->and($unpaidBill->fresh()?->amount)->toBe('300000.00');
});

it('applies bill adjustments and clamps each bill at zero', function () {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create();
    $jemputan = jemputanSalaryPaymentType();

    [$adjustedStudent] = jemputanSalaryStudent($year, 'Siswa Penyesuaian', 'IX Penyesuaian');
    [$clampedStudent] = jemputanSalaryStudent($year, 'Siswa Nol', 'IX Nol');
    [$addedStudent] = jemputanSalaryStudent($year, 'Siswa Tambahan', 'IX Tambahan');

    foreach ([$adjustedStudent, $clampedStudent, $addedStudent] as $student) {
        jemputanSalaryAssign($driver, $student, $year);
    }

    $adjustedBill = jemputanSalaryBill($adjustedStudent, $jemputan, 8, 2026, 500000);
    $clampedBill = jemputanSalaryBill($clampedStudent, $jemputan, 8, 2026, 100000);
    $addedBill = jemputanSalaryBill($addedStudent, $jemputan, 8, 2026, 200000);

    BillAdjustment::factory()->create(['bill_id' => $adjustedBill->id, 'type' => BillAdjustment::TYPE_DISCOUNT, 'amount' => -100000]);
    BillAdjustment::factory()->create(['bill_id' => $adjustedBill->id, 'type' => BillAdjustment::TYPE_DISCOUNT, 'amount' => -50000]);
    BillAdjustment::factory()->create(['bill_id' => $clampedBill->id, 'type' => BillAdjustment::TYPE_DISCOUNT, 'amount' => -150000]);
    BillAdjustment::factory()->create(['bill_id' => $addedBill->id, 'type' => BillAdjustment::TYPE_DISCOUNT, 'amount' => 50000]);
    jemputanSalaryPayment($adjustedStudent, $adjustedBill, Bank::factory()->create(), '2026-08-10', 350000);

    $report = jemputanSalaryReport($year, $driver, '2026-08');
    $rows = collect($report['rows'])->keyBy('student_id');

    expect($rows[$adjustedStudent->id]['bill_amount'])->toBe(350000.0)
        ->and($rows[$adjustedStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_PAID)
        ->and($rows[$clampedStudent->id]['bill_amount'])->toBe(0.0)
        ->and($rows[$clampedStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_PAID)
        ->and($rows[$addedStudent->id]['bill_amount'])->toBe(250000.0)
        ->and($report['summary']['revenue'])->toBe(600000);
});

it('lists a student without a bill at zero rupiah', function () {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create();
    [$student] = jemputanSalaryStudent($year, 'Siswa Tanpa Tagihan', 'VII Tanpa Tagihan');
    [$zeroBillStudent] = jemputanSalaryStudent($year, 'Siswa Tagihan Nol', 'VII Tagihan Nol');
    jemputanSalaryAssign($driver, $student, $year);
    jemputanSalaryAssign($driver, $zeroBillStudent, $year);
    jemputanSalaryBill($zeroBillStudent, jemputanSalaryPaymentType(), 9, 2026, 0);

    $report = jemputanSalaryReport($year, $driver, '2026-09');
    $rows = collect($report['rows'])->keyBy('student_id');

    expect($report['rows'])->toHaveCount(2)
        ->and($rows[$student->id]['bill_amount'])->toBe(0.0)
        ->and($rows[$student->id]['bill_count'])->toBe(0)
        ->and($rows[$student->id]['has_bill'])->toBeFalse()
        ->and($rows[$student->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_NO_BILL)
        ->and($rows[$student->id]['payment_status_label'])->toBe('Belum Ada Tagihan')
        ->and($rows[$zeroBillStudent->id]['bill_count'])->toBe(1)
        ->and($rows[$zeroBillStudent->id]['has_bill'])->toBeTrue()
        ->and($rows[$zeroBillStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_PAID)
        ->and($rows[$zeroBillStudent->id]['payment_status_label'])->toBe('Lunas')
        ->and($report['summary']['revenue'])->toBe(0);
});

it('does not mark a multi-driver student without a bill as missing allocation', function () {
    $year = jemputanSalaryYear();
    [$student, $firstDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Multi Tanpa Tagihan');

    $report = jemputanSalaryReport($year, $firstDriver, '2026-07');
    $row = $report['rows'][0];

    expect($row['has_bill'])->toBeFalse()
        ->and($row['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_NO_BILL)
        ->and($row['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_NOT_REQUIRED)
        ->and($row['salary_amount'])->toBe(0.0)
        ->and($report['summary']['unallocated_multi_driver_count'])->toBe(0)
        ->and($report['summary']['has_allocation_warning'])->toBeFalse();
});

it('ignores cancelled payments and reflects corrected payment detail amounts', function () {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create();
    $type = jemputanSalaryPaymentType();
    $bank = Bank::factory()->create();
    [$cancelledStudent] = jemputanSalaryStudent($year, 'Siswa Pembayaran Batal');
    [$correctedStudent] = jemputanSalaryStudent($year, 'Siswa Pembayaran Koreksi');
    jemputanSalaryAssign($driver, $cancelledStudent, $year);
    jemputanSalaryAssign($driver, $correctedStudent, $year);
    $cancelledBill = jemputanSalaryBill($cancelledStudent, $type, 7, 2026, 500000);
    $correctedBill = jemputanSalaryBill($correctedStudent, $type, 7, 2026, 500000);
    $cancelledPayment = jemputanSalaryPayment($cancelledStudent, $cancelledBill, $bank, '2026-07-10', 500000);
    $correctedPayment = jemputanSalaryPayment($correctedStudent, $correctedBill, $bank, '2026-07-11', 100000);
    $cancelledPayment->update(['status' => Payment::STATUS_CANCELLED]);

    $partialReport = jemputanSalaryReport($year, $driver, '2026-07');
    $partialRows = collect($partialReport['rows'])->keyBy('student_id');

    expect($partialRows[$cancelledStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_UNPAID)
        ->and($partialRows[$correctedStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_PARTIAL)
        ->and($partialReport['summary']['revenue'])->toBe(1000000);

    PaymentDetail::query()->where('payment_id', $correctedPayment->id)->update(['amount' => 500000]);
    $correctedReport = jemputanSalaryReport($year, $driver, '2026-07');
    $correctedRows = collect($correctedReport['rows'])->keyBy('student_id');

    expect($correctedRows[$correctedStudent->id]['payment_status'])->toBe(JemputanDriverSalaryService::PAYMENT_STATUS_PAID)
        ->and($correctedRows[$correctedStudent->id]['paid_amount'])->toBe(500000.0)
        ->and($correctedReport['summary']['revenue'])->toBe(1000000);
});

it('applies the configured percentage consistently', function (float $percentage, int $deduction, int $netSalary) {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => $percentage]);
    $jemputan = jemputanSalaryPaymentType();
    [$student] = jemputanSalaryStudent($year, 'Siswa Persentase', 'VII Persentase');
    jemputanSalaryAssign($driver, $student, $year);
    jemputanSalaryBill($student, $jemputan, 10, 2026, 1000000);

    $summary = jemputanSalaryReport($year, $driver, '2026-10')['summary'];

    expect($summary['revenue'])->toBe(1000000)
        ->and($summary['percentage'])->toBe($percentage)
        ->and($summary['deduction'])->toBe($deduction)
        ->and($summary['net_salary'])->toBe($netSalary)
        ->and($summary['percentage_configured'])->toBeTrue();
})->with([
    'nol persen' => [0.0, 0, 1000000],
    'sepuluh persen' => [10.0, 100000, 900000],
    'lima belas persen' => [15.0, 150000, 850000],
    'seratus persen' => [100.0, 1000000, 0],
]);

it('keeps a null percentage separate from zero percent', function () {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create(['foundation_deduction_percentage' => null]);
    $jemputan = jemputanSalaryPaymentType();
    [$student] = jemputanSalaryStudent($year, 'Siswa Persentase Kosong', 'VII Kosong');
    jemputanSalaryAssign($driver, $student, $year);
    jemputanSalaryBill($student, $jemputan, 11, 2026, 750000);

    $summary = jemputanSalaryReport($year, $driver, '2026-11')['summary'];

    expect($summary['revenue'])->toBe(750000)
        ->and($summary['percentage'])->toBeNull()
        ->and($summary['deduction'])->toBeNull()
        ->and($summary['net_salary'])->toBeNull()
        ->and($summary['percentage_configured'])->toBeFalse();
});

it('shows a multi-driver student under every driver without splitting the bill', function () {
    $year = jemputanSalaryYear();
    $drivers = JemputanDriver::factory()->count(2)->create();
    $jemputan = jemputanSalaryPaymentType();
    [$student] = jemputanSalaryStudent($year, 'Siswa Multi Sopir', 'VIII Multi');
    jemputanSalaryAssign($drivers[0], $student, $year);
    jemputanSalaryAssign($drivers[1], $student, $year);
    $bill = jemputanSalaryBill($student, $jemputan, 7, 2026, 500000);
    $billCount = StudentBill::query()->count();
    $assignmentCount = JemputanDriverAssignment::query()->count();

    $first = jemputanSalaryReport($year, $drivers[0], '2026-07');
    $second = jemputanSalaryReport($year, $drivers[1], '2026-07');

    foreach ([$first, $second] as $report) {
        expect($report['rows'])->toHaveCount(1)
            ->and($report['rows'][0]['student_id'])->toBe($student->id)
            ->and($report['rows'][0]['bill_amount'])->toBe(500000.0)
            ->and($report['rows'][0]['current_driver_count'])->toBe(2)
            ->and($report['rows'][0]['is_multi_driver'])->toBeTrue()
            ->and($report['has_multi_driver'])->toBeTrue()
            ->and($report['multi_driver_student_count'])->toBe(1)
            ->and($report['summary']['revenue'])->toBe(500000);
    }

    expect(StudentBill::query()->count())->toBe($billCount)
        ->and(JemputanDriverAssignment::query()->count())->toBe($assignmentCount)
        ->and($bill->fresh()?->amount)->toBe('500000.00');
});

it('calculates totals from every filtered student instead of the active page', function () {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create();
    $jemputan = jemputanSalaryPaymentType();

    foreach (range(1, 5) as $index) {
        [$student] = jemputanSalaryStudent($year, 'Siswa Total '.$index, 'Total '.$index);
        jemputanSalaryAssign($driver, $student, $year);
        jemputanSalaryBill($student, $jemputan, 7, 2026, 100000);
    }

    $firstPage = jemputanSalaryReport($year, $driver, '2026-07', 2, 1);
    $secondPage = jemputanSalaryReport($year, $driver, '2026-07', 2, 2);

    expect($firstPage['summary']['revenue'])->toBe(500000)
        ->and($secondPage['summary']['revenue'])->toBe(500000)
        ->and($firstPage['pagination']['total'])->toBe(5)
        ->and($firstPage['pagination']['from'])->toBe(1)
        ->and($firstPage['pagination']['to'])->toBe(2)
        ->and($secondPage['pagination']['from'])->toBe(3)
        ->and($secondPage['pagination']['to'])->toBe(4);
});

it('filters by academic year and July through June bill period', function () {
    $firstYear = jemputanSalaryYear();
    $secondYear = jemputanSalaryYear('2027/2028', '2027-07-01', '2028-06-30', false);
    $driver = JemputanDriver::factory()->create();
    $jemputan = jemputanSalaryPaymentType();

    [$firstStudent] = jemputanSalaryStudent($firstYear, 'Siswa Tahun Pertama', 'VIII Pertama');
    [$secondStudent] = jemputanSalaryStudent($secondYear, 'Siswa Tahun Kedua', 'VIII Kedua');
    jemputanSalaryAssign($driver, $firstStudent, $firstYear);
    jemputanSalaryAssign($driver, $secondStudent, $secondYear);
    jemputanSalaryBill($firstStudent, $jemputan, 7, 2026, 100000);
    jemputanSalaryBill($firstStudent, $jemputan, 1, 2027, 200000);
    jemputanSalaryBill($secondStudent, $jemputan, 7, 2027, 900000);

    $firstJuly = jemputanSalaryReport($firstYear, $driver, '2026-07');
    $firstJanuary = jemputanSalaryReport($firstYear, $driver, '2027-01');
    $secondJuly = jemputanSalaryReport($secondYear, $driver, '2027-07');

    expect($firstJuly['summary']['revenue'])->toBe(100000)
        ->and($firstJuly['rows'])->toHaveCount(1)
        ->and($firstJanuary['summary']['revenue'])->toBe(200000)
        ->and($secondJuly['summary']['revenue'])->toBe(900000)
        ->and($firstJuly['month']['key'])->toBe('2026-07')
        ->and($firstJuly['months'])->toHaveCount(12)
        ->and($firstJuly['months'][11]['key'])->toBe('2027-06');
});

it('denies salary generation to unauthorized users', function () {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create();
    [$student] = jemputanSalaryStudent($year, 'Siswa Otorisasi', 'VIII Otorisasi');
    jemputanSalaryAssign($driver, $student, $year);
    $service = app(JemputanDriverSalaryService::class);

    auth()->logout();
    expect(fn () => $service->generate($year->id, $driver->id))->toThrow(AuthorizationException::class);

    $this->actingAs(jemputanSalaryManager(User::ROLE_TU_UNIT));
    expect(fn () => $service->generate($year->id, $driver->id))->toThrow(AuthorizationException::class);

    $this->actingAs(jemputanSalaryManager(User::ROLE_ADMIN, false));
    expect(fn () => $service->generate($year->id, $driver->id))->toThrow(AuthorizationException::class);
});

it('keeps the full bill amount for a single-driver student', function () {
    $year = jemputanSalaryYear();
    $driver = JemputanDriver::factory()->create();
    $jemputan = jemputanSalaryPaymentType();
    [$student] = jemputanSalaryStudent($year, 'Siswa Tunggal Alokasi', 'VIII Tunggal Alokasi');
    jemputanSalaryAssign($driver, $student, $year);
    jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);

    $report = jemputanSalaryReport($year, $driver, '2026-07');
    $row = $report['rows'][0];

    expect($row['bill_amount'])->toBe(700000.0)
        ->and($row['salary_amount'])->toBe(700000.0)
        ->and($row['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_NOT_REQUIRED)
        ->and($row['current_driver_count'])->toBe(1)
        ->and($row['is_multi_driver'])->toBeFalse()
        ->and($report['summary']['revenue'])->toBe(700000)
        ->and($report['summary']['unallocated_multi_driver_count'])->toBe(0)
        ->and($report['summary']['stale_allocation_count'])->toBe(0)
        ->and($report['summary']['has_allocation_warning'])->toBeFalse();
});

it('uses each driver allocation when the multi-driver split is valid', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    [$student, $firstDriver, $secondDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Alokasi Valid');
    jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
    jemputanSalaryAllocation($student, $year, $firstDriver, 7, 2026, 450000);
    jemputanSalaryAllocation($student, $year, $secondDriver, 7, 2026, 250000);

    $first = jemputanSalaryReport($year, $firstDriver, '2026-07');
    $second = jemputanSalaryReport($year, $secondDriver, '2026-07');
    $firstRow = $first['rows'][0];
    $secondRow = $second['rows'][0];

    expect($firstRow['bill_amount'])->toBe(700000.0)
        ->and($firstRow['salary_amount'])->toBe(450000.0)
        ->and($firstRow['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_VALID)
        ->and($firstRow['current_driver_count'])->toBe(2)
        ->and($firstRow['is_multi_driver'])->toBeTrue()
        ->and($secondRow['bill_amount'])->toBe(700000.0)
        ->and($secondRow['salary_amount'])->toBe(250000.0)
        ->and($secondRow['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_VALID)
        ->and($first['summary']['revenue'])->toBe(450000)
        ->and($second['summary']['revenue'])->toBe(250000)
        ->and($first['summary']['has_allocation_warning'])->toBeFalse()
        ->and($second['summary']['has_allocation_warning'])->toBeFalse();
});

it('keeps the sum across drivers equal to the original bill', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    [$student, $firstDriver, $secondDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Total Lintas Sopir');
    jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
    jemputanSalaryAllocation($student, $year, $firstDriver, 7, 2026, 450000);
    jemputanSalaryAllocation($student, $year, $secondDriver, 7, 2026, 250000);

    $firstRevenue = jemputanSalaryReport($year, $firstDriver, '2026-07')['summary']['revenue'];
    $secondRevenue = jemputanSalaryReport($year, $secondDriver, '2026-07')['summary']['revenue'];

    expect($firstRevenue + $secondRevenue)->toBe(700000)
        ->and($firstRevenue)->toBe(450000)
        ->and($secondRevenue)->toBe(250000);
});

it('falls back to the full bill and warns when a multi-driver student has no allocation', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    [$student, $driver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Alokasi Hilang');
    jemputanSalaryBill($student, $jemputan, 7, 2026, 500000);

    $report = jemputanSalaryReport($year, $driver, '2026-07');
    $row = $report['rows'][0];

    expect($row['bill_amount'])->toBe(500000.0)
        ->and($row['salary_amount'])->toBe(500000.0)
        ->and($row['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_MISSING)
        ->and($report['summary']['revenue'])->toBe(500000)
        ->and($report['summary']['unallocated_multi_driver_count'])->toBe(1)
        ->and($report['summary']['stale_allocation_count'])->toBe(0)
        ->and($report['summary']['has_allocation_warning'])->toBeTrue();
});

it('does not use stale allocations after the bill changes', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    [$student, $firstDriver, $secondDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Alokasi Basi');
    $bill = jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
    jemputanSalaryAllocation($student, $year, $firstDriver, 7, 2026, 450000);
    jemputanSalaryAllocation($student, $year, $secondDriver, 7, 2026, 250000);
    BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -100000,
    ]);

    $report = jemputanSalaryReport($year, $firstDriver, '2026-07');
    $row = $report['rows'][0];

    expect($row['bill_amount'])->toBe(600000.0)
        ->and($row['salary_amount'])->toBe(600000.0)
        ->and($row['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_STALE)
        ->and($report['summary']['revenue'])->toBe(600000)
        ->and($report['summary']['stale_allocation_count'])->toBe(1)
        ->and($report['summary']['has_allocation_warning'])->toBeTrue();
});

it('does not use orphan allocations when an allocated driver is no longer a member', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    [$student, $firstDriver, $secondDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Alokasi Orphan');
    $outsider = JemputanDriver::factory()->create();
    jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
    jemputanSalaryAllocation($student, $year, $firstDriver, 7, 2026, 450000);
    jemputanSalaryAllocation($student, $year, $secondDriver, 7, 2026, 250000);
    jemputanSalaryAllocation($student, $year, $outsider, 7, 2026, 0);

    $report = jemputanSalaryReport($year, $firstDriver, '2026-07');
    $row = $report['rows'][0];

    expect($row['bill_amount'])->toBe(700000.0)
        ->and($row['salary_amount'])->toBe(700000.0)
        ->and($row['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_ORPHAN)
        ->and($report['summary']['revenue'])->toBe(700000)
        ->and($report['summary']['stale_allocation_count'])->toBe(1)
        ->and($report['summary']['has_allocation_warning'])->toBeTrue();
});

it('marks allocations stale when the driver membership changes', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    [$student, $firstDriver, $secondDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Alokasi Anggota Baru');
    $thirdDriver = JemputanDriver::factory()->create();
    jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
    jemputanSalaryAllocation($student, $year, $firstDriver, 7, 2026, 450000);
    jemputanSalaryAllocation($student, $year, $secondDriver, 7, 2026, 250000);
    jemputanSalaryAssign($thirdDriver, $student, $year);

    $report = jemputanSalaryReport($year, $firstDriver, '2026-07');
    $row = $report['rows'][0];

    expect($row['bill_amount'])->toBe(700000.0)
        ->and($row['salary_amount'])->toBe(700000.0)
        ->and($row['current_driver_count'])->toBe(3)
        ->and($row['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_STALE)
        ->and($report['summary']['stale_allocation_count'])->toBe(1)
        ->and($report['summary']['has_allocation_warning'])->toBeTrue();
});

it('summarizes allocations from every filtered student across pagination', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    $driver = JemputanDriver::factory()->create();

    foreach (range(1, 3) as $index) {
        [$student] = jemputanSalaryStudent($year, 'Siswa Alokasi Halaman '.$index, 'Halaman Alokasi '.$index);
        $partner = JemputanDriver::factory()->create();
        jemputanSalaryAssign($driver, $student, $year);
        jemputanSalaryAssign($partner, $student, $year);
        jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
        jemputanSalaryAllocation($student, $year, $driver, 7, 2026, 400000);
        jemputanSalaryAllocation($student, $year, $partner, 7, 2026, 300000);
    }

    $firstPage = jemputanSalaryReport($year, $driver, '2026-07', 2, 1);
    $secondPage = jemputanSalaryReport($year, $driver, '2026-07', 2, 2);

    expect($firstPage['rows'])->toHaveCount(2)
        ->and($firstPage['summary']['revenue'])->toBe(1200000)
        ->and($secondPage['summary']['revenue'])->toBe(1200000)
        ->and($firstPage['summary']['has_allocation_warning'])->toBeFalse()
        ->and($secondPage['summary']['has_allocation_warning'])->toBeFalse()
        ->and($firstPage['pagination']['total'])->toBe(3)
        ->and($secondPage['rows'])->toHaveCount(1);
});

it('uses the same allocation amounts in export data and the report', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    $service = app(JemputanDriverSalaryService::class);
    [$multiStudent, $firstDriver, $secondDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Ekspor Alokasi');
    [$singleStudent] = jemputanSalaryStudent($year, 'Siswa Ekspor Tunggal', 'VIII Ekspor Tunggal');
    $singleDriver = JemputanDriver::factory()->create();
    jemputanSalaryAssign($singleDriver, $singleStudent, $year);
    jemputanSalaryBill($multiStudent, $jemputan, 7, 2026, 700000);
    jemputanSalaryBill($singleStudent, $jemputan, 7, 2026, 300000);
    jemputanSalaryAllocation($multiStudent, $year, $firstDriver, 7, 2026, 450000);
    jemputanSalaryAllocation($multiStudent, $year, $secondDriver, 7, 2026, 250000);

    $report = $service->generate($year->id, $firstDriver->id, '2026-07');
    $export = $service->exportData($year->id, $firstDriver->id, '2026-07');

    expect($export['rows'])->toHaveCount(1)
        ->and($export['rows'][0]['student_id'])->toBe($multiStudent->id)
        ->and($export['rows'][0]['bill_amount'])->toBe($report['rows'][0]['bill_amount'])
        ->and($export['rows'][0]['salary_amount'])->toBe($report['rows'][0]['salary_amount'])
        ->and($export['rows'][0]['allocation_status'])->toBe(JemputanDriverAllocationService::STATUS_VALID)
        ->and($export['summary']['revenue'])->toBe($report['summary']['revenue'])
        ->and($export['summary'])->toMatchArray($report['summary']);
});

it('keeps allocation aware queries bounded as students grow', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    $service = app(JemputanDriverSalaryService::class);
    $selectCounts = [];

    foreach ([1, 10, 25] as $studentCount) {
        $driver = JemputanDriver::factory()->create();
        $partner = JemputanDriver::factory()->create();
        $bank = Bank::factory()->create();

        foreach (range(1, $studentCount) as $index) {
            [$student] = jemputanSalaryStudent(
                $year,
                'Siswa Query Alokasi '.$studentCount.'-'.$index,
                'Query Alokasi '.$studentCount.'-'.$index,
            );
            jemputanSalaryAssign($driver, $student, $year);
            jemputanSalaryAssign($partner, $student, $year);
            $bill = jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
            jemputanSalaryPayment($student, $bill, $bank, '2026-07-10', 350000);
            jemputanSalaryAllocation($student, $year, $driver, 7, 2026, 400000);
            jemputanSalaryAllocation($student, $year, $partner, 7, 2026, 300000);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->generate($year->id, $driver->id, '2026-07', 25, 1);
        $selectCounts[] = collect(DB::getQueryLog())->pluck('query')->filter(
            fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select')
        )->count();
        DB::disableQueryLog();
    }

    expect(array_unique($selectCounts))->toHaveCount(1);
});

it('applies the foundation percentage to allocated salary revenue', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    [$student, $firstDriver, $secondDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Persentase Alokasi');
    $firstDriver->update(['foundation_deduction_percentage' => 10]);
    jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
    jemputanSalaryAllocation($student, $year, $firstDriver, 7, 2026, 450000);
    jemputanSalaryAllocation($student, $year, $secondDriver, 7, 2026, 250000);

    $summary = jemputanSalaryReport($year, $firstDriver->refresh(), '2026-07')['summary'];

    expect($summary['revenue'])->toBe(450000)
        ->and($summary['percentage'])->toBe(10.0)
        ->and($summary['deduction'])->toBe(45000)
        ->and($summary['net_salary'])->toBe(405000)
        ->and($summary['has_allocation_warning'])->toBeFalse();
});

it('does not change financial data when reading allocation aware reports', function () {
    $year = jemputanSalaryYear();
    $jemputan = jemputanSalaryPaymentType();
    [$student, $firstDriver, $secondDriver] = jemputanSalaryMultiDriverStudent($year, 'Siswa Baca Alokasi');
    $bill = jemputanSalaryBill($student, $jemputan, 7, 2026, 700000);
    $adjustment = BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -50000,
    ]);
    $firstAllocation = jemputanSalaryAllocation($student, $year, $firstDriver, 7, 2026, 450000);
    $secondAllocation = jemputanSalaryAllocation($student, $year, $secondDriver, 7, 2026, 250000);
    $service = app(JemputanDriverSalaryService::class);

    $service->generate($year->id, $firstDriver->id, '2026-07');
    $service->exportData($year->id, $firstDriver->id, '2026-07');

    expect($bill->refresh()->amount)->toBe('700000.00')
        ->and($adjustment->refresh()->amount)->toBe('-50000.00')
        ->and($firstAllocation->refresh()->allocated_amount)->toBe('450000.00')
        ->and($secondAllocation->refresh()->allocated_amount)->toBe('250000.00')
        ->and(StudentBill::query()->count())->toBe(1)
        ->and(BillAdjustment::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(0)
        ->and(JemputanDriverAssignment::query()->count())->toBe(2)
        ->and(JemputanDriverSalaryAllocation::query()->count())->toBe(2);
});
