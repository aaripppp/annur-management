<?php

use App\Enums\SchoolLevel;
use App\Livewire\StudentExamEligibility;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\StudentEligibilityConfig;
use App\Models\StudentExamRequirement;
use Livewire\Livewire;

it('restores valid saved display filters when not overridden by the URL', function () {
    [$student, $smpClass, $year] = makeEnrolledStudent(SchoolLevel::SMP);
    $otherYear = AcademicYear::create([
        'year' => '2025/2026',
        'is_active' => false,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);

    Livewire::test(StudentExamEligibility::class)
        ->call('restoreExamFilters', [
            'search' => 'zebra',
            'jenjang' => 'SMP',
            'kelas' => (string) $smpClass->id,
            'status' => 'eligible',
            'tahun' => (string) $otherYear->id,
        ])
        ->assertSet('search', 'zebra')
        ->assertSet('filterLevel', 'SMP')
        ->assertSet('filterClassId', (string) $smpClass->id)
        ->assertSet('filterStatus', 'eligible')
        ->assertSet('yearlyAcademicYearId', (string) $otherYear->id);
});

it('keeps a saved kelas compatible with the saved jenjang after restore', function () {
    [$student, $smpClass, $year] = makeEnrolledStudent(SchoolLevel::SMP);

    Livewire::test(StudentExamEligibility::class)
        ->call('restoreExamFilters', ['jenjang' => 'SMP', 'kelas' => (string) $smpClass->id])
        ->assertSet('filterLevel', 'SMP')
        ->assertSet('filterClassId', (string) $smpClass->id);
});

it('drops a saved kelas incompatible with the saved jenjang', function () {
    [$student, $smpClass, $year] = makeEnrolledStudent(SchoolLevel::SMP);
    $sdClass = SchoolClass::factory()->create(['level' => 1]);

    Livewire::test(StudentExamEligibility::class)
        ->call('restoreExamFilters', ['jenjang' => 'SMP', 'kelas' => (string) $sdClass->id])
        ->assertSet('filterLevel', 'SMP')
        ->assertSet('filterClassId', '');
});

it('drops invalid saved jenjang, kelas, status, and tahun values', function () {
    [$student, $smpClass, $year] = makeEnrolledStudent(SchoolLevel::SMP);

    Livewire::test(StudentExamEligibility::class)
        ->call('restoreExamFilters', [
            'search' => '   zebra   ',
            'jenjang' => 'XYZ',
            'kelas' => '424242',
            'status' => 'bogus',
            'tahun' => '999999',
        ])
        ->assertSet('search', 'zebra')
        ->assertSet('filterLevel', '')
        ->assertSet('filterClassId', '')
        ->assertSet('filterStatus', '')
        ->assertSet('yearlyAcademicYearId', (string) $year->id);
});

it('gives explicit URL query params precedence over saved filters', function () {
    [$student, $smpClass, $year] = makeEnrolledStudent(SchoolLevel::SMP);

    Livewire::withQueryParams(['jenjang' => 'SMP'])
        ->test(StudentExamEligibility::class)
        ->call('restoreExamFilters', [
            'search' => 'zebra',
            'jenjang' => 'SD',
            'kelas' => '',
        ])
        ->assertSet('filterLevel', 'SMP')
        ->assertSet('search', 'zebra');
});

it('restoring saved filters does not touch eligibility criteria', function () {
    [$student, $smpClass, $year] = makeEnrolledStudent(SchoolLevel::SMP);

    $requirementsBefore = StudentExamRequirement::query()->count();
    $configsBefore = StudentEligibilityConfig::query()->count();

    Livewire::test(StudentExamEligibility::class)
        ->call('restoreExamFilters', ['jenjang' => 'SMP', 'kelas' => (string) $smpClass->id, 'status' => 'eligible'])
        ->assertSet('filterLevel', 'SMP')
        ->assertSet('filterStatus', 'eligible');

    expect(StudentExamRequirement::query()->count())->toBe($requirementsBefore)
        ->and(StudentEligibilityConfig::query()->count())->toBe($configsBefore);
});

it('reset filters keeps the selected academic year filter', function () {
    [$student, $smpClass, $year] = makeEnrolledStudent(SchoolLevel::SMP);

    Livewire::test(StudentExamEligibility::class)
        ->set('yearlyAcademicYearId', (string) $year->id)
        ->set('search', 'zebra')
        ->set('filterLevel', 'SMP')
        ->set('filterStatus', 'eligible')
        ->call('resetFilters')
        ->assertSet('yearlyAcademicYearId', (string) $year->id)
        ->assertSet('search', '')
        ->assertSet('filterLevel', '')
        ->assertSet('filterClassId', '')
        ->assertSet('filterStatus', '');
});
