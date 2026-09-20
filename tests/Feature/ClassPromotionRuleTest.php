<?php

use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\ClassPromotionRuleManagement;
use App\Models\AcademicYear;
use App\Models\ClassPromotionRule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPaymentSetting;
use App\Models\User;
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

/*
|--------------------------------------------------------------------------
| Effective Resolver
|--------------------------------------------------------------------------
*/

it('blocks promotion when no rule exists for the source class', function () {
    $class7 = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);
    $class12Ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);

    $decision = ClassPromotionMapping::effectiveDecisionFor($class7);

    expect($decision['action'])->toBe('blocked')
        ->and($decision['target'])->toBeNull()
        ->and($decision['source'])->toBe('rule')
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_MISSING_RULE)
        ->and($decision['label'])->toBe('Aturan belum dikonfigurasi');

    $graduation = ClassPromotionMapping::effectiveDecisionFor($class12Ipa);

    expect($graduation['action'])->toBe('blocked')
        ->and($graduation['target'])->toBeNull()
        ->and($graduation['reason'])->toBe(ClassPromotionMapping::REASON_MISSING_RULE);
});

it('manual promote rule overrides the system target', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $ips->id,
        'is_active' => true,
    ]);

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('promote')
        ->and($decision['target']?->id)->toBe($ips->id)
        ->and($decision['source'])->toBe('rule')
        ->and($decision['label'])->toBe('12A-IPS');
});

it('manual graduate rule overrides a promotable class', function () {
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'graduate',
        'target_class_id' => null,
        'is_active' => true,
    ]);

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('graduate')
        ->and($decision['target'])->toBeNull()
        ->and($decision['source'])->toBe('rule')
        ->and($decision['label'])->toBe('Lulus');
});

it('manual promote rule overrides a graduation-point class', function () {
    $source = SchoolClass::create(['name' => '6A', 'level' => 6]);
    $target = SchoolClass::create(['name' => '7A', 'level' => 7]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $target->id,
        'is_active' => true,
    ]);

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('promote')
        ->and($decision['target']?->id)->toBe($target->id)
        ->and($decision['source'])->toBe('rule');
});

it('inactive rule blocks the promotion', function () {
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'graduate',
        'target_class_id' => null,
        'is_active' => false,
    ]);

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('blocked')
        ->and($decision['target'])->toBeNull()
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_INACTIVE_RULE)
        ->and($decision['label'])->toBe('Aturan tidak aktif');
});

it('a configured rule resolves a class that would otherwise be blocked', function () {
    $source = SchoolClass::create(['name' => '4B', 'level' => 4]);
    $target = SchoolClass::create(['name' => '5A', 'level' => 5]);

    expect(ClassPromotionMapping::effectiveDecisionFor($source)['action'])->toBe('blocked');

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $target->id,
        'is_active' => true,
    ]);

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('promote')
        ->and($decision['target']?->id)->toBe($target->id)
        ->and($decision['source'])->toBe('rule');
});

it('deleting the rule blocks the promotion (no resolver fallback)', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    $rule = ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $ips->id,
        'is_active' => true,
    ]);

    expect(ClassPromotionMapping::effectiveDecisionFor($source)['target']?->id)->toBe($ips->id);

    $rule->delete();

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('blocked')
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_MISSING_RULE)
        ->and($decision['target'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Service Preview
|--------------------------------------------------------------------------
*/

it('preview reflects the manual override target for a class', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    $student = Student::factory()->create(['class_id' => $source->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $source->id,
        'status' => 'active',
    ]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $ips->id,
        'is_active' => true,
    ]);

    $preview = app(ClassPromotionService::class)->getPreviewData($this->activeYear, $this->newYear);

    expect($preview['summary']['total_promoted'])->toBe(1)
        ->and($preview['summary']['total_graduated'])->toBe(0);

    $group = $preview['grouped']->first();
    expect($group['target_class']?->id)->toBe($ips->id)
        ->and($group['target_label'])->toBe('12A-IPS')
        ->and($group['is_graduation'])->toBeFalse()
        ->and($group['is_blocked'])->toBeFalse()
        ->and($group['decision_source'])->toBe('rule');
});

