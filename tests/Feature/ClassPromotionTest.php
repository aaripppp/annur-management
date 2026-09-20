<?php

use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Exceptions\BlockedPromotionException;
use App\Livewire\AcademicYearManagement;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPaymentSetting;
use App\Services\ClassPromotionService;
use App\Support\ClassPromotionMapping;
use Livewire\Livewire;

beforeEach(function () {
    $this->sppType = makeBillType('SPP', auto: true, required: true);
    $this->ekskulType = makeBillType('Ekskul', auto: true, required: true);

    $this->activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        [
            'is_active' => true,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]
    );
    $this->activeYear->update(['is_active' => true]);

    $this->newYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        [
            'is_active' => false,
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
        ]
    );
});

function classPromotionKb(): SchoolClass
{
    return SchoolClass::query()->firstOrCreate(['name' => 'KB'], ['level' => -3]);
}

/*
|--------------------------------------------------------------------------
| KB/TKA/TKB Level Values
|--------------------------------------------------------------------------
*/

it('KB TKA and TKB have canonical ordered levels', function () {
    $kb = classPromotionKb();
    $tka = SchoolClass::create(['name' => 'A-1', 'level' => -2]);
    $tkb = SchoolClass::create(['name' => 'B-1', 'level' => -1]);

    expect($kb->level)->toBe(-3)
        ->and($tka->level)->toBe(-2)
        ->and($tkb->level)->toBe(-1)
        ->and(SchoolLevel::TK->classLevels())->toBe([-3, -2, -1]);
});

it('SchoolLevel::fromClassLevel maps KB TKA and TKB to TK', function () {
    expect(SchoolLevel::fromClassLevel(-3))->toBe(SchoolLevel::TK)
        ->and(SchoolLevel::fromClassLevel(-2))->toBe(SchoolLevel::TK)
        ->and(SchoolLevel::fromClassLevel(-1))->toBe(SchoolLevel::TK);
});

/*
|--------------------------------------------------------------------------
| Compact Numeric Class Names (SD/SMP/SMA)
|--------------------------------------------------------------------------
*/

it('promotes compact numeric classes to the next numeric level preserving rombel', function () {
    $class4A = SchoolClass::create(['name' => '4A', 'level' => 4]);
    $class5A = SchoolClass::create(['name' => '5A', 'level' => 5]);
    $class7A = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $class8A = SchoolClass::create(['name' => '8A', 'level' => 8]);
    $class10A = SchoolClass::create(['name' => '10A', 'level' => 10]);
    $class11A = SchoolClass::create(['name' => '11A', 'level' => 11]);

    createPromotionRule($class4A, 'promote', $class5A);
    createPromotionRule($class7A, 'promote', $class8A);
    createPromotionRule($class10A, 'promote', $class11A);

    expect(ClassPromotionMapping::runtimeDecisionFor($class4A)['target']?->id)->toBe($class5A->id)
        ->and(ClassPromotionMapping::runtimeDecisionFor($class7A)['target']?->id)->toBe($class8A->id)
        ->and(ClassPromotionMapping::runtimeDecisionFor($class10A)['target']?->id)->toBe($class11A->id);
});

it('preserves rombel for numeric classes', function () {
    $class4B = SchoolClass::create(['name' => '4B', 'level' => 4]);
    $class5B = SchoolClass::create(['name' => '5B', 'level' => 5]);
    $class7C = SchoolClass::create(['name' => '7C', 'level' => 7]);
    $class8C = SchoolClass::create(['name' => '8C', 'level' => 8]);
    $class10D = SchoolClass::create(['name' => '10D', 'level' => 10]);
    $class11D = SchoolClass::create(['name' => '11D', 'level' => 11]);

    createPromotionRule($class4B, 'promote', $class5B);
    createPromotionRule($class7C, 'promote', $class8C);
    createPromotionRule($class10D, 'promote', $class11D);

    expect(ClassPromotionMapping::runtimeDecisionFor($class4B)['target']?->id)->toBe($class5B->id)
        ->and(ClassPromotionMapping::runtimeDecisionFor($class7C)['target']?->id)->toBe($class8C->id)
        ->and(ClassPromotionMapping::runtimeDecisionFor($class10D)['target']?->id)->toBe($class11D->id);
});

it('blocks a numeric class whose stored rule target is missing', function () {
    $class4B = SchoolClass::create(['name' => '4B', 'level' => 4]);
    SchoolClass::create(['name' => '5A', 'level' => 5]);

    createPromotionRule($class4B, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class4B)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class4B)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET);
});

it('blocks numeric promotion when the target class does not exist', function () {
    $class4A = SchoolClass::create(['name' => '4A', 'level' => 4]);
    // 5A intentionally missing.

    createPromotionRule($class4A, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class4A)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class4A)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET)
        ->and(ClassPromotionMapping::isGraduationLevel(4))->toBeFalse();
});

it('promotes without blocking when the numeric target class exists', function () {
    $class4A = SchoolClass::create(['name' => '4A', 'level' => 4]);
    SchoolClass::create(['name' => '5A', 'level' => 5]);
    $student = Student::factory()->create(['class_id' => $class4A->id]);

    createPromotionRule($class4A, 'promote', SchoolClass::query()->where('name', '5A')->sole());

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class4A->id,
        'status' => 'active',
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result['promoted'])->toBe(1)
        ->and($result['blocked'])->toBe(0);

    $student->refresh();
    expect($student->class_id)->toBe(SchoolClass::query()->where('name', '5A')->first()->id);
});

/*
|--------------------------------------------------------------------------
| SD Level 1–2 Subgroups
|--------------------------------------------------------------------------
*/

it('promotes every level-1 subgroup to its exact level-2 subgroup', function () {
    $expected = [
        '1A-1' => '2A-1',
        '1A-2' => '2A-2',
        '1B-1' => '2B-1',
        '1B-2' => '2B-2',
        '1C-1' => '2C-1',
        '1C-2' => '2C-2',
        '1D-1' => '2D-1',
        '1D-2' => '2D-2',
    ];

    foreach ($expected as $sourceName => $targetName) {
        $source = SchoolClass::create(['name' => $sourceName, 'level' => 1]);
        $target = SchoolClass::create(['name' => $targetName, 'level' => 2]);

        createPromotionRule($source, 'promote', $target);

        expect(ClassPromotionMapping::runtimeDecisionFor($source)['action'])->toBe('promote')
            ->and(ClassPromotionMapping::runtimeDecisionFor($source)['target']?->id)->toBe($target->id);
    }
});

it('collapses every level-2 subgroup into the level-3 rombel', function () {
    $targets = collect(['3A', '3B', '3C', '3D'])
        ->mapWithKeys(fn (string $name): array => [$name => SchoolClass::create(['name' => $name, 'level' => 3])]);

    $expected = [
        '2A-1' => '3A',
        '2A-2' => '3A',
        '2B-1' => '3B',
        '2B-2' => '3B',
        '2C-1' => '3C',
        '2C-2' => '3C',
        '2D-1' => '3D',
        '2D-2' => '3D',
    ];

    foreach ($expected as $sourceName => $targetName) {
        $source = SchoolClass::create(['name' => $sourceName, 'level' => 2]);

        createPromotionRule($source, 'promote', $targets[$targetName]);

        expect(ClassPromotionMapping::runtimeDecisionFor($source)['action'])->toBe('promote')
            ->and(ClassPromotionMapping::runtimeDecisionFor($source)['target']?->id)->toBe($targets[$targetName]->id);
    }
});

