<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\AcademicYearManagement;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Livewire\Livewire;

function uiMonthlyTypes(int $level): void
{
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, $level, 980000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $ekskul = makeBillType('Ekskul', auto: true, required: true);
    makeBillRate($ekskul, $level, 52000);
    makeLevelDefault($ekskul, SchoolLevel::SMP);

    $osis = makeBillType('OSIS', auto: true, required: true);
    makeBillRate($osis, $level, 5000);
    makeLevelDefault($osis, SchoolLevel::SMP);
}

function uiEnrolledStudent(AcademicYear $year, int $level): Student
{
    $class = SchoolClass::factory()->create(['level' => $level]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

/**
 * Mirror of production state on 2026-08-21: promotion was already processed,
 * so 2027/2028 carries the is_active flag and 2026/2027 is inactive.
 */
function uiPostPromotionYears(): array
{
    $y2026 = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => false, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $y2026->update(['is_active' => false]);

    $y2027 = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => true, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );
    $y2027->update(['is_active' => true, 'promotion_processed_at' => '2026-08-21 13:21:52']);

    return [$y2026->fresh(), $y2027->fresh()];
}

it('resolves next year to null when no future year exists', function () {
    $this->travelTo('2026-08-21');

    [$y2026, $y2027] = uiPostPromotionYears();
    uiMonthlyTypes(7);
    $student = uiEnrolledStudent($y2027, 7);

    $component = Livewire::test(AcademicYearManagement::class);

    expect($component->get('newYearId'))->toBeNull()
        ->and($component->get('monthlyPreviewAcademicYear'))->toBe('');

    $component->call('openMonthlyPreview', $y2027->id);

    expect($component->get('monthlyPreviewAcademicYear'))->toBe('2027/2028')
        ->and($component->get('monthlyTargetYearId'))->toBe($y2027->id);
});

/*
|--------------------------------------------------------------------------
| REGRESSION 1: Preview shows explicitly selected year (2027/2028), not
| the previous active year, even after promotion flipped is_active.
|--------------------------------------------------------------------------
*/
it('monthly generation preview shows explicitly selected academic year', function () {
    $this->travelTo('2026-08-21');

    [$y2026, $y2027] = uiPostPromotionYears();
    uiMonthlyTypes(7);
    uiEnrolledStudent($y2027, 7);

    Livewire::test(AcademicYearManagement::class)
        ->call('openMonthlyPreview', $y2027->id)
        ->assertSet('monthlyTargetYearId', $y2027->id)
        ->assertSet('monthlyPreviewAcademicYear', '2027/2028')
        ->assertSet('monthlyPreviewEligibleStudents', 1)
        ->assertSee('Tahun Ajaran: <strong class="text-on-surface">2027/2028</strong>', false)
        ->assertDontSee('Tahun Ajaran: <strong class="text-on-surface">2026/2027</strong>', false);
});

/*
|--------------------------------------------------------------------------
| REGRESSION 2: Confirm generates for the selected year only. Future
| Grade 7 student receives SPP = 980000 for 2027/2028 and zero
| monthly bills for 2026/2027.
|--------------------------------------------------------------------------
*/
it('confirming generation bills future grade 7 student for selected year at current tariff', function () {
    $this->travelTo('2026-08-21');

    [$y2026, $y2027] = uiPostPromotionYears();
    uiMonthlyTypes(7);
    $student = uiEnrolledStudent($y2027, 7);

    Livewire::test(AcademicYearManagement::class)
        ->call('openMonthlyPreview', $y2027->id)
        ->assertSet('monthlyPreviewAcademicYear', '2027/2028')
        ->call('openMonthlyConfirm')
        ->assertSee('2027/2028')
        ->call('executeMonthlyGeneration');

    $monthlyBills = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->get();

    expect($monthlyBills->count())->toBe(36);

    $sppBills = $monthlyBills->filter(fn ($bill) => (float) $bill->amount === 980000.0);

    expect($sppBills->count())->toBe(12);

    foreach ($monthlyBills as $bill) {
        expect($bill->period_year)->toBeGreaterThanOrEqual(2027)
            ->and($bill->period_year)->toBeLessThanOrEqual(2028);
    }

    $billsForActiveYear = $student->bills()
        ->where('billing_frequency', BillFrequency::Monthly->value)
        ->where('period_year', '<=', 2026)
        ->count();

    expect($billsForActiveYear)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| REGRESSION 3: Each table row carries its own generate button with an
| explicit academic year id, so selection cannot fall back silently.
|--------------------------------------------------------------------------
*/
it('renders per row generate buttons carrying explicit academic year ids', function () {
    $this->travelTo('2026-08-21');

    [$y2026, $y2027] = uiPostPromotionYears();

    Livewire::withQueryParams([])
        ->test(AcademicYearManagement::class)
        ->assertSee('wire:key="academic-year-'.$y2026->id.'"', false)
        ->assertSee('wire:key="academic-year-'.$y2027->id.'"', false)
        ->assertSee('openMonthlyPreview('.$y2027->id.')', false)
        ->assertSee('openMonthlyPreview('.$y2026->id.')', false);
});

/*
|--------------------------------------------------------------------------
| REGRESSION 4: No-arg call keeps legacy fallback behaviour instead of
| failing, but never targets a year other than next/active.
|--------------------------------------------------------------------------
*/
it('no arg call falls back to derived target year without error', function () {
    $this->travelTo('2026-08-21');

    [$y2026, $y2027] = uiPostPromotionYears();
    uiMonthlyTypes(7);

    Livewire::test(AcademicYearManagement::class)
        ->call('openMonthlyPreview')
        ->assertSet('monthlyTargetYearId', $y2027->id)
        ->assertSet('monthlyPreviewAcademicYear', '2027/2028');
});

/*
|--------------------------------------------------------------------------
| UI: status badge Aktif/Tidak Aktif compact pill satu baris.
|--------------------------------------------------------------------------
*/
it('renders compact nowrap status badges', function () {
    $this->travelTo('2026-08-21');

    uiPostPromotionYears();

    Livewire::test(AcademicYearManagement::class)
        ->assertSeeHtml('<span class="inline-flex items-center justify-center py-0.5 px-2.5 rounded-full text-label-sm font-label-sm leading-5 whitespace-nowrap bg-secondary-container text-on-secondary-container">Aktif</span>')
        ->assertSeeHtml('<span class="inline-flex items-center justify-center py-0.5 px-2.5 rounded-full text-label-sm font-label-sm leading-5 whitespace-nowrap bg-surface-container-high text-on-surface-variant">Tidak Aktif</span>');
});

/*
|--------------------------------------------------------------------------
| UI: preview tarif dikelompokkan per level (hanya level eligible) dengan
| label Indonesia dari SchoolClass::levelLabels().
|--------------------------------------------------------------------------
*/
it('monthly preview groups tariffs per enrolled level with indonesian labels', function () {
    $this->travelTo('2026-08-21');

    [$y2026, $y2027] = uiPostPromotionYears();
    uiMonthlyTypes(7);
    uiEnrolledStudent($y2027, 7);

    Livewire::test(AcademicYearManagement::class)
        ->call('openMonthlyPreview', $y2027->id)
        ->assertSee('Tarif yang Akan Digunakan')
        ->assertSee('Kelas 7')
        ->assertSee('SPP')
        ->assertSee('Rp 980.000')
        ->assertDontSee('Kelas 1')
        ->assertDontSee('TKA')
        ->assertDontSee('TKB');
});

it('monthly preview uses tk labels from level labels source', function () {
    $this->travelTo('2026-08-21');

    [$y2026, $y2027] = uiPostPromotionYears();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, -1, 450000);
    makeLevelDefault($spp, SchoolLevel::TK);

    $class = SchoolClass::factory()->create(['level' => -1]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $y2027->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    Livewire::test(AcademicYearManagement::class)
        ->call('openMonthlyPreview', $y2027->id)
        ->assertSee('Tarif yang Akan Digunakan')
        ->assertSee('TKB')
        ->assertSee('Rp 450.000')
        ->assertDontSee('Kelas 7');
});
