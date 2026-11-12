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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function jemputanAllocationYear(string $label = '2026/2027'): AcademicYear
{
    [$startYear, $endYear] = explode('/', $label);

    return AcademicYear::query()->firstOrCreate(
        ['year' => $label],
        [
            'is_active' => $label === '2026/2027',
            'start_date' => $startYear.'-07-01',
            'end_date' => $endYear.'-06-30',
        ],
    );
}

function jemputanAllocationUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function jemputanAllocationStudent(AcademicYear $academicYear, string $name = 'Siswa Alokasi'): Student
{
    $class = SchoolClass::query()->firstOrCreate(['name' => 'VIII Alokasi'], ['level' => 8]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

function jemputanAllocationDriver(string $name): JemputanDriver
{
    return JemputanDriver::factory()->create(['name' => $name]);
}

function jemputanAllocationAssign(
    JemputanDriver $driver,
    Student $student,
    AcademicYear $academicYear,
    ?string $effectiveUntil = null,
): JemputanDriverAssignment {
    return JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'effective_from' => '2026-07-01',
        'effective_until' => $effectiveUntil,
    ]);
}

function jemputanAllocationPaymentType(): PaymentType
{
    return PaymentType::query()->firstOrCreate(
        ['name' => 'Jemputan'],
        [
            'is_active' => true,
            'is_auto_enrolled' => false,
            'is_required' => false,
        ],
    );
}

function jemputanAllocationBill(
    Student $student,
    int $amount,
    int $month = 7,
    int $year = 2026,
): StudentBill {
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => jemputanAllocationPaymentType()->id,
        'amount' => $amount,
        'period_month' => $month,
        'period_year' => $year,
        'billing_frequency' => BillFrequency::Monthly->value,
    ]);
}

/**
 * @param  array<int, int|float|string>  $amountsByDriverId
 * @return list<array{jemputan_driver_id: int, allocated_amount: int|float|string}>
 */
function jemputanAllocationRows(array $amountsByDriverId): array
{
    $rows = [];

    foreach ($amountsByDriverId as $driverId => $amount) {
        $rows[] = [
            'jemputan_driver_id' => (int) $driverId,
            'allocated_amount' => $amount,
        ];
    }

    return $rows;
}

/** @return array{Student, JemputanDriver, JemputanDriver} */
function jemputanAllocationMultiDriverStudent(AcademicYear $academicYear): array
{
    $student = jemputanAllocationStudent($academicYear, 'Siswa Multi '.uniqid());
    $firstDriver = jemputanAllocationDriver('Sopir A '.uniqid());
    $secondDriver = jemputanAllocationDriver('Sopir B '.uniqid());

    jemputanAllocationAssign($firstDriver, $student, $academicYear);
    jemputanAllocationAssign($secondDriver, $student, $academicYear);

    return [$student, $firstDriver, $secondDriver];
}

function jemputanAllocationService(): JemputanDriverAllocationService
{
    return app(JemputanDriverAllocationService::class);
}

it('sums effective amounts across multiple Jemputan bills in the same month', function () {
    $academicYear = jemputanAllocationYear();
    $student = jemputanAllocationStudent($academicYear, 'Siswa Multi Tagihan');
    $firstBill = jemputanAllocationBill($student, 400000, 7);
    jemputanAllocationBill($student, 300000, 7);

    BillAdjustment::factory()->create([
        'bill_id' => $firstBill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -50000,
    ]);

    expect(jemputanAllocationService()->effectiveBillAmount($student->id, $academicYear->id, 2026, 7))
        ->toBe(650000.0);
});

it('saves a manual split that matches the effective bill', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    $bill = jemputanAllocationBill($student, 700000);

    $saved = jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
        ])],
        $admin,
    );

    expect($saved)->toHaveCount(2)
        ->and($saved->pluck('allocated_amount')->all())->toBe(['450000.00', '250000.00'])
        ->and($saved->every(fn ($allocation): bool => $allocation->created_by === $admin->id))->toBeTrue()
        ->and($saved->every(fn ($allocation): bool => $allocation->updated_by === $admin->id))->toBeTrue();

    $preview = jemputanAllocationService()->preview($student->id, $academicYear->id, 2026, 7);

    expect($preview['status'])->toBe(JemputanDriverAllocationService::STATUS_VALID)
        ->and($preview['bill_amount'])->toBe(700000.0)
        ->and($preview['total_allocated'])->toBe(700000.0)
        ->and($preview['difference'])->toBe(0.0)
        ->and($bill->refresh()->amount)->toBe('700000.00')
        ->and($student->jemputanDriverAssignments()->count())->toBe(2);
});

