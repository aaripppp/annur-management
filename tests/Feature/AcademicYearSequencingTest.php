<?php

use App\Livewire\AcademicYearManagement;
use App\Models\AcademicYear;
use Livewire\Livewire;

function seqYear(string $year, string $startDate, string $endDate, bool $active): AcademicYear
{
    $model = AcademicYear::query()->updateOrCreate(
        ['year' => $year],
        ['start_date' => $startDate, 'end_date' => $endDate]
    );
    $model->update(['is_active' => $active]);

    return $model->fresh();
}

it('returns null for next when no active academic year exists', function () {
    AcademicYear::query()->delete();

    expect(AcademicYear::next())->toBeNull();
});

it('returns null for next when only the active year exists', function () {
    seqYear('2026/2027', '2026-07-01', '2027-06-30', true);

    expect(AcademicYear::next())->toBeNull();
});

it('resolves the first chronological year after the active year as next', function () {
    $y2026 = seqYear('2026/2027', '2026-07-01', '2027-06-30', true);
    $y2028 = seqYear('2028/2029', '2028-07-01', '2029-06-30', false);
    $y2027 = seqYear('2027/2028', '2027-07-01', '2028-06-30', false);

    expect(AcademicYear::next()->id)->toBe($y2027->id);
});

it('never selects a past inactive year as next', function () {
    seqYear('2025/2026', '2025-07-01', '2026-06-30', false);
    $y2026 = seqYear('2026/2027', '2026-07-01', '2027-06-30', true);
    $y2027 = seqYear('2027/2028', '2027-07-01', '2028-06-30', false);

    expect(AcademicYear::next()->id)->toBe($y2027->id);
});

it('orders next candidates by start_date, not by label or record id', function () {
    $active = seqYear('2026/2027', '2026-05-01', '2027-06-30', true);

    // Label dan id lebih besar, tapi start_date lebih awal dari si kandidat lain.
    $y2028 = seqYear('2028/2029', '2027-08-01', '2029-06-30', false);
    // Label lebih kecil, tapi start_date lebih akhir.
    $y2027 = seqYear('2027/2028', '2027-09-01', '2028-06-30', false);

    expect(AcademicYear::next()->id)->toBe($y2028->id);
});

it('resolves next relative to an explicitly provided active year', function () {
    $y2026 = seqYear('2026/2027', '2026-07-01', '2027-06-30', true);
    $y2027 = seqYear('2027/2028', '2027-07-01', '2028-06-30', false);
    seqYear('2028/2029', '2028-07-01', '2029-06-30', false);

    expect(AcademicYear::next($y2026)->id)->toBe($y2027->id);
});

it('academic year management page shows the chronological next year', function () {
    seqYear('2026/2027', '2026-07-01', '2027-06-30', true);
    seqYear('2028/2029', '2028-07-01', '2029-06-30', false);
    $y2027 = seqYear('2027/2028', '2027-07-01', '2028-06-30', false);

    Livewire::test(AcademicYearManagement::class)
        ->assertSet('newYearId', $y2027->id)
        ->assertSet('newYearLabel', '2027/2028')
        ->assertSee('Tahun Ajaran Berikutnya')
        ->assertSee('2027/2028');
});

it('lists academic years ordered by start date ascending', function () {
    seqYear('2028/2029', '2028-07-01', '2029-06-30', false);
    seqYear('2026/2027', '2026-07-01', '2027-06-30', true);
    seqYear('2027/2028', '2027-07-01', '2028-06-30', false);

    $html = Livewire::test(AcademicYearManagement::class)->html();
    $table = substr($html, strpos($html, 'Daftar Tahun Ajaran'));

    expect(strpos($table, '2026/2027'))->toBeLessThan(strpos($table, '2027/2028'))
        ->and(strpos($table, '2027/2028'))->toBeLessThan(strpos($table, '2028/2029'));
});

it('promotes students into the canonical next year, not the farthest future year', function () {
    $y2026 = seqYear('2026/2027', '2026-07-01', '2027-06-30', true);
    $y2028 = seqYear('2028/2029', '2028-07-01', '2029-06-30', false);
    $y2027 = seqYear('2027/2028', '2027-07-01', '2028-06-30', false);

    $component = Livewire::test(AcademicYearManagement::class);

    expect($component->get('newYearId'))->toBe($y2027->id);

    $component
        ->call('openPreview')
        ->call('openConfirm')
        ->call('executePromotion');

    expect(AcademicYear::find($y2027->id)->is_active)->toBeTrue()
        ->and(AcademicYear::find($y2026->id)->is_active)->toBeFalse()
        ->and(AcademicYear::find($y2028->id)->is_active)->toBeFalse()
        ->and(AcademicYear::find($y2027->id)->promotion_processed_at)->not->toBeNull();

    expect($component->get('activeYearId'))->toBe($y2027->id)
        ->and($component->get('newYearId'))->toBe($y2028->id)
        ->and(AcademicYear::next()->id)->toBe($y2028->id);
});

it('shows a friendly empty state and prevents promotion when no future year exists', function () {
    seqYear('2025/2026', '2025-07-01', '2026-06-30', false);
    $y2026 = seqYear('2026/2027', '2026-07-01', '2027-06-30', true);

    expect(AcademicYear::next())->toBeNull();

    $component = Livewire::test(AcademicYearManagement::class);

    $component
        ->assertSet('newYearId', null)
        ->assertSet('newYearLabel', '')
        ->assertSee('Belum ada tahun ajaran berikutnya')
        ->assertDontSee('Proses Kenaikan Kelas');

    $component->call('openPreview');

    expect($component->get('isPreviewOpen'))->toBeFalse();

    $component->call('executePromotion');

    expect(AcademicYear::active()->id)->toBe($y2026->id);
});
