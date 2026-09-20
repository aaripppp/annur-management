<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Services\BillGenerationService;
use Carbon\Carbon;

function sppBackfill(int $classLevel, SchoolLevel $schoolLevel, int $amount): PaymentType
{
    $spp = PaymentType::create([
        'name' => 'SPP Reg',
        'is_active' => true,
        'is_auto_enrolled' => true,
        'is_required' => true,
    ]);

    PaymentRate::factory()->create([
        'payment_type_id' => $spp->id,
        'class_level' => $classLevel,
        'amount' => $amount,
        'is_monthly' => true,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $spp->id,
        'school_level' => $schoolLevel,
        'is_required' => true,
        'is_active' => true,
    ]);

    return $spp;
}

function ensureYear(string $label, bool $active, string $start, string $end): AcademicYear
{
    return AcademicYear::updateOrCreate(
        ['year' => $label],
        ['is_active' => $active, 'start_date' => $start, 'end_date' => $end],
    );
}

beforeEach(function () {
    $this->travelTo('2026-08-15');
});

it('does not create bill before started_at via generateMonthlyForAcademicYear', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');
    ensureYear('2027/2028', false, '2027-07-01', '2028-06-30');

    $class = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $spp = sppBackfill(7, SchoolLevel::SMP, 950000);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp->id],
        ['is_active' => true, 'started_at' => '2026-08-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    $service = app(BillGenerationService::class);
    $service->generateMonthlyForAcademicYear($year);

    expect(StudentBill::where('student_id', $student->id)
        ->where('period_month', 7)->where('period_year', 2026)
        ->exists())->toBeFalse('No bill should exist for July 2026');
    expect(StudentBill::where('student_id', $student->id)
        ->where('period_month', 8)->where('period_year', 2026)
        ->exists())->toBeTrue('Bill should exist for August 2026');

    $service->generateMonthlyForAcademicYear($year);

    expect(StudentBill::where('student_id', $student->id)
        ->where('period_month', 7)->where('period_year', 2026)
        ->exists())->toBeFalse('Idempotent re-generate must not create July');
});

it('does not create bill before started_at via generateBillbook', function () {
    $class = SchoolClass::factory()->create(['level' => 8]);
    $spp = sppBackfill(8, SchoolLevel::SMP, 970000);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp->id],
        ['is_active' => true, 'started_at' => '2026-08-01'],
    );

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-08-01'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('period_month', 7)->where('period_year', 2026)
        ->exists())->toBeFalse('generateBillbook must not create July 2026');
    expect(StudentBill::where('student_id', $student->id)
        ->where('period_month', 8)->where('period_year', 2026)
        ->exists())->toBeTrue('Bill should exist for August 2026');
});

it('uses enrollment class level not current class_id for past year generation', function () {
    $year2026 = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');
    $year2027 = ensureYear('2027/2028', false, '2027-07-01', '2028-06-30');

    $classVII = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $classVIII = SchoolClass::factory()->create(['name' => 'VIII A', 'level' => 8]);
    $spp7 = sppBackfill(7, SchoolLevel::SMP, 950000);
    $spp8 = sppBackfill(8, SchoolLevel::SMP, 980000);

    $student = Student::factory()->create(['class_id' => $classVII->id]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp7->id],
        ['is_active' => true, 'started_at' => '2026-08-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year2026->id,
        'school_class_id' => $classVII->id,
        'status' => 'active',
    ]);

    $student->update(['class_id' => $classVIII->id]);
    $student->refresh();
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year2027->id,
        'school_class_id' => $classVIII->id,
        'status' => 'active',
    ]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp8->id],
        ['is_active' => true, 'started_at' => '2027-07-01'],
    );

    $service = app(BillGenerationService::class);
    $service->generateMonthlyForAcademicYear($year2026);

    $bill2026 = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp7->id)
        ->where('period_month', 8)->where('period_year', 2026)->first();
    $wrongBill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp8->id)
        ->where('period_month', 8)->where('period_year', 2026)->first();

    expect($bill2026)->not->toBeNull('2026/2027 bill must use SPP level 7 tariff')
        ->and((int) $bill2026->amount)->toBe(950000);
    expect($wrongBill)->toBeNull('No SPP level 8 bill for 2026/2027 year');

    $service->generateMonthlyForAcademicYear($year2027);

    $bill2027 = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp8->id)
        ->where('period_month', 7)->where('period_year', 2027)->first();

    expect($bill2027)->not->toBeNull('2027/2028 bill must use SPP level 8 tariff')
        ->and((int) $bill2027->amount)->toBe(980000);
});

