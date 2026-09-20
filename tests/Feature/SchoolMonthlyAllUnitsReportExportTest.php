<?php

use App\Models\Bank;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolMonthlyAllUnitsReportService;
use App\Services\SchoolMonthlyAllUnitsReportSpreadsheet;
use Barryvdh\DomPDF\PDF as DomPdf;
use OpenSpout\Reader\XLSX\Reader;

it('exports an all-units workbook with dynamic numeric bank and cash values', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI Excel Seluruh Unit', 'account_number' => '111111']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI Excel Seluruh Unit', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Excel Seluruh Unit');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-04', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);

    $report = app(SchoolMonthlyAllUnitsReportService::class)->generate(2026, 9);
    $path = app(SchoolMonthlyAllUnitsReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetName = $sheet->getName();
            $rows = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray());
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $bankRow = $rows->first(fn (array $row): bool => ($row[0] ?? null) === $bank->optionLabel());
    $zeroBankRow = $rows->first(fn (array $row): bool => ($row[0] ?? null) === $zeroBank->optionLabel());
    $cashSection = $rows->search(fn (array $row): bool => ($row[0] ?? null) === 'PENERIMAAN TUNAI');
    $cashRow = $rows->get($cashSection + 2);
    $grandTotal = $rows->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL');

    expect($sheetName)->toBe('Seluruh Unit')
        ->and($rows->flatten())->not->toContain('Tanggal', 'Jenjang')
        ->and($bankRow[2])->toBe(300_000)
        ->and($bankRow[3])->toBe(300_000)
        ->and((float) $zeroBankRow[2])->toBe(0.0)
        ->and((float) $zeroBankRow[3])->toBe(0.0)
        ->and($cashRow[1])->toBe(100_000)
        ->and($cashRow[2])->toBe(100_000)
        ->and($grandTotal[1])->toBe(400_000)
        ->and($rows->flatten()->contains(fn ($cell): bool => is_string($cell) && str_contains($cell, 'Rp ')))->toBeFalse();
});

it('renders the official all-units PDF with dynamic matrices and creator', function () {
    $user = User::factory()->create(['name' => 'Petugas Seluruh Unit']);
    $student = Student::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BCA PDF Seluruh Unit', 'account_number' => '111111']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI PDF Seluruh Unit', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP PDF Seluruh Unit');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-04', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);

    $report = app(SchoolMonthlyAllUnitsReportService::class)->generate(2026, 9);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE",
            'city_and_date' => 'Bekasi, 30 September 2026',
            'report_creator_title' => $user->position ?: $user->roleLabel(),
            'report_creator_name' => $user->name,
        ],
    ];
    $html = view('reports.school-monthly-all-units-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('LAPORAN PENERIMAAN SELURUH UNIT')
        ->toContain('SEPTEMBER 2026')
        ->toContain('PENERIMAAN BANK', 'PENERIMAAN TUNAI', 'RINGKASAN TOTAL')
        ->toContain($bank->optionLabel(), $zeroBank->optionLabel())
        ->not->toContain($cash->optionLabel())
        ->not->toContain('Rp 0')
        ->toContain('Rp 300.000', 'Rp 100.000', 'Rp 400.000')
        ->toContain($user->name)
        ->toContain('Nova Rabi&#039;ah Nurrohmah, SE');
});

it('protects validates and downloads the all-units workbook', function () {
    $this->get(route('laporan.seluruh-unit.export', ['month' => 9, 'year' => 2026]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.seluruh-unit.export', ['month' => 13, 'year' => 2026]))
        ->assertSessionHasErrors('month');

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.seluruh-unit.export', ['month' => 9, 'year' => 2026]))
        ->assertOk()
        ->assertDownload('laporan-seluruh-unit-2026-09.xlsx')
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('protects validates and streams the all-units PDF', function () {
    $this->get(route('laporan.seluruh-unit.pdf', ['month' => 9, 'year' => 2026]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.seluruh-unit.pdf', ['month' => 9, 'year' => 1999]))
        ->assertSessionHasErrors('year');

    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.seluruh-unit.pdf', ['month' => 9, 'year' => 2026]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-seluruh-unit-2026-09.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('uses the active Direktur Keuangan user for the all-units report signature', function () {
    $director = User::factory()->create([
        'name' => 'Nova Rabi\'ah Nurrohmah, SE',
        'position' => 'Direktur Keuangan',
    ]);
    $creator = User::factory()->create(['name' => 'Petugas Seluruh Unit']);

    $pdf = Mockery::mock(DomPdf::class);
    $this->app->instance('dompdf.wrapper', $pdf);
    $pdf->shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data) use ($director, $creator): bool {
            expect($view)->toBe('reports.school-monthly-all-units-pdf')
                ->and($data['document']['approval']['approver_name'])->toBe($director->name)
                ->and($data['document']['approval']['report_creator_title'])->toBe($creator->roleLabel())
                ->and($data['document']['approval']['report_creator_name'])->toBe($creator->name)
                ->and($data['document']['approval']['approver_title'])->toBe('Direktur Keuangan')
                ->and($data['document']['approval']['city_and_date'])->toBe('Bekasi, 30 September 2026');

            return true;
        })
        ->andReturnSelf();
    $pdf->shouldReceive('setOption')->once()->andReturnSelf();
    $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
    $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-mocked', 200, ['Content-Type' => 'application/pdf']));

    $this->actingAs($creator)
        ->get(route('laporan.seluruh-unit.pdf', ['month' => 9, 'year' => 2026]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(User::where('position', 'Direktur Keuangan')->count())->toBe(1);
});
