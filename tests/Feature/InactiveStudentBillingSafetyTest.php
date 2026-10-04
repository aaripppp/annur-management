<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\PaymentCreate;
use App\Livewire\StudentDetail;
use App\Livewire\StudentPaymentSettings;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Services\ClassPromotionService;
use App\Support\BillbookPeriod;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-08-15');
});

/**
 * @return array{monthly: PaymentType, yearly: PaymentType, one_time: PaymentType}
 */
function inactiveSafetyBillingTypes(int $level = 8): array
{
    $monthly = makeBillType('SPP Inactive Safety');
    makeBillRate($monthly, $level, 970000);
    makeLevelDefault($monthly, SchoolLevel::SMP);

    $yearly = makeBillType('Uang Buku Inactive Safety');
    makeBillRate($yearly, $level, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeLevelDefault($yearly, SchoolLevel::SMP);

    $oneTime = makeBillType('Uang Pangkal Inactive Safety');
    makeBillRate($oneTime, $level, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeLevelDefault($oneTime, SchoolLevel::SMP);

    return ['monthly' => $monthly, 'yearly' => $yearly, 'one_time' => $oneTime];
}

/** @return array{0: AcademicYear, 1: AcademicYear} */
function inactiveSafetyAcademicYears(): array
{
    $source = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $source->update(['is_active' => true]);
    $target = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    return [$source, $target];
}

it('blocks every automatic bill frequency for inactive students', function (string $status) {
    inactiveSafetyBillingTypes();
    $student = makeBillStudent(8);
    $student->update(['status' => $status]);

    $created = app(BillGenerationService::class)
        ->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect($created)->toBeEmpty()
        ->and($student->bills()->count())->toBe(0);
})->with([
    'graduated' => StudentStatus::Graduated->value,
    'transferred' => StudentStatus::Transferred->value,
]);

it('keeps automatic billing unchanged for active students', function () {
    inactiveSafetyBillingTypes();
    $student = makeBillStudent(8);

    $created = app(BillGenerationService::class)
        ->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect($created)->toHaveCount(3)
        ->and($student->bills()->pluck('billing_frequency')->sort()->values()->all())
        ->toBe(['monthly', 'one_time', 'yearly']);
});

it('fails closed for an unknown persisted student status', function () {
    inactiveSafetyBillingTypes();
    $student = makeBillStudent(8);

    Student::query()->whereKey($student->id)->update(['status' => 'unknown']);
    $student->refresh();

    $created = app(BillGenerationService::class)
        ->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect($created)->toBeEmpty()
        ->and($student->bills()->count())->toBe(0);
});

it('returns before payment settings are created by automatic billbook initialization', function (string $method) {
    $student = makeBillStudent(8);
    $student->update(['status' => StudentStatus::Graduated]);
    inactiveSafetyBillingTypes();
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $service = app(BillGenerationService::class);

    $created = match ($method) {
        'billbook' => $service->generateBillbook($student, Carbon::parse('2026-07-01')),
        'yearly' => $service->generateYearlyAndOneTimeOnly($student, Carbon::parse('2026-07-01')),
        'initial' => $service->generateInitialAcademicYearBills($student, $academicYear),
    };

    expect($created)->toBeEmpty()
        ->and($student->paymentSettings()->count())->toBe(0)
        ->and($student->bills()->count())->toBe(0);
})->with(['billbook', 'yearly', 'initial']);

it('uses the same active-student roster for monthly batch preview and execution', function () {
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $schoolClass = SchoolClass::factory()->create(['level' => 8]);
    $active = Student::factory()->create(['class_id' => $schoolClass->id]);
    $graduated = Student::factory()->create([
        'class_id' => $schoolClass->id,
        'status' => StudentStatus::Graduated,
    ]);
    $transferred = Student::factory()->create([
        'class_id' => $schoolClass->id,
        'status' => StudentStatus::Transferred,
    ]);

    foreach ([$active, $graduated, $transferred] as $student) {
        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'status' => 'active',
        ]);
    }

    $monthly = makeBillType('SPP Batch Inactive Safety');
    makeBillRate($monthly, 8, 970000);
    makeLevelDefault($monthly, SchoolLevel::SMP);
    $service = app(BillGenerationService::class);
    $preview = $service->getMonthlyGenerationPreview($academicYear);
    $result = $service->generateMonthlyForAcademicYear($academicYear);

    expect($preview['eligible_students'])->toBe(1)
        ->and($preview['will_create'])->toBeGreaterThan(0)
        ->and($result['created'])->toBe($preview['will_create'])
        ->and($active->bills()->count())->toBe($preview['will_create'])
        ->and($graduated->bills()->count())->toBe(0)
        ->and($transferred->bills()->count())->toBe(0)
        ->and($active->paymentSettings()->count())->toBe(1)
        ->and($graduated->paymentSettings()->count())->toBe(0)
        ->and($transferred->paymentSettings()->count())->toBe(0);
});

