<?php

use App\Livewire\ClassPromotionRuleManagement;
use App\Models\AcademicYear;
use App\Models\ClassPromotionRule;
use App\Models\SchoolClass;
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

it('rejects a promote rule mapping backwards to a lower level', function () {
    $source = SchoolClass::create(['name' => '5A', 'level' => 5]);
    $back = SchoolClass::create(['name' => '4A', 'level' => 4]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('targetClassId', $back->id)
        ->call('save')
        ->assertHasErrors('targetClassId');

    expect(ClassPromotionRule::where('source_class_id', $source->id)->doesntExist())->toBeTrue();
});

it('rejects a two-node cycle between a class pair', function () {
    $c4A = SchoolClass::create(['name' => '4A', 'level' => 4]);
    $c4B = SchoolClass::create(['name' => '4B', 'level' => 4]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $c4A->id)
        ->set('targetClassId', $c4B->id)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $c4B->id)
        ->set('targetClassId', $c4A->id)
        ->call('save')
        ->assertHasErrors('targetClassId');

    expect(ClassPromotionRule::where('source_class_id', $c4B->id)->doesntExist())->toBeTrue();
});

it('rejects a self-loop promoting a class to itself', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('targetClassId', $source->id)
        ->call('save')
        ->assertHasErrors('targetClassId');
});

it('allows forward promotions including from a graduation point', function () {
    $source = SchoolClass::create(['name' => '6A', 'level' => 6]);
    $target = SchoolClass::create(['name' => '7A', 'level' => 7]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('action', 'promote')
        ->set('targetClassId', $target->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ClassPromotionMapping::effectiveDecisionFor($source)['target']?->id)->toBe($target->id);
});
