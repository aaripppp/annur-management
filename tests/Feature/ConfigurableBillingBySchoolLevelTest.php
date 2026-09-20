<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use App\Services\StudentCreationService;
use App\Support\BillbookPeriod;
use Carbon\Carbon;

function configurableAcademicYear(string $year, bool $active): AcademicYear
{
    $startYear = (int) substr($year, 0, 4);

    return AcademicYear::updateOrCreate(
        ['year' => $year],
        [
            'is_active' => $active,
            'start_date' => $startYear.'-07-01',
            'end_date' => ($startYear + 1).'-06-30',
        ]
    );
}

function configurableEnrolledStudent(int $level, AcademicYear $academicYear, ?string $nis = null): Student
{
    $schoolClass = SchoolClass::factory()->create(['level' => $level]);
    $student = Student::factory()->create([
        'nis' => $nis ?? fake()->unique()->numerify('CFG-#####'),
        'class_id' => $schoolClass->id,
        'created_at' => $academicYear->start_date,
    ]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return $student;
}

function configurableStudentData(SchoolClass $schoolClass, AcademicYear $academicYear, string $nis): array
{
    return [
        ...Student::factory()->raw([
            'nis' => $nis,
            'class_id' => $schoolClass->id,
        ]),
        'entry_academic_year_id' => $academicYear->id,
    ];
}

it('persists a nullable entry date and casts a provided value as a date', function () {
    $withoutEntryDate = Student::factory()->create();
    $withEntryDate = Student::factory()->create(['entry_date' => '2026-10-15']);

    expect($withoutEntryDate->entry_date)->toBeNull()
        ->and($withEntryDate->entry_date->toDateString())->toBe('2026-10-15');
});

it('uses the student entry month for all monthly types without changing yearly or one-time initialization', function (?string $entryDate, int $firstMonth, int $expectedMonthlyCount) {
    $this->travelTo('2026-08-20');

    $activeYear = configurableAcademicYear('2026/2027', true);
    BillbookPeriod::setStartMonth(Carbon::parse('2026-07-01'));
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $monthlyTypes = [makeBillType('SPP Entry Date'), makeBillType('Ekskul Entry Date')];
    $yearly = makeBillType('Program Tahunan Entry Date');
    $oneTime = makeBillType('Perlengkapan Entry Date');

    foreach ([...$monthlyTypes, $yearly, $oneTime] as $type) {
        makeLevelDefault($type, SchoolLevel::SMA, required: false);
    }

    foreach ($monthlyTypes as $type) {
        makeBillRate($type, 10, 125000, ['billing_frequency' => BillFrequency::Monthly]);
    }

    makeBillRate($yearly, 10, 850000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($oneTime, 10, 1500000, ['billing_frequency' => BillFrequency::OneTime]);

    $data = configurableStudentData($schoolClass, $activeYear, 'CFG-ENTRY-DATE');
    $data['entry_date'] = $entryDate;
    $student = app(StudentCreationService::class)->create($data);

    foreach ($monthlyTypes as $type) {
        $bills = $student->bills()
            ->where('payment_type_id', $type->id)
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        expect($bills)->toHaveCount($expectedMonthlyCount)
            ->and($bills->first()->period_month)->toBe($firstMonth)
            ->and($student->bills()->where('payment_type_id', $type->id)->where('period_month', $firstMonth)->where('period_year', 2026)->exists())->toBeTrue();
    }

    expect($student->entry_date?->toDateString())->toBe($entryDate ?? '2026-08-20')
        ->and($student->bills()->where('payment_type_id', $yearly->id)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $oneTime->id)->count())->toBe(1);
})->with([
    'null falls back to August creation month' => [null, 8, 11],
    'August 5 includes August' => ['2026-08-05', 8, 11],
    'August 31 includes August' => ['2026-08-31', 8, 11],
    'October excludes August and September' => ['2026-10-01', 10, 9],
    'June entry starts in June' => ['2026-06-15', 6, 1],
]);

it('supports a future entry date while preserving future yearly and one-time initialization', function () {
    $this->travelTo('2026-08-20');

    configurableAcademicYear('2026/2027', true);
    $futureYear = configurableAcademicYear('2027/2028', false);
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $monthly = makeBillType('Future Entry Monthly');
    $yearly = makeBillType('Future Entry Yearly');
    $oneTime = makeBillType('Future Entry One Time');

    foreach ([$monthly, $yearly, $oneTime] as $type) {
        makeLevelDefault($type, SchoolLevel::SMA, required: false);
    }

    makeBillRate($monthly, 10, 125000, ['billing_frequency' => BillFrequency::Monthly, 'effective_from' => '2027-07-01']);
    makeBillRate($yearly, 10, 850000, ['billing_frequency' => BillFrequency::Yearly, 'effective_from' => '2027-07-01']);
    makeBillRate($oneTime, 10, 1500000, ['billing_frequency' => BillFrequency::OneTime, 'effective_from' => '2027-07-01']);

    $data = configurableStudentData($schoolClass, $futureYear, 'CFG-FUTURE-ENTRY');
    $data['entry_date'] = '2027-10-01';
    $creationService = app(StudentCreationService::class);
    $student = $creationService->create($data);
    $creationService->initializeAcademicEntry(
        $student,
        $futureYear,
        $futureYear->start_date,
        true,
        true,
        Carbon::parse('2027-10-01'),
    );

    expect($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2027)->exists())->toBeTrue()
        ->and($student->bills()->where('payment_type_id', $yearly->id)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $oneTime->id)->count())->toBe(1)
        ->and($student->enrollments()->where('academic_year_id', $futureYear->id)->count())->toBe(1);

    app(BillGenerationService::class)->generateMonthlyForAcademicYear($futureYear);
    app(BillGenerationService::class)->generateMonthlyForAcademicYear($futureYear);

    expect($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(10)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2027)->exists())->toBeTrue()
        ->and($student->bills()->where('payment_type_id', $monthly->id)->whereIn('period_month', [8, 9])->exists())->toBeFalse()
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 10)->where('period_year', 2027)->exists())->toBeTrue()
        ->and($student->bills()->where('payment_type_id', $yearly->id)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $oneTime->id)->count())->toBe(1);
});