it('excludes inactive students with active source enrollments from promotion', function (string $status) {
    [$sourceYear, $targetYear] = inactiveSafetyAcademicYears();
    $sourceClass = SchoolClass::create(['name' => 'VII Safety', 'level' => 7]);
    $targetClass = SchoolClass::create(['name' => 'VIII Safety', 'level' => 8]);
    createPromotionRule($sourceClass, 'promote', $targetClass);
    $student = Student::factory()->create([
        'class_id' => $sourceClass->id,
        'status' => $status,
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $sourceYear->id,
        'school_class_id' => $sourceClass->id,
        'status' => 'active',
    ]);
    $oldBill = makeMonthlyBill($student, makeBillType('Old SPP Promotion Safety'), 970000);
    $service = app(ClassPromotionService::class);

    $preview = $service->getPreviewData($sourceYear, $targetYear);
    $result = $service->processPromotion($sourceYear, $targetYear);

    expect($preview['students'])->toBeEmpty()
        ->and($result)->toBe(['promoted' => 0, 'graduated' => 0, 'blocked' => 0])
        ->and($student->fresh()->status)->toBe(StudentStatus::from($status))
        ->and($student->fresh()->class_id)->toBe($sourceClass->id)
        ->and(StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $targetYear->id)
            ->exists())->toBeFalse()
        ->and($student->bills()->sole()->is($oldBill))->toBeTrue();
})->with([
    'graduated' => StudentStatus::Graduated->value,
    'transferred' => StudentStatus::Transferred->value,
]);

