<?php

use App\Enums\SchoolLevel;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolMonthlyReportService;
use App\Services\SchoolMonthlyReportSpreadsheet;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Nilai sel terakhir yang bermakna pada sebuah baris hasil baca XLSX.
 * OpenSpout reader melengkapi baris pendek dengan sel kosong hingga lebar
 * sheet, sehingga posisi total tidak selalu berada di indeks terakhir.
 */
function xlsxLastValue(array $row): mixed
{
    $filtered = array_values(array_filter(
        $row,
        fn ($cell): bool => $cell !== '' && $cell !== null
    ));

    return $filtered === [] ? null : $filtered[count($filtered) - 1];
}

/** @return list<string> */
function xlsxMergedRanges(string $path): array
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

function xlsxColumnName(int $zeroBasedIndex): string
{
    $columnName = '';
    $index = $zeroBasedIndex + 1;

    while ($index > 0) {
        $index--;
        $columnName = chr(65 + ($index % 26)).$columnName;
        $index = intdiv($index, 26);
    }

    return $columnName;
}

it('exports monthly summary and detail sheets with valid xlsx workbook and sheets', function () {
    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $path = app(SchoolMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);
        $names = collect(iterator_to_array($reader->getSheetIterator()))
            ->map(fn ($sheet): string => $sheet->getName())
            ->values()
            ->all();
    } finally {
        $reader->close();
        unlink($path);
    }

    expect($names)->toBe(['Laporan Bulanan', 'Rincian Transaksi']);
});

it('exports fixed bank rows with merged daily totals and monthly grand total', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Excel Bulanan']);
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BCA', 'account_number' => '111111']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI', 'account_number' => '222222']);
    $spp = makeBillType('SPP Excel Bulanan');
    $infaq = makeBillType('Infaq Excel Bulanan');
    $sppBill = makeMonthlyBill($student, $spp, 300_000, 8, 2026);

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-03', [
        ['payment_type_id' => $infaq->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-08-03', [
        ['bill_id' => $sppBill->id, 'payment_type_id' => null, 'period_month' => 8, 'period_year' => 2026, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-17', [
        ['payment_type_id' => $infaq->id, 'amount' => 50_000],
        ['payment_type_id' => null, 'description' => 'Kegiatan Excel Bulanan', 'amount' => 25_000],
    ], Payment::KIND_MANUAL);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $path = app(SchoolMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Laporan Bulanan') {
                $rows = collect(iterator_to_array($sheet->getRowIterator()))
                    ->map(fn ($row): array => $row->toArray())
                    ->all();
            }
        }

        $mergedRanges = xlsxMergedRanges($path);
    } finally {
        $reader->close();
        unlink($path);
    }

    $cells = collect($rows);
    $bankSectionIndex = $cells->search(fn (array $row): bool => ($row[0] ?? null) === 'PENERIMAAN BANK');
    $cashSectionIndex = $cells->search(fn (array $row): bool => ($row[0] ?? null) === 'PENERIMAAN TUNAI');
    $bankRows = $cells->slice($bankSectionIndex + 1, $cashSectionIndex - $bankSectionIndex - 1);
    $cashRows = $cells->slice($cashSectionIndex + 1);
    $sectionHeaders = $cells->filter(fn (array $row): bool => ($row[0] ?? null) === 'Hari / Tanggal')->values();
    $bankHeader = $sectionHeaders->first();
    $cashHeader = $sectionHeaders->last();
    $totalColumn = (int) array_search('Total', $bankHeader, true);
    $dailyTotalColumn = (int) array_search('Total Harian', $bankHeader, true);
    $cashTotalColumn = (int) array_search('Total', $cashHeader, true);
    $dailyTotalColumnName = xlsxColumnName($dailyTotalColumn);
    $firstDate = $bankRows->first(fn (array $row): bool => ($row[0] ?? null) === 'Senin, 03 Agustus 2026');
    $firstCashDate = $cashRows->first(fn (array $row): bool => ($row[0] ?? null) === 'Senin, 03 Agustus 2026');
    $secondCashDate = $cashRows->first(fn (array $row): bool => ($row[0] ?? null) === 'Senin, 17 Agustus 2026');
    $bankTotal = $cells->first(fn (array $row): bool => ($row[0] ?? null) === 'TOTAL PENERIMAAN BANK');
    $cashTotal = $cells->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL TUNAI');
    $firstSubtotal = $cells->first(fn (array $row): bool => ($row[0] ?? null) === 'TOTAL 03 AGUSTUS');
    $secondSubtotal = $cells->first(fn (array $row): bool => ($row[0] ?? null) === 'TOTAL 17 AGUSTUS');
    $grandTotal = $cells->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL');

    expect($bankSectionIndex)->toBeInt()
        ->and($cashSectionIndex)->toBeInt()
        ->and($cells->flatten())->not->toContain('Tunai / Cash')
        ->and($bankRows->pluck(1))->not->toContain($cash->optionLabel())
        ->and($bankRows->pluck(1))->toContain($zeroBank->optionLabel())
        ->and($firstCashDate)->not->toBeNull()
        ->and($firstCashDate[$cashTotalColumn])->toBe(100_000)
        ->and($secondCashDate[$cashTotalColumn])->toBe(75_000)
        ->and(xlsxLastValue($cashTotal))->toBe(175_000)
        ->and(xlsxLastValue($bankTotal))->toBe(300_000);

    expect($firstDate)->not->toBeNull()
        ->and($firstDate[1])->toBe($bank->optionLabel())
        ->and($firstDate[$totalColumn])->toBe(300_000)
        ->and($firstDate[$dailyTotalColumn])->toBe(300_000)
        ->and($firstSubtotal)->toBeNull()
        ->and($secondSubtotal)->toBeNull()
        ->and($mergedRanges)->toContain('A9:A10', $dailyTotalColumnName.'9:'.$dailyTotalColumnName.'10')
        ->and($mergedRanges)->not->toContain('A11:A12')
        ->and($grandTotal)->not->toBeNull()
        ->and(xlsxLastValue($grandTotal))->toBe(475_000)
        ->and((float) xlsxLastValue($grandTotal))->toBe($report['grand_total']);
});