it('merges both subgroups of a rombel into the single level-3 class', function () {
    $class3A = SchoolClass::create(['name' => '3A', 'level' => 3]);
    $class2A1 = SchoolClass::create(['name' => '2A-1', 'level' => 2]);
    $class2A2 = SchoolClass::create(['name' => '2A-2', 'level' => 2]);

    createPromotionRule($class2A1, 'promote', $class3A);
    createPromotionRule($class2A2, 'promote', $class3A);

    expect(ClassPromotionMapping::runtimeDecisionFor($class2A1)['target']?->id)->toBe($class3A->id)
        ->and(ClassPromotionMapping::runtimeDecisionFor($class2A2)['target']?->id)->toBe($class3A->id);
});

it('blocks 1A-1 when its exact 2A-1 target is missing despite 2A-2 existing', function () {
    $class1A1 = SchoolClass::create(['name' => '1A-1', 'level' => 1]);
    SchoolClass::create(['name' => '2A-2', 'level' => 2]);

    createPromotionRule($class1A1, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class1A1)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class1A1)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET);
});

it('blocks 1A-2 when its exact 2A-2 target is missing despite 2A-1 existing', function () {
    $class1A2 = SchoolClass::create(['name' => '1A-2', 'level' => 1]);
    SchoolClass::create(['name' => '2A-1', 'level' => 2]);

    createPromotionRule($class1A2, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class1A2)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class1A2)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET);
});

it('blocks both 2A-1 and 2A-2 when 3A is missing', function () {
    $class2A1 = SchoolClass::create(['name' => '2A-1', 'level' => 2]);
    $class2A2 = SchoolClass::create(['name' => '2A-2', 'level' => 2]);

    createPromotionRule($class2A1, 'promote', null);
    createPromotionRule($class2A2, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class2A1)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class2A1)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET)
        ->and(ClassPromotionMapping::runtimeDecisionFor($class2A2)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class2A2)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET);
});

it('blocks a level-1 subgroup whose exact target is missing', function () {
    $class1A1 = SchoolClass::create(['name' => '1A-1', 'level' => 1]);
    SchoolClass::create(['name' => '2B-2', 'level' => 2]);
    SchoolClass::create(['name' => '2A-2', 'level' => 2]);

    createPromotionRule($class1A1, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class1A1)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class1A1)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET);
});

it('promotes a level-1 subgroup student to the exact level-2 subgroup via the service', function () {
    $source = SchoolClass::create(['name' => '1A-1', 'level' => 1]);
    $target = SchoolClass::create(['name' => '2A-1', 'level' => 2]);
    SchoolClass::create(['name' => '2A-2', 'level' => 2]);
    $student = Student::factory()->create(['class_id' => $source->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $source->id,
        'status' => 'active',
    ]);

    createPromotionRule($source, 'promote', $target);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0]);

    expect($student->refresh()->class_id)->toBe($target->id);

    $enrollment = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $this->newYear->id)
        ->sole();

    expect($enrollment->school_class_id)->toBe($target->id)
        ->and($enrollment->status)->toBe('active');
});

it('previews missing subgroup targets with exact labels', function () {
    foreach (['1C-2', '1D-2', '2A-1', '2B-2'] as $name) {
        $class = SchoolClass::create(['name' => $name, 'level' => (int) $name[0]]);
        $student = Student::factory()->create(['class_id' => $class->id]);

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->assertSet('previewTotalBlocked', 4)
        ->assertSee('1C-2 → Aturan belum dikonfigurasi')
        ->assertSee('1D-2 → Aturan belum dikonfigurasi')
        ->assertSee('2A-1 → Aturan belum dikonfigurasi')
        ->assertSee('2B-2 → Aturan belum dikonfigurasi');
});

it('promotes a mixed batch containing subgroups, single rombels, SMA and graduation', function () {
    $classData = [
        ['name' => '1A-1', 'level' => 1],
        ['name' => '1A-2', 'level' => 1],
        ['name' => '2A-1', 'level' => 2],
        ['name' => '2A-2', 'level' => 2],
        ['name' => '2B-1', 'level' => 2],
        ['name' => '2B-2', 'level' => 2],
        ['name' => '3A', 'level' => 3],
        ['name' => '3B', 'level' => 3],
        ['name' => '4A', 'level' => 4],
        ['name' => '5A', 'level' => 5],
        ['name' => '7A', 'level' => 7],
        ['name' => '8A', 'level' => 8],
        ['name' => '11A', 'level' => 11],
        ['name' => '12A-IPA', 'level' => 12],
        ['name' => '12A-IPS', 'level' => 12],
    ];

    $classes = collect($classData)->mapWithKeys(function (array $attributes): array {
        $class = SchoolClass::create($attributes);

        return [$class->name => $class];
    });

    $couplings = [
        '1A-1' => ['promote', '2A-1'],
        '1A-2' => ['promote', '2A-2'],
        '2B-1' => ['promote', '3B'],
        '2B-2' => ['promote', '3B'],
        '4A' => ['promote', '5A'],
        '7A' => ['promote', '8A'],
        '11A' => ['promote', '12A-IPA'],
        '12A-IPS' => ['graduate', null],
    ];

    $students = [];

    foreach ($couplings as $sourceName => [$action, $targetName]) {
        if ($action === 'promote') {
            $student = Student::factory()->create(['class_id' => $classes[$sourceName]->id]);
            StudentAcademicEnrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $this->activeYear->id,
                'school_class_id' => $classes[$sourceName]->id,
                'status' => 'active',
            ]);
            $students[$sourceName] = ['student' => $student, 'target' => $classes[$targetName]];
        }

        createPromotionRule($classes[$sourceName], $action, $targetName === null ? null : $classes[$targetName]);
    }

    $graduateStudent = Student::factory()->create(['class_id' => $classes['12A-IPS']->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $graduateStudent->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $classes['12A-IPS']->id,
        'status' => 'active',
    ]);

    $futureStudent = Student::factory()->create(['class_id' => $classes['7A']->id]);
    $futureEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $classes['7A']->id,
        'status' => 'active',
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 7, 'graduated' => 1, 'blocked' => 0]);

    foreach ($students as $data) {
        expect($data['student']->fresh()->class_id)->toBe($data['target']->id);
    }

    expect($graduateStudent->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and($futureEnrollment->refresh()->status)->toBe('active')
        ->and($futureEnrollment->school_class_id)->toBe($classes['7A']->id);

    expect(AcademicYear::active()->id)->toBe($this->newYear->id);
});

/*
|--------------------------------------------------------------------------
| SMA Level 11 → Level 12 IPA Continuation
|--------------------------------------------------------------------------
*/

