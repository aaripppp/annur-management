<?php

use App\Enums\SchoolLevel;
use App\Models\Bank;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolMonthlyByLevelReportService;
use Barryvdh\DomPDF\PDF as DomPdf;

it('renders the level bank and cash matrices with blank zero amounts and reconciled totals', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SD);
    $bank = Bank::factory()->create(['name' => 'BSI PDF Jenjang', 'account_number' => '111111']);
    $zeroBank = Bank::factory()->create(['name' => 'BRI PDF Jenjang', 'account_number' => '222222']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket PDF Jenjang']);
    $type = makeBillType('SPP PDF Per Jenjang');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-04', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);

    $report = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
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
    $html = view('reports.school-monthly-by-level-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('LAPORAN PENERIMAAN PER JENJANG')
        ->toContain('SEPTEMBER 2026')
        ->toContain($bank->optionLabel(), $zeroBank->optionLabel())
        ->not->toContain($cash->optionLabel())
        ->toContain('rowspan="2"')
        ->toContain('Rp 300.000', 'Rp 100.000', 'Rp 400.000')
        ->not->toContain('Rp 0')
        ->toContain('.level { width: 9%; text-align: center; vertical-align: middle; font-weight: bold; }')
        ->toContain('GRAND TOTAL', $user->name)
        ->toContain('Nova Rabi&#039;ah Nurrohmah, SE');
});

it('classifies candidate calon payments into the correct level in the PDF', function () {
    $user = User::factory()->create();
    $candidate = Student::factory()->create([
        'class_id' => SchoolClass::factory()->create(['name' => 'X-A PDF Calon', 'level' => 10])->id,
    ]);
    $bank = Bank::factory()->create(['name' => 'BSI PDF Calon', 'account_number' => '111111']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket PDF Calon']);
    $type = makeBillType('SPP PDF Calon');

    createSchoolMonthlyReportPayment($candidate, $bank, $user, '2026-09-03', [
        ['payment_type_id' => $type->id, 'amount' => 250_000],
    ]);
    createSchoolMonthlyReportPayment($candidate, $cash, $user, '2026-09-04', [
        ['payment_type_id' => $type->id, 'amount' => 80_000],
    ]);

    $report = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => 'Nova Rabiah',
            'city_and_date' => 'Bekasi, 30 September 2026',
            'report_creator_title' => $user->position ?: $user->roleLabel(),
            'report_creator_name' => $user->name,
        ],
    ];
    $html = view('reports.school-monthly-by-level-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('SMA')
        ->toContain('Rp 250.000')
        ->toContain('Rp 80.000')
        ->toContain('Rp 330.000')
        ->not->toContain('Tidak Terklasifikasi')
        ->toContain('Nova Rabiah');
});

it('protects validates and streams the level report PDF', function () {
    $this->get(route('laporan.jenjang.pdf', ['month' => 9, 'year' => 2026]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.jenjang.pdf', ['month' => 0, 'year' => 2026]))
        ->assertSessionHasErrors('month');

    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.jenjang.pdf', ['month' => 9, 'year' => 2026]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-per-jenjang-2026-09.pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('uses the active Direktur Keuangan user for the level report signature', function () {
    $director = User::factory()->create([
        'name' => 'Nova Rabi\'ah Nurrohmah, SE',
        'position' => 'Direktur Keuangan',
    ]);
    $creator = User::factory()->create(['name' => 'Petugas Jenjang']);

    $pdf = Mockery::mock(DomPdf::class);
    $this->app->instance('dompdf.wrapper', $pdf);
    $pdf->shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data) use ($director, $creator): bool {
            expect($view)->toBe('reports.school-monthly-by-level-pdf')
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
        ->get(route('laporan.jenjang.pdf', ['month' => 9, 'year' => 2026]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(User::where('position', 'Direktur Keuangan')->count())->toBe(1);
});

it('falls back to the fixed Direktur Keuangan name when no user holds the position', function () {
    $creator = User::factory()->create(['name' => 'Petugas Tanpa Direktur']);

    $pdf = Mockery::mock(DomPdf::class);
    $this->app->instance('dompdf.wrapper', $pdf);
    $pdf->shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data) use ($creator): bool {
            expect($view)->toBe('reports.school-monthly-by-level-pdf')
                ->and($data['document']['approval']['approver_name'])->toBe("Nova Rabi'ah Nurrohmah, SE")
                ->and($data['document']['approval']['report_creator_name'])->toBe($creator->name);

            return true;
        })
        ->andReturnSelf();
    $pdf->shouldReceive('setOption')->once()->andReturnSelf();
    $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
    $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-mocked', 200, ['Content-Type' => 'application/pdf']));

    $this->actingAs($creator)
        ->get(route('laporan.jenjang.pdf', ['month' => 9, 'year' => 2026]))
        ->assertOk();

    expect(User::where('position', 'Direktur Keuangan')->exists())->toBeFalse();
});
