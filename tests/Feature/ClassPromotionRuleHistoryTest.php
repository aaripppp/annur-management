<?php

use App\Livewire\ClassPromotionRuleManagement;
use App\Models\ClassPromotionRule;
use App\Models\ClassPromotionRuleLog;
use App\Models\SchoolClass;
use App\Services\ClassPromotionRuleAuditService;
use App\Support\ClassPromotionMapping;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| History Modal
|--------------------------------------------------------------------------
*/

it('opens the riwayat modal and lists audit entries on request', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    $rule = createPromotionRule($source, 'promote', $ips);
    app(ClassPromotionRuleAuditService::class)->createdManual($rule);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->assertDontSee('Riwayat Perubahan Aturan')
        ->call('openHistory')
        ->assertSet('showHistoryModal', true)
        ->assertSee('Riwayat Perubahan Aturan')
        ->assertSee('Dibuat')
        ->assertSee($source->name)
        ->assertSee('Naik ke '.$ips->name)
        ->assertSee('Sistem');
});

/*
|--------------------------------------------------------------------------
| Delete Single History
|--------------------------------------------------------------------------
*/

it('deletes a single history row without touching the rule or creating new audit', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ipa = SchoolClass::create(['name' => '12A-IPA', 'level' => 12]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    $rule = createPromotionRule($source, 'promote', $ipa);
    app(ClassPromotionRuleAuditService::class)->createdManual($rule);
    app(ClassPromotionRuleAuditService::class)->editedManual($rule, [
        'action' => 'promote',
        'target_class_id' => $ipa->id,
        'is_active' => true,
    ]);

    $targetLog = ClassPromotionRuleLog::where('source_class_id', $source->id)->first();

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openHistory')
        ->call('confirmDeleteHistory', $targetLog->id)
        ->assertSet('showDeleteHistoryModal', true)
        ->assertSet('historyToDeleteId', $targetLog->id)
        ->call('deleteHistory')
        ->assertSet('showDeleteHistoryModal', false)
        ->assertSet('showHistoryModal', true);

    expect(ClassPromotionRuleLog::find($targetLog->id))->toBeNull()
        ->and(ClassPromotionRuleLog::where('source_class_id', $source->id)->count())->toBe(1)
        ->and(ClassPromotionRuleLog::count())->toBe(1)
        ->and(ClassPromotionRule::find($rule->id))->not->toBeNull();

    $decision = ClassPromotionMapping::effectiveDecisionFor($source);

    expect($decision['action'])->toBe('promote')
        ->and($decision['target']?->id)->toBe($ipa->id);
});

it('deleting the last history row refreshes to the empty state', function () {
    $source = SchoolClass::create(['name' => '7A', 'level' => 7]);
    $target = SchoolClass::create(['name' => '8A', 'level' => 8]);

    $rule = createPromotionRule($source, 'promote', $target);
    app(ClassPromotionRuleAuditService::class)->createdManual($rule);

    $log = ClassPromotionRuleLog::first();

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openHistory')
        ->call('confirmDeleteHistory', $log->id)
        ->call('deleteHistory')
        ->assertSet('showHistoryModal', true)
        ->assertSee('Belum ada riwayat perubahan.');

    expect(ClassPromotionRuleLog::count())->toBe(0)
        ->and(ClassPromotionRule::find($rule->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Delete All History
|--------------------------------------------------------------------------
*/

it('deletes all history but keeps all promotion rules intact', function () {
    $source = SchoolClass::create(['name' => '11A', 'level' => 11]);
    $ips = SchoolClass::create(['name' => '12A-IPS', 'level' => 12]);

    $rule = createPromotionRule($source, 'promote', $ips);
    app(ClassPromotionRuleAuditService::class)->createdManual($rule);
    app(ClassPromotionRuleAuditService::class)->editedManual($rule, [
        'action' => 'promote',
        'target_class_id' => $ips->id,
        'is_active' => true,
    ]);

    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openHistory')
        ->call('openDeleteAllHistory')
        ->assertSet('showDeleteAllHistoryModal', true)
        ->call('deleteAllHistory')
        ->assertSet('showDeleteAllHistoryModal', false)
        ->assertSet('showHistoryModal', true)
        ->assertSee('Belum ada riwayat perubahan.');

    expect(ClassPromotionRuleLog::count())->toBe(0)
        ->and(ClassPromotionRule::count())->toBe(1)
        ->and(ClassPromotionRule::find($rule->id)->is_active)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Empty History State
|--------------------------------------------------------------------------
*/

it('shows the empty state inside the riwayat modal when no history exists', function () {
    Livewire::test(ClassPromotionRuleManagement::class)
        ->call('openHistory')
        ->assertSet('showHistoryModal', true)
        ->assertSee('Belum ada riwayat perubahan.');
});
