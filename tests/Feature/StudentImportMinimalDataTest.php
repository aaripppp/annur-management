<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\DaycareChild;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentEligibilityConfig;
use App\Models\StudentExamRequirement;
use App\Models\User;
use App\Services\StudentImportService;
use App\Services\StudentImportSpreadsheet;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function minimalImportWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-minimal-import-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('DATA SISWA');
    $writer->addRow(Row::fromValues(StudentImportSpreadsheet::MINIMAL_HEADERS));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

/**
 * @return array{0: AcademicYear, 1: AcademicYear, 2: SchoolClass, 3: SchoolClass, 4: SchoolClass}
 */
function minimalImportContext(): array
{
    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2028/2029'],
        ['is_active' => false, 'start_date' => '2028-07-01', 'end_date' => '2029-06-30'],
    );
    $class7 = SchoolClass::firstOrCreate(['name' => 'VII A'], ['level' => 7]);
    $class8 = SchoolClass::firstOrCreate(['name' => 'VIII A'], ['level' => 8]);
    $class9 = SchoolClass::firstOrCreate(['name' => 'IX A'], ['level' => 9]);

    return [$activeYear, $futureYear, $class7, $class8, $class9];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function minimalImportRow(array $overrides = []): array
{
    return array_merge([
        'row_number' => 2,
        'nis' => '',
        'nama_lengkap' => 'Muhammad Rizky',
        'nama_panggilan' => '',
        'kelas' => 'VII A',
        'jenis_kelamin' => '',
        'tempat_lahir' => '',
        'tanggal_lahir' => '',
        'nama_ayah' => '',
        'no_telp_ayah' => '',
        'nama_ibu' => '',
        'no_telp_ibu' => '',
        'alamat' => '',
        'entry_date' => '',
    ], $overrides);
}

it('reads a minimal workbook containing only Nama Lengkap and Kelas', function () {
    [$activeYear, , $class7] = minimalImportContext();
    $path = minimalImportWorkbook([['Ahmad Fauzan', 'VII A']]);

    try {
        $rows = app(StudentImportSpreadsheet::class)->read($path);
        $preview = app(StudentImportService::class)->preview(
            $rows,
            $activeYear->id,
            StudentImportService::CONTEXT_ACTIVE,
        );
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'nis' => '',
            'nama_lengkap' => 'Ahmad Fauzan',
            'nama_panggilan' => '',
            'kelas' => 'VII A',
            'jenis_kelamin' => '',
            'alamat' => '',
            'entry_date' => '',
        ])
        ->and($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['class_id'])->toBe($class7->id)
        ->and($preview['rows'][0]['status'])->toBe('new');
});

