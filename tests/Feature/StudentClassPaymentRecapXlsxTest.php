<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentCategory;
use App\Models\User;
use App\Services\StudentClassPaymentRecapService;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

function enrollRecapExportStudent(
    Student $student,
    AcademicYear $academicYear,
    SchoolClass $schoolClass,
    string $status = 'active',
): void {
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => $status,
    ]);
}

/** @param list<int> $classLevels */
function configureRecapExportType(
    string $name,
    BillFrequency $frequency,
    array $classLevels = [7],
    int $amount = 100_000,
): PaymentType {
    $type = makeBillType($name);
    makeLevelDefault($type, SchoolLevel::SMP, false);

    foreach ($classLevels as $classLevel) {
        makeBillRate($type, $classLevel, $amount, ['billing_frequency' => $frequency]);
    }

    return $type;
}

/** @return array<string, list<array<int, mixed>>> */
function readRecapExportWorkbook(string $path): array
{
    $reader = new Reader;
    $sheets = [];

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
    }

    return $sheets;
}

function recapExportWorksheetXml(string $path, int $sheetNumber = 1): string
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return '';
    }

    $worksheet = $archive->getFromName("xl/worksheets/sheet{$sheetNumber}.xml");
    $archive->close();

    return is_string($worksheet) ? $worksheet : '';
}

function recapExportStylesXml(string $path): string
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return '';
    }

    $styles = $archive->getFromName('xl/styles.xml');
    $archive->close();

    return is_string($styles) ? $styles : '';
}

/** @return array{style_id: int, font: string, fill: string, border_id: int, format: string} */
function recapExportCellStyle(string $worksheetXml, string $stylesXml, string $cellReference): array
{
    $empty = ['style_id' => -1, 'font' => '', 'fill' => '', 'border_id' => -1, 'format' => ''];
    $pattern = '/<c\b(?=[^>]*\br="'.preg_quote($cellReference, '/').'")(?=[^>]*\bs="(\d+)")[^>]*>/';

    if (preg_match($pattern, $worksheetXml, $cellMatch) !== 1) {
        return $empty;
    }

    $styles = simplexml_load_string($stylesXml);

    if ($styles === false) {
        return $empty;
    }

    $styleId = (int) $cellMatch[1];
    $cellFormat = $styles->cellXfs->xf[$styleId];
    $fontId = (int) $cellFormat['fontId'];
    $fillId = (int) $cellFormat['fillId'];
    $borderId = (int) $cellFormat['borderId'];
    $numberFormatId = (int) $cellFormat['numFmtId'];
    $font = strtoupper((string) $styles->fonts->font[$fontId]->color['rgb']);
    $fill = strtoupper((string) $styles->fills->fill[$fillId]->patternFill->fgColor['rgb']);
    $format = '';

    foreach ($styles->numFmts->numFmt as $numberFormat) {
        if ((int) $numberFormat['numFmtId'] === $numberFormatId) {
            $format = (string) $numberFormat['formatCode'];
            break;
        }
    }

    return [
        'style_id' => $styleId,
        'font' => substr($font, -6),
        'fill' => substr($fill, -6),
        'border_id' => $borderId,
        'format' => $format,
    ];
}

/** @return list<string> */
function recapExportMergedRanges(string $worksheetXml): array
{
    preg_match_all('/<mergeCell ref="([^"]+)"/', $worksheetXml, $matches);

    return $matches[1];
}

/** @return list<array{min: int, max: int, width: float}> */
function recapExportColumnWidths(string $worksheetXml): array
{
    preg_match_all('/<col min="(\d+)" max="(\d+)" width="([\d.]+)"/', $worksheetXml, $matches, PREG_SET_ORDER);

    return array_map(fn (array $match): array => [
        'min' => (int) $match[1],
        'max' => (int) $match[2],
        'width' => (float) $match[3],
    ], $matches);
}

/** @param list<array{min: int, max: int, width: float}> $widths */
function recapExportWidthForColumn(array $widths, int $column): ?float
{
    foreach ($widths as $width) {
        if ($column >= $width['min'] && $column <= $width['max']) {
            return $width['width'];
        }
    }

    return null;
}

