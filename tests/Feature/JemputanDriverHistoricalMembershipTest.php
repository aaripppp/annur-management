<?php

use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use App\Services\JemputanDriverAssignmentService;
use App\Services\JemputanDriverMembershipPeriodValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function historicalMembershipYear(
    string $label = '2026/2027',
    string $start = '2026-07-01',
    string $end = '2027-06-30',
): AcademicYear {
    return AcademicYear::query()->firstOrCreate(
        ['year' => $label],
        [
            'is_active' => false,
            'start_date' => $start,
            'end_date' => $end,
        ],
    );
}

function historicalMembershipAssignment(
    AcademicYear $academicYear,
    Student $student,
    JemputanDriver $driver,
    string $from,
    ?string $until = null,
): JemputanDriverAssignment {
    return JemputanDriverAssignment::query()->create([
        'academic_year_id' => $academicYear->id,
        'student_id' => $student->id,
        'jemputan_driver_id' => $driver->id,
        'effective_from' => $from,
        'effective_until' => $until,
    ]);
}

/** @return list<int> */
function historicalMembershipIds(AcademicYear $academicYear, Student $student, int $year, int $month): array
{
    return JemputanDriverAssignment::query()
        ->where('student_id', $student->id)
        ->activeDuringMonth($academicYear->id, $year, $month)
        ->orderBy('id')
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();
}

it('resolves a stop gap and rejoin from July through December without changing history', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    $firstPeriod = historicalMembershipAssignment($academicYear, $student, $driver, '2026-07-01', '2026-09-01');
    $rejoinedPeriod = historicalMembershipAssignment($academicYear, $student, $driver, '2026-11-01');

    expect(historicalMembershipIds($academicYear, $student, 2026, 7))->toBe([$firstPeriod->id])
        ->and(historicalMembershipIds($academicYear, $student, 2026, 8))->toBe([$firstPeriod->id])
        ->and(historicalMembershipIds($academicYear, $student, 2026, 9))->toBe([])
        ->and(historicalMembershipIds($academicYear, $student, 2026, 10))->toBe([])
        ->and(historicalMembershipIds($academicYear, $student, 2026, 11))->toBe([$rejoinedPeriod->id])
        ->and(historicalMembershipIds($academicYear, $student, 2026, 12))->toBe([$rejoinedPeriod->id])
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(2)
        ->and($firstPeriod->fresh()?->effective_from->toDateString())->toBe('2026-07-01')
        ->and($firstPeriod->fresh()?->effective_until?->toDateString())->toBe('2026-09-01');
});

it('allows overlapping memberships for different drivers but rejects overlap for the same driver', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    historicalMembershipAssignment($academicYear, $student, $firstDriver, '2026-07-01', '2026-12-01');
    historicalMembershipAssignment($academicYear, $student, $secondDriver, '2026-07-01', '2026-12-01');
    $validator = app(JemputanDriverMembershipPeriodValidator::class);

    $validator->assertValid(
        $student->id,
        $secondDriver->id,
        $academicYear->id,
        CarbonImmutable::parse('2026-12-01'),
        CarbonImmutable::parse('2027-01-01'),
    );

    expect(JemputanDriverAssignment::query()
        ->activeDuringMonth($academicYear->id, 2026, 8)
        ->where('student_id', $student->id)
        ->distinct()
        ->count('jemputan_driver_id'))->toBe(2)
        ->and(fn () => $validator->assertValid(
            $student->id,
            $firstDriver->id,
            $academicYear->id,
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-10-01'),
        ))->toThrow(ValidationException::class);
});

it('treats a stop and rejoin on the same date as contiguous non-overlapping periods', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    historicalMembershipAssignment($academicYear, $student, $driver, '2026-07-01', '2026-11-01');
    $validator = app(JemputanDriverMembershipPeriodValidator::class);

    $validator->assertValid(
        $student->id,
        $driver->id,
        $academicYear->id,
        CarbonImmutable::parse('2026-11-01'),
        null,
    );

    expect($validator->overlapExists(
        $student->id,
        $driver->id,
        $academicYear->id,
        CarbonImmutable::parse('2026-11-01'),
        null,
    ))->toBeFalse();
});

it('honors month boundaries null end dates and academic year isolation', function () {
    $firstYear = historicalMembershipYear();
    $secondYear = historicalMembershipYear('2027/2028', '2027-07-01', '2028-06-30');
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    $firstYearAssignment = historicalMembershipAssignment($firstYear, $student, $driver, '2026-08-01', '2026-09-01');
    $secondYearAssignment = historicalMembershipAssignment($secondYear, $student, $driver, '2027-07-01');

    expect(historicalMembershipIds($firstYear, $student, 2026, 7))->toBe([])
        ->and(historicalMembershipIds($firstYear, $student, 2026, 8))->toBe([$firstYearAssignment->id])
        ->and(historicalMembershipIds($firstYear, $student, 2026, 9))->toBe([])
        ->and(historicalMembershipIds($firstYear, $student, 2027, 7))->toBe([])
        ->and(historicalMembershipIds($secondYear, $student, 2027, 7))->toBe([$secondYearAssignment->id])
        ->and(historicalMembershipIds($secondYear, $student, 2028, 6))->toBe([$secondYearAssignment->id]);
});

