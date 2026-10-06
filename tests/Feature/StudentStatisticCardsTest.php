<?php

use App\Enums\SchoolLevel;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Services\StudentStatisticService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $this->futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );
    $this->laterYear = AcademicYear::firstOrCreate(
        ['year' => '2028/2029'],
        ['is_active' => false, 'start_date' => '2028-07-01', 'end_date' => '2029-06-30']
    );
});

/** @return array{0: Student, 1: SchoolClass} */
function statEnrolledStudent(AcademicYear $year, int $level = 7, string $status = 'active'): array
{
    $class = SchoolClass::factory()->create(['level' => $level]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => $status,
    ]);

    return [$student, $class];
}

/**
 * @return array{total: int, active: int, prospective: int, graduated: int}
 */
function statCounts(AcademicYear $year, ?SchoolLevel $level = null, ?int $classId = null): array
{
    return app(StudentStatisticService::class)->counts($year, $level, $classId);
}

/*
|--------------------------------------------------------------------------
| KARTU STATISTIK DATA SISWA
|--------------------------------------------------------------------------
*/

it('defaults the statistic filter to the active academic year and renders empty cards', function () {
    $component = Livewire::test(StudentManagement::class);

    $component
        ->assertSet('statYearId', (string) $this->activeYear->id)
        ->assertSet('statLevel', '')
        ->assertSet('statClassId', '')
        ->assertSee('Statistik Siswa')
        ->assertSee('Total Siswa')
        ->assertSee('Siswa Aktif')
        ->assertSee('Calon Siswa')
        ->assertSee('Lulus')
        ->assertSeeHtml('tracking-wider">0 Siswa</p>');
});

it('counts a student with an active enrollment in the selected year as Siswa Aktif', function () {
    statEnrolledStudent($this->activeYear);

    $counts = statCounts($this->activeYear);

    expect($counts['active'])->toBe(1)
        ->and($counts['total'])->toBe(1)
        ->and($counts['prospective'])->toBe(0)
        ->and($counts['graduated'])->toBe(0);

    Livewire::test(StudentManagement::class)
        ->assertSeeHtml('tracking-wider">1 Siswa</p>');
});

it('counts a converted student with only a future enrollment as Calon Siswa for the active year', function () {
    statEnrolledStudent($this->futureYear);

    $counts = statCounts($this->activeYear);

    expect($counts['prospective'])->toBe(1)
        ->and($counts['active'])->toBe(0)
        ->and($counts['graduated'])->toBe(0);
});

it('counts the same converted student as Siswa Aktif when the future year is selected', function () {
    statEnrolledStudent($this->futureYear);

    $counts = statCounts($this->futureYear);

    expect($counts['active'])->toBe(1)
        ->and($counts['prospective'])->toBe(0)
        ->and($counts['graduated'])->toBe(0);
});

it('counts a student with a lulus enrollment in the selected year as Lulus', function () {
    statEnrolledStudent($this->activeYear, 9, 'lulus');

    $counts = statCounts($this->activeYear);

    expect($counts['graduated'])->toBe(1)
        ->and($counts['active'])->toBe(0)
        ->and($counts['prospective'])->toBe(0);
});

it('excludes terminal students even when they hold an active-style enrollment', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = Student::factory()->create(['class_id' => $class->id, 'status' => 'pindah']);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->activeYear->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    $counts = statCounts($this->activeYear);

    expect($counts['active'])->toBe(0)
        ->and($counts['prospective'])->toBe(0)
        ->and($counts['graduated'])->toBe(0);
});

it('filters by jenjang using the enrollment school class', function () {
    statEnrolledStudent($this->activeYear, 7);
    statEnrolledStudent($this->activeYear, 8);
    statEnrolledStudent($this->activeYear, 10);

    $counts = statCounts($this->activeYear, SchoolLevel::SMP);

    expect($counts['active'])->toBe(2)
        ->and($counts['total'])->toBe(2);
});

it('filters by a single school class', function () {
    [$student, $classA] = statEnrolledStudent($this->activeYear, 7);
    statEnrolledStudent($this->activeYear, 8);

    $counts = statCounts($this->activeYear, null, $classA->id);

    expect($counts['active'])->toBe(1);
});

it('treats students enrolled only in a far-future year as Calon Siswa, never Aktif or Lulus', function () {
    statEnrolledStudent($this->laterYear, 7);

    $counts = statCounts($this->activeYear);

    expect($counts['prospective'])->toBe(1)
        ->and($counts['active'])->toBe(0)
        ->and($counts['graduated'])->toBe(0);
});

