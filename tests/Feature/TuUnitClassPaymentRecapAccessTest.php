<?php

use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function tuClassRecapYear(string $year = '2026/2027', bool $active = true): AcademicYear
{
    $startYear = (int) substr($year, 0, 4);

    return AcademicYear::query()->updateOrCreate(['year' => $year], [
        'is_active' => $active,
        'start_date' => $startYear.'-07-01',
        'end_date' => ($startYear + 1).'-06-30',
    ]);
}

function tuClassRecapClass(string $name, int $level): SchoolClass
{
    return SchoolClass::query()->create(['name' => $name, 'level' => $level]);
}

function tuClassRecapStudent(AcademicYear $academicYear, SchoolClass $schoolClass, string $name): Student
{
    $student = Student::factory()->create([
        'nama_lengkap' => $name,
        'class_id' => $schoolClass->id,
    ]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return $student;
}

function tuClassRecapUser(?SchoolLevel $schoolLevel): User
{
    return User::factory()->create([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => $schoolLevel,
    ]);
}

/** @return list<string> */
function tuClassRecapWorkbookValues(string $path): array
{
    $reader = new Reader;
    $values = [];

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $values[] = $sheet->getName();

            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->toArray() as $value) {
                    if (is_scalar($value)) {
                        $values[] = (string) $value;
                    }
                }
            }
        }
    } finally {
        $reader->close();
    }

    return $values;
}

it('allows TU Unit to open only Rekap Per Kelas with a locked unit context', function () {
    $academicYear = tuClassRecapYear();
    $smpClass = tuClassRecapClass('VII TU SMP', 7);
    $sdClass = tuClassRecapClass('VI Rahasia SD', 6);
    tuClassRecapStudent($academicYear, $smpClass, 'Siswa SMP Terlihat');
    tuClassRecapStudent($academicYear, $sdClass, 'Siswa SD Rahasia');
    $tuUser = tuClassRecapUser(SchoolLevel::SMP);

    $this->actingAs($tuUser)
        ->get(route('laporan.index'))
        ->assertOk()
        ->assertSee('Laporan')
        ->assertSee('Rekap Per Kelas')
        ->assertDontSee('Laporan Harian')
        ->assertDontSee('Laporan Bulanan')
        ->assertDontSee('Rekap Bank')
        ->assertDontSeeHtml('Target & Tunggakan');

    Livewire::actingAs($tuUser)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'daily',
            'classRecapSchoolLevel' => SchoolLevel::SD->value,
            'classRecapSchoolClassId' => (string) $sdClass->id,
        ])
        ->assertSet('activeTab', 'class')
        ->assertSet('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->assertSet('classRecapSchoolClassId', '')
        ->assertSee('VII TU SMP')
        ->assertDontSee('VI Rahasia SD')
        ->assertDontSee('Siswa SD Rahasia')
        ->assertSee('disabled', false)
        ->call('setActiveTab', 'bank')
        ->assertSet('activeTab', 'class')
        ->set('classRecapSchoolLevel', SchoolLevel::SD->value)
        ->assertSet('classRecapSchoolLevel', SchoolLevel::SMP->value)
        ->set('classRecapSchoolClassId', (string) $sdClass->id)
        ->assertSet('classRecapSchoolClassId', '')
        ->set('classRecapSchoolClassId', (string) $smpClass->id)
        ->assertSee('SISWA SMP TERLIHAT')
        ->assertDontSee('SISWA SD RAHASIA');
});

it('keeps academic year selection available to TU Unit', function () {
    $firstYear = tuClassRecapYear('2026/2027', true);
    $secondYear = tuClassRecapYear('2027/2028', false);
    $schoolClass = tuClassRecapClass('VII Tahun TU', 7);
    tuClassRecapStudent($firstYear, $schoolClass, 'Siswa Tahun Pertama');
    tuClassRecapStudent($secondYear, $schoolClass, 'Siswa Tahun Kedua');
    $tuUser = tuClassRecapUser(SchoolLevel::SMP);

    Livewire::actingAs($tuUser)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'class',
            'classRecapSchoolClassId' => (string) $schoolClass->id,
        ])
        ->assertSee('SISWA TAHUN PERTAMA')
        ->assertDontSee('SISWA TAHUN KEDUA')
        ->set('classRecapAcademicYearId', (string) $secondYear->id)
        ->assertSee('SISWA TAHUN KEDUA')
        ->assertDontSee('SISWA TAHUN PERTAMA');
});