it('rejects invalid and out of academic year periods', function (
    string $from,
    ?string $until,
    string $errorKey,
) {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();

    try {
        app(JemputanDriverMembershipPeriodValidator::class)->assertValid(
            $student->id,
            $driver->id,
            $academicYear->id,
            CarbonImmutable::parse($from),
            $until === null ? null : CarbonImmutable::parse($until),
        );

        $this->fail('Periode tidak valid seharusnya ditolak.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($errorKey);
    }
})->with([
    'zero length' => ['2026-08-01', '2026-08-01', 'effective_until'],
    'reversed' => ['2026-09-01', '2026-08-01', 'effective_until'],
    'before academic year' => ['2026-06-30', '2026-08-01', 'effective_from'],
    'after academic year' => ['2027-07-01', null, 'effective_from'],
    'end beyond academic year' => ['2027-06-01', '2027-07-02', 'effective_until'],
]);

it('keeps historical membership relation loading queries bounded as rows grow', function () {
    $academicYear = historicalMembershipYear();
    $driver = JemputanDriver::factory()->create();
    historicalMembershipAssignment($academicYear, Student::factory()->create(), $driver, '2026-07-01');

    $countSelects = function () use ($academicYear): int {
        DB::flushQueryLog();
        JemputanDriverAssignment::query()
            ->activeDuringMonth($academicYear->id, 2026, 8)
            ->with(['student', 'driver'])
            ->get()
            ->each(fn (JemputanDriverAssignment $assignment): array => [
                $assignment->student->id,
                $assignment->driver->id,
            ]);

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->count();
    };

    DB::enableQueryLog();
    $smallSelects = $countSelects();

    foreach (range(1, 20) as $index) {
        historicalMembershipAssignment(
            $academicYear,
            Student::factory()->create(),
            $driver,
            '2026-07-'.str_pad((string) min($index, 28), 2, '0', STR_PAD_LEFT),
        );
    }

    $largeSelects = $countSelects();
    DB::disableQueryLog();

    expect($largeSelects)->toBe($smallSelects)
        ->and($largeSelects)->toBe(3);
});

it('writes explicit effective months and keeps old periods when a student rejoins', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);

    $firstPeriod = $service->assign($driver->id, $student->id, $academicYear->id, '2026-07');
    $closedPeriod = $service->remove($firstPeriod->id, '2026-09');
    $rejoinedPeriod = $service->assign($driver->id, $student->id, $academicYear->id, '2026-11');

    expect($closedPeriod->effective_from->toDateString())->toBe('2026-07-01')
        ->and($closedPeriod->effective_until?->toDateString())->toBe('2026-09-01')
        ->and($rejoinedPeriod->effective_from->toDateString())->toBe('2026-11-01')
        ->and($rejoinedPeriod->effective_until)->toBeNull()
        ->and(historicalMembershipIds($academicYear, $student, 2026, 9))->toBe([])
        ->and(historicalMembershipIds($academicYear, $student, 2026, 10))->toBe([])
        ->and(historicalMembershipIds($academicYear, $student, 2026, 11))->toBe([$rejoinedPeriod->id])
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(2);
});

it('rejects overlapping writes for the same driver while allowing multi-driver membership', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $firstDriver = JemputanDriver::factory()->create();
    $secondDriver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);
    $firstPeriod = $service->assign($firstDriver->id, $student->id, $academicYear->id, '2026-07');
    $service->remove($firstPeriod->id, '2026-11');

    expect(fn () => $service->assign($firstDriver->id, $student->id, $academicYear->id, '2026-09'))
        ->toThrow(ValidationException::class);

    $multiDriver = $service->assign($secondDriver->id, $student->id, $academicYear->id, '2026-09');

    expect($multiDriver->effective_from->toDateString())->toBe('2026-09-01')
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(2);
});

it('rejects same-month and reversed removals without changing the assignment', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);
    $assignment = $service->assign($driver->id, $student->id, $academicYear->id, '2026-09');

    expect(fn () => $service->remove($assignment->id, '2026-09'))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->remove($assignment->id, '2026-08'))
        ->toThrow(ValidationException::class)
        ->and($assignment->fresh()?->effective_until)->toBeNull()
        ->and(JemputanDriverAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

it('moves one membership atomically on the requested month and preserves other drivers', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $sourceDriver = JemputanDriver::factory()->create();
    $otherDriver = JemputanDriver::factory()->create();
    $targetDriver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);
    $source = $service->assign($sourceDriver->id, $student->id, $academicYear->id, '2026-07');
    $other = $service->assign($otherDriver->id, $student->id, $academicYear->id, '2026-07');

    $target = $service->move($source->id, $targetDriver->id, '2026-11');

    expect($source->fresh()?->effective_until?->toDateString())->toBe('2026-11-01')
        ->and($target->effective_from->toDateString())->toBe('2026-11-01')
        ->and($target->jemputan_driver_id)->toBe($targetDriver->id)
        ->and($other->fresh()?->effective_until)->toBeNull();
});

