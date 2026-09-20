<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\StudentExamEligibility;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentEligibilityConfig;
use App\Models\StudentExamRequirement;
use App\Models\User;
use App\Services\StudentExamEligibilityService;
use Livewire\Livewire;

function makePageApplicableExamType(
    Student $student,
    string $name,
    SchoolLevel $level,
    int $classLevel,
    BillFrequency $frequency = BillFrequency::Monthly,
): PaymentType {
    $type = makeBillType($name);
    makeLevelDefault($type, $level);
    makeBillRate($type, $classLevel, 100_000, ['billing_frequency' => $frequency]);
    makeActiveSetting($student, $type);

    return $type;
}

function makeCriteriaRequirement(
    StudentEligibilityConfig $config,
    PaymentType $type,
    SchoolLevel $level,
    BillFrequency $frequency,
    float $percentage,
    ?string $startMonth = null,
    ?string $endMonth = null,
): StudentExamRequirement {
    return StudentExamRequirement::query()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => $level,
        'payment_type_id' => $type->id,
        'billing_frequency' => $frequency,
        'start_month' => $startMonth,
        'end_month' => $endMonth,
        'required_percentage' => $percentage,
        'is_active' => true,
    ]);
}

function makePageApplicableTypeRecord(
    Student $student,
    string $name,
    SchoolLevel $level,
    int $classLevel,
    BillFrequency $frequency,
): PaymentType {
    $type = PaymentType::query()->create([
        'name' => $name,
        'is_active' => true,
        'is_auto_enrolled' => false,
        'is_required' => false,
    ]);
    makeLevelDefault($type, $level);
    makeBillRate($type, $classLevel, 100_000, ['billing_frequency' => $frequency]);
    makeActiveSetting($student, $type);

    return $type;
}

/**
 * Membuat pasangan jenis pembayaran "Uang Buku" + "Uang Kegiatan" yang
 * berlaku untuk sebuah jenjang, sesuai default kanonikal.
 *
 * @return array{0: PaymentType, 1: PaymentType}
 */
function makeCanonicalPoolTypes(
    Student $student,
    SchoolLevel $level,
    int $classLevel,
): array {
    return [
        makePageApplicableTypeRecord($student, 'Uang Buku', $level, $classLevel, BillFrequency::Yearly),
        makePageApplicableTypeRecord($student, 'Uang Kegiatan', $level, $classLevel, BillFrequency::Yearly),
    ];
}

it('requires authentication and renders the criteria eligibility workspace', function () {
    $this->get(route('siswa.exam-eligibility'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makePageApplicableExamType($student, 'Kriteria Berjalan', SchoolLevel::SMP, 8);
    makeCriteriaRequirement($config, $type, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-08-01');
    makeMonthlyBill($student, $type, 100_000);

    $this->get(route('siswa.exam-eligibility'))
        ->assertOk()
        ->assertSee('Kelayakan Ujian')
        ->assertSee('Kriteria Aktif')
        ->assertSee($academicYear->year)
        ->assertSee($student->nama_lengkap)
        ->assertSee('Memenuhi')
        ->assertSee('Menampilkan')
        ->assertDontSee('Tambah Ujian')
        ->assertDontSee('Ubah Ujian')
        ->assertDontSee('wire:click="openCreateExam"', false)
        ->assertSee('bg-primary-fixed text-on-surface', false)
        ->assertSee('bg-secondary-container text-on-surface', false)
        ->assertSee('bg-error-container text-on-surface', false)
        ->assertSee('exam-student-'.$student->id, false)
        ->assertSee('exam-student-mobile-'.$student->id, false);
});

it('includes KB students and class options in the TK eligibility filter', function () {
    AcademicYear::query()->update(['is_active' => false]);
    $academicYear = AcademicYear::query()->updateOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $kb = SchoolClass::query()->firstOrCreate(['name' => 'KB'], ['level' => -3]);
    $sdClass = SchoolClass::factory()->create(['level' => 1]);
    $kbStudent = Student::factory()->create(['nama_lengkap' => 'Siswa KB Ujian', 'class_id' => $kb->id]);
    $sdStudent = Student::factory()->create(['nama_lengkap' => 'Siswa SD Ujian', 'class_id' => $sdClass->id]);

    foreach ([[$kbStudent, $kb], [$sdStudent, $sdClass]] as [$student, $class]) {
        StudentAcademicEnrollment::query()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    $component = Livewire::test(StudentExamEligibility::class)->set('filterLevel', SchoolLevel::TK->value);
    $studentIds = collect($component->viewData('students')->items())->pluck('id');
    $classIds = collect($component->viewData('classes'))->pluck('id');

    expect($studentIds)->toContain($kbStudent->id)->not->toContain($sdStudent->id)
        ->and($classIds)->toContain($kb->id)->not->toContain($sdClass->id);
});

it('relies on the shared layout gutter instead of a page-level cap', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('siswa.exam-eligibility'))->assertOk();

    $html = '<main class="lg:ml-sidebar-width pt-20 lg:pt-24 px-margin-mobile lg:px-margin-desktop pb-margin-mobile lg:pb-margin-desktop min-h-screen flex flex-col gap-stack-lg">';
    $response->assertSee($html, false);
    $response->assertDontSee('max-w-'.'[1600px]', false);
    $response->assertDontSee('max-w-container-max-width mx-auto', false);
});

it('renders a neutral no-criteria state without any exam controls', function () {
    $this->actingAs(User::factory()->create());
    makeEnrolledStudent(SchoolLevel::SMP);

    $this->get(route('siswa.exam-eligibility'))
        ->assertOk()
        ->assertSee('Belum ada kriteria aktif')
        ->assertSee('Atur Kriteria')
        ->assertDontSee('Belum ada periode ujian')
        ->assertDontSee('wire:click="openCreateExam"', false);
});

it('renders a no-academic-year state', function () {
    $this->actingAs(User::factory()->create());
    AcademicYear::query()->update(['is_active' => false]);

    $this->get(route('siswa.exam-eligibility'))
        ->assertOk()
        ->assertSee('Belum ada tahun ajaran aktif')
        ->assertDontSee('Atur Kriteria');
});

it('saves criteria only for the selected level and persists them without an exam', function () {
    $this->actingAs(User::factory()->create());
    [$smpStudent, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $smpType = makePageApplicableExamType($smpStudent, 'Kriteria SMP', SchoolLevel::SMP, 8);
    $sdType = makePageApplicableExamType($sdStudent, 'Kriteria SD', SchoolLevel::SD, 5, BillFrequency::Yearly);
    $sdConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SD]);
    makeCriteriaRequirement($sdConfig, $sdType, SchoolLevel::SD, BillFrequency::Yearly, 75);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->set("selectedRequirements.monthly.{$smpType->id}", true)
        ->set("requirementPercentages.monthly.{$smpType->id}", '80')
        ->set('monthlyStart', '2026-08')
        ->set('monthlyEnd', '2026-10')
        ->call('saveRequirements')
        ->assertHasNoErrors()
        ->assertSet('isRequirementModalOpen', false);

    $smpConfig = StudentEligibilityConfig::query()->where('school_level', SchoolLevel::SMP)->firstOrFail();

    $this->assertDatabaseHas('student_exam_requirements', [
        'student_exam_id' => null,
        'student_eligibility_config_id' => $smpConfig->id,
        'school_level' => SchoolLevel::SMP->value,
        'payment_type_id' => $smpType->id,
        'billing_frequency' => BillFrequency::Monthly->value,
        'start_month' => '2026-08-01 00:00:00',
        'end_month' => '2026-10-01 00:00:00',
        'required_percentage' => 80,
    ]);
    $this->assertDatabaseHas('student_exam_requirements', [
        'student_eligibility_config_id' => $sdConfig->id,
        'school_level' => SchoolLevel::SD->value,
        'payment_type_id' => $sdType->id,
        'required_percentage' => 75,
    ]);
});

it('persists criteria across reloads and isolates levels from each other', function () {
    $this->actingAs(User::factory()->create());
    [$smpStudent, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $smpType = makePageApplicableExamType($smpStudent, 'Kriteria SMP Konsisten', SchoolLevel::SMP, 8);
    $sdType = makePageApplicableExamType($sdStudent, 'Kriteria SD Aman', SchoolLevel::SD, 5, BillFrequency::Yearly);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->set("selectedRequirements.monthly.{$smpType->id}", true)
        ->set("requirementPercentages.monthly.{$smpType->id}", '60')
        ->set('monthlyStart', '2026-08')
        ->set('monthlyEnd', '2026-09')
        ->call('saveRequirements')
        ->assertHasNoErrors();

    $smpConfig = StudentEligibilityConfig::query()->where('school_level', SchoolLevel::SMP)->firstOrFail();

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSet("selectedRequirements.monthly.{$smpType->id}", true)
        ->assertSet("requirementPercentages.monthly.{$smpType->id}", '60');

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SD->value)
        ->assertSet("selectedRequirements.yearly.{$sdType->id}", false)
        ->assertDontSee('Kriteria SMP Konsisten')
        ->assertSet('monthlyStart', '2026-07');

    expect(StudentExamRequirement::query()->where('student_eligibility_config_id', $smpConfig->id)->count())->toBe(1);
});

it('opens a detailed eligibility matrix for a student', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makePageApplicableExamType($student, 'Detail Kriteria', SchoolLevel::SMP, 8);
    makeCriteriaRequirement($config, $type, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-09-01');
    $bill = makeMonthlyBill($student, $type, 100_000, 8, 2026);
    createSchoolMonthlyReportPayment(
        $student,
        Bank::factory()->cash()->create(),
        User::factory()->create(),
        '2026-09-15',
        [[
            'bill_id' => $bill->id,
            'payment_type_id' => $type->id,
            'period_month' => 8,
            'period_year' => 2026,
            'amount' => 100_000,
        ]],
    );

    Livewire::test(StudentExamEligibility::class)
        ->call('showDetail', $student->id)
        ->assertSet('isDetailModalOpen', true)
        ->assertSee($student->nama_lengkap)
        ->assertSee('Matriks Bulanan')
        ->assertSee('Memenuhi');
});

it('renders one pooled control without duplicate member checkboxes', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $firstType = makePageApplicableExamType($student, 'Komponen Pertama', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $secondType = makePageApplicableExamType($student, 'Komponen Kedua', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $otherType = makePageApplicableExamType($student, 'Daftar Ulang', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $requirement = makeCriteriaRequirement($config, $firstType, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $requirement->pooledPaymentTypes()->attach([$firstType->id, $secondType->id]);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSee('Syarat Gabungan')
        ->assertSee('Komponen Pertama')
        ->assertSee('Komponen Kedua')
        ->assertSee('Daftar Ulang')
        ->assertSee('wire:model.live="pooledRequirements.yearly.enabled"', false)
        ->assertSee("wire:model=\"selectedRequirements.yearly.{$otherType->id}\"", false)
        ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$firstType->id}\"", false)
        ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$secondType->id}\"", false)
        ->assertDontSee('pooledRequirements.yearly.members', false)
        ->assertDontSee('Anggota gabungan');
});