it('preview reports a manual graduate override as graduation', function () {
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $source->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $source->id,
        'status' => 'active',
    ]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'graduate',
        'target_class_id' => null,
        'is_active' => true,
    ]);

    $preview = app(ClassPromotionService::class)->getPreviewData($this->activeYear, $this->newYear);

    expect($preview['summary']['total_promoted'])->toBe(0)
        ->and($preview['summary']['total_graduated'])->toBe(1);

    $group = $preview['grouped']->first();
    expect($group['target_label'])->toBe('Lulus')
        ->and($group['is_graduation'])->toBeTrue()
        ->and($group['is_blocked'])->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Process Promotion
|--------------------------------------------------------------------------
*/

it('processes promotion to the manual override target', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    makeBillRate($this->sppType, 12, 1000000);
    makeLevelDefault($this->sppType, SchoolLevel::SMA);

    $student = Student::factory()->create(['class_id' => $source->id]);
    StudentPaymentSetting::firstOrCreate(
        ['student_id' => $student->id, 'payment_type_id' => $this->sppType->id],
        ['is_active' => true, 'started_at' => '2026-08-01']
    );
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $source->id,
        'status' => 'active',
    ]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $ips->id,
        'is_active' => true,
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0])
        ->and($student->fresh()->class_id)->toBe($ips->id);

    $enrollment = StudentAcademicEnrollment::query()
        ->where('student_id', $student->id)
        ->where('academic_year_id', $this->newYear->id)
        ->first();
    expect($enrollment->school_class_id)->toBe($ips->id)
        ->and($enrollment->status)->toBe('active');
});

it('graduates a promotable class when the manual rule says so', function () {
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);

    $student = Student::factory()->create(['class_id' => $source->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $source->id,
        'status' => 'active',
    ]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'graduate',
        'target_class_id' => null,
        'is_active' => true,
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 0, 'graduated' => 1, 'blocked' => 0])
        ->and($student->fresh()->status)->toBe(StudentStatus::Graduated);

    $enrollment = StudentAcademicEnrollment::query()
        ->where('student_id', $student->id)
        ->where('academic_year_id', $this->newYear->id)
        ->first();
    expect($enrollment->school_class_id)->toBe($source->id)
        ->and($enrollment->status)->toBe('lulus');
});

it('promotes a graduation-point class when the manual rule says so', function () {
    $source = SchoolClass::create(['name' => '6A', 'level' => 6]);
    $target = SchoolClass::create(['name' => '7A', 'level' => 7]);

    $student = Student::factory()->create(['class_id' => $source->id]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $source->id,
        'status' => 'active',
    ]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $target->id,
        'is_active' => true,
    ]);

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0])
        ->and($student->fresh()->class_id)->toBe($target->id);
});