it('promotes 11A to 12A IPA, never IPS', function () {
    $class11A = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    createPromotionRule($class11A, 'promote', $ipa);

    expect(ClassPromotionMapping::runtimeDecisionFor($class11A)['target']?->id)->toBe($ipa->id)
        ->and(ClassPromotionMapping::runtimeDecisionFor($class11A)['target']?->name)->toBe('12A-IPA');
});

it('promotes 11B to 12B IPA, never IPS', function () {
    $class11B = SchoolClass::create(['name' => '11B', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12B-IPA', 'level' => 12]);
    SchoolClass::create(['name' => '12B-IPS', 'level' => 12]);

    createPromotionRule($class11B, 'promote', $ipa);

    expect(ClassPromotionMapping::runtimeDecisionFor($class11B)['target']?->id)->toBe($ipa->id)
        ->and(ClassPromotionMapping::runtimeDecisionFor($class11B)['target']?->name)->toBe('12B-IPA');
});

it('promotes 11A into 12A IPA via the full promotion flow', function () {
    $class11A = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $student = Student::factory()->create(['class_id' => $class11A->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class11A->id,
        'status' => 'active',
    ]);

    createPromotionRule($class11A, 'promote', $ipa);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0]);

    $student->refresh();
    expect($student->class_id)->toBe($ipa->id);

    $enrollment = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $this->newYear->id)
        ->sole();
    expect($enrollment->school_class_id)->toBe($ipa->id)
        ->and($enrollment->status)->toBe('active');
});

it('blocks 11A when the stored target is missing', function () {
    $class11A = SchoolClass::create(['name' => '11A', 'level' => 11]);
    SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    createPromotionRule($class11A, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class11A)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class11A)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET);
});

it('previews 4A and 11A missing targets with numeric labels', function () {
    $class4A = SchoolClass::create(['name' => '4A', 'level' => 4]);
    $class11A = SchoolClass::create(['name' => '11A', 'level' => 11]);
    // No rules configured for either class.

    foreach ([$class4A, $class11A] as $class) {
        $student = Student::factory()->create(['class_id' => $class->id]);
        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->assertSet('previewTotalBlocked', 2)
        ->assertSee('4A → Aturan belum dikonfigurasi')
        ->assertSee('11A → Aturan belum dikonfigurasi');
});

/*
|--------------------------------------------------------------------------
| Level 12 Graduation (Level-Based, Any Specialization)
|--------------------------------------------------------------------------
*/

it('treats every level-12 class as graduation when configured so', function () {
    foreach (['12A-IPA', '12A-IPS', '12B-IPA', '12B-IPS'] as $name) {
        $class = SchoolClass::create(['name' => $name, 'level' => 12]);

        createPromotionRule($class, 'graduate');

        $decision = ClassPromotionMapping::runtimeDecisionFor($class);

        expect(ClassPromotionMapping::isGraduationLevel($class->level))->toBeTrue()
            ->and($decision['action'])->toBe('graduate')
            ->and($decision['target'])->toBeNull()
            ->and($decision['label'])->toBe('Lulus');
    }
});

it('graduates 12A-IPA students without promoting or generating target-year bills', function () {
    $class12Ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $student = Student::factory()->create(['class_id' => $class12Ipa->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class12Ipa->id,
        'status' => 'active',
    ]);

    createPromotionRule($class12Ipa, 'graduate');

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 0, 'graduated' => 1, 'blocked' => 0]);

    $student->refresh();
    expect($student->status)->toBe(StudentStatus::Graduated)
        ->and($student->class_id)->toBe($class12Ipa->id);

    $targetEnrollment = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $this->newYear->id)
        ->sole();
    expect($targetEnrollment->school_class_id)->toBe($class12Ipa->id)
        ->and($targetEnrollment->status)->toBe('lulus');

    expect($student->bills()->where('academic_year', $this->newYear->year)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Mixed Numeric Batch
|--------------------------------------------------------------------------
*/

it('promotes a mixed numeric batch with IPA targets and level-12 graduation', function () {
    $classData = [
        ['name' => '4A', 'level' => 4],
        ['name' => '5A', 'level' => 5],
        ['name' => '7A', 'level' => 7],
        ['name' => '8A', 'level' => 8],
        ['name' => '10A', 'level' => 10],
        ['name' => '11A', 'level' => 11],
        ['name' => '11B', 'level' => 11],
        ['name' => '12A-IPA', 'level' => 12],
        ['name' => '12A-IPS', 'level' => 12],
        ['name' => '12B-IPA', 'level' => 12],
        ['name' => '12B-IPS', 'level' => 12],
    ];

    $classes = collect($classData)->mapWithKeys(function (array $attributes): array {
        $class = SchoolClass::create($attributes);

        return [$class->name => $class];
    });

    $students = [];
    $promoteFrom = ['4A' => '5A', '7A' => '8A', '10A' => '11A', '11A' => '12A-IPA', '11B' => '12B-IPA'];

    foreach ($promoteFrom as $sourceName => $targetName) {
        $student = Student::factory()->create(['class_id' => $classes[$sourceName]->id]);
        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $classes[$sourceName]->id,
            'status' => 'active',
        ]);
        $students[$sourceName] = ['student' => $student, 'target' => $classes[$targetName]];

        createPromotionRule($classes[$sourceName], 'promote', $classes[$targetName]);
    }

    $graduationStudents = [];

    foreach (['12A-IPA', '12B-IPS'] as $name) {
        $student = Student::factory()->create(['class_id' => $classes[$name]->id]);
        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $classes[$name]->id,
            'status' => 'active',
        ]);
        $graduationStudents[] = $student;

        createPromotionRule($classes[$name], 'graduate');
    }

    // Converted prospective-style future student: enrolled in the target year.
    $futureStudent = Student::factory()->create(['class_id' => $classes['7A']->id]);
    $futureEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $futureStudent->id,
        'academic_year_id' => $this->newYear->id,
        'school_class_id' => $classes['7A']->id,
        'status' => 'active',
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 5, 'graduated' => 2, 'blocked' => 0]);

    foreach ($students as $data) {
        expect($data['student']->fresh()->class_id)->toBe($data['target']->id);
    }

    $targetEnrollments = StudentAcademicEnrollment::where('academic_year_id', $this->newYear->id)->count();
    expect($targetEnrollments)->toBe(8); // 1 future + 5 promoted + 2 graduation markers

    foreach ($graduationStudents as $student) {
        expect($student->fresh()->status)->toBe(StudentStatus::Graduated);
    }

    $firstGraduateEnrollment = StudentAcademicEnrollment::where('student_id', $graduationStudents[0]->id)
        ->where('academic_year_id', $this->newYear->id)
        ->sole();
    expect($firstGraduateEnrollment->status)->toBe('lulus');

    $futureEnrollment->refresh();
    expect($futureEnrollment->school_class_id)->toBe($classes['7A']->id)
        ->and($futureEnrollment->status)->toBe('active');

    expect(AcademicYear::active()->id)->toBe($this->newYear->id);
});

/*
|--------------------------------------------------------------------------
| Class Level Ordering
|--------------------------------------------------------------------------
*/