it('rejects totals that are lower or higher than the effective bill', function (string $firstAmount, string $secondAmount) {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => $firstAmount,
            $secondDriver->id => $secondAmount,
        ])],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
})->with([
    'kurang' => ['449999', '250000'],
    'lebih' => ['450001', '250000'],
]);

it('rejects negative allocation amounts', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '-1',
            $secondDriver->id => '700001',
        ])],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('rejects amounts with more than two decimals', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000.001',
            $secondDriver->id => '249999.999',
        ])],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('rejects allocations for drivers that are not current members', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    $outsider = jemputanAllocationDriver('Sopir Luar '.uniqid());
    jemputanAllocationBill($student, 700000);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
            $outsider->id => '0',
        ])],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('rejects duplicate drivers in the same period', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => [
            ['jemputan_driver_id' => $firstDriver->id, 'allocated_amount' => '450000'],
            ['jemputan_driver_id' => $firstDriver->id, 'allocated_amount' => '250000'],
        ]],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('rejects an incomplete driver set even when the total matches', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '700000',
        ])],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('does not require an allocation for a single-driver student', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    $student = jemputanAllocationStudent($academicYear, 'Siswa Satu Sopir');
    $driver = jemputanAllocationDriver('Sopir Tunggal '.uniqid());
    jemputanAllocationAssign($driver, $student, $academicYear);
    jemputanAllocationBill($student, 700000);

    $preview = jemputanAllocationService()->preview($student->id, $academicYear->id, 2026, 7);

    expect($preview['status'])->toBe(JemputanDriverAllocationService::STATUS_NOT_REQUIRED);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([$driver->id => '700000'])],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('rejects saving when the period has no Jemputan bill', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
        ])],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('keeps allocations isolated between months', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000, 7);
    jemputanAllocationBill($student, 800000, 8);

    jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
        ])],
        $admin,
    );

    expect(JemputanDriverSalaryAllocation::query()->where('period_month', 7)->count())->toBe(2)
        ->and(JemputanDriverSalaryAllocation::query()->where('period_month', 8)->count())->toBe(0)
        ->and(jemputanAllocationService()->preview($student->id, $academicYear->id, 2026, 8)['status'])
        ->toBe(JemputanDriverAllocationService::STATUS_MISSING);
});

it('saves multiple valid months in one transaction', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000, 7);
    jemputanAllocationBill($student, 800000, 8);

    $saved = jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        [
            '2026-07' => jemputanAllocationRows([
                $firstDriver->id => '450000',
                $secondDriver->id => '250000',
            ]),
            '2026-08' => jemputanAllocationRows([
                $firstDriver->id => '600000',
                $secondDriver->id => '200000',
            ]),
        ],
        $admin,
    );

    expect($saved)->toHaveCount(4)
        ->and(JemputanDriverSalaryAllocation::query()->count())->toBe(4)
        ->and(jemputanAllocationService()->preview($student->id, $academicYear->id, 2026, 7)['status'])
        ->toBe(JemputanDriverAllocationService::STATUS_VALID)
        ->and(jemputanAllocationService()->preview($student->id, $academicYear->id, 2026, 8)['status'])
        ->toBe(JemputanDriverAllocationService::STATUS_VALID);
});

it('rolls back every month when one month is invalid', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000, 7);
    jemputanAllocationBill($student, 800000, 8);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        [
            '2026-07' => jemputanAllocationRows([
                $firstDriver->id => '450000',
                $secondDriver->id => '250000',
            ]),
            '2026-08' => jemputanAllocationRows([
                $firstDriver->id => '500000',
                $secondDriver->id => '200000',
            ]),
        ],
        $admin,
    ))->toThrow(ValidationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
});