it('respects rate effective dates after the student entry month', function () {
    $this->travelTo('2026-08-20');

    $activeYear = configurableAcademicYear('2026/2027', true);
    BillbookPeriod::setStartMonth(Carbon::parse('2026-07-01'));
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $monthly = makeBillType('Entry Date Rate Window');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 125000, [
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2026-11-01',
    ]);
    $data = configurableStudentData($schoolClass, $activeYear, 'CFG-ENTRY-RATE');
    $data['entry_date'] = '2026-10-01';

    $student = app(StudentCreationService::class)->create($data);

    expect($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 10)->exists())->toBeFalse()
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 11)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(8);
});

it('initializes arbitrary configured billing for active and future Grade 10 students', function () {
    $this->travelTo('2026-08-24');

    $activeYear = configurableAcademicYear('2026/2027', true);
    $futureYear = configurableAcademicYear('2027/2028', false);
    $monthly = makeBillType('Iuran Digital');
    $yearly = makeBillType('Program Tahunan');
    $oneTime = makeBillType('Biaya Perlengkapan');

    foreach ([$monthly, $yearly, $oneTime] as $type) {
        makeLevelDefault($type, SchoolLevel::SMA, required: false);
    }

    makeBillRate($monthly, 10, 125000, ['billing_frequency' => BillFrequency::Monthly]);
    makeBillRate($yearly, 10, 850000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($oneTime, 10, 1500000, ['billing_frequency' => BillFrequency::OneTime]);

    $activeClass = SchoolClass::factory()->create(['level' => 10]);
    $futureClass = SchoolClass::factory()->create(['level' => 10]);
    $service = app(StudentCreationService::class);
    $activeStudent = $service->create(configurableStudentData($activeClass, $activeYear, 'CFG-ACTIVE-10'));
    $futureStudent = $service->create(configurableStudentData($futureClass, $futureYear, 'CFG-FUTURE-10'));

    expect($activeStudent->bills()->where('payment_type_id', $monthly->id)->count())->toBe(11)
        ->and($activeStudent->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2026)->exists())->toBeFalse()
        ->and($activeStudent->bills()->where('payment_type_id', $monthly->id)->where('period_month', 8)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($activeStudent->bills()->where('payment_type_id', $yearly->id)->count())->toBe(1)
        ->and($activeStudent->bills()->where('payment_type_id', $oneTime->id)->count())->toBe(1)
        ->and($futureStudent->bills()->where('payment_type_id', $monthly->id)->count())->toBe(1)
        ->and($futureStudent->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2027)->exists())->toBeTrue()
        ->and($futureStudent->bills()->where('payment_type_id', $yearly->id)->count())->toBe(1)
        ->and($futureStudent->bills()->where('payment_type_id', $oneTime->id)->count())->toBe(1)
        ->and($activeStudent->paymentSettings()->whereIn('payment_type_id', [$monthly->id, $yearly->id, $oneTime->id])->count())->toBe(3)
        ->and($activeStudent->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-08-01')
        ->and($futureStudent->paymentSettings()->whereIn('payment_type_id', [$monthly->id, $yearly->id, $oneTime->id])->count())->toBe(3);
});

it('does not initialize active billing before the student entry month without an explicit entry year', function () {
    $this->travelTo('2026-08-24');

    configurableAcademicYear('2026/2027', true);
    BillbookPeriod::setStartMonth(Carbon::parse('2026-07-01'));
    $monthly = makeBillType('Iuran Digital Default Entry');
    makeLevelDefault($monthly, SchoolLevel::SMA, required: false);
    makeBillRate($monthly, 10, 125000, ['billing_frequency' => BillFrequency::Monthly]);
    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $data = configurableStudentData($schoolClass, AcademicYear::query()->where('is_active', true)->sole(), 'CFG-ACTIVE-DEFAULT');
    unset($data['entry_academic_year_id']);

    $student = app(StudentCreationService::class)->create($data);

    expect($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(11)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2026)->exists())->toBeFalse()
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 8)->where('period_year', 2026)->exists())->toBeTrue()
        ->and($student->paymentSettings()->where('payment_type_id', $monthly->id)->sole()->started_at->toDateString())->toBe('2026-08-01');
});