it('promotes TK, SD, SMP and SMA classes to the next level when configured', function () {
    // TK: KB → TKA
    $kb = classPromotionKb();
    $tka = SchoolClass::create(['name' => 'A-1', 'level' => -2]);
    createPromotionRule($kb, 'promote', $tka);
    expect(ClassPromotionMapping::runtimeDecisionFor($kb)['target']?->id)->toBe($tka->id);

    // SD: level 1 → 2
    $class1 = SchoolClass::create(['name' => 'I A', 'level' => 1]);
    $class2 = SchoolClass::create(['name' => 'II A', 'level' => 2]);
    createPromotionRule($class1, 'promote', $class2);
    $target = ClassPromotionMapping::runtimeDecisionFor($class1)['target'];
    expect($target?->level)->toBe(2);

    // SD graduation: level 6 → Lulus
    $class6 = SchoolClass::create(['name' => 'VI A', 'level' => 6]);
    createPromotionRule($class6, 'graduate');
    $decision = ClassPromotionMapping::runtimeDecisionFor($class6);
    expect($decision['action'])->toBe('graduate');
    expect($decision['target'])->toBeNull();

    // SMP: level 7 → 8
    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    createPromotionRule($class7, 'promote', $class8);
    $target = ClassPromotionMapping::runtimeDecisionFor($class7)['target'];
    expect($target?->level)->toBe(8);

    // SMP graduation: level 9 → Lulus
    $class9 = SchoolClass::create(['name' => 'IX A', 'level' => 9]);
    createPromotionRule($class9, 'graduate');
    $decision = ClassPromotionMapping::runtimeDecisionFor($class9);
    expect($decision['action'])->toBe('graduate');
    expect($decision['target'])->toBeNull();

    // SMA: level 10 → 11
    $class10 = SchoolClass::create(['name' => 'X A', 'level' => 10]);
    $class11 = SchoolClass::create(['name' => 'XI A', 'level' => 11]);
    createPromotionRule($class10, 'promote', $class11);
    $target = ClassPromotionMapping::runtimeDecisionFor($class10)['target'];
    expect($target?->level)->toBe(11);

    // SMA graduation: level 12 → Lulus
    $class12 = SchoolClass::create(['name' => 'XII A', 'level' => 12]);
    createPromotionRule($class12, 'graduate');
    $decision = ClassPromotionMapping::runtimeDecisionFor($class12);
    expect($decision['action'])->toBe('graduate');
    expect($decision['target'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| TKA → TKB Promotion
|--------------------------------------------------------------------------
*/

it('promotes the single KB class to the first TKA rombel', function () {
    $kb = classPromotionKb();
    $tka = SchoolClass::create(['name' => 'A-1', 'level' => -2]);

    createPromotionRule($kb, 'promote', $tka);

    expect(ClassPromotionMapping::runtimeDecisionFor($kb)['target']?->id)->toBe($tka->id);
});

it('promotes TKA to TKB preserving section', function () {
    $tka = SchoolClass::create(['name' => 'A-1', 'level' => -2]);
    $tkb = SchoolClass::create(['name' => 'B-1', 'level' => -1]);

    createPromotionRule($tka, 'promote', $tkb);

    $decision = ClassPromotionMapping::runtimeDecisionFor($tka);
    expect($decision['target'])->not->toBeNull();
    expect($decision['target']->name)->toBe('B-1');
});

it('promotes A-5 to B-5', function () {
    $tka = SchoolClass::create(['name' => 'A-5', 'level' => -2]);
    $tkb = SchoolClass::create(['name' => 'B-5', 'level' => -1]);

    createPromotionRule($tka, 'promote', $tkb);

    $decision = ClassPromotionMapping::runtimeDecisionFor($tka);
    expect($decision['target'])->not->toBeNull();
    expect($decision['target']->name)->toBe('B-5');
});

/*
|--------------------------------------------------------------------------
| TKB Graduation
|--------------------------------------------------------------------------
*/

it('TKB graduates when configured so', function () {
    $tkb = SchoolClass::create(['name' => 'B-1', 'level' => -1]);

    createPromotionRule($tkb, 'graduate');

    $decision = ClassPromotionMapping::runtimeDecisionFor($tkb);
    expect($decision['action'])->toBe('graduate');
    expect($decision['target'])->toBeNull();
});

it('TKB does not promote to Grade 1', function () {
    $tkb = SchoolClass::create(['name' => 'B-3', 'level' => -1]);

    createPromotionRule($tkb, 'graduate');

    expect(ClassPromotionMapping::isGraduationLevel(-1))->toBeTrue();
    expect(ClassPromotionMapping::runtimeDecisionFor($tkb)['action'])->toBe('graduate');
    expect(ClassPromotionMapping::runtimeDecisionFor($tkb)['target'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Regular Rombel Preservation
|--------------------------------------------------------------------------
*/

it('preserves rombel for regular classes VII A to VIII A', function () {
    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    createPromotionRule($class7, 'promote', $class8);

    $decision = ClassPromotionMapping::runtimeDecisionFor($class7);
    expect($decision['target'])->not->toBeNull();
    expect($decision['target']->name)->toBe('VIII A');
});

it('preserves rombel for VII C to VIII C', function () {
    $class7 = SchoolClass::create(['name' => 'VII C', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII C', 'level' => 8]);

    createPromotionRule($class7, 'promote', $class8);

    $decision = ClassPromotionMapping::runtimeDecisionFor($class7);
    expect($decision['target'])->not->toBeNull();
    expect($decision['target']->name)->toBe('VIII C');
});

it('preserves rombel for VIII E to IX E', function () {
    $class8 = SchoolClass::create(['name' => 'VIII E', 'level' => 8]);
    $class9 = SchoolClass::create(['name' => 'IX E', 'level' => 9]);

    createPromotionRule($class8, 'promote', $class9);

    $decision = ClassPromotionMapping::runtimeDecisionFor($class8);
    expect($decision['target'])->not->toBeNull();
    expect($decision['target']->name)->toBe('IX E');
});

it('preserves rombel for hyphen classes X-A to XI-A', function () {
    $class10 = SchoolClass::create(['name' => 'X-A', 'level' => 10]);
    $class11 = SchoolClass::create(['name' => 'XI-A', 'level' => 11]);

    createPromotionRule($class10, 'promote', $class11);

    $decision = ClassPromotionMapping::runtimeDecisionFor($class10);
    expect($decision['target'])->not->toBeNull();
    expect($decision['target']->name)->toBe('XI-A');
});

/*
|--------------------------------------------------------------------------
| Missing Target Rombel Safety
|--------------------------------------------------------------------------
*/

it('blocks promotion when the stored target rombel does not exist', function () {
    $class7 = SchoolClass::create(['name' => 'VII E', 'level' => 7]);
    SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    createPromotionRule($class7, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class7)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class7)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET)
        ->and(ClassPromotionMapping::isGraduationLevel(7))->toBeFalse();
});

it('never silently assigns first class of target level', function () {
    $class7 = SchoolClass::create(['name' => 'VII E', 'level' => 7]);
    SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    SchoolClass::create(['name' => 'VIII B', 'level' => 8]);

    createPromotionRule($class7, 'promote', null);

    expect(ClassPromotionMapping::runtimeDecisionFor($class7)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class7)['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET);
});

/*
|--------------------------------------------------------------------------
| Graduation Levels
|--------------------------------------------------------------------------
*/

it('identifies all graduation levels correctly', function () {
    expect(ClassPromotionMapping::isGraduationLevel(-1))->toBeTrue();  // TKB
    expect(ClassPromotionMapping::isGraduationLevel(6))->toBeTrue();   // SD
    expect(ClassPromotionMapping::isGraduationLevel(9))->toBeTrue();   // SMP
    expect(ClassPromotionMapping::isGraduationLevel(12))->toBeTrue();  // SMA

    expect(ClassPromotionMapping::isGraduationLevel(-3))->toBeFalse(); // KB
    expect(ClassPromotionMapping::isGraduationLevel(-2))->toBeFalse(); // TKA
    expect(ClassPromotionMapping::isGraduationLevel(1))->toBeFalse();  // SD
    expect(ClassPromotionMapping::isGraduationLevel(7))->toBeFalse();  // SMP
    expect(ClassPromotionMapping::isGraduationLevel(10))->toBeFalse(); // SMA
    expect(ClassPromotionMapping::isGraduationLevel(99))->toBeFalse(); // Unknown levels are blocked, never graduated
});

/*
|--------------------------------------------------------------------------
| Preview Data
|--------------------------------------------------------------------------
*/

it('generates correct preview data for class promotion', function () {
    $class1 = SchoolClass::create(['name' => 'I A', 'level' => 1]);
    $class2 = SchoolClass::create(['name' => 'II A', 'level' => 2]);
    $class6 = SchoolClass::create(['name' => 'VI A', 'level' => 6]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    $s1 = Student::factory()->create(['class_id' => $class1->id]);
    $s2 = Student::factory()->create(['class_id' => $class1->id]);
    $s3 = Student::factory()->create(['class_id' => $class6->id]);
    $s4 = Student::factory()->create(['class_id' => $class8->id]);

    foreach ([$s1, $s2, $s3, $s4] as $s) {
        StudentAcademicEnrollment::create([
            'student_id' => $s->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $s->class_id,
            'status' => 'active',
        ]);
    }

    createPromotionRule($class1, 'promote', $class2);
    createPromotionRule($class6, 'graduate');
    createPromotionRule($class8, 'promote', SchoolClass::create(['name' => 'IX A', 'level' => 9]));

    $service = app(ClassPromotionService::class);
    $preview = $service->getPreviewData($this->activeYear, $this->newYear);

    expect($preview['summary']['total_promoted'])->toBe(3)
        ->and($preview['summary']['total_graduated'])->toBe(1);

    $grouped = $preview['grouped'];
    expect($grouped)->toHaveCount(3);

    $groupA = $grouped->first(fn ($g) => $g['current_class']->name === 'I A');
    expect($groupA['target_label'])->toBe('II A')
        ->and($groupA['is_graduation'])->toBeFalse()
        ->and($groupA['count'])->toBe(2);

    $group6 = $grouped->first(fn ($g) => $g['current_class']->name === 'VI A');
    expect($group6['target_label'])->toBe('Lulus')
        ->and($group6['is_graduation'])->toBeTrue()
        ->and($group6['count'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Full Promotion Flow
|--------------------------------------------------------------------------
*/

it('promotes students to next class with correct enrollment and billbook', function () {
    $class7 = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);

    makeBillRate($this->sppType, 8, 975000);
    makeLevelDefault($this->sppType, SchoolLevel::SMP);
    makeLevelDefault($this->ekskulType, SchoolLevel::SMP);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentPaymentSetting::firstOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $this->sppType->id],
        ['is_active' => true, 'started_at' => '2026-08-01']
    );

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    createPromotionRule($class7, 'promote', $class8);

    $service = app(ClassPromotionService::class);
    $result = $service->processPromotion($this->activeYear, $this->newYear);

    expect($result['promoted'])->toBe(1)
        ->and($result['graduated'])->toBe(0);

    $student->refresh();
    expect($student->class_id)->toBe($class8->id)
        ->and($student->status)->toBe(StudentStatus::Active);

    $enrollment = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $this->newYear->id)
        ->first();
    expect($enrollment)->not->toBeNull()
        ->and($enrollment->school_class_id)->toBe($class8->id)
        ->and($enrollment->status)->toBe('active');

    $newBills = $student->bills()
        ->where(function ($q) {
            $q->where('period_year', 2027)->where('period_month', '>=', 7)
                ->orWhere('period_year', 2028);
        })
        ->count();
    expect($newBills)->toBeGreaterThan(0);

    expect(AcademicYear::active()->id)->toBe($this->newYear->id);
});

it('promotes KB to TKA and preserves normal target-level bill generation', function () {
    $kb = classPromotionKb();
    $tka = SchoolClass::create(['name' => 'A-1', 'level' => -2]);
    makeBillRate($this->sppType, -2, 500000);
    makeLevelDefault($this->sppType, SchoolLevel::TK);

    $student = Student::factory()->create(['class_id' => $kb->id]);
    StudentPaymentSetting::firstOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $this->sppType->id],
        ['is_active' => true, 'started_at' => '2026-08-01']
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $kb->id,
        'status' => 'active',
    ]);

    createPromotionRule($kb, 'promote', $tka);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0])
        ->and($student->fresh()->class_id)->toBe($tka->id)
        ->and(StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $this->newYear->id)
            ->where('school_class_id', $tka->id)
            ->where('status', 'active')
            ->exists())->toBeTrue()
        ->and($student->bills()
            ->where(fn ($query) => $query
                ->where('period_year', '>', 2027)
                ->orWhere(fn ($periodQuery) => $periodQuery->where('period_year', 2027)->where('period_month', '>=', 7)))
            ->exists())->toBeTrue();
});

it('uses the source-year enrollment instead of current student class during promotion', function () {
    $kb = classPromotionKb();
    $tka = SchoolClass::create(['name' => 'A-1', 'level' => -2]);
    $tkb = SchoolClass::create(['name' => 'B-1', 'level' => -1]);
    $student = Student::factory()->create(['class_id' => $tkb->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $kb->id,
        'status' => 'active',
    ]);

    createPromotionRule($kb, 'promote', $tka);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0])
        ->and($student->fresh()->class_id)->toBe($tka->id)
        ->and(StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $this->activeYear->id)
            ->sole()->school_class_id)->toBe($kb->id);
});

it('graduates TKB without promoting to SD or generating target-year bills', function () {
    $tkb = SchoolClass::create(['name' => 'B-1', 'level' => -1]);
    SchoolClass::create(['name' => 'I A', 'level' => 1]);
    $student = Student::factory()->create(['class_id' => $tkb->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $tkb->id,
        'status' => 'active',
    ]);

    createPromotionRule($tkb, 'graduate');

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);
    $targetEnrollment = StudentAcademicEnrollment::query()
        ->where('student_id', $student->id)
        ->where('academic_year_id', $this->newYear->id)
        ->sole();

    expect($result)->toBe(['promoted' => 0, 'graduated' => 1, 'blocked' => 0])
        ->and($student->fresh()->class_id)->toBe($tkb->id)
        ->and($targetEnrollment->school_class_id)->toBe($tkb->id)
        ->and($targetEnrollment->status)->toBe('lulus')
        ->and($student->bills()->where('academic_year', $this->newYear->year)->count())->toBe(0);
});