it('updates existing allocations without creating duplicates', function () {
    $academicYear = jemputanAllocationYear();
    $firstAdmin = jemputanAllocationUser();
    $secondAdmin = jemputanAllocationUser(User::ROLE_SUPER_ADMIN);
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
        ])],
        $firstAdmin,
    );

    jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '400000',
            $secondDriver->id => '300000',
        ])],
        $secondAdmin,
    );

    $allocations = JemputanDriverSalaryAllocation::query()
        ->orderBy('jemputan_driver_id')
        ->get();

    expect($allocations)->toHaveCount(2)
        ->and($allocations->pluck('allocated_amount')->sort()->values()->all())
        ->toBe(['300000.00', '400000.00'])
        ->and($allocations->every(fn ($allocation): bool => $allocation->created_by === $firstAdmin->id))->toBeTrue()
        ->and($allocations->every(fn ($allocation): bool => $allocation->updated_by === $secondAdmin->id))->toBeTrue();
});

it('marks allocations stale when the bill changes', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    $bill = jemputanAllocationBill($student, 700000);

    jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
        ])],
        $admin,
    );

    BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -50000,
    ]);

    $preview = jemputanAllocationService()->preview($student->id, $academicYear->id, 2026, 7);

    expect($preview['status'])->toBe(JemputanDriverAllocationService::STATUS_STALE)
        ->and($preview['bill_amount'])->toBe(650000.0)
        ->and($preview['total_allocated'])->toBe(700000.0)
        ->and($preview['difference'])->toBe(-50000.0);
});

it('marks allocations stale when membership grows', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
        ])],
        $admin,
    );

    $thirdDriver = jemputanAllocationDriver('Sopir C '.uniqid());
    jemputanAllocationAssign($thirdDriver, $student, $academicYear);

    expect(jemputanAllocationService()->preview($student->id, $academicYear->id, 2026, 7)['status'])
        ->toBe(JemputanDriverAllocationService::STATUS_STALE);
});

it('marks allocations orphan when an allocated driver leaves the membership', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
        ])],
        $admin,
    );

    JemputanDriverAssignment::query()
        ->where('student_id', $student->id)
        ->where('jemputan_driver_id', $secondDriver->id)
        ->update(['effective_until' => '2026-08-01']);

    expect(jemputanAllocationService()->preview($student->id, $academicYear->id, 2026, 7)['status'])
        ->toBe(JemputanDriverAllocationService::STATUS_ORPHAN);
});

it('rejects saving for users outside the existing admin policy', function (string $role, bool $active) {
    $academicYear = jemputanAllocationYear();
    $actor = jemputanAllocationUser($role, $active);
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    jemputanAllocationBill($student, 700000);

    expect(fn () => jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '450000',
            $secondDriver->id => '250000',
        ])],
        $actor,
    ))->toThrow(AuthorizationException::class);

    expect(JemputanDriverSalaryAllocation::query()->count())->toBe(0);
})->with([
    'tu unit' => [User::ROLE_TU_UNIT, true],
    'admin nonaktif' => [User::ROLE_ADMIN, false],
]);

it('does not change bills payments adjustments or memberships when saving', function () {
    $academicYear = jemputanAllocationYear();
    $admin = jemputanAllocationUser();
    [$student, $firstDriver, $secondDriver] = jemputanAllocationMultiDriverStudent($academicYear);
    $bill = jemputanAllocationBill($student, 700000);
    $adjustment = BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -10000,
    ]);
    $bank = Bank::factory()->create();
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-ALLOC-'.uniqid(),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-07-10',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $admin->id,
    ]);
    $detail = $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => 7,
        'period_year' => 2026,
        'amount' => 100000,
    ]);

    jemputanAllocationService()->save(
        $student->id,
        $academicYear->id,
        ['2026-07' => jemputanAllocationRows([
            $firstDriver->id => '400000',
            $secondDriver->id => '290000',
        ])],
        $admin,
    );

    expect($bill->refresh()->amount)->toBe('700000.00')
        ->and($adjustment->refresh()->amount)->toBe('-10000.00')
        ->and($payment->refresh()->total_amount)->toBe('100000.00')
        ->and($detail->refresh()->amount)->toBe('100000.00')
        ->and($student->jemputanDriverAssignments()->count())->toBe(2)
        ->and(PaymentDetail::query()->where('payment_id', $payment->id)->count())->toBe(1);
});