it('imports a minimal row without generating a fake NIS or placeholder biodata', function () {
    [$activeYear] = minimalImportContext();
    $result = app(StudentImportService::class)->import(
        [minimalImportRow(['nama_lengkap' => 'Ahmad Fauzan'])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->where('nama_lengkap', 'Ahmad Fauzan')->firstOrFail();

    expect($result['new'])->toBe(1)
        ->and($student->nis)->toBeNull()
        ->and($student->nama_panggilan)->toBeNull()
        ->and($student->jenis_kelamin)->toBeNull()
        ->and($student->alamat)->toBeNull()
        ->and($student->tempat_lahir)->toBeNull()
        ->and($student->tanggal_lahir)->toBeNull()
        ->and($student->nama_ayah)->toBeNull()
        ->and($student->schoolClass->name)->toBe('VII A')
        ->and(Student::query()->where('nis', 'like', 'NIS%')->exists())->toBeFalse()
        ->and(Student::query()->where('nama_panggilan', '-')->exists())->toBeFalse();
});

it('keeps the enrollment relationship to the resolved class and import year', function () {
    [$activeYear, , $class7] = minimalImportContext();
    app(StudentImportService::class)->import(
        [minimalImportRow()],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->firstOrFail();

    expect(StudentAcademicEnrollment::query())
        ->where('student_id', $student->id)
        ->where('academic_year_id', $activeYear->id)
        ->where('school_class_id', $class7->id)
        ->where('status', 'active')
        ->exists()
        ->toBeTrue();
});

it('flags a row without Nama Lengkap as a blocking error', function () {
    [$activeYear] = minimalImportContext();
    $preview = app(StudentImportService::class)->preview(
        [minimalImportRow(['nama_lengkap' => ''])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status'])->toBe('error')
        ->and($preview['rows'][0]['status_label'])->toContain('Nama Lengkap wajib diisi');
});

it('flags a row without Kelas as a blocking error', function () {
    [$activeYear] = minimalImportContext();
    $preview = app(StudentImportService::class)->preview(
        [minimalImportRow(['kelas' => ''])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status'])->toBe('error')
        ->and($preview['rows'][0]['status_label'])->toContain('Kelas wajib diisi');
});

it('reports an unknown class with a clear error and never auto-creates the class', function () {
    [$activeYear] = minimalImportContext();
    $preview = app(StudentImportService::class)->preview(
        [minimalImportRow(['kelas' => 'IX Z'])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeTrue()
        ->and($preview['rows'][0]['status_label'])->toContain('Kelas IX Z tidak ditemukan.')
        ->and(SchoolClass::query()->where('name', 'IX Z')->exists())->toBeFalse();
});

it('normalizes harmless whitespace and case when resolving the class', function () {
    [$activeYear, , $class7] = minimalImportContext();
    $preview = app(StudentImportService::class)->preview(
        [minimalImportRow(['kelas' => '  vii   a  '])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['class_id'])->toBe($class7->id);
});

it('treats duplicate name and class as a non-blocking warning and never merges rows', function () {
    [$activeYear] = minimalImportContext();
    $duplicate = minimalImportRow(['nama_lengkap' => 'Siswa Kembar', 'kelas' => 'VII A']);

    $preview = app(StudentImportService::class)->preview(
        [$duplicate, $duplicate],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($preview['has_errors'])->toBeFalse()
        ->and($preview['rows'][0]['warnings'])->toContain('Nama dan kelas yang sama ditemukan lebih dari sekali.')
        ->and($preview['rows'][0]['status'])->toBe('new')
        ->and($preview['rows'][1]['warnings'])->toContain('Nama dan kelas yang sama ditemukan lebih dari sekali.');

    app(StudentImportService::class)->import(
        [$duplicate, $duplicate],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect(Student::query()->where('nama_lengkap', 'Siswa Kembar')->count())->toBe(2);
});

it('imports an SMP-only file across VII, VIII, and IX and derives jenjang from the class', function () {
    [, $futureYear] = minimalImportContext();
    $result = app(StudentImportService::class)->import(
        [
            minimalImportRow(['nama_lengkap' => 'Siswa VII', 'kelas' => 'VII A']),
            minimalImportRow(['nama_lengkap' => 'Siswa VIII', 'kelas' => 'VIII A']),
            minimalImportRow(['nama_lengkap' => 'Siswa IX', 'kelas' => 'IX A']),
        ],
        $futureYear->id,
        StudentImportService::CONTEXT_PROSPECTIVE,
    );

    expect($result['new'])->toBe(3)
        ->and($result['breakdown'])->toMatchArray(['SMP' => 3])
        ->and(Student::query()->count())->toBe(3)
        ->and($result['breakdown'])->not->toHaveKey('SD')
        ->and($result['breakdown'])->not->toHaveKey('TK');
});

it('keeps the automatic payment setting and initial billing flow intact for a minimal row', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 7, 100000, ['billing_frequency' => BillFrequency::Monthly]);
    makeLevelDefault($spp, SchoolLevel::SMP);
    [, $futureYear] = minimalImportContext();

    app(StudentImportService::class)->import(
        [minimalImportRow()],
        $futureYear->id,
        StudentImportService::CONTEXT_PROSPECTIVE,
    );

    $student = Student::query()->firstOrFail();

    expect($student->paymentSettings()->where('payment_type_id', $spp->id)->where('is_active', true)->exists())->toBeTrue()
        ->and(StudentBill::query()->where('student_id', $student->id)->where('payment_type_id', $spp->id)->count())->toBeGreaterThanOrEqual(1);
});

it('still imports a row with complete biodata', function () {
    [$activeYear] = minimalImportContext();
    app(StudentImportService::class)->import(
        [minimalImportRow([
            'nis' => 'IMP-FULL',
            'nama_lengkap' => 'Ahmad Fauzan',
            'nama_panggilan' => 'Ahmad',
            'kelas' => 'VII A',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2014-05-02',
            'nama_ayah' => 'Bapak Fauzan',
            'no_telp_ayah' => '081234567890',
            'nama_ibu' => 'Ibu Fauzan',
            'no_telp_ibu' => '+6281234567890',
            'alamat' => 'Jl. Pendidikan',
            'entry_date' => '2026-07-15',
        ])],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    $student = Student::query()->where('nis', 'IMP-FULL')->firstOrFail();

    expect($student->nama_panggilan)->toBe('Ahmad')
        ->and($student->jenis_kelamin)->toBe('L')
        ->and($student->alamat)->toBe('Jl. Pendidikan')
        ->and($student->nama_ayah)->toBe('Bapak Fauzan')
        ->and($student->tanggal_lahir->toDateString())->toBe('2014-05-02')
        ->and($student->entry_date->toDateString())->toBe('2026-07-15');
});

it('keeps the exam eligibility page working for a student without NIS', function () {
    [$student, , $academicYear] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['nis' => null]);

    $config = StudentEligibilityConfig::query()->firstOrCreate(['school_level' => SchoolLevel::SMP]);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeBillRate($spp, 8, 1750000, ['billing_frequency' => BillFrequency::Monthly]);
    makeActiveSetting($student, $spp);
    StudentExamRequirement::query()->create([
        'student_exam_id' => null,
        'student_eligibility_config_id' => $config->id,
        'school_level' => SchoolLevel::SMP,
        'payment_type_id' => $spp->id,
        'billing_frequency' => BillFrequency::Monthly,
        'start_month' => '2026-08-01',
        'end_month' => '2026-08-01',
        'required_percentage' => 100,
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create());

    $this->get(route('siswa.exam-eligibility'))
        ->assertOk()
        ->assertSee($student->nama_lengkap)
        ->assertSee('NIS -', false);
});

it('does not touch Daycare data when importing students', function () {
    [$activeYear] = minimalImportContext();
    app(StudentImportService::class)->import(
        [minimalImportRow()],
        $activeYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect(Student::query()->count())->toBe(1)
        ->and(DaycareChild::query()->count())->toBe(0);
});