it('graduates students at final level without creating new billbook', function () {
    $class6 = SchoolClass::create(['name' => 'VI A', 'level' => 6]);

    $student = Student::factory()->create(['class_id' => $class6->id]);

    makeMonthlyBill($student, $this->sppType, 500000, 8, 2026);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class6->id,
        'status' => 'active',
    ]);

    createPromotionRule($class6, 'graduate');

    $service = app(ClassPromotionService::class);
    $result = $service->processPromotion($this->activeYear, $this->newYear);

    expect($result['promoted'])->toBe(0)
        ->and($result['graduated'])->toBe(1);

    $student->refresh();
    expect($student->status)->toBe(StudentStatus::Graduated);

    $enrollment = StudentAcademicEnrollment::where('student_id', $student->id)
        ->where('academic_year_id', $this->newYear->id)
        ->first();
    expect($enrollment)->not->toBeNull()
        ->and($enrollment->status)->toBe('lulus')
        ->and($enrollment->school_class_id)->toBe($class6->id);

    expect($student->bills()->count())->toBe(1);

    $newYearBills = $student->bills()->where('academic_year', '2027/2028')->count();
    expect($newYearBills)->toBe(0);
});

it('is idempotent — processing same promotion twice does not duplicate', function () {
    $class1 = SchoolClass::create(['name' => 'I A', 'level' => 1]);
    $class2 = SchoolClass::create(['name' => 'II A', 'level' => 2]);

    $student = Student::factory()->create(['class_id' => $class1->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class1->id,
        'status' => 'active',
    ]);

    createPromotionRule($class1, 'promote', $class2);

    $service = app(ClassPromotionService::class);

    $result1 = $service->processPromotion($this->activeYear, $this->newYear);
    expect($result1['promoted'])->toBe(1);

    $enrollmentCount1 = StudentAcademicEnrollment::where('academic_year_id', $this->newYear->id)->count();

    $result2 = $service->processPromotion($this->activeYear, $this->newYear);
    expect($result2['promoted'])->toBe(0)
        ->and($result2['graduated'])->toBe(0);

    $enrollmentCount2 = StudentAcademicEnrollment::where('academic_year_id', $this->newYear->id)->count();
    expect($enrollmentCount2)->toBe($enrollmentCount1);
});