it('initialization respects inactive settings and is idempotent', function () {
    $this->travelTo('2026-08-24');

    $activeYear = configurableAcademicYear('2026/2027', true);
    $monthly = makeBillType('Iuran Digital Nonaktif');
    $yearly = makeBillType('Program Tahunan Idempotent');
    $oneTime = makeBillType('Perlengkapan Idempotent');

    foreach ([$monthly, $yearly, $oneTime] as $type) {
        makeLevelDefault($type, SchoolLevel::SMA, required: false);
    }

    makeBillRate($monthly, 10, 125000, ['billing_frequency' => BillFrequency::Monthly]);
    makeBillRate($yearly, 10, 850000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($oneTime, 10, 1500000, ['billing_frequency' => BillFrequency::OneTime]);

    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $student = Student::factory()->create(['class_id' => $schoolClass->id]);
    $inactiveSetting = makeActiveSetting($student, $monthly, false);
    $service = app(StudentCreationService::class);

    $service->initializeAcademicEntry($student, $activeYear, $activeYear->start_date, false, true);
    $service->initializeAcademicEntry($student, $activeYear, $activeYear->start_date, false, true);

    expect($student->bills()->where('payment_type_id', $monthly->id)->count())->toBe(0)
        ->and($student->bills()->where('payment_type_id', $yearly->id)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $oneTime->id)->count())->toBe(1)
        ->and($student->bills()->count())->toBe(2)
        ->and($student->enrollments()->where('academic_year_id', $activeYear->id)->count())->toBe(1)
        ->and($inactiveSetting->fresh()->is_active)->toBeFalse();
});