it('processes a mixed batch honoring configure rules', function () {
    $c7A = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $c8A = SchoolClass::create(['name' => '8A', 'level' => 8]);
    $c8B = SchoolClass::create(['name' => '8B', 'level' => 8]);
    $c9B = SchoolClass::create(['name' => '9B', 'level' => 9]);
    $c9C = SchoolClass::create(['name' => '9C', 'level' => 9]);
    $c4B = SchoolClass::create(['name' => '4B', 'level' => 4]);
    $c5A = SchoolClass::create(['name' => '5A', 'level' => 5]);

    ClassPromotionRule::create([
        'source_class_id' => $c8B->id,
        'action' => 'graduate',
        'target_class_id' => null,
        'is_active' => true,
    ]);
    ClassPromotionRule::create([
        'source_class_id' => $c4B->id,
        'action' => 'promote',
        'target_class_id' => $c5A->id,
        'is_active' => true,
    ]);

    createPromotionRule($c7A, 'promote', $c8A);
    createPromotionRule($c9C, 'graduate');

    foreach ([$c7A, $c7A, $c8B, $c9C, $c4B] as $class) {
        $student = Student::factory()->create(['class_id' => $class->id]);
        StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeYear->id,
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 3, 'graduated' => 2, 'blocked' => 0])
        ->and(StudentAcademicEnrollment::where('academic_year_id', $this->newYear->id)->where('status', 'active')->count())->toBe(3)
        ->and(StudentAcademicEnrollment::where('academic_year_id', $this->newYear->id)->where('status', 'lulus')->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Livewire Component
|--------------------------------------------------------------------------
*/

it('page shows jenjang tabs and lists classes under the default tab', function () {
    $classSd = SchoolClass::create(['name' => '4A', 'level' => 4]);
    SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->assertSee('Aturan Kenaikan Kelas')
        ->assertSee('TK')
        ->assertSee('SD')
        ->assertSee('SMP')
        ->assertSee('SMA')
        ->assertSee($classSd->name)
        ->assertSee('Belum Dikonfigurasi');
});

it('filters classes by jenjang tab', function () {
    $sd = SchoolClass::create(['name' => '4A', 'level' => 4]);
    $smp = SchoolClass::create(['name' => '7A', 'level' => 7]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('setJenjang', 'SMP')
        ->assertSee($smp->name)
        ->assertDontSee('4A');
});

it('creates a promote override rule through the component', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->assertSet('sourceClassName', $source->name.' • '.$source->level_name)
        ->set('targetClassId', $ips->id)
        ->call('save')
        ->assertHasNoErrors();

    $rule = ClassPromotionRule::where('source_class_id', $source->id)->first();
    expect($rule)->not->toBeNull()
        ->and($rule->action)->toBe('promote')
        ->and($rule->target_class_id)->toBe($ips->id)
        ->and($rule->is_active)->toBeTrue();
});

it('creates a graduate override rule through the component', function () {
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    SchoolClass::create(['name' => '8A', 'level' => 8]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('action', 'graduate')
        ->set('targetClassId', null)
        ->call('save')
        ->assertHasNoErrors();

    $rule = ClassPromotionRule::where('source_class_id', $source->id)->first();
    expect($rule->action)->toBe('graduate')
        ->and($rule->target_class_id)->toBeNull();
});

it('requires a target class for a promote override', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('targetClassId', null)
        ->call('save')
        ->assertHasErrors('targetClassId');
});

it('rejects a target equal to the source class', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('targetClassId', $source->id)
        ->call('save')
        ->assertHasErrors('targetClassId');
});

it('rejects a graduate override that carries a target class', function () {
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $other = SchoolClass::create(['name' => '8A', 'level' => 8]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('action', 'graduate')
        ->set('targetClassId', $other->id)
        ->call('save')
        ->assertHasErrors('targetClassId');
});

it('does not allow two rules for the same source class', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $ipa->id,
        'is_active' => true,
    ]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->assertSet('ruleId', null)
        ->set('targetClassId', $ips->id)
        ->call('save')
        ->assertHasErrors('sourceClassId');
});

it('edits an existing rule and deletes it through the page', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    $rule = ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $ipa->id,
        'is_active' => true,
    ]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('edit', $source->id)
        ->assertSet('ruleId', $rule->id)
        ->assertSet('action', 'promote')
        ->set('targetClassId', $ips->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($rule->fresh()->target_class_id)->toBe($ips->id);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('confirmDelete', $rule->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('deleteRule');

    expect(ClassPromotionRule::find($rule->id))->toBeNull()
        ->and(ClassPromotionMapping::effectiveDecisionFor($source)['action'])->toBe('blocked');
});

it('can disable an override so the promotion is blocked', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    $rule = ClassPromotionRule::create([
        'source_class_id' => $source->id,
        'action' => 'promote',
        'target_class_id' => $ips->id,
        'is_active' => true,
    ]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('edit', $source->id)
        ->set('isActive', false)
        ->call('save')
        ->assertHasNoErrors();

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($rule->fresh()->is_active)->toBeFalse()
        ->and($decision['action'])->toBe('blocked')
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_INACTIVE_RULE);
});

/*
|--------------------------------------------------------------------------
| Route & Navigation
|--------------------------------------------------------------------------
*/

it('authenticated users can open the aturan kenaikan kelas page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('aturan-kenaikan-kelas.index'))
        ->assertStatus(200)
        ->assertSee('Aturan Kenaikan Kelas');
});

it('guests are redirected away from the aturan kenaikan kelas page', function () {
    $this->get(route('aturan-kenaikan-kelas.index'))
        ->assertRedirect(route('login'));
});

it('renders all master data navigation links including the new menu item', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('aturan-kenaikan-kelas.index'))
        ->assertStatus(200)
        ->assertSee(route('bank.index'), false)
        ->assertSee(route('jenis-pembayaran.index'), false)
        ->assertSee(route('tarif-pembayaran.index'), false)
        ->assertSee(route('kelas.index'), false)
        ->assertSee(route('tahun-ajaran.index'), false)
        ->assertSee(route('aturan-kenaikan-kelas.index'), false);
});