it('preview uses enrollment class level not current class_id', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');

    $classVII = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $classVIII = SchoolClass::factory()->create(['name' => 'VIII A', 'level' => 8]);
    $spp7 = sppBackfill(7, SchoolLevel::SMP, 950000);
    sppBackfill(8, SchoolLevel::SMP, 980000);

    $student = Student::factory()->create(['class_id' => $classVII->id]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp7->id],
        ['is_active' => true, 'started_at' => '2026-08-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $classVII->id,
        'status' => 'active',
    ]);

    $student->update(['class_id' => $classVIII->id]);
    $student->refresh();

    $preview = app(BillGenerationService::class)->getMonthlyGenerationPreview($year);
    $tariffLevels = array_column($preview['tariffs'], 'class_level');

    expect(in_array(7, $tariffLevels))->toBeTrue('Preview must show level 7 tariffs')
        ->and(in_array(8, $tariffLevels))->toBeFalse('Preview must not show level 8 tariffs');
});

it('respects different started_at per payment type', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');

    $class = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $spp = sppBackfill(7, SchoolLevel::SMP, 950000);

    $osis = PaymentType::create([
        'name' => 'OSIS Reg',
        'is_active' => true,
        'is_auto_enrolled' => true,
        'is_required' => false,
    ]);
    PaymentRate::factory()->create([
        'payment_type_id' => $osis->id,
        'class_level' => 7,
        'amount' => 200000,
        'is_monthly' => true,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);
    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $osis->id,
        'school_level' => SchoolLevel::SMP,
        'is_required' => false,
        'is_active' => true,
    ]);

    $student = Student::factory()->create([
        'class_id' => $class->id,
        'created_at' => '2026-06-01',
    ]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $osis->id],
        ['is_active' => true, 'started_at' => '2026-09-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    app(BillGenerationService::class)->generateMonthlyForAcademicYear($year);

    $sppCount = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('period_year', 2026)->count();
    $osisCount = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $osis->id)
        ->where('period_year', 2026)->count();

    expect($sppCount)->toBe(6, 'SPP started Jul => Jul-Dec 2026 (6 bills)')
        ->and($osisCount)->toBe(4, 'OSIS started Sep => Sep-Dec 2026 (4 bills)');
});

it('shows only level 7 tariffs when only level 7 students enrolled', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');

    $classVII = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $classVIII = SchoolClass::factory()->create(['name' => 'VIII A', 'level' => 8]);
    $spp7 = sppBackfill(7, SchoolLevel::SMP, 950000);
    sppBackfill(8, SchoolLevel::SMP, 980000);

    $student = Student::factory()->create(['class_id' => $classVII->id]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp7->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $classVII->id,
        'status' => 'active',
    ]);

    $preview = app(BillGenerationService::class)->getMonthlyGenerationPreview($year);
    $levels = array_column($preview['tariffs'], 'class_level');

    expect($levels)->toBe([7], 'Only level 7 tariffs should be displayed');
});

it('shows level 7 and 8 tariffs when both levels have enrollments', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');

    $classVII = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $classVIII = SchoolClass::factory()->create(['name' => 'VIII A', 'level' => 8]);
    $spp7 = sppBackfill(7, SchoolLevel::SMP, 950000);
    $spp8 = sppBackfill(8, SchoolLevel::SMP, 980000);

    $s1 = Student::factory()->create(['class_id' => $classVII->id]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $s1->id, 'payment_type_id' => $spp7->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $s1->id, 'academic_year_id' => $year->id,
        'school_class_id' => $classVII->id, 'status' => 'active',
    ]);

    $s2 = Student::factory()->create(['class_id' => $classVIII->id]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $s2->id, 'payment_type_id' => $spp8->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $s2->id, 'academic_year_id' => $year->id,
        'school_class_id' => $classVIII->id, 'status' => 'active',
    ]);

    $preview = app(BillGenerationService::class)->getMonthlyGenerationPreview($year);
    $levels = array_column($preview['tariffs'], 'class_level');

    expect($levels)->toBe([7, 8], 'Both level 7 and 8 tariffs should be displayed');
});

it('does not show unused level 11 tariffs in preview', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');

    $classVII = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $classXI = SchoolClass::factory()->create(['name' => 'XI A', 'level' => 11]);
    $spp7 = sppBackfill(7, SchoolLevel::SMP, 950000);
    sppBackfill(11, SchoolLevel::SMA, 1100000);

    $student = Student::factory()->create(['class_id' => $classVII->id]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp7->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'school_class_id' => $classVII->id, 'status' => 'active',
    ]);

    $preview = app(BillGenerationService::class)->getMonthlyGenerationPreview($year);
    $levels = array_column($preview['tariffs'], 'class_level');

    expect($levels)->toBe([7], 'Level 11 must not appear when no students enrolled there');
});