it('rolls back a move when the target period overlaps and preserves every membership', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $sourceDriver = JemputanDriver::factory()->create();
    $otherDriver = JemputanDriver::factory()->create();
    $targetDriver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);
    $source = $service->assign($sourceDriver->id, $student->id, $academicYear->id, '2026-07');
    $other = $service->assign($otherDriver->id, $student->id, $academicYear->id, '2026-07');
    $targetHistory = historicalMembershipAssignment(
        $academicYear,
        $student,
        $targetDriver,
        '2026-11-01',
        '2027-01-01',
    );

    expect(fn () => $service->move($source->id, $targetDriver->id, '2026-12'))
        ->toThrow(ValidationException::class)
        ->and($source->fresh()?->effective_until)->toBeNull()
        ->and($other->fresh()?->effective_until)->toBeNull()
        ->and($targetHistory->fresh()?->effective_until?->toDateString())->toBe('2027-01-01')
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(3);
});

it('updates only one selected membership period with locking and overlap validation', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    $first = historicalMembershipAssignment($academicYear, $student, $driver, '2026-07-01', '2026-09-01');
    $second = historicalMembershipAssignment($academicYear, $student, $driver, '2026-11-01');
    $service = app(JemputanDriverAssignmentService::class);

    expect(fn () => $service->updatePeriod($second->id, '2026-08', null))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->updatePeriod($second->id, '2026-10', '2026-10'))
        ->toThrow(ValidationException::class)
        ->and($first->fresh()?->effective_from->toDateString())->toBe('2026-07-01')
        ->and($first->fresh()?->effective_until?->toDateString())->toBe('2026-09-01')
        ->and($second->fresh()?->effective_from->toDateString())->toBe('2026-11-01');

    $updated = $service->updatePeriod($second->id, '2026-10', '2027-02');

    expect($updated->id)->toBe($second->id)
        ->and($updated->effective_from->toDateString())->toBe('2026-10-01')
        ->and($updated->effective_until?->toDateString())->toBe('2027-02-01')
        ->and(JemputanDriverAssignment::query()->where('student_id', $student->id)->count())->toBe(2);
});

it('serializes competing assignment attempts and leaves only one non-overlapping period', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $driver = JemputanDriver::factory()->create();
    $firstRequest = app(JemputanDriverAssignmentService::class);
    $competingRequest = app(JemputanDriverAssignmentService::class);

    $winner = $firstRequest->assign($driver->id, $student->id, $academicYear->id, '2026-07');

    expect(fn () => $competingRequest->assign($driver->id, $student->id, $academicYear->id, '2026-08'))
        ->toThrow(ValidationException::class)
        ->and(JemputanDriverAssignment::query()
            ->where('student_id', $student->id)
            ->where('jemputan_driver_id', $driver->id)
            ->count())->toBe(1)
        ->and($winner->fresh()?->effective_until)->toBeNull();
});

it('does not modify any financial record during assign remove rejoin and move', function () {
    $academicYear = historicalMembershipYear();
    $student = Student::factory()->create();
    $paymentType = PaymentType::factory()->create();
    $bill = StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $paymentType->id,
    ]);
    StudentPaymentSetting::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $paymentType->id,
    ]);
    BillAdjustment::factory()->create(['bill_id' => $bill->id]);
    $payment = Payment::query()->create([
        'receipt_number' => 'JEMPUTAN-HISTORY-001',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-01',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => User::factory()->create()->id,
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $paymentType->id,
        'period_month' => 8,
        'period_year' => 2026,
        'academic_year' => '2026/2027',
        'amount' => 100000,
    ]);
    $financialTables = [
        'student_bills',
        'payments',
        'payment_details',
        'bill_adjustments',
        'student_payment_settings',
    ];
    $snapshot = fn (): array => collect($financialTables)
        ->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ])
        ->all();
    $before = $snapshot();
    $sourceDriver = JemputanDriver::factory()->create();
    $otherDriver = JemputanDriver::factory()->create();
    $service = app(JemputanDriverAssignmentService::class);

    $first = $service->assign($sourceDriver->id, $student->id, $academicYear->id, '2026-07');
    $service->remove($first->id, '2026-09');
    $service->updatePeriod($first->id, '2026-07', '2026-10');
    $rejoined = $service->assign($sourceDriver->id, $student->id, $academicYear->id, '2026-11');
    $service->move($rejoined->id, $otherDriver->id, '2027-01');

    expect($snapshot())->toBe($before);
});