it('loads the persisted SMP pooled criteria and isolates it across levels', function () {
    $this->actingAs(User::factory()->create());
    [$smpStudent, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $sdConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SD]);
    $bookType = makePageApplicableExamType($smpStudent, 'Uang Buku', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $activityType = makePageApplicableExamType($smpStudent, 'Uang Kegiatan', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $otherType = makePageApplicableExamType($smpStudent, 'Daftar Ulang', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    makePageApplicableExamType($sdStudent, 'Syarat Tahunan SD', SchoolLevel::SD, 5, BillFrequency::Yearly);
    $pooledRequirement = makeCriteriaRequirement($config, $bookType, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $pooledRequirement->pooledPaymentTypes()->attach([$bookType->id, $activityType->id]);

    $component = Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSet('pooledRequirements.yearly.requirement_id', $pooledRequirement->id)
        ->assertSet('pooledRequirements.yearly.enabled', true)
        ->assertSet('pooledRequirements.yearly.percentage', '50')
        ->assertSee('Syarat Gabungan')
        ->assertSee('Uang Buku + Uang Kegiatan')
        ->assertSee('wire:model.live="pooledRequirements.yearly.enabled" checked', false)
        ->assertSee('value="50" wire:model="pooledRequirements.yearly.percentage"', false)
        ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$bookType->id}\"", false)
        ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$activityType->id}\"", false)
        ->assertSee("wire:model=\"selectedRequirements.yearly.{$otherType->id}\"", false);

    $component
        ->call('openRequirements', SchoolLevel::SD->value)
        ->assertSet('pooledRequirements', [])
        ->assertDontSee('Syarat Gabungan')
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSet('pooledRequirements.yearly.requirement_id', $pooledRequirement->id)
        ->assertSet('pooledRequirements.yearly.percentage', '50')
        ->assertSee('Uang Buku + Uang Kegiatan');
});

it('disables and re-enables a pooled criterion without changing its identity members or threshold unexpectedly', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $sdConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SD]);
    $firstType = makePageApplicableExamType($student, 'Komponen Pertama', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $secondType = makePageApplicableExamType($student, 'Komponen Kedua', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $sdType = makePageApplicableExamType($sdStudent, 'Syarat SD Tetap', SchoolLevel::SD, 5, BillFrequency::Yearly);
    $requirement = makeCriteriaRequirement($config, $firstType, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $requirement->pooledPaymentTypes()->attach([$firstType->id, $secondType->id]);
    $sdRequirement = makeCriteriaRequirement($sdConfig, $sdType, SchoolLevel::SD, BillFrequency::Yearly, 80);
    $memberIds = collect([$firstType->id, $secondType->id])->sort()->values()->all();

    $component = Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSet('pooledRequirements.yearly.requirement_id', $requirement->id)
        ->assertSet('pooledRequirements.yearly.enabled', true)
        ->assertSet('pooledRequirements.yearly.percentage', '50')
        ->set('pooledRequirements.yearly.enabled', false)
        ->set('pooledRequirements.yearly.percentage', '60')
        ->call('saveRequirements')
        ->assertHasNoErrors();

    $disabledRequirement = $requirement->fresh();

    expect($disabledRequirement)->not->toBeNull()
        ->and($disabledRequirement->is_active)->toBeFalse()
        ->and((float) $disabledRequirement->required_percentage)->toBe(60.0)
        ->and($disabledRequirement->pooledPaymentTypes()->pluck('payment_types.id')->sort()->values()->all())->toBe($memberIds)
        ->and((float) $sdRequirement->fresh()->required_percentage)->toBe(80.0);

    $component
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSet('pooledRequirements.yearly.enabled', false)
        ->assertSet('pooledRequirements.yearly.percentage', '60')
        ->assertSee('Syarat Gabungan')
        ->assertSee('Komponen Kedua + Komponen Pertama')
        ->assertDontSee('wire:model.live="pooledRequirements.yearly.enabled" checked', false)
        ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$firstType->id}\"", false)
        ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$secondType->id}\"", false)
        ->set('pooledRequirements.yearly.enabled', true)
        ->call('saveRequirements')
        ->assertHasNoErrors();

    expect($requirement->fresh()->is_active)->toBeTrue()
        ->and((float) $requirement->fresh()->required_percentage)->toBe(60.0)
        ->and($requirement->fresh()->pooledPaymentTypes()->pluck('payment_types.id')->sort()->values()->all())->toBe($memberIds)
        ->and(StudentExamRequirement::query()
            ->where('student_eligibility_config_id', $config->id)
            ->where('school_level', SchoolLevel::SMP)
            ->where('billing_frequency', BillFrequency::Yearly)
            ->count())->toBe(1);
});