it('prevents direct promotion reactivation before any mutation', function () {
    [$sourceYear, $targetYear] = inactiveSafetyAcademicYears();
    $sourceClass = SchoolClass::create(['name' => 'VII Inner Guard', 'level' => 7]);
    $targetClass = SchoolClass::create(['name' => 'VIII Inner Guard', 'level' => 8]);
    $student = Student::factory()->create([
        'class_id' => $sourceClass->id,
        'status' => StudentStatus::Graduated,
    ]);
    $service = new class(app(BillGenerationService::class)) extends ClassPromotionService
    {
        public function invokePromotion(Student $student, SchoolClass $targetClass, AcademicYear $fromYear, AcademicYear $toYear): void
        {
            $this->promoteStudent($student, $targetClass, $fromYear, $toYear);
        }
    };

    $service->invokePromotion($student, $targetClass, $sourceYear, $targetYear);

    expect($student->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and($student->fresh()->class_id)->toBe($sourceClass->id)
        ->and($student->enrollments()->count())->toBe(0)
        ->and($student->bills()->count())->toBe(0);
});

it('preserves active promotion behavior', function () {
    [$sourceYear, $targetYear] = inactiveSafetyAcademicYears();
    $sourceClass = SchoolClass::create(['name' => 'VII Active Safety', 'level' => 7]);
    $targetClass = SchoolClass::create(['name' => 'VIII Active Safety', 'level' => 8]);
    createPromotionRule($sourceClass, 'promote', $targetClass);
    $student = Student::factory()->create(['class_id' => $sourceClass->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $sourceYear->id,
        'school_class_id' => $sourceClass->id,
        'status' => 'active',
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($sourceYear, $targetYear);

    expect($result['promoted'])->toBe(1)
        ->and($student->fresh()->status)->toBe(StudentStatus::Active)
        ->and($student->fresh()->class_id)->toBe($targetClass->id)
        ->and(StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $targetYear->id)
            ->where('status', 'active')
            ->exists())->toBeTrue();
});

it('preserves active graduation without generating bills', function () {
    [$sourceYear, $targetYear] = inactiveSafetyAcademicYears();
    $finalClass = SchoolClass::create(['name' => 'VI Graduation Safety', 'level' => 6]);
    createPromotionRule($finalClass, 'graduate');
    $student = Student::factory()->create(['class_id' => $finalClass->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $sourceYear->id,
        'school_class_id' => $finalClass->id,
        'status' => 'active',
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($sourceYear, $targetYear);

    expect($result['graduated'])->toBe(1)
        ->and($student->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and(StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $targetYear->id)
            ->where('status', 'lulus')
            ->exists())->toBeTrue()
        ->and($student->bills()->count())->toBe(0);
});

it('hides and rejects automatic generation UI while preserving manual controls', function (string $status) {
    AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $user = User::factory()->create();
    $student = makeBillStudent(8);
    $student->update(['status' => $status]);
    $type = makeBillType('Old UI Safety '.$status);
    $oldBill = makeMonthlyBill($student, $type, 970000);
    Setting::set(BillbookPeriod::SETTING_START_MONTH, '2026-07');
    Livewire::actingAs($user);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSee('Lengkapi Tagihan')
        ->assertSee('Tambah Tagihan')
        ->assertSee($type->name)
        ->call('openBillbook')
        ->assertSet('isBillbookOpen', false)
        ->set('billbookStartMonth', '2026-09')
        ->call('generateBillbook')
        ->call('generateBills')
        ->call('openGenerateUntil')
        ->assertSet('isGenerateUntilOpen', false);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->assertDontSee('Generate Tagihan')
        ->call('generateBills');

    expect(Setting::get(BillbookPeriod::SETTING_START_MONTH))->toBe('2026-07')
        ->and($student->bills()->sole()->is($oldBill))->toBeTrue();
})->with([
    'graduated' => StudentStatus::Graduated->value,
    'transferred' => StudentStatus::Transferred->value,
]);

it('keeps automatic generation controls visible for active students', function () {
    $user = User::factory()->create();
    $student = makeBillStudent(8);
    Livewire::actingAs($user);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Lengkapi Tagihan')
        ->assertSee('Tambah Tagihan');

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->assertSee('Generate Tagihan');
});

it('allows intentional manual historical bills for inactive students', function (string $status) {
    $academicYear = AcademicYear::create([
        'year' => '2025/2026',
        'is_active' => false,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);
    $student = makeBillStudent(8);
    $student->update(['status' => $status]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $student->class_id,
        'status' => 'active',
    ]);
    $catalog = manualAddCatalog(8);
    $type = $catalog['Lain-lain'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $type->id)
        ->set('addAcademicYear', $academicYear->year)
        ->set('addAmount', '250000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    expect($student->bills()
        ->where('payment_type_id', $type->id)
        ->where('billing_frequency', BillFrequency::OneTime->value)
        ->where('academic_year', $academicYear->year)
        ->where('amount', 250000)
        ->exists())->toBeTrue();
})->with([
    'graduated' => StudentStatus::Graduated->value,
    'transferred' => StudentStatus::Transferred->value,
]);

it('keeps an old bill searchable and payable after the student graduates', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent(8);
    $type = makeBillType('Old Payable SPP');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
    $student->update(['status' => StudentStatus::Graduated]);
    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->set('student_search', $student->nis)
        ->assertSee($student->nama_lengkap);

    $component = Livewire::withQueryParams(['student' => $student->id])
        ->test(PaymentCreate::class)
        ->assertSee($type->name)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-22')
        ->call('save')
        ->assertHasNoErrors();

    $payment = Payment::query()->where('student_id', $student->id)->sole();

    $component->assertRedirect(route('pembayaran.show', ['id' => $payment->id]));
    expect($student->bills()->count())->toBe(1)
        ->and($bill->fresh()->remaining_amount)->toBe(0.0)
        ->and($payment->details()->where('bill_id', $bill->id)->exists())->toBeTrue();
});
