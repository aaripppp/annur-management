<?php

use App\Livewire\ClassPromotionRuleManagement;
use App\Models\ClassPromotionRule;
use App\Models\SchoolClass;
use App\Support\ClassPromotionMapping;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| DB-Only Promotion Semantics
|--------------------------------------------------------------------------
*/

it('a new class shows Belum Dikonfigurasi, blocks, and becomes promotable after configuring', function () {
    $source = SchoolClass::create(['name' => '5F', 'level' => 5]);
    $target = SchoolClass::create(['name' => '6F', 'level' => 6]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->assertSee('5F')
        ->assertSee('Belum Dikonfigurasi')
        ->assertSee('Tidak Dapat Diproses')
        ->assertSee('Diblokir');

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('blocked')
        ->and($decision['target'])->toBeNull()
        ->and($decision['reason'])->toBe(ClassPromotionMapping::REASON_MISSING_RULE);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openCreate', $source->id)
        ->set('targetClassId', $target->id)
        ->call('save')
        ->assertHasNoErrors();

    $rule = ClassPromotionRule::where('source_class_id', $source->id)->first();
    expect($rule)->not->toBeNull()
        ->and($rule->action)->toBe('promote')
        ->and($rule->target_class_id)->toBe($target->id);

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('promote')
        ->and($decision['target']?->id)->toBe($target->id);
});

it('renaming the source class keeps the configured rule intact', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    createPromotionRule($source, 'promote', $ips);

    $source->update(['name' => '11D']);

    $decision = ClassPromotionMapping::effectiveDecisionFor($source->fresh());

    expect($decision['action'])->toBe('promote')
        ->and($decision['target']?->id)->toBe($ips->id)
        ->and($decision['label'])->toBe('12A-IPS');

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('edit', $source->id)
        ->assertSet('sourceClassName', '11D • '.$source->fresh()->level_name)
        ->assertSet('action', 'promote')
        ->assertSet('targetClassId', $ips->id);
});

it('renaming the target class is reflected in the effective decision', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $target = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    createPromotionRule($source, 'promote', $target);

    $target->update(['name' => '12C']);

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('promote')
        ->and($decision['target']?->name)->toBe('12C')
        ->and($decision['label'])->toBe('12C');

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('setJenjang', 'SMA')
        ->assertSee('12C')
        ->assertSee('Naik Kelas');
});

it('deleting the target class cascades to remove the configured rule', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $target = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    createPromotionRule($source, 'promote', $target);

    $target->delete();

    expect(SchoolClass::find($target->id))->toBeNull()
        ->and(ClassPromotionRule::where('source_class_id', $source->id)->exists())->toBeFalse()
        ->and(ClassPromotionMapping::effectiveDecisionFor($source)['action'])->toBe('blocked');
});

/*
|--------------------------------------------------------------------------
| Coverage Summary
|--------------------------------------------------------------------------
*/

it('coverage summary reflects a fully configured roster', function () {
    $roster = createPromotionRuleRoster();
    createPromotionRosterRules($roster);

    expect($roster)->toHaveCount(74)
        ->and(ClassPromotionRule::count())->toBe(74)
        ->and(ClassPromotionRule::where('is_active', true)->count())->toBe(74);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->assertSee('Total Kelas')
        ->assertSee('Rule Aktif')
        ->assertSee('Rule Tidak Aktif')
        ->assertSee('Belum Punya Rule')
        ->assertSeeHtml('<span class="font-label-lg text-on-surface">74</span> aturan terdaftar')
        ->assertDontSee('Promotion tidak dapat dijalankan sampai seluruh kelas sumber memiliki aturan aktif.');
});

it('an unconfigured class bumps the missing count and shows the warning banner', function () {
    $roster = createPromotionRuleRoster();
    createPromotionRosterRules($roster);

    SchoolClass::create(['name' => '5F', 'level' => 5]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->assertSee('5F')
        ->assertSee('Belum Dikonfigurasi')
        ->assertSee('Promotion tidak dapat dijalankan sampai seluruh kelas sumber memiliki aturan aktif.');
});

it('blocks a new class across the full roster', function () {
    $roster = createPromotionRuleRoster();
    createPromotionRosterRules($roster);

    $newClass = SchoolClass::create(['name' => '5F', 'level' => 5]);

    expect(ClassPromotionMapping::effectiveDecisionFor($newClass)['action'])->toBe('blocked');
});

it('resolves the full roster to 55 promotions and 19 graduations with nothing blocked', function () {
    $roster = createPromotionRuleRoster();
    createPromotionRosterRules($roster);

    $decisions = $roster->map(fn (SchoolClass $class) => ClassPromotionMapping::effectiveDecisionFor($class)['action']);

    expect($decisions->filter(fn (string $action) => $action === 'promote')->count())->toBe(55)
        ->and($decisions->filter(fn (string $action) => $action === 'graduate')->count())->toBe(19)
        ->and($decisions->filter(fn (string $action) => $action === 'blocked')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| No Generated-Default Machinery
|--------------------------------------------------------------------------
*/

it('the component exposes only DB-backed actions (no generate/reset/resync machinery)', function () {
    $forbiddenMethods = [
        'generateRules',
        'regenerateRules',
        'resetRules',
        'resetToDefaults',
        'resyncRules',
        'resyncDefaultRules',
        'syncWithDefaults',
        'driftCheck',
        'checkDrift',
    ];

    expect(array_intersect(get_class_methods(ClassPromotionRuleManagement::class), $forbiddenMethods))->toBe([]);
});

it('the page never offers buttons for generated defaults or resync', function () {
    SchoolClass::create(['name' => '5A', 'level' => 5]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->assertDontSee('Generate Otomatis')
        ->assertDontSee('Reset Default')
        ->assertDontSee('Sinkronisasi')
        ->assertDontSee('Sinkron Dengan Default')
        ->assertDontSee('Aturan Tersimpan Sebagai Default');
});
