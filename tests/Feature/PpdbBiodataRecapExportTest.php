<?php

use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectiveStudentManagement;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\PpdbBiodataRecapQuery;
use App\Services\PpdbBiodataRecapSpreadsheet;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function recapExportUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => $active,
    ]);
}

function recapExportYear(string $year = '2034/2035'): AcademicYear
{
    [$start, $end] = explode('/', $year);

    return AcademicYear::create([
        'year' => $year,
        'is_active' => false,
        'start_date' => $start.'-07-01',
        'end_date' => $end.'-06-30',
    ]);
}

function recapExportApplicant(AcademicYear $year, SchoolClass $class, array $overrides = []): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create(array_merge([
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => ProspectiveStudentStatus::Registered,
        'jenis_kelamin' => 'L',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ], $overrides));
}

function recapExportPath(TestResponse $response): string
{
    $binaryResponse = $response->baseResponse;
    expect($binaryResponse)->toBeInstanceOf(BinaryFileResponse::class);

    return $binaryResponse->getFile()->getPathname();
}

/** @return array<string, list<array<int, mixed>>> */
function recapExportWorkbook(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->values()
                ->all();
        }
    } finally {
        $reader->close();
    }

    return $sheets;
}

/** @return list<string> */
function recapExportRegistrationNumbers(array $sheets): array
{
    $numbers = [];

    foreach (array_slice(PpdbBiodataRecapQuery::SHEET_NAMES, 1) as $sheetName) {
        $rows = collect($sheets[$sheetName]);
        $headerIndex = $rows->search(fn (array $row): bool => ($row[0] ?? null) === 'No.');
        $registrationIndex = array_search('No. Pendaftaran', $rows[$headerIndex], true);

        foreach ($rows->slice($headerIndex + 1) as $row) {
            if (is_numeric($row[0] ?? null)) {
                $numbers[] = $row[$registrationIndex] ?? '';
            }
        }
    }

    return $numbers;
}

beforeEach(function () {
    $this->year = recapExportYear();
    $this->smpClass = SchoolClass::factory()->create(['level' => 8]);
    $this->exportParameters = [
        'academic_year_id' => $this->year->id,
        'school_level' => SchoolLevel::SMP->value,
    ];
});

it('requires authentication before exporting the workbook', function () {
    $this->get(route('calon-siswa.biodata-recap.export', $this->exportParameters))
        ->assertRedirect(route('login'));
});