it('persists the same payment type independently under multiple billing frequencies', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makePageApplicableExamType($student, 'Program Multi Frekuensi', SchoolLevel::SMP, 8);
    makeBillRate($type, 8, 500_000, ['billing_frequency' => BillFrequency::Yearly]);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->set("selectedRequirements.monthly.{$type->id}", true)
        ->set("requirementPercentages.monthly.{$type->id}", '100')
        ->set("selectedRequirements.yearly.{$type->id}", true)
        ->set("requirementPercentages.yearly.{$type->id}", '50')
        ->set('monthlyStart', '2026-08')
        ->set('monthlyEnd', '2026-09')
        ->call('saveRequirements')
        ->assertHasNoErrors();

    expect(StudentExamRequirement::query()
        ->where('student_eligibility_config_id', $config->id)
        ->where('school_level', SchoolLevel::SMP)
        ->where('payment_type_id', $type->id)
        ->pluck('billing_frequency')
        ->map(fn (BillFrequency $frequency): string => $frequency->value)
        ->sort()
        ->values()
        ->all())->toBe([BillFrequency::Monthly->value, BillFrequency::Yearly->value]);
});

it('recomputes student eligibility after saving criteria and never mutates billing data', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makePageApplicableExamType($student, 'Kriteria Baru', SchoolLevel::SMP, 8);
    $bill = makeMonthlyBill($student, $type, 100_000, 8, 2026);
    createSchoolMonthlyReportPayment(
        $student,
        Bank::factory()->cash()->create(),
        User::factory()->create(),
        '2026-09-15',
        [[
            'bill_id' => $bill->id,
            'payment_type_id' => $type->id,
            'period_month' => 8,
            'period_year' => 2026,
            'amount' => 100_000,
        ]],
    );
    $billStateBefore = StudentBill::query()->whereKey($bill->id)->get(['id', 'amount', 'billing_frequency'])->toArray();
    $paymentDetailStateBefore = PaymentDetail::query()->where('bill_id', $bill->id)->get(['id', 'bill_id', 'payment_type_id', 'amount'])->toArray();

    $component = Livewire::test(StudentExamEligibility::class);

    $component
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->set("selectedRequirements.monthly.{$type->id}", true)
        ->set("requirementPercentages.monthly.{$type->id}", '100')
        ->set('monthlyStart', '2026-08')
        ->set('monthlyEnd', '2026-08')
        ->call('saveRequirements')
        ->assertHasNoErrors();

    $afterSave = $component->call('showDetail', $student->id)->assertSet('isDetailModalOpen', true);

    expect($afterSave->get('detail')['is_eligible'])->toBeTrue()
        ->and(StudentBill::query()->whereKey($bill->id)->get(['id', 'amount', 'billing_frequency'])->toArray())->toBe($billStateBefore)
        ->and(PaymentDetail::query()->where('bill_id', $bill->id)->get(['id', 'bill_id', 'payment_type_id', 'amount'])->toArray())->toBe($paymentDetailStateBefore);
});

it('blocks eligibility when a required bill is missing and ignores not-applicable types', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $requiredType = makePageApplicableExamType($student, 'Wajib Tanpa Tagihan', SchoolLevel::SMP, 8);
    $optionalType = makeBillType('Opsional');
    makeLevelDefault($optionalType, SchoolLevel::SMP, required: false);
    makeBillRate($optionalType, 8, 100_000);
    makeCriteriaRequirement($config, $requiredType, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-09-01', '2026-09-01');
    makeCriteriaRequirement($config, $optionalType, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-09-01', '2026-09-01');

    Livewire::test(StudentExamEligibility::class)
        ->call('showDetail', $student->id)
        ->assertSet('isDetailModalOpen', true)
        ->assertSee('Wajib Tanpa Tagihan')
        ->assertSee('Tagihan Belum Tersedia')
        ->assertSee('Belum Memenuhi');
});

it('persists criteria across an HTTP page navigation boundary', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makePageApplicableExamType($student, 'Kriteria Lintas Request', SchoolLevel::SMP, 8);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->set("selectedRequirements.monthly.{$type->id}", true)
        ->set("requirementPercentages.monthly.{$type->id}", '90')
        ->set('monthlyStart', '2026-08')
        ->set('monthlyEnd', '2026-09')
        ->call('saveRequirements')
        ->assertHasNoErrors();

    $this->get(route('siswa.exam-eligibility'))
        ->assertOk()
        ->assertSee('Kelayakan Ujian');

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSet("selectedRequirements.monthly.{$type->id}", true)
        ->assertSet("requirementPercentages.monthly.{$type->id}", '90')
        ->assertSet('monthlyStart', '2026-08')
        ->assertSet('monthlyEnd', '2026-09');
});

it('does not let result filters mutate persisted criteria', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    [$smaStudent] = makeEnrolledStudent(SchoolLevel::SMA);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makePageApplicableExamType($student, 'Syarat Tersimpan', SchoolLevel::SMP, 8);
    makePageApplicableExamType($smaStudent, 'Syarat SMA Aman', SchoolLevel::SMA, 11, BillFrequency::Yearly);
    makeCriteriaRequirement($config, $type, SchoolLevel::SMP, BillFrequency::Monthly, 80, '2026-08-01', '2026-09-01');
    $classId = (int) $student->enrollments()->first()->school_class_id;

    $columns = ['id', 'student_eligibility_config_id', 'payment_type_id', 'billing_frequency', 'required_percentage', 'start_month', 'end_month'];
    $before = StudentExamRequirement::query()->get($columns)->toArray();

    Livewire::test(StudentExamEligibility::class)
        ->set('search', 'zebra')
        ->set('filterLevel', SchoolLevel::SMP->value)
        ->set('filterClassId', (string) $classId)
        ->set('filterStatus', 'eligible')
        ->call('resetFilters')
        ->assertOk();

    expect(StudentExamRequirement::query()->get($columns)->toArray())->toBe($before)
        ->and(Livewire::test(StudentExamEligibility::class)
            ->call('openRequirements', SchoolLevel::SMP->value)
            ->assertSet("selectedRequirements.monthly.{$type->id}", true)
            ->assertSet("requirementPercentages.monthly.{$type->id}", '80')
            ->get('pooledRequirements'))->toBe([]);
});