it('student creation skips inactive configuration and unavailable rates', function () {
    $this->travelTo('2026-08-24');

    $activeYear = configurableAcademicYear('2026/2027', true);
    $valid = makeBillType('Konfigurasi Valid');
    makeLevelDefault($valid, SchoolLevel::SMA, required: false);
    makeBillRate($valid, 10, 125000, ['billing_frequency' => BillFrequency::Monthly]);

    $inactiveMapping = makeBillType('Mapping Tidak Aktif');
    makeLevelDefault($inactiveMapping, SchoolLevel::SMA, required: false, active: false);
    makeBillRate($inactiveMapping, 10, 125000, ['billing_frequency' => BillFrequency::Monthly]);

    $inactiveType = makeBillType('Tipe Tidak Aktif');
    $inactiveType->update(['is_active' => false]);
    makeLevelDefault($inactiveType, SchoolLevel::SMA, required: false);
    makeBillRate($inactiveType, 10, 125000, ['billing_frequency' => BillFrequency::Monthly]);

    $missingRate = makeBillType('Tarif Tidak Ada');
    makeLevelDefault($missingRate, SchoolLevel::SMA, required: false);

    $expiredRate = makeBillType('Tarif Sudah Berakhir');
    makeLevelDefault($expiredRate, SchoolLevel::SMA, required: false);
    makeBillRate($expiredRate, 10, 125000, [
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2025-01-01',
        'effective_until' => '2025-12-31',
    ]);

    $schoolClass = SchoolClass::factory()->create(['level' => 10]);
    $student = app(StudentCreationService::class)->create(configurableStudentData($schoolClass, $activeYear, 'CFG-NEGATIVE-10'));
    $invalidTypeIds = [$inactiveMapping->id, $inactiveType->id, $missingRate->id, $expiredRate->id];

    expect($student->bills()->where('payment_type_id', $valid->id)->count())->toBe(11)
        ->and($student->bills()->whereIn('payment_type_id', $invalidTypeIds)->count())->toBe(0)
        ->and($student->paymentSettings()->whereIn('payment_type_id', [$inactiveMapping->id, $inactiveType->id])->count())->toBe(0);
});

it('generates an arbitrary monthly type only for configured jenjang and backfills missing settings', function () {
    $academicYear = configurableAcademicYear('2026/2027', true);
    $tkStudent = configurableEnrolledStudent(-1, $academicYear);
    $sdStudent = configurableEnrolledStudent(5, $academicYear);
    $smpStudent = configurableEnrolledStudent(7, $academicYear);
    $smaStudent = configurableEnrolledStudent(10, $academicYear);
    $type = makeBillType('Iuran Digital');
    makeLevelDefault($type, SchoolLevel::SMP, required: false);
    makeLevelDefault($type, SchoolLevel::SMA, required: false);

    foreach ([7, 8, 9] as $level) {
        makeBillRate($type, $level, 100000, ['billing_frequency' => BillFrequency::Monthly]);
    }
    foreach ([10, 11, 12] as $level) {
        makeBillRate($type, $level, 150000, ['billing_frequency' => BillFrequency::Monthly]);
    }

    $service = app(BillGenerationService::class);
    $preview = $service->getMonthlyGenerationPreview($academicYear);

    expect($preview['will_create'])->toBe(24)
        ->and(StudentPaymentSetting::where('payment_type_id', $type->id)->count())->toBe(0);

    $result = $service->generateMonthlyForAcademicYear($academicYear);

    expect($result['created'])->toBe(24)
        ->and($smpStudent->bills()->where('payment_type_id', $type->id)->count())->toBe(12)
        ->and($smaStudent->bills()->where('payment_type_id', $type->id)->count())->toBe(12)
        ->and($sdStudent->bills()->where('payment_type_id', $type->id)->count())->toBe(0)
        ->and($tkStudent->bills()->where('payment_type_id', $type->id)->count())->toBe(0)
        ->and(StudentPaymentSetting::where('payment_type_id', $type->id)->count())->toBe(2);

    $service->generateMonthlyForAcademicYear($academicYear);

    expect(StudentBill::where('payment_type_id', $type->id)->count())->toBe(24)
        ->and(StudentPaymentSetting::where('payment_type_id', $type->id)->count())->toBe(2);
});

