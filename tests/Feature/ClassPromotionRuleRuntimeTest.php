<?php

use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Exceptions\BlockedPromotionException;
use App\Livewire\ClassPromotionRuleManagement;
use App\Models\AcademicYear;
use App\Models\ClassPromotionRule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
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

it('promotes to the active database rule target via preview', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);
    $class9 = SchoolClass::create(['name' => '9A', 'level' => 9]);

    createPromotionRule($class7, 'promote', $class9);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $preview = app(ClassPromotionService::class)->getPreviewData($this->activeYear, $this->newYear);

    expect($preview['summary']['total_promoted'])->toBe(1)
        ->and($preview['summary']['total_graduated'])->toBe(0);

    $group = $preview['grouped']->first();
    expect($group['target_class']?->id)->toBe($class9->id)
        ->and($group['target_label'])->toBe('9A')
        ->and($group['is_blocked'])->toBeFalse()
        ->and($group['decision_source'])->toBe('rule');
});

it('a missing rule blocks the preview decision with the reason label', function () {
    SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);

    $decision = ClassPromotionMapping::runtimeDecisionFor(
        SchoolClass::query()->where('name', '7A')->sole()
    );

    expect($decision['action'])->toBe('blocked')
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_MISSING_RULE)
        ->and($decision['label'])->toBe('Aturan belum dikonfigurasi');
});

it('a missing rule blocks processPromotion with zero writes', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => '8A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    expect(fn () => app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear))
        ->toThrow(BlockedPromotionException::class);

    expect($student->fresh()->class_id)->toBe($class7->id)
        ->and(StudentAcademicEnrollment::query()
            ->where('academic_year_id', $this->newYear->id)
            ->exists())->toBeFalse()
        ->and(AcademicYear::active()->id)->toBe($this->activeYear->id);
});

it('an inactive rule blocks the runtime decision even when a target exists', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => '8A', 'level' => 8]);

    createPromotionRule($class7, 'promote', $class8, isActive: false);

    $decision = ClassPromotionMapping::runtimeDecisionFor($class7);

    expect($decision['action'])->toBe('blocked')
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_INACTIVE_RULE)
        ->and($decision['label'])->toBe('Aturan tidak aktif');

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    expect(fn () => app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear))
        ->toThrow(BlockedPromotionException::class);
});

it('a stored rule target wins at runtime regardless of class naming', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $class8A = SchoolClass::create(['name' => '8A', 'level' => 8]);

    $drifted = SchoolClass::create(['name' => '8B', 'level' => 8]);
    createPromotionRule($class7, 'promote', $drifted);

    $decision = ClassPromotionMapping::runtimeDecisionFor($class7);
    expect($decision['action'])->toBe('promote')
        ->and($decision['target']?->id)->toBe($drifted->id);
});

it('executes promotion to the stored target, not a name-derived one', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);
    $class8B = SchoolClass::create(['name' => '8B', 'level' => 8]);

    createPromotionRule($class7, 'promote', $class8B);

    $student = Student::factory()->create(['class_id' => $class7->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class7->id,
        'status' => 'active',
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0])
        ->and($student->fresh()->class_id)->toBe($class8B->id);
});

it('executes promotion to the configured target class', function () {
    $class11 = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    createPromotionRule($class11, 'promote', $ips);

    makeBillRate($this->sppType, 12, 1000000);
    makeLevelDefault($this->sppType, SchoolLevel::SMA);

    $student = Student::factory()->create(['class_id' => $class11->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class11->id,
        'status' => 'active',
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0])
        ->and($student->fresh()->class_id)->toBe($ips->id);
});

it('deactivating a rule via Livewire blocks the runtime decision', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => '8A', 'level' => 8]);

    createPromotionRule($class7, 'promote', $class8);

    expect(ClassPromotionMapping::runtimeDecisionFor($class7)['action'])->toBe('promote');

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('edit', $class7->id)
        ->set('isActive', false)
        ->call('save')
        ->assertSet('isModalOpen', false);

    $decision = ClassPromotionMapping::runtimeDecisionFor($class7);
    expect($decision['action'])->toBe('blocked')
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_INACTIVE_RULE);
});