it('recomputes eligibility live when a new payment arrives without touching criteria', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makePageApplicableExamType($student, 'Bayar Kemudian', SchoolLevel::SMP, 8);
    $bill = makeMonthlyBill($student, $type, 100_000, 8, 2026);
    makeCriteriaRequirement($config, $type, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-08-01');
    $criteriaCount = StudentExamRequirement::query()->count();

    Livewire::test(StudentExamEligibility::class)
        ->call('showDetail', $student->id)
        ->assertSet('isDetailModalOpen', true)
        ->assertSee('Belum Memenuhi');

    createSchoolMonthlyReportPayment(
        $student,
        Bank::factory()->cash()->create(),
        User::factory()->create(),
        '2026-09-15',
        [[
            'bill_id' => $bill->id,
            'payment_type_id' => $type->id,
            'period_month' => 8,
            'period_year' => 2026,
            'amount' => 100_000,
        ]],
    );

    Livewire::test(StudentExamEligibility::class)
        ->call('showDetail', $student->id)
        ->assertSet('isDetailModalOpen', true)
        ->assertSee('Memenuhi');

    expect(StudentExamRequirement::query()->count())->toBe($criteriaCount);
});

it('resets only the selected jenjang criteria', function () {
    $this->actingAs(User::factory()->create());
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smaStudent] = makeEnrolledStudent(SchoolLevel::SMA);
    $smpConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $sdConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SD]);
    $smaConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMA]);
    $smpType = makePageApplicableExamType($smpStudent, 'SMP Direset', SchoolLevel::SMP, 8);
    $sdType = makePageApplicableExamType($sdStudent, 'SD Dipertahankan', SchoolLevel::SD, 5, BillFrequency::Yearly);
    $smaType = makePageApplicableExamType($smaStudent, 'SMA Dipertahankan', SchoolLevel::SMA, 11, BillFrequency::Yearly);
    makeCriteriaRequirement($smpConfig, $smpType, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-09-01');
    $sdRequirement = makeCriteriaRequirement($sdConfig, $sdType, SchoolLevel::SD, BillFrequency::Yearly, 80);
    makeCriteriaRequirement($smaConfig, $smaType, SchoolLevel::SMA, BillFrequency::Yearly, 70);

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SMP->value)
        ->assertSet('isResetConfirmOpen', true)
        ->call('confirmReset')
        ->assertSet('isResetConfirmOpen', false)
        ->assertSet('isRequirementModalOpen', true);

    expect(StudentExamRequirement::query()->where('student_eligibility_config_id', $smpConfig->id)->count())->toBe(0)
        ->and(StudentExamRequirement::query()->whereKey($sdRequirement->id)->first())->not->toBeNull()
        ->and(StudentExamRequirement::query()->where('student_eligibility_config_id', $smaConfig->id)->count())->toBe(1)
        ->and(StudentExamRequirement::query()->count())->toBe(2)
        ->and(StudentEligibilityConfig::query()->count())->toBe(4);
});

it('reset does not modify any financial records', function () {
    $this->actingAs(User::factory()->create());
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $type = makePageApplicableExamType($student, 'Reset Tanpa Efek', SchoolLevel::SMP, 8);
    $bill = makeMonthlyBill($student, $type, 100_000, 8, 2026);
    createSchoolMonthlyReportPayment(
        $student,
        Bank::factory()->cash()->create(),
        User::factory()->create(),
        '2026-09-15',
        [[
            'bill_id' => $bill->id,
            'payment_type_id' => $type->id,
            'period_month' => 8,
            'period_year' => 2026,
            'amount' => 100_000,
        ]],
    );
    makeCriteriaRequirement($config, $type, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-08-01');

    $billColumns = ['id', 'amount', 'billing_frequency', 'status'];
    $paymentColumns = ['id', 'total_amount', 'status'];
    $detailColumns = ['id', 'bill_id', 'payment_type_id', 'amount'];
    $billState = StudentBill::query()->get($billColumns)->toArray();
    $paymentState = Payment::query()->get($paymentColumns)->toArray();
    $detailState = PaymentDetail::query()->get($detailColumns)->toArray();

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SMP->value)
        ->assertSet('isResetConfirmOpen', true)
        ->call('confirmReset')
        ->assertSet('isResetConfirmOpen', false);

    expect(StudentBill::query()->get($billColumns)->toArray())->toBe($billState)
        ->and(Payment::query()->get($paymentColumns)->toArray())->toBe($paymentState)
        ->and(PaymentDetail::query()->get($detailColumns)->toArray())->toBe($detailState)
        ->and(StudentExamRequirement::query()->count())->toBe(0);
});

it('loads suppresses and persists pooled yearly requirements identically for every jenjang', function () {
    $this->actingAs(User::factory()->create());

    foreach (SchoolLevel::cases() as $level) {
        [$student, $schoolClass] = makeEnrolledStudent($level);
        $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => $level]);
        $book = makePageApplicableTypeRecord($student, 'Buku '.$level->value, $level, (int) $schoolClass->level, BillFrequency::Yearly);
        $activity = makePageApplicableTypeRecord($student, 'Kegiatan '.$level->value, $level, (int) $schoolClass->level, BillFrequency::Yearly);
        $independent = makePageApplicableTypeRecord($student, 'Daftar Ulang '.$level->value, $level, (int) $schoolClass->level, BillFrequency::Yearly);
        $pooled = makeCriteriaRequirement($config, $book, $level, BillFrequency::Yearly, 50);
        $pooled->pooledPaymentTypes()->attach([$book->id, $activity->id]);
        $memberIds = collect([$book->id, $activity->id])->sort()->values()->all();

        $component = Livewire::test(StudentExamEligibility::class)
            ->call('openRequirements', $level->value)
            ->assertSet('pooledRequirements.yearly.requirement_id', $pooled->id)
            ->assertSet('pooledRequirements.yearly.enabled', true)
            ->assertSet('pooledRequirements.yearly.percentage', '50')
            ->assertSee('Syarat Gabungan')
            ->assertSee('Buku '.$level->value)
            ->assertSee('Kegiatan '.$level->value)
            ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$book->id}\"", false)
            ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$activity->id}\"", false)
            ->assertSee("wire:model=\"selectedRequirements.yearly.{$independent->id}\"", false);

        $component
            ->set('pooledRequirements.yearly.enabled', false)
            ->set('pooledRequirements.yearly.percentage', '60')
            ->call('saveRequirements')
            ->assertHasNoErrors();

        $persisted = StudentExamRequirement::query()->with('pooledPaymentTypes')->findOrFail($pooled->id);

        expect($persisted->is_active)->toBeFalse()
            ->and((float) $persisted->required_percentage)->toBe(60.0)
            ->and($persisted->pooledPaymentTypes->pluck('id')->sort()->values()->all())->toBe($memberIds)
            ->and(StudentExamRequirement::query()->where('student_eligibility_config_id', $config->id)->count())->toBe(1);

        $component
            ->call('openRequirements', $level->value)
            ->assertSet('pooledRequirements.yearly.enabled', false)
            ->assertSet('pooledRequirements.yearly.percentage', '60')
            ->set('pooledRequirements.yearly.enabled', true)
            ->call('saveRequirements')
            ->assertHasNoErrors();

        expect($pooled->fresh()->is_active)->toBeTrue()
            ->and($pooled->fresh()->pooledPaymentTypes()->pluck('payment_types.id')->sort()->values()->all())->toBe($memberIds)
            ->and(StudentExamRequirement::query()->where('student_eligibility_config_id', $config->id)->count())->toBe(1);
    }

    expect(StudentEligibilityConfig::query()->count())->toBe(4);
});