it('scopes TU Unit class and unit workbooks and rejects cross-unit parameters', function () {
    $academicYear = tuClassRecapYear();
    $smpClass = tuClassRecapClass('VII Export TU', 7);
    $sdClass = tuClassRecapClass('VI Export Rahasia', 6);
    tuClassRecapStudent($academicYear, $smpClass, 'Siswa Export SMP');
    tuClassRecapStudent($academicYear, $sdClass, 'Siswa Export SD Rahasia');
    $tuUser = tuClassRecapUser(SchoolLevel::SMP);

    $unitResponse = $this->actingAs($tuUser)
        ->get(route('laporan.kelas.unit.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
        ]))
        ->assertOk();
    $unitPath = $unitResponse->baseResponse->getFile()->getPathname();

    try {
        $unitValues = tuClassRecapWorkbookValues($unitPath);
    } finally {
        if (is_file($unitPath)) {
            unlink($unitPath);
        }
    }

    expect($unitValues)->toContain('VII Export TU', 'SISWA EXPORT SMP')
        ->not->toContain('VI Export Rahasia', 'SISWA EXPORT SD RAHASIA');

    $classResponse = $this->actingAs($tuUser)
        ->get(route('laporan.kelas.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
            'class_id' => $smpClass->id,
        ]))
        ->assertOk();
    $classPath = $classResponse->baseResponse->getFile()->getPathname();

    try {
        $classValues = tuClassRecapWorkbookValues($classPath);
    } finally {
        if (is_file($classPath)) {
            unlink($classPath);
        }
    }

    expect($classValues)->toContain('SISWA EXPORT SMP')
        ->not->toContain('SISWA EXPORT SD RAHASIA');

    $this->actingAs($tuUser)
        ->get(route('laporan.kelas.unit.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SD->value,
        ]))
        ->assertForbidden();

    $this->actingAs($tuUser)
        ->get(route('laporan.kelas.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SD->value,
            'class_id' => $sdClass->id,
        ]))
        ->assertForbidden();

    $this->actingAs($tuUser)
        ->get(route('laporan.kelas.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
            'class_id' => $sdClass->id,
        ]))
        ->assertSessionHasErrors('class_id');
});

it('denies invalid TU Unit and guests while preserving administrator access', function () {
    $academicYear = tuClassRecapYear();
    $schoolClass = tuClassRecapClass('VII Otorisasi', 7);
    tuClassRecapStudent($academicYear, $schoolClass, 'Siswa Otorisasi');

    $this->get(route('laporan.index'))->assertRedirect(route('login'));

    $invalidTuUser = tuClassRecapUser(null);

    $this->actingAs($invalidTuUser)
        ->get(route('laporan.index'))
        ->assertForbidden();

    $this->actingAs($invalidTuUser)
        ->get(route('laporan.kelas.unit.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
        ]))
        ->assertForbidden();

    foreach ([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN] as $role) {
        $administrator = User::factory()->create(['role' => $role]);

        $this->actingAs($administrator)
            ->get(route('laporan.index'))
            ->assertOk()
            ->assertSee('Laporan Harian')
            ->assertSee('Laporan Bulanan')
            ->assertSee('Rekap Bank')
            ->assertSeeHtml('Target & Tunggakan')
            ->assertSee('Rekap Per Kelas');
    }
});

it('keeps TU Unit report queries bounded as class size grows', function () {
    $academicYear = tuClassRecapYear();
    $tuUser = tuClassRecapUser(SchoolLevel::SMP);
    $selectCounts = [];

    foreach ([1, 10] as $studentCount) {
        $schoolClass = tuClassRecapClass('VII Query TU '.$studentCount, 7);

        foreach (range(1, $studentCount) as $index) {
            tuClassRecapStudent($academicYear, $schoolClass, 'Siswa Query TU '.$studentCount.'-'.$index);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($tuUser)
            ->test(SchoolDailyReport::class, [
                'activeTab' => 'class',
                'classRecapSchoolClassId' => (string) $schoolClass->id,
            ])
            ->assertOk();

        $selectCounts[] = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_starts_with(strtolower(ltrim($query)), 'select'))
            ->count();
        DB::disableQueryLog();
    }

    expect(array_unique($selectCounts))->toHaveCount(1);
});
