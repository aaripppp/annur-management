<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\JemputanDriverSemesterRecap;
use App\Models\AcademicYear;
use App\Models\BillAdjustment;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverDeductionRate;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\JemputanDriverSemesterRecapService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function semesterExportUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

function semesterExportYear(
    string $year = '2026/2027',
    string $start = '2026-07-01',
    string $end = '2027-06-30',
): AcademicYear {
    return AcademicYear::query()->firstOrCreate(
        ['year' => $year],
        ['is_active' => $year === '2026/2027', 'start_date' => $start, 'end_date' => $end],
    );
}

function semesterExportStudent(AcademicYear $year, string $name, int $level = 8): Student
{
    $class = SchoolClass::factory()->create(['name' => $name.' Class', 'level' => $level]);
    $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $class->id]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => 'active',
    ]);

    return $student;
}

function semesterExportAssign(JemputanDriver $driver, Student $student, AcademicYear $year): void
{
    JemputanDriverAssignment::query()->create([
        'jemputan_driver_id' => $driver->id,
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'effective_from' => $year->start_date,
        'effective_until' => null,
    ]);
}

function semesterExportBill(
    Student $student,
    PaymentType $type,
    int $year,
    int $month,
    float $amount,
): StudentBill {
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $type->id,
        'period_year' => $year,
        'period_month' => $month,
        'billing_frequency' => BillFrequency::Monthly->value,
        'academic_year' => null,
        'amount' => $amount,
    ]);
}

function semesterExportRate(JemputanDriver $driver, int $year, int $month, float $percentage): void
{
    JemputanDriverDeductionRate::query()->create([
        'jemputan_driver_id' => $driver->id,
        'effective_year' => $year,
        'effective_month' => $month,
        'percentage' => $percentage,
    ]);
}

function semesterExportAllocation(
    Student $student,
    AcademicYear $year,
    JemputanDriver $driver,
    int $periodYear,
    int $periodMonth,
    float $amount,
): void {
    JemputanDriverSalaryAllocation::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'period_year' => $periodYear,
        'period_month' => $periodMonth,
        'jemputan_driver_id' => $driver->id,
        'allocated_amount' => $amount,
    ]);
}

/** @param array<string, mixed> $parameters */
function semesterExportResponse(array $parameters): TestResponse
{
    return test()->get(route('jemputan.semester-recap.export', $parameters));
}

function semesterExportPath(TestResponse $response): string
{
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);

    return $response->baseResponse->getFile()->getPathname();
}

/** @return list<array<int, mixed>> */
function semesterExportRows(string $path): array
{
    $reader = new Reader;

    try {
        $reader->open($path);
        $sheet = $reader->getSheetIterator()->current();

        return collect(iterator_to_array($sheet->getRowIterator()))
            ->map(fn ($row): array => $row->toArray())
            ->values()
            ->all();
    } finally {
        $reader->close();
    }
}

function semesterExportXml(string $path, string $entry): string
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return '';
    }

    $xml = $archive->getFromName($entry);
    $archive->close();

    return is_string($xml) ? $xml : '';
}

function semesterExportCellFill(string $path, string $cell): ?string
{
    $sheetXml = semesterExportXml($path, 'xl/worksheets/sheet1.xml');

    if (preg_match('/<c r="'.preg_quote($cell, '/').'"[^>]*\bs="(\d+)"/', $sheetXml, $matches) !== 1) {
        return null;
    }

    $styles = simplexml_load_string(semesterExportXml($path, 'xl/styles.xml'));

    if ($styles === false) {
        return null;
    }

    $xfs = $styles->xpath('//*[local-name()="cellXfs"]/*[local-name()="xf"]');
    $fillId = (int) ($xfs[(int) $matches[1]]['fillId'] ?? 0);
    $fills = $styles->xpath('//*[local-name()="fills"]/*[local-name()="fill"]');
    $colors = $fills[$fillId]->xpath('.//*[local-name()="fgColor"]');

    return $colors === false || $colors === [] ? null : (string) $colors[0]['rgb'];
}

beforeEach(function () {
    $this->actingAs(semesterExportUser());
});