function recapExportYear(
    string $year = '2026/2027',
    string $startDate = '2026-07-01',
    string $endDate = '2027-06-30',
): AcademicYear {
    return AcademicYear::query()->updateOrCreate(['year' => $year], [
        'is_active' => false,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);
}

it('protects export routes and rejects a class outside the selected unit', function () {
    $academicYear = recapExportYear();
    $sdClass = SchoolClass::query()->create(['name' => 'VI A', 'level' => 6]);

    $this->get(route('laporan.kelas.export', [
        'academic_year_id' => $academicYear->id,
        'school_level' => SchoolLevel::SMP->value,
        'class_id' => $sdClass->id,
    ]))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.kelas.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
            'class_id' => $sdClass->id,
        ]))
        ->assertSessionHasErrors('class_id');
});

it('exports one historical class with canonical totals and Jemputan data', function () {
    $academicYear = recapExportYear();
    $otherYear = recapExportYear('2025/2026', '2025-07-01', '2026-06-30');
    $selectedClass = SchoolClass::query()->create(['name' => 'VII A', 'level' => 7]);
    $otherClass = SchoolClass::query()->create(['name' => 'VII B', 'level' => 7]);
    $currentClass = SchoolClass::query()->create(['name' => 'VIII A', 'level' => 8]);
    $included = Student::factory()->create([
        'nama_lengkap' => 'Siswa Historis VII A',
        'class_id' => $currentClass->id,
    ]);
    $otherClassStudent = Student::factory()->create([
        'nama_lengkap' => 'Siswa VII B',
        'class_id' => $otherClass->id,
    ]);
    $otherYearStudent = Student::factory()->create([
        'nama_lengkap' => 'Siswa Tahun Lain',
        'class_id' => $selectedClass->id,
    ]);
    enrollRecapExportStudent($included, $academicYear, $selectedClass);
    enrollRecapExportStudent($otherClassStudent, $academicYear, $otherClass);
    enrollRecapExportStudent($otherYearStudent, $otherYear, $selectedClass);

    $spp = configureRecapExportType('SPP', BillFrequency::Monthly, [7], 970_000);
    $ekskul = configureRecapExportType('Ekskul', BillFrequency::Monthly, [7], 60_000);
    $osis = configureRecapExportType('OSIS', BillFrequency::Monthly, [7], 5_000);
    $uangBuku = configureRecapExportType('Uang Buku', BillFrequency::Yearly, [7], 1_000_000);
    $uangPangkal = configureRecapExportType('Uang Pangkal', BillFrequency::OneTime, [7], 100_000_000);
    $jemputan = configureRecapExportType('Jemputan', BillFrequency::Monthly, [7], 500_000);
    $admJemputan = configureRecapExportType('Adm Jemputan', BillFrequency::OneTime, [7], 50_000);
    $sppBill = makeMonthlyBill($included, $spp, 970_000, 7, 2026);
    makeMonthlyBill($included, $ekskul, 60_000, 7, 2026);
    makeMonthlyBill($included, $osis, 5_000, 7, 2026);
    $uangBukuBill = makeYearlyBill($included, $uangBuku, 1_000_000, '2026/2027');
    $uangPangkalBill = makeOneTimeBill($included, $uangPangkal, 100_000_000, '2026/2027');
    $jemputanBill = makeMonthlyBill($included, $jemputan, 500_000, 7, 2026);
    $admBill = makeOneTimeBill($included, $admJemputan, 50_000, '2026/2027');
    payActiveBill($sppBill, 600_000);
    payActiveBill($uangBukuBill, 400_000);
    payActiveBill($uangPangkalBill, 2_000_000);
    payActiveBill($jemputanBill, 200_000);
    payActiveBill($admBill, 50_000);
    makeMonthlyBill($otherClassStudent, $spp, 9_700_000, 7, 2026);
    makeMonthlyBill($otherYearStudent, $spp, 8_700_000, 7, 2026);

    $canonical = app(StudentClassPaymentRecapService::class)
        ->generate($academicYear->id, SchoolLevel::SMP, $selectedClass->id);
    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.kelas.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
            'class_id' => $selectedClass->id,
        ]))
        ->assertOk()
        ->assertDownload('Laporan_Kelas_VII_A_2026-2027.xlsx')
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $path = $response->baseResponse->getFile()->getPathname();

    try {
        $sheets = readRecapExportWorkbook($path);
        $worksheetXml = recapExportWorksheetXml($path);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }

    expect(array_keys($sheets))->toBe(['VII A']);
    $rows = collect($sheets['VII A']);
    $flat = $rows->flatten();
    $mainHeaderIndex = $rows->search(fn (array $row): bool => in_array('RINGKASAN TAGIHAN', $row, true)
        && in_array('JULI 2026', $row, true));
    $mainHeader = $rows[$mainHeaderIndex];
    $mainDetailHeader = $rows[$mainHeaderIndex + 1];
    $mainStudent = $rows->first(fn (array $row): bool => ($row[1] ?? null) === 'Siswa Historis VII A');
    $mainTotal = $rows->first(fn (array $row): bool => ($row[1] ?? null) === 'JUMLAH');
    $jemputanHeaderIndex = $rows->search(fn (array $row, int $index): bool => $index > $mainHeaderIndex
        && in_array('RINGKASAN TAGIHAN', $row, true)
        && in_array('ADM JEMPUTAN', $row, true));
    $jemputanHeader = $rows[$jemputanHeaderIndex];
    $jemputanDetailHeader = $rows[$jemputanHeaderIndex + 1];
    $ringkasanSppIndex = array_search('SPP', $mainDetailHeader, true);
    $uangBukuStartIndex = array_search('UANG BUKU', $mainHeader, true);
    $uangPangkalStartIndex = array_search('UANG PANGKAL', $mainHeader, true);
    $uangBukuRemainingIndex = $uangBukuStartIndex + 2;
    $uangPangkalTargetIndex = $uangPangkalStartIndex;
    $uangPangkalPaidIndex = $uangPangkalStartIndex + 1;
    $jemputanTargetIndex = array_search('Jemputan', $jemputanDetailHeader, true);
    $admTargetIndex = array_search('Adm Jemputan', $jemputanDetailHeader, true);
    $admPaymentStartIndex = array_search('ADM JEMPUTAN', $jemputanHeader, true);
    $admPaidIndex = $admPaymentStartIndex;
    $admStatusIndex = $admPaymentStartIndex + 1;
    $jemputanStudent = $rows->filter(fn (array $row): bool => ($row[1] ?? null) === 'Siswa Historis VII A')->last();
    $mergedRanges = recapExportMergedRanges($worksheetXml);
    $columnWidths = recapExportColumnWidths($worksheetXml);

    expect($flat)->toContain('2026/2027', 'Siswa Historis VII A', 'REKAP JEMPUTAN')
        ->and($flat)->not->toContain('Siswa VII B', 'Siswa Tahun Lain')
        ->and($mainHeader)->toContain('JULI 2026', 'AGUSTUS 2026', 'UANG BUKU', 'UANG PANGKAL')
        ->and(array_slice($mainHeader, 0, 3))->toBe(['NO.', 'NAMA SISWA', 'KATEGORI'])
        ->and(array_slice($mainDetailHeader, 7, 3))->toBe(['SPP', 'Ekskul', 'OSIS'])
        ->and(array_slice($mainDetailHeader, $uangBukuStartIndex, 3))->toBe(['Tagihan', 'Terbayar', 'Sisa'])
        ->and(array_slice($mainDetailHeader, $uangPangkalStartIndex, 3))->toBe(['Tagihan', 'Terbayar', 'Sisa'])
        ->and($mergedRanges)->toContain(
            'A9:A10',
            'B9:B10',
            'C9:C10',
            'D9:F9',
            'G9:G10',
            'H9:J9',
            'AR9:AT9',
            'AU9:AW9',
            'C15:E15',
            'R15:S15',
        )
        ->and(recapExportWidthForColumn($columnWidths, 1))->toBe(7.0)
        ->and(recapExportWidthForColumn($columnWidths, 2))->toBe(34.0)
        ->and(recapExportWidthForColumn($columnWidths, 3))->toBe(16.0)
        ->and(recapExportWidthForColumn($columnWidths, 4))->toBe(16.0)
        ->and(recapExportWidthForColumn($columnWidths, 49))->toBe(16.0)
        ->and(recapExportWidthForColumn($columnWidths, 2))->toBeGreaterThan(recapExportWidthForColumn($columnWidths, 3))
        ->and($mainStudent[2])->toBe('Siswa Normal')
        ->and($mainStudent[$ringkasanSppIndex])->toBe(970_000)
        ->and($mainTotal[$ringkasanSppIndex])->toBe((int) $canonical['totals']['monthly_summary'][$spp->id])
        ->and($mainStudent[$uangBukuRemainingIndex])->toBe(600_000)
        ->and($mainTotal[$uangBukuRemainingIndex])->toBe((int) $canonical['totals']['yearly'][$uangBuku->id]['remaining'])
        ->and($mainStudent[$uangPangkalTargetIndex])->toBe(100_000_000)
        ->and($mainStudent[$uangPangkalTargetIndex])->toBeInt()
        ->and($mainStudent[$uangPangkalPaidIndex])->toBe(2_000_000)
        ->and($mainTotal[$uangPangkalPaidIndex])->toBe((int) $canonical['totals']['one_time'][$uangPangkal->id]['paid'])
        ->and($jemputanStudent[$jemputanTargetIndex])->toBe(500_000)
        ->and($jemputanStudent[$admTargetIndex])->toBe(50_000)
        ->and($jemputanStudent[$admPaidIndex])->toBe(50_000)
        ->and($jemputanStudent[$admStatusIndex])->toBe('Lunas')
        ->and($jemputanStudent[$jemputanTargetIndex])->toBeInt();
});