it('respects an inactive per-student setting for an automatic monthly type', function () {
    $academicYear = configurableAcademicYear('2026/2027', true);
    $student = configurableEnrolledStudent(7, $academicYear);
    $type = makeBillType('Iuran Digital');
    makeLevelDefault($type, SchoolLevel::SMP, required: false);
    makeBillRate($type, 7, 100000, ['billing_frequency' => BillFrequency::Monthly]);
    $setting = makeActiveSetting($student, $type, false);

    $service = app(BillGenerationService::class);
    $preview = $service->getMonthlyGenerationPreview($academicYear);
    $service->generateMonthlyForAcademicYear($academicYear);

    expect($preview['will_create'])->toBe(0)
        ->and($student->bills()->where('payment_type_id', $type->id)->count())->toBe(0)
        ->and($setting->fresh()->is_active)->toBeFalse();
});

it('generates arbitrary yearly billing by configuration without duplicates', function () {
    $academicYear = configurableAcademicYear('2026/2027', true);
    $schoolClass = SchoolClass::factory()->create(['level' => 7]);
    $type = makeBillType('Program Tahunan');
    makeLevelDefault($type, SchoolLevel::SMP, required: false);
    makeBillRate($type, 7, 900000, ['billing_frequency' => BillFrequency::Yearly]);

    $student = app(StudentCreationService::class)->create(configurableStudentData($schoolClass, $academicYear, 'CFG-YEARLY'));
    app(BillGenerationService::class)->generateBillbook($student, $academicYear->start_date);

    $bills = $student->bills()->where('payment_type_id', $type->id)->get();

    expect($bills)->toHaveCount(1)
        ->and($bills->first()->billing_frequency)->toBe(BillFrequency::Yearly->value)
        ->and($bills->first()->academic_year)->toBe($academicYear->year)
        ->and((float) $bills->first()->amount)->toBe(900000.0);
});