it('exports dynamic filtered semester rows and recap from the Batch 8 service', function () {
    $year = semesterExportYear();
    $otherYear = semesterExportYear('2027/2028', '2027-07-01', '2028-06-30');
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $smpDriver = JemputanDriver::factory()->create(['name' => 'Sopir SMP']);
    $sdDriver = JemputanDriver::factory()->create(['name' => 'Sopir SD']);
    $otherDriver = JemputanDriver::factory()->create(['name' => 'Sopir Tahun Lain']);
    $smpStudent = semesterExportStudent($year, 'Siswa SMP', 8);
    $sdStudent = semesterExportStudent($year, 'Siswa SD', 5);
    $otherStudent = semesterExportStudent($otherYear, 'Siswa Tahun Lain', 8);

    semesterExportAssign($smpDriver, $smpStudent, $year);
    semesterExportAssign($sdDriver, $sdStudent, $year);
    semesterExportAssign($otherDriver, $otherStudent, $otherYear);
    semesterExportRate($smpDriver, 2026, 7, 10);
    semesterExportRate($sdDriver, 2026, 7, 10);
    semesterExportRate($otherDriver, 2027, 7, 10);
    semesterExportBill($smpStudent, $type, 2026, 7, 100000);
    semesterExportBill($smpStudent, $type, 2026, 8, 200000);
    semesterExportBill($sdStudent, $type, 2026, 7, 900000);
    semesterExportBill($otherStudent, $type, 2027, 7, 700000);

    $parameters = [
        'academic_year_id' => $year->id,
        'school_level' => SchoolLevel::SMP->value,
        'semester' => 1,
    ];
    $report = app(JemputanDriverSemesterRecapService::class)->generate($year->id, SchoolLevel::SMP, 1);
    $response = semesterExportResponse($parameters);
    $path = semesterExportPath($response);

    try {
        $rows = semesterExportRows($path);

        $response->assertOk()->assertDownload('Rekap_Jemputan_6_Bulan_SMP_Semester_1_2026-2027.xlsx');
        expect($rows[0][0])->toBe('REKAP PEMBAYARAN JEMPUTAN')
            ->and($rows[1][0])->toBe('SMP ANNUR SEMESTER 1 TP. 2026/2027')
            ->and(array_slice($rows[6], 0, 10))->toBe([
                'No', 'Nama Sopir', 'Pembayaran', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember', 'Total',
            ])->and($rows[7][0])->toBe(1)
            ->and($rows[7][1])->toBe('Sopir SMP')
            ->and($rows[7][2])->toBe('Sopir')
            ->and($rows[8][2])->toBe('Fee Yayasan')
            ->and($rows[9][2])->toBe('Total')
            ->and($rows[7][3])->toBe($report['rows'][0]['months']['2026-07']['driver_income'])
            ->and($rows[8][3])->toBe($report['rows'][0]['months']['2026-07']['foundation_fee'])
            ->and($rows[9][9])->toBe($report['rows'][0]['totals']['total'])
            ->and($rows[10][0])->toBe('REKAP')
            ->and($rows[10][2])->toBe('Sopir')
            ->and($rows[11][2])->toBe('Fee Yayasan')
            ->and($rows[12][2])->toBe('Total')
            ->and(collect($rows)->flatten()->contains('Sopir SD'))->toBeFalse()
            ->and(collect($rows)->flatten()->contains('Sopir Tahun Lain'))->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('exports January through June for semester two with the correct title', function () {
    $year = semesterExportYear();
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Semester Dua']);
    $student = semesterExportStudent($year, 'Siswa Semester Dua', 5);
    semesterExportAssign($driver, $student, $year);
    semesterExportRate($driver, 2027, 1, 10);
    semesterExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 2027, 1, 100000);

    $response = semesterExportResponse([
        'academic_year_id' => $year->id,
        'school_level' => SchoolLevel::SD->value,
        'semester' => 2,
    ]);
    $path = semesterExportPath($response);

    try {
        $rows = semesterExportRows($path);
        expect($rows[1][0])->toBe('SD ANNUR SEMESTER 2 TP. 2026/2027')
            ->and(array_slice($rows[6], 3, 6))->toBe(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni']);
    } finally {
        @unlink($path);
    }
});

it('keeps valid multi-driver allocations free from double counting and rounds fees monthly', function () {
    $year = semesterExportYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $firstDriver = JemputanDriver::factory()->create(['name' => 'Sopir A']);
    $secondDriver = JemputanDriver::factory()->create(['name' => 'Sopir B']);
    $student = semesterExportStudent($year, 'Siswa Multi');
    semesterExportAssign($firstDriver, $student, $year);
    semesterExportAssign($secondDriver, $student, $year);
    semesterExportRate($firstDriver, 2026, 7, 10);
    semesterExportRate($secondDriver, 2026, 7, 20);
    semesterExportBill($student, $type, 2026, 7, 700005);
    semesterExportAllocation($student, $year, $firstDriver, 2026, 7, 400005);
    semesterExportAllocation($student, $year, $secondDriver, 2026, 7, 300000);

    $response = semesterExportResponse(['academic_year_id' => $year->id, 'semester' => 1]);
    $path = semesterExportPath($response);

    try {
        $rows = semesterExportRows($path);

        expect($rows[7][3])->toBe(360004)
            ->and($rows[8][3])->toBe(40001)
            ->and($rows[9][3])->toBe(400005)
            ->and($rows[13][3])->toBe(600004)
            ->and($rows[14][3])->toBe(100001)
            ->and($rows[15][3])->toBe(700005)
            ->and($rows[15][9])->toBe(700005);
    } finally {
        @unlink($path);
    }
});

it('exports allocation warnings and does not hide fallback double counting', function () {
    $year = semesterExportYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $drivers = JemputanDriver::factory()->count(2)->create();
    $outsider = JemputanDriver::factory()->create();

    foreach ($drivers as $driver) {
        semesterExportRate($driver, 2026, 7, 10);
    }

    $missing = semesterExportStudent($year, 'Siswa Missing');
    $stale = semesterExportStudent($year, 'Siswa Stale');
    $orphan = semesterExportStudent($year, 'Siswa Orphan');

    foreach ([$missing, $stale, $orphan] as $student) {
        foreach ($drivers as $driver) {
            semesterExportAssign($driver, $student, $year);
        }
    }

    semesterExportBill($missing, $type, 2026, 7, 100000);
    semesterExportBill($stale, $type, 2026, 8, 200000);
    semesterExportAllocation($stale, $year, $drivers[0], 2026, 8, 50000);
    semesterExportAllocation($stale, $year, $drivers[1], 2026, 8, 50000);
    semesterExportBill($orphan, $type, 2026, 9, 300000);
    semesterExportAllocation($orphan, $year, $drivers[0], 2026, 9, 150000);
    semesterExportAllocation($orphan, $year, $drivers[1], 2026, 9, 150000);
    semesterExportAllocation($orphan, $year, $outsider, 2026, 9, 0);

    $response = semesterExportResponse(['academic_year_id' => $year->id, 'semester' => 1]);
    $path = semesterExportPath($response);

    try {
        $rows = semesterExportRows($path);

        expect($rows[2][0])->toContain('BELUM FINAL')
            ->and($rows[3][0])->toContain('1 missing, 1 stale, 1 orphan')
            ->and($rows[3][0])->toContain('Nominal fallback bukan nilai final')
            ->and($rows[15][3])->toBe(200000)
            ->and($rows[15][4])->toBe(400000)
            ->and($rows[15][5])->toBe(600000);
    } finally {
        @unlink($path);
    }
});

it('keeps null amounts blank-like and warns for missing and legacy rates', function () {
    $year = semesterExportYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $missingDriver = JemputanDriver::factory()->create(['name' => 'Sopir Null', 'foundation_deduction_percentage' => null]);
    $legacyDriver = JemputanDriver::factory()->create(['name' => 'Sopir Legacy', 'foundation_deduction_percentage' => 10]);
    $missingStudent = semesterExportStudent($year, 'Siswa Null');
    $legacyStudent = semesterExportStudent($year, 'Siswa Legacy');
    semesterExportAssign($missingDriver, $missingStudent, $year);
    semesterExportAssign($legacyDriver, $legacyStudent, $year);
    semesterExportBill($missingStudent, $type, 2026, 7, 100000);
    semesterExportBill($legacyStudent, $type, 2026, 7, 100000);

    $response = semesterExportResponse(['academic_year_id' => $year->id, 'semester' => 1]);
    $path = semesterExportPath($response);

    try {
        $rows = semesterExportRows($path);
        $nullDriverStart = ($rows[7][1] ?? null) === 'Sopir Null' ? 7 : 10;

        expect($rows[2][0])->toContain('BELUM FINAL')
            ->and($rows[3][0])->toContain('Persentase belum diatur: 6 periode')
            ->and($rows[3][0])->toContain('Persentase legacy: 6 periode')
            ->and($rows[$nullDriverStart][3])->toBe('—')
            ->and($rows[$nullDriverStart + 1][3])->toBe('—')
            ->and($rows[$nullDriverStart + 2][3])->toBe('—')
            ->and($rows[4][0])->toContain('membership aktif');
    } finally {
        @unlink($path);
    }
});

it('uses F4B Folio portrait fit-to-width print area borders and currency formatting', function () {
    $year = semesterExportYear();
    $driver = JemputanDriver::factory()->create(['name' => 'Sopir Format']);
    $student = semesterExportStudent($year, 'Siswa Format');
    semesterExportAssign($driver, $student, $year);
    semesterExportRate($driver, 2026, 7, 10);
    semesterExportBill($student, PaymentType::factory()->create(['name' => 'Jemputan']), 2026, 7, 100000);

    $response = semesterExportResponse(['academic_year_id' => $year->id, 'semester' => 1]);
    $path = semesterExportPath($response);

    try {
        $sheetXml = semesterExportXml($path, 'xl/worksheets/sheet1.xml');
        $workbookXml = semesterExportXml($path, 'xl/workbook.xml');
        $stylesXml = semesterExportXml($path, 'xl/styles.xml');

        expect($sheetXml)->toContain('topLeftCell="A1"')
            ->and($sheetXml)->not->toContain('<pane')
            ->and($sheetXml)->toContain('orientation="portrait"')
            ->and($sheetXml)->toContain('paperSize="14"')
            ->and($sheetXml)->toContain('fitToWidth="1"')
            ->and($sheetXml)->toContain('fitToHeight="0"')
            ->and($sheetXml)->toContain('ref="A1:J13"')
            ->and($sheetXml)->toContain('ref="A8:A10"')
            ->and($sheetXml)->toContain('ref="B8:B10"')
            ->and($sheetXml)->toContain('ref="A11:B13"')
            ->and($workbookXml)->toContain('_xlnm.Print_Area')
            ->and($workbookXml)->toContain('REKAP_6_BULAN!$A$1:$J$13')
            ->and($workbookXml)->toContain('_xlnm.Print_Titles')
            ->and($workbookXml)->toContain('REKAP_6_BULAN!7:7')
            ->and($stylesXml)->toContain('&quot;Rp&quot; #,##0')
            ->and($stylesXml)->toContain('<borders count="2"')
            ->and(strtoupper((string) semesterExportCellFill($path, 'J12')))->toEndWith('FFD966');
    } finally {
        @unlink($path);
    }
});

it('shows and redirects the Livewire export button with the active filters', function () {
    $year = semesterExportYear();
    $driver = JemputanDriver::factory()->create();
    $student = semesterExportStudent($year, 'Siswa Tombol', 8);
    semesterExportAssign($driver, $student, $year);
    semesterExportRate($driver, 2027, 1, 10);

    Livewire::test(JemputanDriverSemesterRecap::class)
        ->set('selectedSchoolLevel', SchoolLevel::SMP->value)
        ->set('selectedSemester', 2)
        ->assertSee('Export Excel')
        ->assertSee('wire:click="exportExcel"', false)
        ->call('exportExcel')
        ->assertRedirect(route('jemputan.semester-recap.export', [
            'academic_year_id' => $year->id,
            'school_level' => SchoolLevel::SMP->value,
            'semester' => 2,
        ]));
});

it('validates export filters', function (array $parameters, string $error) {
    semesterExportYear();

    semesterExportResponse($parameters)->assertSessionHasErrors($error);
})->with([
    'missing year' => [['semester' => 1], 'academic_year_id'],
    'unknown year' => [['academic_year_id' => 999999, 'semester' => 1], 'academic_year_id'],
    'invalid level' => [['academic_year_id' => 1, 'school_level' => 'INVALID', 'semester' => 1], 'school_level'],
    'invalid semester' => [['academic_year_id' => 1, 'semester' => 3], 'semester'],
]);

it('allows active Admin and Superadmin exports', function (string $role) {
    auth()->logout();
    $this->actingAs(semesterExportUser($role));
    $year = semesterExportYear();

    $response = semesterExportResponse(['academic_year_id' => $year->id, 'semester' => 1]);
    $path = semesterExportPath($response);

    try {
        $response->assertOk();
    } finally {
        @unlink($path);
    }
})->with([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN]);

it('denies guests TU Unit and inactive users at the export endpoint', function () {
    auth()->logout();
    $year = semesterExportYear();
    $parameters = ['academic_year_id' => $year->id, 'semester' => 1];

    semesterExportResponse($parameters)->assertRedirect(route('login'));
    $this->actingAs(semesterExportUser(User::ROLE_TU_UNIT));
    semesterExportResponse($parameters)->assertRedirect(route('tu.pending'));
    $this->actingAs(semesterExportUser(User::ROLE_ADMIN, false));
    semesterExportResponse($parameters)->assertRedirect(route('login'));
});

it('keeps export queries bounded and performs no financial writes', function () {
    $year = semesterExportYear();
    $type = PaymentType::factory()->create(['name' => 'Jemputan']);
    $firstDriver = JemputanDriver::factory()->create();
    $firstStudent = semesterExportStudent($year, 'Siswa Awal');
    semesterExportAssign($firstDriver, $firstStudent, $year);
    semesterExportRate($firstDriver, 2026, 7, 10);
    semesterExportBill($firstStudent, $type, 2026, 7, 100000);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $smallResponse = semesterExportResponse(['academic_year_id' => $year->id, 'semester' => 1]);
    $smallPath = semesterExportPath($smallResponse);
    $smallQueries = collect(DB::getQueryLog());
    @unlink($smallPath);

    foreach (range(1, 15) as $index) {
        $driver = JemputanDriver::factory()->create();
        $student = semesterExportStudent($year, 'Siswa Banyak '.$index);
        semesterExportAssign($driver, $student, $year);
        semesterExportRate($driver, 2026, 7, 10);
        semesterExportBill($student, $type, 2026, 7, 100000);
    }

    $before = [
        StudentBill::query()->count(),
        BillAdjustment::query()->count(),
        JemputanDriverSalaryAllocation::query()->count(),
        JemputanDriverDeductionRate::query()->count(),
        Payment::query()->count(),
        PaymentDetail::query()->count(),
    ];
    DB::flushQueryLog();
    $largeResponse = semesterExportResponse(['academic_year_id' => $year->id, 'semester' => 1]);
    $largePath = semesterExportPath($largeResponse);
    $largeQueries = collect(DB::getQueryLog());
    @unlink($largePath);
    DB::disableQueryLog();
    $after = [
        StudentBill::query()->count(),
        BillAdjustment::query()->count(),
        JemputanDriverSalaryAllocation::query()->count(),
        JemputanDriverDeductionRate::query()->count(),
        Payment::query()->count(),
        PaymentDetail::query()->count(),
    ];
    $smallSelects = $smallQueries->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))->count();
    $largeSelects = $largeQueries->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))->count();
    $writes = $largeQueries->filter(
        fn (array $query): bool => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query['query']) === 1,
    );

    expect($largeSelects)->toBe($smallSelects)
        ->and($largeSelects)->toBeLessThanOrEqual(10)
        ->and($largeQueries->filter(fn (array $query): bool => str_contains($query['query'], 'jemputan_driver_deduction_rates'))->count())->toBe(1)
        ->and($writes)->toBeEmpty()
        ->and($after)->toBe($before);
});