it('evaluates the pooled fifty-percent formula identically for every jenjang', function () {
    $this->actingAs(User::factory()->create());

    foreach (SchoolLevel::cases() as $level) {
        [$student, $schoolClass] = makeEnrolledStudent($level);
        $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => $level]);
        $book = makePageApplicableTypeRecord($student, 'Buku Eval '.$level->value, $level, (int) $schoolClass->level, BillFrequency::Yearly);
        $activity = makePageApplicableTypeRecord($student, 'Kegiatan Eval '.$level->value, $level, (int) $schoolClass->level, BillFrequency::Yearly);
        $pooled = makeCriteriaRequirement($config, $book, $level, BillFrequency::Yearly, 50);
        $pooled->pooledPaymentTypes()->attach([$book->id, $activity->id]);
        $bookBill = makeYearlyBill($student, $book, 1_000_000);
        makeYearlyBill($student, $activity, 1_000_000);
        createSchoolMonthlyReportPayment(
            $student,
            Bank::factory()->cash()->create(),
            User::factory()->create(),
            '2026-09-15',
            [[
                'bill_id' => $bookBill->id,
                'payment_type_id' => $book->id,
                'period_month' => null,
                'period_year' => null,
                'academic_year' => '2026/2027',
                'amount' => 1_000_000,
            ]],
        );

        $component = Livewire::test(StudentExamEligibility::class)
            ->call('showDetail', $student->id)
            ->assertSet('isDetailModalOpen', true)
            ->assertSee('Memenuhi');
        $detail = $component->get('detail');
        $pooledBlock = collect($detail['requirements'])->firstWhere('is_pooled', true);

        expect($detail['is_eligible'])->toBeTrue()
            ->and($pooledBlock)->not->toBeNull()
            ->and((float) $pooledBlock['paid'])->toBe(1_000_000.0)
            ->and((float) $pooledBlock['target'])->toBe(2_000_000.0)
            ->and((float) $pooledBlock['required_amount'])->toBe(1_000_000.0)
            ->and($pooledBlock['status'])->toBe(StudentExamEligibilityService::STATUS_PASS);
    }

    expect(StudentEligibilityConfig::query()->count())->toBe(4);
});

it('confirms reset through a dedicated modal without native browser dialogs', function () {
    $this->actingAs(User::factory()->create());
    makeEnrolledStudent(SchoolLevel::SMP);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSee("wire:click=\"openResetConfirmation('SMP')\"", false)
        ->assertDontSee('wire:confirm', false)
        ->call('openResetConfirmation', SchoolLevel::SMP->value)
        ->assertSet('isResetConfirmOpen', true)
        ->assertSee('Reset Kriteria SMP?', false)
        ->assertSee('Batal')
        ->assertSee('Ya, Reset Kriteria')
        ->assertSee('wire:click="confirmReset"', false);
});

it('initializes canonical pooled yearly defaults for every applicable jenjang and is idempotent', function () {
    $this->actingAs(User::factory()->create());

    $book = PaymentType::query()->create(['name' => 'Uang Buku', 'is_active' => true, 'is_auto_enrolled' => false, 'is_required' => false]);
    $activity = PaymentType::query()->create(['name' => 'Uang Kegiatan', 'is_active' => true, 'is_auto_enrolled' => false, 'is_required' => false]);

    foreach (SchoolLevel::cases() as $level) {
        [$student, $schoolClass] = makeEnrolledStudent($level);

        foreach ([$book, $activity] as $type) {
            makeBillRate($type, (int) $schoolClass->level, 100_000, ['billing_frequency' => BillFrequency::Yearly]);
            makeActiveSetting($student, $type);
        }
    }

    $memberIds = collect([$book->id, $activity->id])->sort()->values()->all();

    StudentEligibilityConfig::initializeCanonicalDefaults();

    foreach (SchoolLevel::cases() as $level) {
        $config = StudentEligibilityConfig::query()->where('school_level', $level->value)->firstOrFail();
        $pooled = StudentExamRequirement::query()
            ->where('student_eligibility_config_id', $config->id)
            ->where('billing_frequency', BillFrequency::Yearly)
            ->with('pooledPaymentTypes')
            ->get()
            ->first(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() >= 2);

        expect($pooled)->not->toBeNull()
            ->and($pooled->is_active)->toBeFalse()
            ->and((float) $pooled->required_percentage)->toBe(50.0)
            ->and($pooled->pooledPaymentTypes->pluck('id')->sort()->values()->all())->toBe($memberIds)
            ->and($config->canonicalPooledMemberIds())->toBe($memberIds)
            ->and((float) $config->default_pooled_threshold)->toBe(50.0)
            ->and($config->default_pooled_is_active)->toBeFalse()
            ->and($config->activeRequirementCount())->toBe(0);
    }

    StudentEligibilityConfig::initializeCanonicalDefaults();

    expect(StudentExamRequirement::query()->count())->toBe(4)
        ->and(StudentExamRequirement::query()
            ->where('billing_frequency', BillFrequency::Yearly)
            ->whereDoesntHave('pooledPaymentTypes')
            ->count())->toBe(0)
        ->and(StudentExamRequirement::query()
            ->where('billing_frequency', BillFrequency::Yearly)
            ->with('pooledPaymentTypes')
            ->get()
            ->filter(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() >= 2)
            ->count())->toBe(4)
        ->and(StudentEligibilityConfig::query()->count())->toBe(4);
});

it('renders the canonical pooled control per jenjang without duplicate member checkboxes', function () {
    $this->actingAs(User::factory()->create());

    $book = PaymentType::query()->create(['name' => 'Uang Buku', 'is_active' => true, 'is_auto_enrolled' => false, 'is_required' => false]);
    $activity = PaymentType::query()->create(['name' => 'Uang Kegiatan', 'is_active' => true, 'is_auto_enrolled' => false, 'is_required' => false]);

    foreach (SchoolLevel::cases() as $level) {
        [$student, $schoolClass] = makeEnrolledStudent($level);

        foreach ([$book, $activity] as $type) {
            makeBillRate($type, (int) $schoolClass->level, 100_000, ['billing_frequency' => BillFrequency::Yearly]);
            makeActiveSetting($student, $type);
        }
    }

    StudentEligibilityConfig::initializeCanonicalDefaults();
    $memberIds = collect([$book->id, $activity->id])->sort()->values()->all();

    foreach (SchoolLevel::cases() as $level) {
        $config = StudentEligibilityConfig::query()->where('school_level', $level->value)->firstOrFail();
        $pooled = StudentExamRequirement::query()
            ->where('student_eligibility_config_id', $config->id)
            ->where('billing_frequency', BillFrequency::Yearly)
            ->with('pooledPaymentTypes')
            ->get()
            ->first(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() >= 2);

        Livewire::test(StudentExamEligibility::class)
            ->call('openRequirements', $level->value)
            ->assertSet('pooledRequirements.yearly.requirement_id', $pooled->id)
            ->assertSet('pooledRequirements.yearly.enabled', false)
            ->assertSet('pooledRequirements.yearly.percentage', '50')
            ->assertSee('Syarat Gabungan')
            ->assertSee('Uang Buku + Uang Kegiatan')
            ->assertDontSee('wire:model.live="pooledRequirements.yearly.enabled" checked', false)
            ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$memberIds[0]}\"", false)
            ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$memberIds[1]}\"", false);
    }

    expect(StudentEligibilityConfig::query()->count())->toBe(4);
});

