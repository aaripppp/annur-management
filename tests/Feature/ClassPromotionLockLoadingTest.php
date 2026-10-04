<?php

use App\Exceptions\BlockedPromotionException;
use App\Livewire\AcademicYearManagement;
use App\Models\AcademicYear;
use App\Models\ClassPromotionRule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Services\ClassPromotionService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/**
 * @return array{source: AcademicYear, target: AcademicYear, student: Student, sourceClass: SchoolClass, targetClass: SchoolClass}
 */
function classPromotionLockFixture(): array
{
    AcademicYear::query()->update(['is_active' => false]);

    $source = AcademicYear::create([
        'year' => '2030/2031',
        'is_active' => true,
        'start_date' => '2030-07-01',
        'end_date' => '2031-06-30',
    ]);
    $target = AcademicYear::create([
        'year' => '2031/2032',
        'is_active' => false,
        'start_date' => '2031-07-01',
        'end_date' => '2032-06-30',
    ]);
    $sourceClass = SchoolClass::create(['name' => 'VII Lock', 'level' => 7]);
    $targetClass = SchoolClass::create(['name' => 'VIII Lock', 'level' => 8]);
    createPromotionRule($sourceClass, 'promote', $targetClass);
    $student = Student::factory()->create(['class_id' => $sourceClass->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $source->id,
        'school_class_id' => $sourceClass->id,
        'status' => 'active',
    ]);

    return compact('source', 'target', 'student', 'sourceClass', 'targetClass');
}

it('targets only final promotion execution with the established loading controls', function () {
    $fixture = classPromotionLockFixture();

    $html = Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->call('openConfirm')
        ->html();

    expect($html)
        ->toContain('wire:click="closeConfirm" wire:loading.attr="disabled" wire:target="executePromotion"')
        ->toContain('wire:click="executePromotion" wire:loading.attr="disabled" wire:target="executePromotion"')
        ->toContain('<span wire:loading.remove wire:target="executePromotion">Proses Kenaikan Kelas</span>')
        ->toContain('<span wire:loading.flex wire:target="executePromotion" class="items-center justify-center gap-2">')
        ->toContain('material-symbols-outlined animate-spin text-[18px]">progress_activity</span>')
        ->toContain('Sedang memproses...')
        ->and(substr_count($html, 'wire:target="executePromotion"'))->toBe(4)
        ->and($fixture['target']->promotion_processed_at)->toBeNull();
});

it('rejects lock contention before any promotion writes', function () {
    $fixture = classPromotionLockFixture();
    $lock = Cache::lock('class-promotion-processing', 300);

    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(ClassPromotionService::class)->processPromotion($fixture['source'], $fixture['target']))
            ->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }

    expect($fixture['student']->fresh()->class_id)->toBe($fixture['sourceClass']->id)
        ->and($fixture['student']->enrollments()->where('academic_year_id', $fixture['target']->id)->exists())->toBeFalse()
        ->and($fixture['student']->bills()->count())->toBe(0)
        ->and($fixture['source']->fresh()->is_active)->toBeTrue()
        ->and($fixture['target']->fresh()->is_active)->toBeFalse()
        ->and($fixture['target']->fresh()->promotion_processed_at)->toBeNull();
});

it('shows controlled feedback when the promotion lock is already held', function () {
    $fixture = classPromotionLockFixture();
    $lock = Cache::lock('class-promotion-processing', 300);

    expect($lock->get())->toBeTrue();

    try {
        Livewire::test(AcademicYearManagement::class)
            ->call('openPreview')
            ->call('openConfirm')
            ->call('executePromotion')
            ->assertSet('isConfirmOpen', false)
            ->assertSet('confirmedPromotionSourceYearId', null)
            ->assertSet('confirmedPromotionTargetYearId', null)
            ->assertSee('Proses kenaikan kelas sedang dijalankan. Silakan tunggu sampai proses sebelumnya selesai.');
    } finally {
        $lock->release();
    }

    expect($fixture['student']->fresh()->class_id)->toBe($fixture['sourceClass']->id)
        ->and($fixture['target']->fresh()->promotion_processed_at)->toBeNull();
});

it('authoritatively rejects a confirmed target processed after preview', function () {
    $fixture = classPromotionLockFixture();
    $component = Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->call('openConfirm');

    $fixture['target']->update(['promotion_processed_at' => now()]);

    $component
        ->call('executePromotion')
        ->assertSet('isConfirmOpen', false)
        ->assertSet('confirmedPromotionSourceYearId', null)
        ->assertSet('confirmedPromotionTargetYearId', null)
        ->assertSee('Data kenaikan kelas sudah berubah atau sudah diproses. Silakan buka preview kembali.');

    expect($fixture['student']->fresh()->class_id)->toBe($fixture['sourceClass']->id)
        ->and($fixture['student']->enrollments()->where('academic_year_id', $fixture['target']->id)->exists())->toBeFalse()
        ->and($fixture['source']->fresh()->is_active)->toBeTrue()
        ->and($fixture['target']->fresh()->is_active)->toBeFalse();
});

it('authoritatively rejects a confirmation when the active source changes', function () {
    $fixture = classPromotionLockFixture();
    $component = Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->call('openConfirm');
    $replacementSource = AcademicYear::create([
        'year' => '2029/2030',
        'is_active' => false,
        'start_date' => '2029-07-01',
        'end_date' => '2030-06-30',
    ]);

    AcademicYear::query()->update(['is_active' => false]);
    AcademicYear::query()->whereKey($replacementSource->id)->update(['is_active' => true]);

    $component
        ->call('executePromotion')
        ->assertSee('Data kenaikan kelas sudah berubah atau sudah diproses. Silakan buka preview kembali.');

    expect($fixture['student']->fresh()->class_id)->toBe($fixture['sourceClass']->id)
        ->and($fixture['student']->enrollments()->where('academic_year_id', $fixture['target']->id)->exists())->toBeFalse()
        ->and($fixture['target']->fresh()->promotion_processed_at)->toBeNull();
});

it('releases the promotion lock when execution throws', function () {
    $fixture = classPromotionLockFixture();
    ClassPromotionRule::query()->delete();

    expect(fn () => app(ClassPromotionService::class)->processPromotion($fixture['source'], $fixture['target']))
        ->toThrow(BlockedPromotionException::class);

    $lock = Cache::lock('class-promotion-processing', 300);

    expect($lock->get())->toBeTrue();
    $lock->release();
});

it('does not advance a queued second execution into another future academic year', function () {
    $fixture = classPromotionLockFixture();
    $later = AcademicYear::create([
        'year' => '2032/2033',
        'is_active' => false,
        'start_date' => '2032-07-01',
        'end_date' => '2033-06-30',
    ]);

    $component = Livewire::test(AcademicYearManagement::class)
        ->call('openPreview')
        ->call('openConfirm')
        ->call('executePromotion')
        ->assertSet('activeYearId', $fixture['target']->id)
        ->assertSet('newYearId', $later->id)
        ->assertSet('isConfirmOpen', false)
        ->assertSet('confirmedPromotionSourceYearId', null)
        ->assertSet('confirmedPromotionTargetYearId', null);

    $component->call('executePromotion');

    expect(AcademicYear::active()?->id)->toBe($fixture['target']->id)
        ->and($fixture['target']->fresh()->promotion_processed_at)->not->toBeNull()
        ->and($later->fresh()->is_active)->toBeFalse()
        ->and($later->fresh()->promotion_processed_at)->toBeNull()
        ->and($fixture['student']->fresh()->class_id)->toBe($fixture['targetClass']->id);
});