it('rejects the entire promotion when a target rombel is missing', function () {
    $class7a = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class7b = SchoolClass::create(['name' => 'VII B', 'level' => 7]);
    $class7c = SchoolClass::create(['name' => 'VII C', 'level' => 7]);
    SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    SchoolClass::create(['name' => 'VIII B', 'level' => 8]);
    // VIII C intentionally does NOT exist.

    createPromotionRule($class7a, 'promote', SchoolClass::query()->where('name', 'VIII A')->sole());
    createPromotionRule($class7b, 'promote', SchoolClass::query()->where('name', 'VIII B')->sole());
    createPromotionRule($class7c, 'promote', null);

    $students = collect([$class7a, $class7b, $class7c])->map(function (SchoolClass $class) {
        $student = Student::factory()->create(['class_id' => $class->id]);

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);

        return ['student' => $student, 'source_class' => $class];
    });

    $service = app(ClassPromotionService::class);

    $exception = null;

    try {
        $service->processPromotion($this->activeYear, $this->newYear);
    } catch (BlockedPromotionException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(BlockedPromotionException::class);
    expect($exception->blockedMappings())->toHaveCount(1);
    expect($exception->blockedMappings()[0]['source_class']->name)->toBe('VII C');

    // ZERO students were promoted.
    foreach ($students as $entry) {
        $student = $entry['student'];
        $student->refresh();
        expect($student->class_id)->toBe($entry['source_class']->id);

        // No target-year enrollment rows.
        expect(StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $this->newYear->id)
            ->exists())->toBeFalse();

        // No target-year bills generated.
        expect($student->bills()->where('academic_year', $this->newYear->year)->count())->toBe(0);

        // Source enrollment rows unchanged (still active).
        expect(StudentAcademicEnrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $this->activeYear->id)
            ->sole()->status)->toBe('active');
    }

    // Active AcademicYear remains the source year.
    expect(AcademicYear::active()->id)->toBe($this->activeYear->id)
        ->and($this->newYear->fresh()->is_active)->toBeFalse()
        ->and($this->newYear->fresh()->promotion_processed_at)->toBeNull();
});

it('never silently reassigns a blocked student to another rombel', function () {
    $class7c = SchoolClass::create(['name' => 'VII C', 'level' => 7]);
    SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    SchoolClass::create(['name' => 'VIII B', 'level' => 8]);
    // VIII C intentionally does NOT exist.

    createPromotionRule($class7c, 'promote', null);

    $student = Student::factory()->create(['class_id' => $class7c->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7c->id,
        'status' => 'active',
    ]);

    expect(fn () => app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear))
        ->toThrow(BlockedPromotionException::class);

    expect($student->fresh()->class_id)->toBe($class7c->id)
        ->and(AcademicYear::active()->id)->toBe($this->activeYear->id);
});

it('does not partially graduate when any source class is blocked', function () {
    $class6 = SchoolClass::create(['name' => 'VI A', 'level' => 6]);   // would graduate
    $class7c = SchoolClass::create(['name' => 'VII C', 'level' => 7]); // blocked: no rule / no VIII C
    SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    SchoolClass::create(['name' => 'VIII C', 'level' => 8]);

    createPromotionRule($class6, 'graduate');
    // No rule for VII C.

    foreach ([$class6, $class7c] as $class) {
        $student = Student::factory()->create(['class_id' => $class->id]);

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    expect(fn () => app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear))
        ->toThrow(BlockedPromotionException::class);

    // No graduation marker was created.
    expect(StudentAcademicEnrollment::query()
        ->where('academic_year_id', $this->newYear->id)
        ->exists())->toBeFalse();
    expect(Student::query()->where('status', StudentStatus::Graduated)->count())->toBe(0);

    // Active year untouched.
    expect(AcademicYear::active()->id)->toBe($this->activeYear->id);
});