it('reset restores the canonical pooled structure per jenjang and is idempotent', function () {
    $this->actingAs(User::factory()->create());

    $book = PaymentType::query()->create(['name' => 'Uang Buku', 'is_active' => true, 'is_auto_enrolled' => false, 'is_required' => false]);
    $activity = PaymentType::query()->create(['name' => 'Uang Kegiatan', 'is_active' => true, 'is_auto_enrolled' => false, 'is_required' => false]);

    foreach (SchoolLevel::cases() as $level) {
        [$student, $schoolClass] = makeEnrolledStudent($level);

        foreach ([$book, $activity] as $type) {
            makeBillRate($type, (int) $schoolClass->level, 100_000, ['billing_frequency' => BillFrequency::Yearly]);
            makeActiveSetting($student, $type);
        }
    }

    StudentEligibilityConfig::initializeCanonicalDefaults();

    $config = StudentEligibilityConfig::query()->where('school_level', SchoolLevel::SMP->value)->firstOrFail();
    $memberIds = $config->canonicalPooledMemberIds();
    $pooled = StudentExamRequirement::query()
        ->where('student_eligibility_config_id', $config->id)
        ->where('billing_frequency', BillFrequency::Yearly)
        ->with('pooledPaymentTypes')
        ->get()
        ->first(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() >= 2);
    $foreign = PaymentType::query()->create(['name' => 'Daftar Ulang', 'is_active' => true, 'is_auto_enrolled' => false, 'is_required' => false]);
    makeBillRate($foreign, 8, 100_000, ['billing_frequency' => BillFrequency::Yearly]);
    [$smpStudentFixture] = makeEnrolledStudent(SchoolLevel::SMP);
    makeActiveSetting($smpStudentFixture, $foreign);
    $pooled->pooledPaymentTypes()->attach($foreign->id);
    $pooled->update(['is_active' => false, 'required_percentage' => 30]);
    $monthlyType = makePageApplicableTypeRecord($smpStudentFixture, 'Syarat Bulanan Reset', SchoolLevel::SMP, 8, BillFrequency::Monthly);
    makeCriteriaRequirement($config, $monthlyType, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-09-01');

    expect(StudentExamRequirement::query()->where('student_eligibility_config_id', $config->id)->count())->toBe(2);

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SMP->value)
        ->assertSet('isResetConfirmOpen', true)
        ->call('confirmReset')
        ->assertSet('isResetConfirmOpen', false)
        ->assertSet('isRequirementModalOpen', true);

    $restored = $pooled->fresh();

    expect($restored)->not->toBeNull()
        ->and($restored->is_active)->toBeFalse()
        ->and((float) $restored->required_percentage)->toBe(50.0)
        ->and($restored->pooledPaymentTypes->pluck('id')->sort()->values()->all())->toBe($memberIds)
        ->and($config->activeRequirementCount())->toBe(0);

    foreach (SchoolLevel::cases() as $otherLevel) {
        if ($otherLevel === SchoolLevel::SMP) {
            continue;
        }

        $otherConfig = StudentEligibilityConfig::query()->where('school_level', $otherLevel->value)->firstOrFail();

        expect(StudentExamRequirement::query()->where('student_eligibility_config_id', $otherConfig->id)->count())->toBe(1);
    }

    expect(StudentExamRequirement::query()->where('student_eligibility_config_id', $config->id)->count())->toBe(1);

    StudentExamRequirement::query()->whereKey($restored->id)->delete();

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SMP->value)
        ->call('confirmReset');

    $recreated = StudentExamRequirement::query()
        ->where('student_eligibility_config_id', $config->id)
        ->with('pooledPaymentTypes')
        ->get();

    expect($recreated)->toHaveCount(1)
        ->and($recreated->first()->pooledPaymentTypes->count())->toBeGreaterThanOrEqual(2)
        ->and($recreated->first()->pooledPaymentTypes->pluck('id')->sort()->values()->all())->toBe($memberIds)
        ->and($recreated->first()->is_active)->toBeFalse()
        ->and((float) $recreated->first()->required_percentage)->toBe(50.0);

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SMP->value)
        ->call('confirmReset');

    expect(StudentExamRequirement::query()->where('student_eligibility_config_id', $config->id)->count())->toBe(1)
        ->and(StudentExamRequirement::query()->find($recreated->first()->id))->not->toBeNull();
});

it('cancel reset leaves criteria and data untouched', function () {
    $this->actingAs(User::factory()->create());
    [$student, $schoolClass] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->where('school_level', SchoolLevel::SMP->value)->firstOrFail();
    $type = makePageApplicableExamType($student, 'Syarat Tak Terganggu', SchoolLevel::SMP, (int) $schoolClass->level);
    makeCriteriaRequirement($config, $type, SchoolLevel::SMP, BillFrequency::Monthly, 100, '2026-08-01', '2026-09-01');
    $countBefore = StudentExamRequirement::query()->count();

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SMP->value)
        ->assertSet('isResetConfirmOpen', true)
        ->call('cancelReset')
        ->assertSet('isResetConfirmOpen', false)
        ->assertSet('isRequirementModalOpen', false);

    expect(StudentExamRequirement::query()->count())->toBe($countBefore);
});

it('counts only active criteria in the level badge and updates it after save and reset', function () {
    $this->actingAs(User::factory()->create());
    [$sdStudent, $sdClass] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent, $smpClass] = makeEnrolledStudent(SchoolLevel::SMP);
    [$smaStudent, $smaClass] = makeEnrolledStudent(SchoolLevel::SMA);
    $sdConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SD]);
    $smpConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $smaConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMA]);
    $sdBook = makePageApplicableTypeRecord($sdStudent, 'Buku Badge SD', SchoolLevel::SD, (int) $sdClass->level, BillFrequency::Yearly);
    $sdActivity = makePageApplicableTypeRecord($sdStudent, 'Kegiatan Badge SD', SchoolLevel::SD, (int) $sdClass->level, BillFrequency::Yearly);
    $sdPooled = makeCriteriaRequirement($sdConfig, $sdBook, SchoolLevel::SD, BillFrequency::Yearly, 50);
    $sdPooled->pooledPaymentTypes()->attach([$sdBook->id, $sdActivity->id]);
    $sdPooled->update(['is_active' => false]);
    $smpType = makePageApplicableTypeRecord($smpStudent, 'Syarat Badge SMP', SchoolLevel::SMP, (int) $smpClass->level, BillFrequency::Yearly);
    makeCriteriaRequirement($smpConfig, $smpType, SchoolLevel::SMP, BillFrequency::Yearly, 70);
    $smaType = makePageApplicableTypeRecord($smaStudent, 'Syarat Badge SMA', SchoolLevel::SMA, (int) $smaClass->level, BillFrequency::Yearly);
    $smaRequirement = makeCriteriaRequirement($smaConfig, $smaType, SchoolLevel::SMA, BillFrequency::Yearly, 50);
    $smaRequirement->update(['is_active' => false]);

    $readBadge = fn (string $html, string $level): int => preg_match(
        '#>'.$level.'</span>\s*<span class="[^"]*">(\d+)</span>#',
        $html,
        $matches,
    ) === 1 ? (int) $matches[1] : -1;

    $component = Livewire::test(StudentExamEligibility::class);

    expect($readBadge($component->html(), 'SD'))->toBe(0)
        ->and($readBadge($component->html(), 'SMP'))->toBe(1)
        ->and($readBadge($component->html(), 'SMA'))->toBe(0);

    $component
        ->call('openRequirements', SchoolLevel::SD->value)
        ->assertSet('pooledRequirements.yearly.enabled', false)
        ->set('pooledRequirements.yearly.enabled', true)
        ->call('saveRequirements')
        ->assertHasNoErrors()
        ->assertSet('isRequirementModalOpen', false);

    expect($readBadge($component->html(), 'SD'))->toBe(1);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SD->value)
        ->assertSet('pooledRequirements.yearly.enabled', true)
        ->assertSet('pooledRequirements.yearly.percentage', '50');

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SD->value)
        ->call('confirmReset')
        ->assertSet('isResetConfirmOpen', false);

    $restored = $sdPooled->fresh();

    expect($restored)->not->toBeNull()
        ->and($restored->is_active)->toBeFalse()
        ->and((float) $restored->required_percentage)->toBe(50.0)
        ->and($restored->pooledPaymentTypes()->pluck('payment_types.id')->sort()->values()->all())
        ->toBe(collect([$sdBook->id, $sdActivity->id])->sort()->values()->all());

    $resetPage = Livewire::test(StudentExamEligibility::class);

    expect($readBadge($resetPage->html(), 'SD'))->toBe(0)
        ->and($readBadge($resetPage->html(), 'SMP'))->toBe(1);
});

