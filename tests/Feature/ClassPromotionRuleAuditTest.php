<?php

use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Livewire\ClassPromotionRuleManagement;
use App\Models\AcademicYear;
use App\Models\ClassPromotionRule;
use App\Models\ClassPromotionRuleLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use App\Services\ClassPromotionService;
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
| Audit Trail — Manual Changes
|--------------------------------------------------------------------------
*/

it('records a created manual rule with the acting user', function () {
    $user = User::factory()->create();
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    Livewire::actingAs($user)
        ->test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('targetClassId', $ips->id)
        ->call('save')
        ->assertHasNoErrors();

    $log = ClassPromotionRuleLog::where('action_type', 'created_manual')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->source_class_id)->toBe($source->id)
        ->and($log->new_action)->toBe('promote')
        ->and($log->new_target_class_id)->toBe($ips->id)
        ->and($log->old_action)->toBeNull()
        ->and($log->metadata)->toBeNull()
        ->and($log->changed_by)->toBe($user->id);
});

it('records an edited manual rule with old and new state', function () {
    $user = User::factory()->create();
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    $rule = createPromotionRule($source, 'promote', $ipa);

    Livewire::actingAs($user)
        ->test(ClassPromotionRuleManagement::class)
        ->call('edit', $source->id)
        ->set('targetClassId', $ips->id)
        ->call('save')
        ->assertHasNoErrors();

    $log = ClassPromotionRuleLog::where('action_type', 'edited_manual')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->class_promotion_rule_id)->toBe($rule->id)
        ->and($log->old_target_class_id)->toBe($ipa->id)
        ->and($log->new_target_class_id)->toBe($ips->id)
        ->and($log->old_source_type)->toBeNull()
        ->and($log->new_source_type)->toBeNull()
        ->and($log->changed_by)->toBe($user->id)
        ->and($rule->fresh()->target_class_id)->toBe($ips->id);
});

it('records activation with metadata and keeps the rule active', function () {
    $user = User::factory()->create();
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $target = SchoolClass::create(['name' => '8A', 'level' => 8]);

    $rule = createPromotionRule($source, 'promote', $target, isActive: false);

    Livewire::actingAs($user)
        ->test(ClassPromotionRuleManagement::class)
        ->call('edit', $source->id)
        ->set('isActive', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($rule->fresh()->is_active)->toBeTrue();

    $log = ClassPromotionRuleLog::where('action_type', 'rule_activated')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->class_promotion_rule_id)->toBe($rule->id)
        ->and($log->source_class_id)->toBe($source->id)
        ->and($log->new_action)->toBe('promote')
        ->and($log->new_target_class_id)->toBe($target->id)
        ->and($log->metadata['was_active'])->toBeFalse()
        ->and($log->metadata['is_active'])->toBeTrue()
        ->and($log->changed_by)->toBe($user->id);
});

it('records deactivation with metadata and blocks the runtime decision', function () {
    $user = User::factory()->create();
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $target = SchoolClass::create(['name' => '8A', 'level' => 8]);

    $rule = createPromotionRule($source, 'promote', $target);

    Livewire::actingAs($user)
        ->test(ClassPromotionRuleManagement::class)
        ->call('edit', $source->id)
        ->set('isActive', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($rule->fresh()->is_active)->toBeFalse();

    $log = ClassPromotionRuleLog::where('action_type', 'rule_deactivated')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->class_promotion_rule_id)->toBe($rule->id)
        ->and($log->metadata['was_active'])->toBeTrue()
        ->and($log->metadata['is_active'])->toBeFalse()
        ->and($log->changed_by)->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Audit Trail — Deletion
|--------------------------------------------------------------------------
*/

it('records a deleted rule with the acting user and metadata', function () {
    $user = User::factory()->create();
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $target = SchoolClass::create(['name' => '8A', 'level' => 8]);

    $rule = createPromotionRule($source, 'promote', $target);

    Livewire::actingAs($user)
        ->test(ClassPromotionRuleManagement::class)
        ->call('confirmDelete', $rule->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('deleteRule');

    expect(ClassPromotionRule::find($rule->id))->toBeNull();

    $log = ClassPromotionRuleLog::where('action_type', 'rule_deleted')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->class_promotion_rule_id)->toBe($rule->id)
        ->and($log->source_class_id)->toBe($source->id)
        ->and($log->old_action)->toBe('promote')
        ->and($log->old_target_class_id)->toBe($target->id)
        ->and($log->metadata['was_active'])->toBeTrue()
        ->and($log->changed_by)->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Runtime Non-Regression
|--------------------------------------------------------------------------
*/

it('gracefully handles a mixed run after audit-logged manual changes', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
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

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('targetClassId', $ips->id)
        ->call('save')
        ->assertHasNoErrors();

    $result = app(ClassPromotionService::class)->processPromotion($this->activeYear, $this->newYear);

    expect($result)->toBe(['promoted' => 1, 'graduated' => 0, 'blocked' => 0])
        ->and($student->fresh()->class_id)->toBe($ips->id)
        ->and($student->fresh()->status)->toBe(StudentStatus::Active);
});

/*
|--------------------------------------------------------------------------
| History UI
|--------------------------------------------------------------------------
*/

it('opens the audit history modal with recent entries', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('targetClassId', $ips->id)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openHistory')
        ->assertSet('showHistoryModal', true)
        ->assertSee('Riwayat Perubahan Aturan')
        ->assertSee('Dibuat')
        ->assertSee($source->name)
        ->assertSee('Naik ke '.$ips->name)
        ->assertSee('Sistem');
});