it('does not show yearly or one-time tariffs in monthly generation preview', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');

    $class = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $spp = sppBackfill(7, SchoolLevel::SMP, 950000);

    $yearlyType = PaymentType::create([
        'name' => 'Uang Buku', 'is_active' => true,
        'is_auto_enrolled' => false, 'is_required' => false,
    ]);
    PaymentRate::factory()->create([
        'payment_type_id' => $yearlyType->id, 'class_level' => 7,
        'amount' => 500000, 'is_monthly' => false,
        'billing_frequency' => BillFrequency::Yearly,
        'effective_from' => '2026-01-01', 'effective_until' => null,
    ]);
    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $yearlyType->id, 'school_level' => SchoolLevel::SMP,
        'is_required' => false, 'is_active' => true,
    ]);

    $oneTimeType = PaymentType::create([
        'name' => 'Uang Pangkal', 'is_active' => true,
        'is_auto_enrolled' => false, 'is_required' => false,
    ]);
    PaymentRate::factory()->create([
        'payment_type_id' => $oneTimeType->id, 'class_level' => 7,
        'amount' => 5000000, 'is_monthly' => false,
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => '2026-01-01', 'effective_until' => null,
    ]);
    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $oneTimeType->id, 'school_level' => SchoolLevel::SMP,
        'is_required' => false, 'is_active' => true,
    ]);

    $student = Student::factory()->create(['class_id' => $class->id]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $yearlyType->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $oneTimeType->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'school_class_id' => $class->id, 'status' => 'active',
    ]);

    $preview = app(BillGenerationService::class)->getMonthlyGenerationPreview($year);
    $typeNames = array_column($preview['tariffs'], 'payment_type');

    expect($typeNames)->toBe(['SPP Reg'], 'Only monthly tariffs should appear in preview');
});

it('preview tariff amount matches actual generation snapshot', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');

    $class = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $spp = sppBackfill(7, SchoolLevel::SMP, 950000);

    $student = Student::factory()->create([
        'class_id' => $class->id,
        'created_at' => '2026-06-01',
    ]);
    StudentPaymentSetting::updateOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $spp->id],
        ['is_active' => true, 'started_at' => '2026-07-01'],
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id, 'academic_year_id' => $year->id,
        'school_class_id' => $class->id, 'status' => 'active',
    ]);

    $service = app(BillGenerationService::class);
    $preview = $service->getMonthlyGenerationPreview($year);

    $previewTariff = collect($preview['tariffs'])->firstWhere('payment_type', 'SPP Reg');
    expect($previewTariff)->not->toBeNull('Preview must include SPP tariff');

    $service->generateMonthlyForAcademicYear($year);

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 7)->where('period_year', 2026)->first();

    expect($bill)->not->toBeNull('Bill must be created');
    expect((int) $bill->amount)->toBe((int) $previewTariff['amount'],
        'Generated bill amount must match preview tariff amount');
});

it('shows correct level labels for TK classes in tariff preview', function () {
    $labels = SchoolClass::levelLabels();

    expect($labels[-2])->toBe('TKA', 'Level -2 must render as TKA');
    expect($labels[-1])->toBe('TKB', 'Level -1 must render as TKB');
    expect($labels[7])->toBe('7', 'Level 7 must render as 7');
    expect($labels[12])->toBe('12', 'Level 12 must render as 12');
});

it('returns tariffs sorted by numeric class_level ascending', function () {
    $year = ensureYear('2026/2027', true, '2026-07-01', '2027-06-30');

    $classTKA = SchoolClass::factory()->create(['name' => 'A-1', 'level' => -2]);
    $classTKB = SchoolClass::factory()->create(['name' => 'B-1', 'level' => -1]);
    $class2 = SchoolClass::factory()->create(['name' => '2 A', 'level' => 2]);
    $class8 = SchoolClass::factory()->create(['name' => 'VIII A', 'level' => 8]);
    $class7 = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);

    sppBackfill(-2, SchoolLevel::TK, 970000);
    sppBackfill(-1, SchoolLevel::TK, 970000);
    $spp2 = sppBackfill(2, SchoolLevel::SD, 650000);
    $spp7 = sppBackfill(7, SchoolLevel::SMP, 950000);
    sppBackfill(8, SchoolLevel::SMP, 980000);

    foreach ([$classTKA, $classTKB, $class2, $class7, $class8] as $cls) {
        $s = Student::factory()->create(['class_id' => $cls->id]);
        $sppTypeId = $cls->level >= 7
            ? $spp7->id
            : ($cls->level >= 1 ? $spp2->id : PaymentTypeSchoolLevel::where('school_level', SchoolLevel::TK)->first()->payment_type_id);
        StudentPaymentSetting::updateOrCreate(
            ['student_id' => $s->id, 'payment_type_id' => $sppTypeId],
            ['is_active' => true, 'started_at' => '2026-07-01'],
        );
        StudentAcademicEnrollment::create([
            'student_id' => $s->id, 'academic_year_id' => $year->id,
            'school_class_id' => $cls->id, 'status' => 'active',
        ]);
    }

    $preview = app(BillGenerationService::class)->getMonthlyGenerationPreview($year);
    $levels = array_column($preview['tariffs'], 'class_level');

    expect($levels)->toBe([-2, -1, 2, 7, 8], 'Tariffs must be sorted by numeric class_level ascending');
});