it('promotes all rombels after the missing target class and its rule are created', function () {
    $class7a = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class7b = SchoolClass::create(['name' => 'VII B', 'level' => 7]);
    $class7c = SchoolClass::create(['name' => 'VII C', 'level' => 7]);
    SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    SchoolClass::create(['name' => 'VIII B', 'level' => 8]);

    $students = collect([$class7a, $class7b, $class7c])->map(function (SchoolClass $class) {
        $student = Student::factory()->create(['class_id' => $class->id]);

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);

        return ['student' => $student, 'source_class' => $class];
    });

    createPromotionRule($class7a, 'promote', SchoolClass::query()->where('name', 'VIII A')->sole());
    createPromotionRule($class7b, 'promote', SchoolClass::query()->where('name', 'VIII B')->sole());
    // VII C has no rule and no VIII C target yet.

    $service = app(ClassPromotionService::class);

    // First attempt is blocked.
    expect(fn () => $service->processPromotion($this->activeYear, $this->newYear))
        ->toThrow(BlockedPromotionException::class);
    expect(AcademicYear::active()->id)->toBe($this->activeYear->id);

    // Admin creates the missing class and its rule.
    $class8c = SchoolClass::create(['name' => 'VIII C', 'level' => 8]);
    createPromotionRule($class7c, 'promote', $class8c);

    // Retry succeeds: 7C → 8C, others promote normally.
    $result = $service->processPromotion($this->activeYear, $this->newYear);

    expect($result['promoted'])->toBe(3)
        ->and($result['graduated'])->toBe(0)
        ->and($result['blocked'])->toBe(0);

    $sevenCEntry = $students->first(fn (array $entry) => $entry['source_class']->id === $class7c->id);
    $sevenCStudent = $sevenCEntry['student'];
    expect($sevenCStudent->fresh()->class_id)->toBe($class8c->id);

    $sevenCEnrollment = StudentAcademicEnrollment::query()
        ->where('student_id', $sevenCStudent->id)
        ->where('academic_year_id', $this->newYear->id)
        ->sole();
    expect($sevenCEnrollment->school_class_id)->toBe($class8c->id)
        ->and($sevenCEnrollment->status)->toBe('active');

    // Target year becomes active once and only once all mappings are valid.
    expect(AcademicYear::active()->id)->toBe($this->newYear->id)
        ->and($this->newYear->fresh()->promotion_processed_at)->not->toBeNull();
});

it('keeps KB to TK promotion working with the safety guard', function () {
    $kb = classPromotionKb();
    $tka = SchoolClass::create(['name' => 'A-1', 'level' => -2]);
    $tkaExtra = SchoolClass::create(['name' => 'A-5', 'level' => -2]);
    $tkb = SchoolClass::create(['name' => 'B-5', 'level' => -1]);
    // Intentionally no B-1 for the A-1 source rombel.

    $kbStudent = Student::factory()->create(['class_id' => $kb->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $kbStudent->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $kb->id,
        'status' => 'active',
    ]);

    $a1Student = Student::factory()->create(['class_id' => $tka->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $a1Student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $tka->id,
        'status' => 'active',
    ]);

    $a5Student = Student::factory()->create(['class_id' => $tkaExtra->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $a5Student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $tkaExtra->id,
        'status' => 'active',
    ]);

    createPromotionRule($kb, 'promote', $tka);
    createPromotionRule($tka, 'promote', null); // A-1 → blocked: no B-1 target
    createPromotionRule($tkaExtra, 'promote', $tkb);

    // Missing B-1 blocks A-1's students; whole promotion rejected, zero writes.
    $exception = null;

    try {
        app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);
    } catch (BlockedPromotionException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(BlockedPromotionException::class);
    expect($exception->blockedMappings()[0]['source_class']->name)->toBe('A-1');

    $kbStudent->refresh();
    $a1Student->refresh();
    $a5Student->refresh();
    expect($kbStudent->class_id)->toBe($kb->id)
        ->and($a1Student->class_id)->toBe($tka->id)
        ->and($a5Student->class_id)->toBe($tkaExtra->id)
        ->and(AcademicYear::active()->id)->toBe($this->activeYear->id);
});

/*
|--------------------------------------------------------------------------
| Livewire Tests
|--------------------------------------------------------------------------
*/

it('academic year management page renders correctly', function () {
    Livewire::test(AcademicYearManagement::class)
        ->assertSee('2026/2027')
        ->assertSee('Tahun Ajaran');
});

it('shows blocked feedback and takes no writes when Livewire executes a blocked promotion', function () {
    $class7a = SchoolClass::create(['name' => 'VII A', 'level' => 7]);
    $class7c = SchoolClass::create(['name' => 'VII C', 'level' => 7]);
    SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    // VII C has no rule configured.

    createPromotionRule($class7a, 'promote', SchoolClass::query()->where('name', 'VIII A')->sole());

    foreach ([$class7a, $class7c] as $class) {
        $student = Student::factory()->create(['class_id' => $class->id]);
        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->assertSet('previewTotalBlocked', 1)
        ->assertSee('Promosi belum dapat diproses karena terdapat kelas sumber tanpa aturan aktif yang valid.')
        ->assertSee('VII C → Aturan belum dikonfigurasi');

    // Even direct executePromotion is guarded server-side.
    $component = Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->call('openConfirm')
        ->call('executePromotion')
        ->assertSet('isConfirmOpen', false)
        ->assertSet('isBlockedOpen', true)
        ->assertSee('Promosi Diblokir');

    expect(AcademicYear::active()->id)->toBe($this->activeYear->id)
        ->and($this->newYear->fresh()->is_active)->toBeFalse()
        ->and($this->newYear->fresh()->promotion_processed_at)->toBeNull()
        ->and(StudentAcademicEnrollment::query()
            ->where('academic_year_id', $this->newYear->id)
            ->exists())->toBeFalse();
});

it('class promotion preview shows correct data via Livewire', function () {
    $class1 = SchoolClass::create(['name' => 'I A', 'level' => 1]);
    $class2 = SchoolClass::create(['name' => 'II A', 'level' => 2]);
    $student = Student::factory()->create(['class_id' => $class1->id]);

    createPromotionRule($class1, 'promote', $class2);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class1->id,
        'status' => 'active',
    ]);

    Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->assertSee('II A')
        ->assertSee('Naik Kelas')
        ->assertDontSee('data-preview-jenjang="TKA"', false)
        ->assertDontSee('data-preview-jenjang="TKB"', false)
        ->assertSee('data-preview-jenjang="SD"', false);
});

