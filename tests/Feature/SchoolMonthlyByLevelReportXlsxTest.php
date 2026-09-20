<?php

use App\Enums\SchoolLevel;
use App\Models\Bank;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolMonthlyByLevelReportService;
use App\Services\SchoolMonthlyByLevelReportSpreadsheet;
use OpenSpout\Reader\XLSX\Reader;

/** @return list<string> */
function levelReportMergedRanges(string $path): array
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return [];
    }

    $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
    $archive->close();

    if (! is_string($worksheet)) {
        return [];
    }

    preg_match_all('/<mergeCell ref="([^"]+)"/', $worksheet, $matches);

    return $matches[1];
}

/** @return array{horizontal: string, vertical: string} */
function levelReportCellAlignment(string $path, string $cellReference): array
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        return ['horizontal' => '', 'vertical' => ''];
    }

    $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
    $stylesXml = $archive->getFromName('xl/styles.xml');
    $archive->close();

    if (! is_string($worksheet) || ! is_string($stylesXml)) {
        return ['horizontal' => '', 'vertical' => ''];
    }

    $cellPattern = '/<c\b(?=[^>]*\br="'.preg_quote($cellReference, '/').'")(?=[^>]*\bs="(\d+)")[^>]*>/';

    if (preg_match($cellPattern, $worksheet, $cellMatch) !== 1) {
        return ['horizontal' => '', 'vertical' => ''];
    }

    $styles = simplexml_load_string($stylesXml);

    if ($styles === false) {
        return ['horizontal' => '', 'vertical' => ''];
    }

    $alignment = $styles->cellXfs->xf[(int) $cellMatch[1]]->alignment;

    return [
        'horizontal' => (string) $alignment['horizontal'],
        'vertical' => (string) $alignment['vertical'],
    ];
}

it('exports numeric level bank and cash matrices with merged level totals', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SD);
    $bank = Bank::factory()->create(['name' => 'BSI Jenjang', 'account_number' => '111111']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI Jenjang', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Jenjang']);
    $type = makeBillType('SPP Excel Per Jenjang');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-04', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);

    $report = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
    $path = app(SchoolMonthlyByLevelReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetName = $sheet->getName();
            $rows = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray());
        }

        $mergedRanges = levelReportMergedRanges($path);
        $bankLevelAlignment = levelReportCellAlignment($path, 'A8');
        $cashLevelAlignment = levelReportCellAlignment($path, 'A14');
    } finally {
        $reader->close();
        unlink($path);
    }

    $bankRow = $rows->first(fn (array $row): bool => ($row[1] ?? null) === $bank->optionLabel());
    $zeroBankRow = $rows->first(fn (array $row): bool => ($row[1] ?? null) === $zeroBank->optionLabel());
    $levelBankRows = collect([$bankRow, $zeroBankRow]);
    $grandTotal = $rows->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL');

    expect($sheetName)->toBe('Laporan Per Jenjang')
        ->and($levelBankRows->pluck(0))->toContain(SchoolLevel::SD->value)
        ->and($bankRow[3])->toBe(300_000)
        ->and($bankRow[4])->toBe(300_000)
        ->and((float) $zeroBankRow[3])->toBe(0.0)
        ->and((float) $zeroBankRow[4])->toBe(0.0)
        ->and($mergedRanges)->toContain('A8:A9', 'F8:F9')
        ->and($bankLevelAlignment)->toBe(['horizontal' => 'center', 'vertical' => 'center'])
        ->and($cashLevelAlignment)->toBe(['horizontal' => 'center', 'vertical' => 'center'])
        ->and($grandTotal[1])->toBe(400_000)
        ->and($rows->flatten()->contains(fn ($cell): bool => is_string($cell) && str_contains($cell, 'Rp ')))->toBeFalse();
});

it('classifies candidate calon payments into the correct level in the workbook', function () {
    $user = User::factory()->create();
    $candidate = Student::factory()->create([
        'class_id' => SchoolClass::factory()->create(['name' => 'X-A Xlsx Calon', 'level' => 10])->id,
    ]);
    $bank = Bank::factory()->create(['name' => 'BSI Xlsx Calon', 'account_number' => '111111']);
    $type = makeBillType('SPP Xlsx Calon');

    createSchoolMonthlyReportPayment($candidate, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 220_000],
    ]);

    $report = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
    $path = app(SchoolMonthlyByLevelReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray());
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $bankRow = $rows->first(fn (array $row): bool => ($row[1] ?? null) === $bank->optionLabel());

    expect($bankRow)->not->toBeNull()
        ->and($bankRow[0])->toBe(SchoolLevel::SMA->value)
        ->and($bankRow[3])->toBe(220_000)
        ->and($rows->flatten()->contains('Tidak Terklasifikasi'))->toBeFalse();
});

it('protects validates and downloads the level report workbook', function () {
    $this->get(route('laporan.jenjang.export', ['month' => 9, 'year' => 2026]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.jenjang.export', ['month' => 13, 'year' => 2026]))
        ->assertSessionHasErrors('month');

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.jenjang.export', ['month' => 9, 'year' => 2026]))
        ->assertOk()
        ->assertDownload('laporan-per-jenjang-2026-09.xlsx')
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