it('exports every category state with semantic styles while preserving body formatting', function () {
    $academicYear = recapExportYear();
    $schoolClass = SchoolClass::query()->create(['name' => 'VII Kategori', 'level' => 7]);
    $spp = configureRecapExportType('SPP', BillFrequency::Monthly, [7], 970_000);
    $employee = StudentCategory::factory()->create(['name' => 'Anak Pegawai', 'code' => 'anak_pegawai']);
    $teacher = StudentCategory::query()->where('code', 'anak_guru')->sole();
    $orphan = StudentCategory::query()->where('code', 'anak_yatim')->sole();
    $scholarship = StudentCategory::query()->where('code', 'beasiswa')->sole();
    $unknown = StudentCategory::factory()->create(['name' => 'Kategori Khusus', 'code' => 'kategori_khusus']);
    $definitions = [
        ['A Normal', []],
        ['B Employee', [$employee->id]],
        ['C Teacher', [$teacher->id]],
        ['D Orphan', [$orphan->id]],
        ['E Scholarship', [$scholarship->id]],
        ['F Unknown', [$unknown->id]],
        ['G Multiple', [$employee->id, $orphan->id]],
    ];

    foreach ($definitions as [$name, $categoryIds]) {
        $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $schoolClass->id]);
        enrollRecapExportStudent($student, $academicYear, $schoolClass);
        $student->categories()->attach($categoryIds);
        makeMonthlyBill($student, $spp, 970_000, 7, 2026);
    }

    $databaseCounts = [
        'categories' => StudentCategory::query()->count(),
        'assignments' => DB::table('student_category_student')->count(),
        'bills' => StudentBill::query()->count(),
    ];
    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.kelas.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
            'class_id' => $schoolClass->id,
        ]))
        ->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();

    try {
        $rows = collect(readRecapExportWorkbook($path)['VII Kategori']);
        $worksheetXml = recapExportWorksheetXml($path);
        $stylesXml = recapExportStylesXml($path);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }

    $studentRows = $rows
        ->filter(fn (array $row): bool => isset($row[1]) && preg_match('/^[A-G] /', (string) $row[1]) === 1)
        ->values();

    expect($studentRows->pluck(2)->all())->toBe([
        'Siswa Normal',
        'Anak Pegawai',
        'Anak Guru',
        'Anak Yatim',
        'Beasiswa',
        'Kategori Khusus',
        'Anak Pegawai, Anak Yatim',
    ])->and($studentRows->pluck(3)->all())->each->toBeInt()
        ->and(StudentCategory::query()->count())->toBe($databaseCounts['categories'])
        ->and(DB::table('student_category_student')->count())->toBe($databaseCounts['assignments'])
        ->and(StudentBill::query()->count())->toBe($databaseCounts['bills']);

    $expectedColors = [
        12 => ['FFC000', '000000'],
        13 => ['FFC000', '000000'],
        14 => ['548235', 'FFFFFF'],
        15 => ['2F5597', 'FFFFFF'],
        16 => ['7F8C8D', 'FFFFFF'],
        17 => ['7030A0', 'FFFFFF'],
    ];
    $normalCategoryStyle = recapExportCellStyle($worksheetXml, $stylesXml, 'C11');
    $normalFinancialStyle = recapExportCellStyle($worksheetXml, $stylesXml, 'D11');

    expect($normalCategoryStyle['style_id'])->toBe($normalFinancialStyle['style_id'])
        ->and($normalCategoryStyle['fill'])->toBe('')
        ->and($normalFinancialStyle['format'])->toBe('#,##0;;')
        ->and($normalFinancialStyle['border_id'])->toBeGreaterThan(0);

    foreach ($expectedColors as $rowNumber => [$fill, $font]) {
        $categoryStyle = recapExportCellStyle($worksheetXml, $stylesXml, "C{$rowNumber}");
        $financialStyle = recapExportCellStyle($worksheetXml, $stylesXml, "D{$rowNumber}");

        expect($categoryStyle['fill'])->toBe($fill)
            ->and($categoryStyle['font'])->toBe($font)
            ->and($categoryStyle['border_id'])->toBe($financialStyle['border_id'])
            ->and($categoryStyle['format'])->toBe('#,##0;;')
            ->and($financialStyle['format'])->toBe('#,##0;;');
    }
});