it('counts legacy students without enrollments as Siswa Aktif for the active year', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    Student::factory()->create(['class_id' => $class->id]);

    $counts = statCounts($this->activeYear);

    expect($counts['active'])->toBe(1)
        ->and($counts['total'])->toBe(1);
});

it('does not count legacy fallback students for non-active years', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    Student::factory()->create(['class_id' => $class->id]);

    $counts = statCounts($this->futureYear);

    expect($counts['active'])->toBe(0)
        ->and($counts['prospective'])->toBe(0)
        ->and($counts['graduated'])->toBe(0);
});

it('counts students with stored lulus status and no lulus enrollment as Lulus for the active year', function () {
    $class = SchoolClass::factory()->create(['level' => 12]);
    Student::factory()->create(['class_id' => $class->id, 'status' => 'lulus']);

    $counts = statCounts($this->activeYear);

    expect($counts['graduated'])->toBe(1)
        ->and($counts['active'])->toBe(0)
        ->and($counts['prospective'])->toBe(0);
});

it('Total equals the exact sum of the three status cards without double counting', function () {
    statEnrolledStudent($this->activeYear);

    statEnrolledStudent($this->futureYear);

    $lulusClass = SchoolClass::factory()->create(['level' => 12]);
    Student::factory()->create(['class_id' => $lulusClass->id, 'status' => 'lulus']);

    $legacyClass = SchoolClass::factory()->create(['level' => 7]);
    Student::factory()->create(['class_id' => $legacyClass->id]);

    $counts = statCounts($this->activeYear);

    expect($counts['active'])->toBe(2)
        ->and($counts['prospective'])->toBe(1)
        ->and($counts['graduated'])->toBe(1)
        ->and($counts['total'])->toBe(4)
        ->and($counts['total'])->toBe($counts['active'] + $counts['prospective'] + $counts['graduated']);
});

it('clears the selected statistic class when jenjang no longer contains it', function () {
    $smp = SchoolClass::factory()->create(['level' => 7]);
    $sma = SchoolClass::factory()->create(['level' => 10]);

    Livewire::test(StudentManagement::class)
        ->set('statLevel', SchoolLevel::SMP->value)
        ->set('statClassId', (string) $smp->id)
        ->assertSet('statClassId', (string) $smp->id)
        ->set('statLevel', SchoolLevel::SMA->value)
        ->assertSet('statClassId', '');
});

it('preserves the selected statistic class when jenjang is cleared', function () {
    $smp = SchoolClass::factory()->create(['level' => 7]);

    Livewire::test(StudentManagement::class)
        ->set('statLevel', SchoolLevel::SMP->value)
        ->set('statClassId', (string) $smp->id)
        ->assertSet('statClassId', (string) $smp->id)
        ->set('statLevel', '')
        ->assertSet('statClassId', (string) $smp->id);
});

it('scopes the statistic class options to the selected jenjang', function () {
    $smp = SchoolClass::factory()->create(['level' => 7]);
    $sma = SchoolClass::factory()->create(['level' => 10]);

    $component = Livewire::test(StudentManagement::class);

    expect(collect($component->viewData('statClasses'))->pluck('id')->all())
        ->toContain($smp->id)
        ->toContain($sma->id);

    $component->set('statLevel', SchoolLevel::SMP->value);

    expect(collect($component->viewData('statClasses'))->pluck('id')->all())
        ->toContain($smp->id)
        ->not->toContain($sma->id);
});

it('requires a future enrollment with active status to qualify as Calon Siswa', function () {
    $class = SchoolClass::factory()->create(['level' => 7]);
    $student = Student::factory()->create(['class_id' => $class->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $this->futureYear->id,
        'school_class_id' => $class->id,
        'status' => 'planned',
    ]);

    $counts = statCounts($this->activeYear);

    expect($counts['prospective'])->toBe(0)
        ->and($counts['graduated'])->toBe(0);

    // Tanpa enrollment aktif di masa depan, siswa tetap menjadi legacy aktif.
    expect($counts['active'])->toBe(1);
});

it('computes all status cards with a constant number of queries', function () {
    for ($i = 0; $i < 5; $i++) {
        statEnrolledStudent($this->activeYear);
    }

    for ($i = 0; $i < 5; $i++) {
        statEnrolledStudent($this->futureYear);
    }

    for ($i = 0; $i < 3; $i++) {
        statEnrolledStudent($this->activeYear, 12, 'lulus');
    }

    $legacyClass = SchoolClass::factory()->create(['level' => 7]);

    for ($i = 0; $i < 4; $i++) {
        Student::factory()->create(['class_id' => $legacyClass->id]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    statCounts($this->activeYear);

    $queries = count(DB::getQueryLog());
    expect($queries)->toBe(3);
});