it('groups the promotion preview by jenjang and source level', function () {
    $classes = collect([
        ['name' => 'X-A', 'level' => 10],
        ['name' => 'XI-A', 'level' => 11],
        ['name' => 'XII-A', 'level' => 12],
        ['name' => 'A-1', 'level' => -2],
        ['name' => 'A-10', 'level' => -2],
        ['name' => 'B-10', 'level' => -1],
        ['name' => 'A-2', 'level' => -2],
        ['name' => 'B-2', 'level' => -1],
        ['name' => 'B-1', 'level' => -1],
        ['name' => 'I A', 'level' => 1],
        ['name' => 'II A', 'level' => 2],
        ['name' => 'III A', 'level' => 3],
        ['name' => 'IV A', 'level' => 4],
        ['name' => 'V A', 'level' => 5],
        ['name' => 'VI A', 'level' => 6],
        ['name' => 'VII A', 'level' => 7],
        ['name' => 'VIII A', 'level' => 8],
        ['name' => 'IX A', 'level' => 9],
        ['name' => 'VII E', 'level' => 7],
        ['name' => 'VIII E', 'level' => 8],
        ['name' => 'VII C', 'level' => 7],
        ['name' => 'VIII C', 'level' => 8],
        ['name' => 'VII B', 'level' => 7],
        ['name' => 'VIII B', 'level' => 8],
        ['name' => 'VII D', 'level' => 7],
        ['name' => 'VIII D', 'level' => 8],
    ])->mapWithKeys(function (array $attributes): array {
        $class = SchoolClass::create($attributes);

        return [$class->name => $class];
    });
    $classes->put('KB', classPromotionKb());
    $classes->put('12A-IPA', SchoolClass::create(['name' => '12A-IPA', 'level' => 12]));

    $sourceClasses = [
        'KB',
        'X-A',
        'I A',
        'A-10',
        'VII E',
        'VI A',
        'B-1',
        'III A',
        'IX A',
        'A-2',
        'XI-A',
        'VII A',
        'II A',
        'XII-A',
        'VIII A',
        'VII C',
        'V A',
        'VII B',
        'IV A',
        'VII D',
    ];

    $couplings = [
        'KB' => ['promote', 'A-1'],
        'X-A' => ['promote', 'XI-A'],
        'I A' => ['promote', 'II A'],
        'A-10' => ['promote', 'B-10'],
        'VII E' => ['promote', 'VIII E'],
        'VI A' => ['graduate', null],
        'B-1' => ['graduate', null],
        'III A' => ['promote', 'IV A'],
        'IX A' => ['graduate', null],
        'A-2' => ['promote', 'B-2'],
        'XI-A' => ['promote', '12A-IPA'],
        'VII A' => ['promote', 'VIII A'],
        'II A' => ['promote', 'III A'],
        'XII-A' => ['graduate', null],
        'VIII A' => ['promote', 'IX A'],
        'VII C' => ['promote', 'VIII C'],
        'V A' => ['promote', 'VI A'],
        'VII B' => ['promote', 'VIII B'],
        'IV A' => ['promote', 'V A'],
        'VII D' => ['promote', 'VIII D'],
    ];

    foreach ($couplings as $sourceName => [$action, $targetName]) {
        if ($targetName === null) {
            createPromotionRule($classes[$sourceName], $action);
        } else {
            createPromotionRule($classes[$sourceName], $action, $classes[$targetName]);
        }
    }

    foreach ($sourceClasses as $className) {
        $student = Student::factory()->create(['class_id' => $classes[$className]->id]);

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $classes[$className]->id,
            'status' => 'active',
        ]);
    }

    $additionalStudent = Student::factory()->create(['class_id' => $classes['VII C']->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $additionalStudent->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $classes['VII C']->id,
        'status' => 'active',
    ]);

    $component = Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->assertSet('previewTotalPromoted', 17)
        ->assertSet('previewTotalGraduated', 4)
        ->assertSee('6 tingkat • 6 rombel • 6 siswa')
        ->assertSee('3 tingkat • 7 rombel • 8 siswa');

    $html = $component->html();
    $lastPosition = -1;

    foreach (['TK', 'SD', 'SMP', 'SMA'] as $jenjang) {
        $position = strpos($html, 'data-preview-jenjang="'.$jenjang.'"');

        expect($position)->not->toBeFalse()->toBeGreaterThan($lastPosition);
        $lastPosition = $position;
    }

    $lastPosition = -1;

    foreach ([-3, -2, -1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $level) {
        $position = strpos($html, 'data-preview-level="'.$level.'"');

        expect($position)->not->toBeFalse()->toBeGreaterThan($lastPosition);
        expect(substr_count($html, 'data-preview-level="'.$level.'"'))->toBe(1);
        $lastPosition = $position;
    }

    expect($html)
        ->toMatch('/data-preview-level="-3".*?KB.*?TKA.*?A-1/s')
        ->toMatch('/data-preview-level="-2".*?A-2 - A-10.*?TKB.*?B-2 - B-10/s')
        ->toMatch('/data-preview-level="1".*?Kelas I.*?I A.*?Kelas II.*?II A/s')
        ->toMatch('/data-preview-level="7"\s+data-rombel-count="5"\s+data-student-count="6".*?Kelas VII.*?VII A - VII E.*?Kelas VIII.*?VIII A - VIII E/s')
        ->toMatch('/data-preview-level="10".*?Kelas X.*?X-A.*?Kelas XI.*?XI-A/s');

    foreach ([-1, 6, 9, 12] as $graduationLevel) {
        expect($html)->toMatch('/data-preview-level="'.$graduationLevel.'".*?data-preview-target.*?Lulus/s');
    }
});

it('starts all preview accordions collapsed and resets them when reopened', function () {
    $classes = collect([
        ['name' => 'A-1', 'level' => -2],
        ['name' => 'B-1', 'level' => -1],
        ['name' => 'I A', 'level' => 1],
        ['name' => 'II A', 'level' => 2],
        ['name' => 'VII A', 'level' => 7],
        ['name' => 'VIII A', 'level' => 8],
        ['name' => 'X-A', 'level' => 10],
        ['name' => 'XI-A', 'level' => 11],
    ])->mapWithKeys(function (array $attributes): array {
        $class = SchoolClass::create($attributes);

        return [$class->name => $class];
    });

    $couplings = [
        'A-1' => ['promote', 'B-1'],
        'B-1' => ['graduate', null],
        'I A' => ['promote', 'II A'],
        'VII A' => ['promote', 'VIII A'],
        'X-A' => ['promote', 'XI-A'],
    ];

    foreach ($couplings as $sourceName => [$action, $targetName]) {
        if ($targetName === null) {
            createPromotionRule($classes[$sourceName], $action);
        } else {
            createPromotionRule($classes[$sourceName], $action, $classes[$targetName]);
        }
    }

    foreach (['A-1', 'B-1', 'I A', 'VII A', 'X-A'] as $className) {
        $student = Student::factory()->create(['class_id' => $classes[$className]->id]);

        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $classes[$className]->id,
            'status' => 'active',
        ]);
    }

    $component = Livewire::test(AcademicYearManagement::class)->call('openPreview');
    $html = $component->html();

    expect(substr_count($html, 'x-data="{ expanded: false }"'))->toBe(4)
        ->and($html)->not->toContain('x-data="{ expanded: true }"')
        ->and($html)->toMatch('/data-preview-jenjang="SD".*?x-data="\{ expanded: false \}".*?x-on:click="expanded = ! expanded".*?x-show="expanded"/s');

    $component
        ->call('closePreview')
        ->assertSet('isPreviewOpen', false)
        ->call('openPreview')
        ->assertSet('isPreviewOpen', true);

    expect(substr_count($component->html(), 'x-data="{ expanded: false }"'))->toBe(4)
        ->and($component->html())->not->toContain('x-data="{ expanded: true }"');
});

it('academic year selector in student detail renders correctly', function () {
    $class8 = SchoolClass::create(['name' => 'VIII A', 'level' => 8]);
    $student = Student::factory()->create(['class_id' => $class8->id]);

    makeMonthlyBill($student, $this->sppType, 975000, 8, 2026);
    makeMonthlyBill($student, $this->sppType, 975000, 8, 2027);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tahun Ajaran')
        ->assertSee('2026/2027')
        ->assertSee('2027/2028');
});

it('student status is aktif by default', function () {
    $student = Student::factory()->create();
    expect($student->status)->toBe(StudentStatus::Active)
        ->and($student->status_label)->toBe('Aktif');
});