it('exports only eligible unit classes in level and name order', function () {
    $academicYear = recapExportYear();
    $otherYear = recapExportYear('2027/2028', '2027-07-01', '2028-06-30');
    $classSevenB = SchoolClass::query()->create(['name' => 'VII B', 'level' => 7]);
    $classSevenA = SchoolClass::query()->create(['name' => 'VII A', 'level' => 7]);
    $classEightA = SchoolClass::query()->create(['name' => 'VIII A', 'level' => 8]);
    $emptyClass = SchoolClass::query()->create(['name' => 'IX E', 'level' => 9]);
    $inactiveClass = SchoolClass::query()->create(['name' => 'IX D', 'level' => 9]);
    $otherYearClass = SchoolClass::query()->create(['name' => 'IX C', 'level' => 9]);
    $sdClass = SchoolClass::query()->create(['name' => 'VI A', 'level' => 6]);
    $students = collect([
        [$classSevenA, 'Siswa VII A'],
        [$classSevenB, 'Siswa VII B'],
        [$classEightA, 'Siswa VIII A'],
    ])->map(function (array $definition) use ($academicYear): Student {
        [$schoolClass, $name] = $definition;
        $student = Student::factory()->create(['nama_lengkap' => $name, 'class_id' => $schoolClass->id]);
        enrollRecapExportStudent($student, $academicYear, $schoolClass);

        return $student;
    });
    $inactiveStudent = Student::factory()->create(['nama_lengkap' => 'Siswa Tidak Aktif', 'class_id' => $inactiveClass->id]);
    $otherYearStudent = Student::factory()->create(['nama_lengkap' => 'Siswa Tahun Depan', 'class_id' => $otherYearClass->id]);
    $sdStudent = Student::factory()->create(['nama_lengkap' => 'Siswa SD', 'class_id' => $sdClass->id]);
    enrollRecapExportStudent($inactiveStudent, $academicYear, $inactiveClass, 'lulus');
    enrollRecapExportStudent($otherYearStudent, $otherYear, $otherYearClass);
    enrollRecapExportStudent($sdStudent, $academicYear, $sdClass);

    $jemputan = makeBillType('Jemputan');
    $admJemputan = makeBillType('Adm Jemputan');
    configureRecapExportType('SPP', BillFrequency::Monthly, [7, 8], 970_000);
    configureRecapExportType('Ekskul', BillFrequency::Monthly, [7, 8], 60_000);
    $jemputanBill = makeMonthlyBill($students[0], $jemputan, 500_000, 7, 2026);
    $admBill = makeOneTimeBill($students[2], $admJemputan, 50_000, '2026/2027');
    payActiveBill($jemputanBill, 250_000);
    payActiveBill($admBill, 50_000);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.kelas.unit.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
        ]))
        ->assertOk()
        ->assertDownload('Laporan_SMP_2026-2027.xlsx');
    $path = $response->baseResponse->getFile()->getPathname();

    try {
        $sheets = readRecapExportWorkbook($path);
        $worksheetXmls = [
            recapExportWorksheetXml($path, 1),
            recapExportWorksheetXml($path, 2),
            recapExportWorksheetXml($path, 3),
        ];
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }

    expect(array_keys($sheets))->toBe(['VII A', 'VII B', 'VIII A'])
        ->and(collect($sheets['VII A'])->flatten())->toContain('Siswa VII A', 500_000, 250_000)
        ->and(collect($sheets['VII A'])->flatten())->not->toContain('Siswa VII B', 'Siswa VIII A')
        ->and(collect($sheets['VII B'])->flatten())->toContain('Siswa VII B')
        ->and(collect($sheets['VIII A'])->flatten())->toContain('Siswa VIII A', 50_000, 'Lunas')
        ->and(collect($sheets)->flatten())->not->toContain(
            $emptyClass->name,
            'Siswa Tidak Aktif',
            'Siswa Tahun Depan',
            'Siswa SD',
        );

    foreach ($sheets as $rows) {
        $rows = collect($rows);
        $groupHeaderIndex = $rows->search(fn (array $row): bool => in_array('JULI 2026', $row, true));

        expect($groupHeaderIndex)->not->toBeFalse()
            ->and(array_slice($rows[$groupHeaderIndex], 0, 3))->toBe(['NO.', 'NAMA SISWA', 'KATEGORI'])
            ->and(array_slice($rows[$groupHeaderIndex + 1], 6, 2))->toBe(['SPP', 'Ekskul'])
            ->and(collect($rows)->first(fn (array $row): bool => isset($row[1]) && str_starts_with((string) $row[1], 'Siswa '))[2])->toBe('Siswa Normal');
    }

    foreach ($worksheetXmls as $worksheetXml) {
        expect(recapExportMergedRanges($worksheetXml))->toContain('C9:C10', 'D9:E9', 'F9:F10', 'G9:H9');
    }
});