it('generates arbitrary one-time billing once per Student ID including same NIS across jenjang', function () {
    $academicYear = configurableAcademicYear('2026/2027', true);
    $sdClass = SchoolClass::factory()->create(['level' => 6]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $type = makeBillType('Biaya Perlengkapan');
    makeLevelDefault($type, SchoolLevel::SD, required: false);
    makeLevelDefault($type, SchoolLevel::SMP, required: false);
    makeBillRate($type, 6, 750000, ['billing_frequency' => BillFrequency::OneTime]);
    makeBillRate($type, 7, 1000000, ['billing_frequency' => BillFrequency::OneTime]);

    $oldStudent = app(StudentCreationService::class)->create(configurableStudentData($sdClass, $academicYear, 'CFG-SAME-NIS'));
    $newStudent = app(StudentCreationService::class)->create(configurableStudentData($smpClass, $academicYear, 'CFG-SAME-NIS'));
    $service = app(BillGenerationService::class);
    $service->generateBillbook($oldStudent, $academicYear->start_date);
    $service->generateBillbook($newStudent, $academicYear->start_date);

    expect($oldStudent->id)->not->toBe($newStudent->id)
        ->and($oldStudent->bills()->where('payment_type_id', $type->id)->count())->toBe(1)
        ->and($newStudent->bills()->where('payment_type_id', $type->id)->count())->toBe(1);
});

it('creates only July monthly for calon students while generating configured yearly and one-time bills', function () {
    $activeYear = configurableAcademicYear('2026/2027', true);
    $futureYear = configurableAcademicYear('2027/2028', false);
    $schoolClass = SchoolClass::factory()->create(['level' => 7]);
    $monthly = makeBillType('Layanan Bulanan Baru');
    $yearly = makeBillType('Program Tahunan Baru');
    $oneTime = makeBillType('Perlengkapan Baru');

    foreach ([$monthly, $yearly, $oneTime] as $type) {
        makeLevelDefault($type, SchoolLevel::SMP, required: false);
    }
    makeBillRate($monthly, 7, 100000, ['billing_frequency' => BillFrequency::Monthly]);
    makeBillRate($yearly, 7, 900000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($oneTime, 7, 1200000, ['billing_frequency' => BillFrequency::OneTime]);

    $student = app(StudentCreationService::class)->create(configurableStudentData($schoolClass, $futureYear, 'CFG-FUTURE'));

    expect($student->bills()->where('billing_frequency', BillFrequency::Monthly)->count())->toBe(1)
        ->and($student->bills()->where('payment_type_id', $monthly->id)->where('period_month', 7)->where('period_year', 2027)->exists())->toBeTrue()
        ->and($student->bills()->where('billing_frequency', BillFrequency::Yearly)->count())->toBe(1)
        ->and($student->bills()->where('billing_frequency', BillFrequency::OneTime)->count())->toBe(1)
        ->and($activeYear->is_active)->toBeTrue();

    app(BillGenerationService::class)->generateMonthlyForAcademicYear($futureYear);

    expect($student->bills()->where('billing_frequency', BillFrequency::Monthly)->count())->toBe(12);
});

it('skips inactive types inactive mappings and unavailable rates without changing historical bills', function () {
    $academicYear = configurableAcademicYear('2026/2027', true);
    $student = configurableEnrolledStudent(7, $academicYear);
    $inactiveType = makeBillType('Tipe Nonaktif');
    $inactiveType->update(['is_active' => false]);
    makeLevelDefault($inactiveType, SchoolLevel::SMP, required: false);
    makeBillRate($inactiveType, 7, 100000, ['billing_frequency' => BillFrequency::Monthly]);

    $inactiveMappingType = makeBillType('Mapping Nonaktif');
    makeLevelDefault($inactiveMappingType, SchoolLevel::SMP, required: false, active: false);
    makeBillRate($inactiveMappingType, 7, 100000, ['billing_frequency' => BillFrequency::Monthly]);

    $missingRateType = makeBillType('Tanpa Tarif');
    makeLevelDefault($missingRateType, SchoolLevel::SMP, required: false);

    $expiredRateType = makeBillType('Tarif Kedaluwarsa');
    makeLevelDefault($expiredRateType, SchoolLevel::SMP, required: false);
    makeBillRate($expiredRateType, 7, 100000, [
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2025-01-01',
        'effective_until' => '2025-12-31',
    ]);
    $historicalBill = makeMonthlyBill($student, $inactiveType, 87500, 6, 2026);

    app(BillGenerationService::class)->generateMonthlyForAcademicYear($academicYear);

    expect($student->bills()->whereIn('payment_type_id', [
        $inactiveMappingType->id,
        $missingRateType->id,
        $expiredRateType->id,
    ])->count())->toBe(0)
        ->and($historicalBill->fresh()->amount)->toBe('87500.00');
});

it('backfills legacy standard applicability idempotently without overwriting existing mappings', function () {
    $book = makeBillType('Uang Buku');
    $activity = makeBillType('Uang Kegiatan');
    $entry = makeBillType('Uang Pangkal');
    $existing = PaymentTypeSchoolLevel::create([
        'payment_type_id' => $book->id,
        'school_level' => SchoolLevel::SMP,
        'is_required' => true,
        'is_active' => false,
    ]);

    $this->artisan('billing:backfill-legacy-applicability')->assertSuccessful();
    $this->artisan('billing:backfill-legacy-applicability')->assertSuccessful();

    expect(PaymentTypeSchoolLevel::whereIn('payment_type_id', [$book->id, $activity->id, $entry->id])->count())->toBe(12)
        ->and($existing->fresh()->is_active)->toBeFalse()
        ->and($existing->fresh()->is_required)->toBeTrue();
});