it('writes numeric amounts without Rp prefix in summary and detail sheets', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Numerik');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-06', [
        ['payment_type_id' => $type->id, 'amount' => 250_000],
    ]);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $path = app(SchoolMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);
        $rowData = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rowData[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $summary = collect($rowData['Laporan Bulanan']);
    $detail = collect($rowData['Rincian Transaksi']);
    $grandTotalRow = $summary->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL');
    $grandTotal = xlsxLastValue($grandTotalRow);
    $detailTotal = $detail->first(fn (array $row): bool => ($row[9] ?? null) === 'TOTAL')[10];

    expect($grandTotal)->toBeInt()
        ->and($grandTotal)->toBe(250_000)
        ->and($detailTotal)->toBeInt()
        ->and($detailTotal)->toBe(250_000)
        ->and(collect($rowData['Laporan Bulanan'])->flatten()->contains(fn ($cell) => is_string($cell) && str_contains($cell, 'Rp ')))->toBeFalse()
        ->and(collect($rowData['Rincian Transaksi'])->flatten()->contains(fn ($cell) => is_string($cell) && str_contains($cell, 'Rp ')))->toBeFalse();
});

it('orders template columns as configured payment types followed by discovered manuals', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Kolom');
    $infaq = makeBillType('Infaq Kolom');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-08', [
        ['payment_type_id' => $spp->id, 'amount' => 10_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-09', [
        ['payment_type_id' => null, 'description' => 'Sumbangan Kolom', 'amount' => 20_000],
    ], Payment::KIND_MANUAL);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $path = app(SchoolMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Laporan Bulanan') {
                $headRow = collect(iterator_to_array($sheet->getRowIterator()))
                    ->map(fn ($row): array => $row->toArray())
                    ->first(fn (array $row): bool => ($row[0] ?? null) === 'Hari / Tanggal');
                $head = $headRow;
            }
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $categoryNames = collect($head)->slice(2, -2)->values()->all();

    expect($categoryNames)->toBe(['Formulir Pendaftaran', 'SPP Kolom', 'Infaq Kolom', 'Sumbangan Kolom']);
});

it('writes ascending Indonesian day and recorded-date rows in the summary sheet', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Tanggal Excel');

    foreach (['2026-08-19', '2026-08-05', '2026-08-26'] as $date) {
        createSchoolMonthlyReportPayment($student, $cash, $user, $date, [
            ['payment_type_id' => $type->id, 'amount' => 10_000],
        ]);
    }

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $path = app(SchoolMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Laporan Bulanan') {
                $dates = collect(iterator_to_array($sheet->getRowIterator()))
                    ->map(fn ($row): array => $row->toArray())
                    ->filter(fn (array $row): bool => preg_match('/^[A-Za-z]+, \d{2} [A-Za-z]+ \d{4}$/', (string) ($row[0] ?? '')))
                    ->pluck(0)
                    ->all();
            }
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    expect($dates)->toBe(['Rabu, 05 Agustus 2026', 'Rabu, 19 Agustus 2026', 'Rabu, 26 Agustus 2026']);
});