it('sanitizes and de-duplicates worksheet names deterministically', function () {
    $academicYear = recapExportYear();
    $classes = collect([
        ['name' => 'VII/A', 'level' => 7],
        ['name' => 'VII?A', 'level' => 7],
        ['name' => 'Kelas Dengan Nama Yang Sangat Panjang Sekali', 'level' => 8],
    ])->map(function (array $attributes) use ($academicYear): SchoolClass {
        $schoolClass = SchoolClass::query()->create($attributes);
        $student = Student::factory()->create(['class_id' => $schoolClass->id]);
        enrollRecapExportStudent($student, $academicYear, $schoolClass);

        return $schoolClass;
    });

    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.kelas.unit.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
        ]))
        ->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();

    try {
        $names = array_keys(readRecapExportWorkbook($path));
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }

    expect($names)->toBe(['VII-A', 'VII-A (2)', 'Kelas Dengan Nama Yang Sangat P'])
        ->and(collect($names)->unique(fn (string $name): string => strtolower($name)))->toHaveCount($classes->count());

    foreach ($names as $name) {
        expect(mb_strlen($name))->toBeLessThanOrEqual(31)
            ->and($name)->not->toMatch('~[\\/?*:\[\]]~');
    }
});

it('deletes the temporary workbook after the download response is sent', function () {
    $academicYear = recapExportYear();
    $schoolClass = SchoolClass::query()->create(['name' => 'VII A', 'level' => 7]);
    $student = Student::factory()->create(['class_id' => $schoolClass->id]);
    enrollRecapExportStudent($student, $academicYear, $schoolClass);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.kelas.export', [
            'academic_year_id' => $academicYear->id,
            'school_level' => SchoolLevel::SMP->value,
            'class_id' => $schoolClass->id,
        ]))
        ->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();

    expect(is_file($path))->toBeTrue();

    ob_start();
    $response->baseResponse->sendContent();
    ob_end_clean();

    expect(is_file($path))->toBeFalse();
});