it('deleting a rule blocks the runtime decision', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);

    createPromotionRule($class7, 'promote', SchoolClass::query()->where('name', '8A')->sole());

    expect(ClassPromotionMapping::runtimeDecisionFor($class7)['action'])->toBe('promote');

    ClassPromotionRule::query()->where('source_class_id', $class7->id)->delete();

    $decision = ClassPromotionMapping::runtimeDecisionFor($class7);
    expect($decision['action'])->toBe('blocked')
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_MISSING_RULE);
});

it('graduation is database-authoritative and blocked once the rule disappears', function () {
    $class6 = SchoolClass::create(['name' => '6A', 'level' => 6]);

    createPromotionRule($class6, 'graduate');

    expect(ClassPromotionMapping::runtimeDecisionFor($class6)['action'])->toBe('graduate');

    $student = Student::factory()->create(['class_id' => $class6->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class6->id,
        'status' => 'active',
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 0, 'graduated' => 1, 'blocked' => 0])
        ->and($student->fresh()->status)->toBe(StudentStatus::Graduated);

    ClassPromotionRule::query()->where('source_class_id', $class6->id)->delete();

    expect(ClassPromotionMapping::runtimeDecisionFor($class6)['action'])->toBe('blocked');
});

it('blocks when the stored rule target is missing', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);

    createPromotionRule($class7, 'promote', null);

    $decision = ClassPromotionMapping::runtimeDecisionFor($class7);

    expect($decision['action'])->toBe('blocked')
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_INVALID_TARGET)
        ->and($decision['label'])->toBe('Kelas tujuan pada aturan tidak tersedia');
});

it('deleting the target class cascades the rule away and blocks the runtime decision', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => '8A', 'level' => 8]);

    createPromotionRule($class7, 'promote', $class8);

    expect(ClassPromotionMapping::runtimeDecisionFor($class7)['action'])->toBe('promote');

    $class8->delete();

    expect(ClassPromotionRule::query()->where('source_class_id', $class7->id)->exists())->toBeFalse()
        ->and(ClassPromotionMapping::runtimeDecisionFor($class7)['action'])->toBe('blocked')
        ->and(ClassPromotionMapping::runtimeDecisionFor($class7)['reason'])->toBe(ClassPromotionMapping::REASON_MISSING_RULE);
});

it('a fully configured roster promotes and graduates with nothing blocked', function () {
    $roster = createPromotionRuleRoster();
    createPromotionRosterRules($roster);

    expect(SchoolClass::query()->count())->toBe(74)
        ->and(ClassPromotionRule::query()->count())->toBe(74);

    $decisions = $roster->map(
        fn (SchoolClass $class) => ClassPromotionMapping::runtimeDecisionFor($class)
    );

    expect($decisions->where('action', 'promote')->count())->toBe(55)
        ->and($decisions->where('action', 'graduate')->count())->toBe(19)
        ->and($decisions->where('action', 'blocked')->count())->toBe(0);
});

it('blocks the entire mixed batch when a single class lacks a rule', function () {
    $class4 = SchoolClass::create(['name' => '4A', 'level' => 4]);
    $class5 = SchoolClass::create(['name' => '5A', 'level' => 5]);
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $class8 = SchoolClass::create(['name' => '8A', 'level' => 8]);
    $class10 = SchoolClass::create(['name' => '10A', 'level' => 10]);
    SchoolClass::create(['name' => '11A', 'level' => 11]);

    createPromotionRule($class4, 'promote', $class5);
    createPromotionRule($class7, 'promote', $class8);

    foreach ([$class4, $class7, $class10] as $class) {
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

    expect(StudentAcademicEnrollment::query()
        ->where('academic_year_id', $this->newYear->id)
        ->exists())->toBeFalse()
        ->and(AcademicYear::active()->id)->toBe($this->activeYear->id);
});