it('reconciles detail sheet amounts with payment detail rows and the grand total', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Detail Bulanan']);
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BRI', 'account_number' => '111111']);
    $type = makeBillType('SPP Detail Bulanan');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-10', [
        ['payment_type_id' => $type->id, 'amount' => 120_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-08-11', [
        ['payment_type_id' => $type->id, 'amount' => 80_000],
        ['payment_type_id' => null, 'description' => 'Kegiatan Detail', 'amount' => 40_000],
    ], Payment::KIND_MANUAL);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $path = app(SchoolMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Rincian Transaksi') {
                $detailRows = collect(iterator_to_array($sheet->getRowIterator()))
                    ->map(fn ($row): array => $row->toArray())
                    ->all();
            }
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $collected = collect($detailRows);
    $transactionRows = $collected->filter(fn (array $row): bool => str_starts_with((string) ($row[1] ?? ''), 'KWT-BLN-'));
    $detailTotal = $collected->first(fn (array $row): bool => ($row[9] ?? null) === 'TOTAL')[10];

    expect($transactionRows)->toHaveCount(3)
        ->and($transactionRows->pluck(4))->toContain('Siswa Detail Bulanan')
        ->and($transactionRows->pluck(6))->toContain('Manual', 'Tagihan')
        ->and($transactionRows->sum(10))->toBe(240_000)
        ->and($detailTotal)->toBe(240_000)
        ->and((float) $detailTotal)->toBe($report['grand_total']);
});

it('exports a valid zero workbook and intact sheets for an empty month', function () {
    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $path = app(SchoolMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();

            if ($sheet->getName() === 'Laporan Bulanan') {
                $summary = $rows;
            } else {
                $detail = $rows;
            }
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $summaryCollected = collect($summary);
    $grandTotal = $summaryCollected->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL');

    expect($grandTotal)->not->toBeNull()
        ->and(xlsxLastValue($grandTotal))->toBe(0)
        ->and($summaryCollected->contains(fn (array $row): bool => ($row[0] ?? null) === 'Tidak ada transaksi'))->toBeTrue()
        ->and(collect($detail))->toHaveCount(5)
        ->and(collect($detail)->filter(fn (array $row): bool => str_starts_with((string) ($row[1] ?? ''), 'KWT-BLN-')))->toBeEmpty();
});

it('streams the monthly export endpoint as a named xlsx download', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.export', ['month' => 3, 'year' => 2026]))
        ->assertOk()
        ->assertDownload('laporan-bulanan-sekolah-2026-03.xlsx')
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('exports a level-scoped monthly workbook with matching Unit metadata and no Jenjang rows', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Excel Jenjang');

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);

    createSchoolMonthlyReportPayment($sdStudent, $cash, $user, '2026-08-04', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($smpStudent, $cash, $user, '2026-08-05', [
        ['payment_type_id' => $type->id, 'amount' => 200_000],
    ]);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SMP);
    $path = app(SchoolMonthlyReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $summary = collect($rows['Laporan Bulanan']);
    $detail = collect($rows['Rincian Transaksi']);
    $summaryUnit = $summary->first(fn (array $row): bool => ($row[0] ?? null) === 'UNIT : SMP An-Nur');
    $summaryJenjang = $summary->first(fn (array $row): bool => str_starts_with((string) ($row[0] ?? ''), 'JENJANG :'));
    $detailUnit = $detail->first(fn (array $row): bool => ($row[0] ?? null) === 'Unit');
    $detailJenjang = $detail->first(fn (array $row): bool => ($row[0] ?? null) === 'Jenjang');
    $grandTotal = $summary->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL');

    expect($summaryUnit)->not->toBeNull()
        ->and($summaryJenjang)->toBeNull()
        ->and($detailUnit[1])->toBe('SMP An-Nur')
        ->and($detailJenjang)->toBeNull()
        ->and($grandTotal)->not->toBeNull()
        ->and(xlsxLastValue($grandTotal))->toBe(200_000)
        ->and($detail->filter(fn (array $row): bool => str_starts_with((string) ($row[1] ?? ''), 'KWT-BLN-')))->toHaveCount(1);
});