it('does not mark any student eligible while all criteria are inactive', function () {
    $this->actingAs(User::factory()->create());
    [$student, $schoolClass] = makeEnrolledStudent(SchoolLevel::SMP);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $book = makePageApplicableTypeRecord($student, 'Uang Buku', SchoolLevel::SMP, (int) $schoolClass->level, BillFrequency::Yearly);
    $activity = makePageApplicableTypeRecord($student, 'Uang Kegiatan', SchoolLevel::SMP, (int) $schoolClass->level, BillFrequency::Yearly);
    $pooled = makeCriteriaRequirement($config, $book, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $pooled->pooledPaymentTypes()->attach([$book->id, $activity->id]);
    $pooled->update(['is_active' => false]);

    expect($config->activeRequirementCount())->toBe(0);

    Livewire::test(StudentExamEligibility::class)
        ->call('showDetail', $student->id)
        ->assertSet('isDetailModalOpen', false);

    $this->get(route('siswa.exam-eligibility'))
        ->assertOk()
        ->assertSee('Belum ada kriteria aktif')
        ->assertDontSee('exam-student-'.$student->id, false)
        ->assertDontSee('exam-student-mobile-'.$student->id, false);
});

it('normalizes legacy active eligibility criteria to the empty baseline while preserving the pooled structure', function () {
    $this->actingAs(User::factory()->create());

    $memberIdsByLevel = [];

    foreach (SchoolLevel::cases() as $level) {
        [$student, $schoolClass] = makeEnrolledStudent($level);
        $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => $level]);
        $book = makePageApplicableTypeRecord($student, 'Buku Lama '.$level->value, $level, (int) $schoolClass->level, BillFrequency::Yearly);
        $activity = makePageApplicableTypeRecord($student, 'Kegiatan Lama '.$level->value, $level, (int) $schoolClass->level, BillFrequency::Yearly);
        $monthly = makePageApplicableTypeRecord($student, 'Bulanan Lama '.$level->value, $level, (int) $schoolClass->level, BillFrequency::Monthly);
        $oneTime = makePageApplicableTypeRecord($student, 'Sekali Lama '.$level->value, $level, (int) $schoolClass->level, BillFrequency::OneTime);
        $legacyPooled = makeCriteriaRequirement($config, $book, $level, BillFrequency::Yearly, 50);
        $legacyPooled->pooledPaymentTypes()->attach([$book->id, $activity->id]);
        makeCriteriaRequirement($config, $monthly, $level, BillFrequency::Monthly, 100, '2026-08-01', '2026-10-01');
        makeCriteriaRequirement($config, $oneTime, $level, BillFrequency::OneTime, 100);

        $memberIdsByLevel[$level->value] = collect([$book->id, $activity->id])->sort()->values()->all();

        expect($config->activeRequirementCount())->toBe(3);
    }

    $legacyExamRow = StudentExamRequirement::factory()->create();
    $rowCountBefore = StudentExamRequirement::query()->count();

    expect(StudentExamRequirement::query()->where('is_active', true)->count())->toBe(13);
    expect($legacyExamRow->fresh()->is_active)->toBeTrue();

    StudentEligibilityConfig::normalizeToEmptyBaseline();

    foreach (SchoolLevel::cases() as $level) {
        $config = StudentEligibilityConfig::query()->where('school_level', $level->value)->firstOrFail();
        $pooled = StudentExamRequirement::query()
            ->where('student_eligibility_config_id', $config->id)
            ->where('billing_frequency', BillFrequency::Yearly)
            ->with('pooledPaymentTypes')
            ->get()
            ->first(fn (StudentExamRequirement $requirement): bool => $requirement->pooledPaymentTypes->count() >= 2);

        expect($pooled)->not->toBeNull()
            ->and($pooled->is_active)->toBeFalse()
            ->and((float) $pooled->required_percentage)->toBe(50.0)
            ->and($pooled->pooledPaymentTypes->pluck('id')->sort()->values()->all())->toBe($memberIdsByLevel[$level->value])
            ->and($config->activeRequirementCount())->toBe(0);
    }

    expect(StudentExamRequirement::query()->where('is_active', true)->count())->toBe(0)
        ->and($legacyExamRow->fresh()->is_active)->toBeFalse()
        ->and(StudentExamRequirement::query()->count())->toBe($rowCountBefore);

    StudentEligibilityConfig::normalizeToEmptyBaseline();

    expect(StudentExamRequirement::query()->where('is_active', true)->count())->toBe(0);
});

it('shows every control unchecked when a jenjang has zero active criteria', function () {
    $this->actingAs(User::factory()->create());
    [$student, $schoolClass] = makeEnrolledStudent(SchoolLevel::SD);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SD]);
    $book = makePageApplicableTypeRecord($student, 'Buku Mati', SchoolLevel::SD, (int) $schoolClass->level, BillFrequency::Yearly);
    $activity = makePageApplicableTypeRecord($student, 'Kegiatan Mati', SchoolLevel::SD, (int) $schoolClass->level, BillFrequency::Yearly);
    $monthly = makePageApplicableTypeRecord($student, 'Bulanan Mati', SchoolLevel::SD, (int) $schoolClass->level, BillFrequency::Monthly);
    $independentYearly = makePageApplicableTypeRecord($student, 'Tahunan Mati', SchoolLevel::SD, (int) $schoolClass->level, BillFrequency::Yearly);
    $oneTime = makePageApplicableTypeRecord($student, 'Sekali Mati', SchoolLevel::SD, (int) $schoolClass->level, BillFrequency::OneTime);

    $pooled = makeCriteriaRequirement($config, $book, SchoolLevel::SD, BillFrequency::Yearly, 50);
    $pooled->pooledPaymentTypes()->attach([$book->id, $activity->id]);
    $pooled->update(['is_active' => false]);

    foreach ([
        [$monthly, BillFrequency::Monthly, ['2026-08-01', '2026-09-01']],
        [$independentYearly, BillFrequency::Yearly, []],
        [$oneTime, BillFrequency::OneTime, []],
    ] as [$type, $frequency, $months]) {
        $requirement = makeCriteriaRequirement($config, $type, SchoolLevel::SD, $frequency, 100, ...$months);
        $requirement->update(['is_active' => false]);
    }

    expect($config->activeRequirementCount())->toBe(0);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SD->value)
        ->assertSet('pooledRequirements.yearly.enabled', false)
        ->assertSet("selectedRequirements.monthly.{$monthly->id}", false)
        ->assertSet("selectedRequirements.yearly.{$independentYearly->id}", false)
        ->assertSet("selectedRequirements.one_time.{$oneTime->id}", false)
        ->assertDontSee('wire:model.live="pooledRequirements.yearly.enabled" checked', false)
        ->assertDontSee("wire:model=\"selectedRequirements.monthly.{$monthly->id}\" checked", false)
        ->assertDontSee("wire:model=\"selectedRequirements.yearly.{$independentYearly->id}\" checked", false)
        ->assertDontSee("wire:model=\"selectedRequirements.one_time.{$oneTime->id}\" checked", false);
});