it('allows active admin and superadmin with private download headers', function (string $role) {
    $response = $this->actingAs(recapExportUser($role))
        ->get(route('calon-siswa.biodata-recap.export', $this->exportParameters));

    $path = recapExportPath($response);

    try {
        $response->assertOk()
            ->assertDownload('Rekap_Biodata_PPDB_2034-2035_SMP.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect((string) $response->headers->get('Cache-Control'))
            ->toContain('private')
            ->toContain('no-store')
            ->and($response->baseResponse->shouldDeleteFileAfterSend())->toBeTrue()
            ->and(array_keys(recapExportWorkbook($path)))->toBe(PpdbBiodataRecapQuery::SHEET_NAMES);
    } finally {
        @unlink($path);
    }
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);

it('denies inactive admins and unsupported roles before generation', function (string $role, bool $active) {
    $user = recapExportUser(User::ROLE_ADMIN, $active);
    $user->setAttribute('role', $role);
    $spreadsheet = Mockery::mock(PpdbBiodataRecapSpreadsheet::class);
    $spreadsheet->shouldNotReceive('create');
    $this->app->instance(PpdbBiodataRecapSpreadsheet::class, $spreadsheet);

    $this->actingAs($user)
        ->get(route('calon-siswa.biodata-recap.export', $this->exportParameters))
        ->assertForbidden();
})->with([
    'inactive admin' => [User::ROLE_ADMIN, false],
    'unsupported role' => ['auditor', true],
]);

it('rejects missing and invalid export parameters', function () {
    $user = recapExportUser();

    $this->actingAs($user)
        ->from(route('calon-siswa.index'))
        ->get(route('calon-siswa.biodata-recap.export'))
        ->assertRedirect(route('calon-siswa.index'))
        ->assertSessionHasErrors(['academic_year_id', 'school_level']);

    $this->actingAs($user)
        ->from(route('calon-siswa.index'))
        ->get(route('calon-siswa.biodata-recap.export', [
            'academic_year_id' => 999999,
            'school_level' => 'INVALID',
        ]))
        ->assertRedirect(route('calon-siswa.index'))
        ->assertSessionHasErrors(['academic_year_id', 'school_level']);
});

it('isolates academic year and status while preserving registered and converted', function () {
    $otherYear = recapExportYear('2035/2036');
    $registered = recapExportApplicant($this->year, $this->smpClass, ['registration_number' => 'REG-TARGET']);
    $converted = recapExportApplicant($this->year, $this->smpClass, [
        'registration_number' => 'REG-CONVERTED',
        'status' => ProspectiveStudentStatus::Converted,
    ]);
    $cancelled = recapExportApplicant($this->year, $this->smpClass, [
        'registration_number' => 'REG-CANCELLED',
        'status' => ProspectiveStudentStatus::Cancelled,
    ]);
    $other = recapExportApplicant($otherYear, $this->smpClass, ['registration_number' => 'REG-OTHER-YEAR']);

    $response = $this->actingAs(recapExportUser())
        ->get(route('calon-siswa.biodata-recap.export', $this->exportParameters));
    $path = recapExportPath($response);

    try {
        $numbers = recapExportRegistrationNumbers(recapExportWorkbook($path));
        expect($numbers)->toHaveCount(2)
            ->toContain($registered->registration_number, $converted->registration_number)
            ->not->toContain($cancelled->registration_number, $other->registration_number);
    } finally {
        @unlink($path);
    }
});

it('isolates each requested school level', function (SchoolLevel $requestedLevel) {
    $classes = [
        SchoolLevel::TK->value => SchoolClass::factory()->create(['level' => -1]),
        SchoolLevel::SD->value => SchoolClass::factory()->create(['level' => 5]),
        SchoolLevel::SMP->value => $this->smpClass,
        SchoolLevel::SMA->value => SchoolClass::factory()->create(['level' => 11]),
    ];

    foreach ($classes as $level => $class) {
        recapExportApplicant($this->year, $class, ['registration_number' => 'REG-'.$level]);
    }

    $response = $this->actingAs(recapExportUser())
        ->get(route('calon-siswa.biodata-recap.export', [
            'academic_year_id' => $this->year->id,
            'school_level' => $requestedLevel->value,
        ]));
    $path = recapExportPath($response);

    try {
        expect(recapExportRegistrationNumbers(recapExportWorkbook($path)))->toBe(['REG-'.$requestedLevel->value]);
    } finally {
        @unlink($path);
    }
})->with([
    'TK' => [SchoolLevel::TK],
    'SD' => [SchoolLevel::SD],
    'SMP' => [SchoolLevel::SMP],
    'SMA' => [SchoolLevel::SMA],
]);

it('downloads an empty six sheet workbook with zero grand total', function () {
    $response = $this->actingAs(recapExportUser())
        ->get(route('calon-siswa.biodata-recap.export', $this->exportParameters));
    $path = recapExportPath($response);

    try {
        $workbook = recapExportWorkbook($path);
        $recapRows = collect($workbook['REKAP']);
        $grandTotal = $recapRows->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL');

        expect(array_keys($workbook))->toBe(PpdbBiodataRecapQuery::SHEET_NAMES)
            ->and($grandTotal[1])->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('keeps formula like biodata literal in the HTTP workbook', function () {
    recapExportApplicant($this->year, $this->smpClass, ['alamat' => '=1+1']);
    $response = $this->actingAs(recapExportUser())
        ->get(route('calon-siswa.biodata-recap.export', $this->exportParameters));
    $path = recapExportPath($response);
    $archive = new ZipArchive;

    try {
        expect($archive->open($path))->toBeTrue();
        $xml = '';
        for ($sheet = 1; $sheet <= 6; $sheet++) {
            $xml .= (string) $archive->getFromName("xl/worksheets/sheet{$sheet}.xml");
        }

        expect($xml)->toContain('=1+1')->not->toContain('<f>');
    } finally {
        $archive->close();
        @unlink($path);
    }
});

it('does not change PPDB billing or payment records while exporting', function () {
    recapExportApplicant($this->year, $this->smpClass);
    $billCount = ProspectiveStudentBill::query()->count();
    $paymentCount = ProspectiveStudentPayment::query()->count();

    $response = $this->actingAs(recapExportUser())
        ->get(route('calon-siswa.biodata-recap.export', $this->exportParameters));
    $path = recapExportPath($response);

    try {
        expect(ProspectiveStudentBill::query()->count())->toBe($billCount)
            ->and(ProspectiveStudentPayment::query()->count())->toBe($paymentCount);
    } finally {
        @unlink($path);
    }
});

it('shows the export action only to authorized users while the page remains usable', function () {
    $admin = recapExportUser();

    Livewire::actingAs($admin)
        ->test(ProspectiveStudentManagement::class)
        ->assertSee('Export Excel Biodata')
        ->assertSee(route('calon-siswa.biodata-recap.export'), false);

    foreach ([recapExportUser(User::ROLE_ADMIN, false), recapExportUser()] as $user) {
        if ($user->isActive()) {
            $user->setAttribute('role', 'auditor');
        }

        Livewire::actingAs($user)
            ->test(ProspectiveStudentManagement::class)
            ->assertDontSee('Export Excel Biodata');
    }

    $this->actingAs($admin)->get(route('calon-siswa.index'))->assertOk();
});

it('returns a safe validation error when workbook generation fails', function () {
    $spreadsheet = Mockery::mock(PpdbBiodataRecapSpreadsheet::class);
    $spreadsheet->shouldReceive('create')
        ->once()
        ->andThrow(new RuntimeException('raw applicant details'));
    $this->app->instance(PpdbBiodataRecapSpreadsheet::class, $spreadsheet);

    $this->actingAs(recapExportUser())
        ->from(route('calon-siswa.index'))
        ->get(route('calon-siswa.biodata-recap.export', $this->exportParameters))
        ->assertRedirect(route('calon-siswa.index'))
        ->assertSessionHasErrors([
            'export' => 'Rekap biodata PPDB gagal dibuat. Silakan coba kembali.',
        ]);
});