it('keeps a level at zero active through the full enable, reload and reset cycle', function () {
    $this->actingAs(User::factory()->create());
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    $sdConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SD]);
    $smpConfig = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $sdBook = makePageApplicableTypeRecord($sdStudent, 'Buku SD Fokus', SchoolLevel::SD, 5, BillFrequency::Yearly);
    $sdActivity = makePageApplicableTypeRecord($sdStudent, 'Kegiatan SD Fokus', SchoolLevel::SD, 5, BillFrequency::Yearly);
    $smpType = makePageApplicableTypeRecord($smpStudent, 'Syarat SMP Fokus', SchoolLevel::SMP, 8, BillFrequency::Yearly);
    $sdPooled = makeCriteriaRequirement($sdConfig, $sdBook, SchoolLevel::SD, BillFrequency::Yearly, 50);
    $sdPooled->pooledPaymentTypes()->attach([$sdBook->id, $sdActivity->id]);
    $sdPooled->update(['is_active' => false]);
    makeCriteriaRequirement($smpConfig, $smpType, SchoolLevel::SMP, BillFrequency::Yearly, 70);
    $memberIds = collect([$sdBook->id, $sdActivity->id])->sort()->values()->all();

    $readBadge = fn (string $html, string $level): int => preg_match(
        '#>'.$level.'</span>\s*<span class="[^"]*">(\d+)</span>#',
        $html,
        $matches,
    ) === 1 ? (int) $matches[1] : -1;

    $page = Livewire::test(StudentExamEligibility::class);

    expect($readBadge($page->html(), 'SD'))->toBe(0)
        ->and($readBadge($page->html(), 'SMP'))->toBe(1);

    $page->call('openRequirements', SchoolLevel::SD->value)
        ->assertSet('pooledRequirements.yearly.enabled', false)
        ->set('pooledRequirements.yearly.enabled', true)
        ->call('saveRequirements')
        ->assertHasNoErrors();

    expect($readBadge($page->html(), 'SD'))->toBe(1);

    Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SD->value)
        ->assertSet('pooledRequirements.yearly.enabled', true);

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SD->value)
        ->call('confirmReset')
        ->assertSet('isResetConfirmOpen', false);

    $restored = $sdPooled->fresh();

    expect($restored)->not->toBeNull()
        ->and($restored->is_active)->toBeFalse()
        ->and((float) $restored->required_percentage)->toBe(50.0)
        ->and($restored->pooledPaymentTypes()->pluck('payment_types.id')->sort()->values()->all())->toBe($memberIds)
        ->and($sdConfig->activeRequirementCount())->toBe(0);

    $resetPage = Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SD->value)
        ->assertSet('pooledRequirements.yearly.enabled', false)
        ->assertSet('pooledRequirements.yearly.percentage', '50');

    expect($readBadge($resetPage->html(), 'SD'))->toBe(0);

    Livewire::test(StudentExamEligibility::class)
        ->call('openResetConfirmation', SchoolLevel::SD->value)
        ->call('confirmReset');

    expect(StudentExamRequirement::query()->where('student_eligibility_config_id', $sdConfig->id)->count())->toBe(1)
        ->and($sdPooled->fresh()->id)->toBe($restored->id)
        ->and($sdPooled->fresh()->pooledPaymentTypes()->count())->toBe(2);
});

it('defaults the yearly academic-year selector to the active academic year', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $activeAcademicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);

    Livewire::test(StudentExamEligibility::class)
        ->assertSet('yearlyAcademicYearId', (string) $activeAcademicYear->id)
        ->call('openRequirements', SchoolLevel::SMP->value)
        ->assertSee('Tahun Ajaran')
        ->assertSee($activeAcademicYear->year);
});

it('lists every existing academic year in the Tahunan selector', function () {
    $this->actingAs(User::factory()->create());
    [$student, , $activeYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $nextYear = AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $priorYear = AcademicYear::query()->create([
        'year' => '2025/2026',
        'is_active' => false,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);

    $component = Livewire::test(StudentExamEligibility::class)
        ->call('openRequirements', SchoolLevel::SMP->value);

    expect($component->viewData('academicYears')->pluck('year'))
        ->toContain($activeYear->year)
        ->toContain($nextYear->year)
        ->toContain($priorYear->year);

    $html = $component->html();

    expect($html)->toContain('value="'.$activeYear->id.'"')
        ->and($html)->toContain('value="'.$nextYear->id.'"')
        ->and($html)->toContain('value="'.$priorYear->id.'"');
});

it('switches the evaluated academic year and shows it in the eligibility detail', function () {
    $this->actingAs(User::factory()->create());
    [$student, $class, $activeYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $nextYear = AcademicYear::query()->create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $nextYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);
    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    [$book, $activity] = makeCanonicalPoolTypes($student, SchoolLevel::SMP, 8);
    $pooledRequirement = makeCriteriaRequirement($config, $book, SchoolLevel::SMP, BillFrequency::Yearly, 50);
    $pooledRequirement->pooledPaymentTypes()->attach([$book->id, $activity->id]);
    makeYearlyBill($student, $book, 1_000_000, '2026/2027');
    makeYearlyBill($student, $activity, 1_000_000, '2026/2027');
    $currentBookBill = makeYearlyBill($student, $book, 1_200_000, '2027/2028');
    makeYearlyBill($student, $activity, 1_200_000, '2027/2028');
    createSchoolMonthlyReportPayment(
        $student,
        Bank::factory()->cash()->create(),
        User::factory()->create(),
        '2028-09-15',
        [[
            'bill_id' => $currentBookBill->id,
            'payment_type_id' => $book->id,
            'academic_year' => '2027/2028',
            'amount' => 1_200_000,
        ]],
    );

    $component = Livewire::test(StudentExamEligibility::class)
        ->set('yearlyAcademicYearId', (string) $nextYear->id);

    $detail = $component->call('showDetail', $student->id)->get('detail');

    expect($detail['academic_year'])->toBe('2027/2028')
        ->and($detail['non_monthly_requirements'][0]['target'])->toBe(2_400_000.0)
        ->and($detail['non_monthly_requirements'][0]['paid'])->toBe(1_200_000.0)
        ->and($detail['non_monthly_requirements'][0]['status'])->toBe(StudentExamEligibilityService::STATUS_PASS)
        ->and($detail['is_eligible'])->toBeTrue();

    $component->assertSeeHtml('Tahun Ajaran 2027/2028');

    $component->set('yearlyAcademicYearId', (string) $activeYear->id)
        ->call('showDetail', $student->id)
        ->assertSet('isDetailModalOpen', true);
    $activeDetail = $component->get('detail');

    expect($activeDetail['academic_year'])->toBe('2026/2027')
        ->and($activeDetail['non_monthly_requirements'][0]['target'])->toBe(2_000_000.0);
});
