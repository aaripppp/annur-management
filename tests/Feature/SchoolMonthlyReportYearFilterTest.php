<?php

use App\Livewire\SchoolDailyReport;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\ReportYearOptionsService;
use App\Services\SchoolMonthlyReportService;
use Livewire\Livewire;

function createMonthlyReportAcademicYear(string $year, bool $isActive = false): AcademicYear
{
    $startYear = (int) explode('/', $year, 2)[0];

    if ($isActive) {
        AcademicYear::deactivateAll();
    }

    $attributes = [
        'is_active' => $isActive,
        'start_date' => $startYear.'-07-01',
        'end_date' => ($startYear + 1).'-06-30',
    ];

    $academicYear = AcademicYear::query()->firstOrCreate(['year' => $year], $attributes);
    $academicYear->forceFill($attributes)->save();

    return $academicYear;
}

it('derives year options from transactions, current year and active academic year without a hardcoded range', function () {
    $this->travelTo('2026-09-14');

    AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

    AcademicYear::query()->updateOrCreate(
        ['year' => '2030/2031'],
        [
            'is_active' => true,
            'start_date' => '2030-07-01',
            'end_date' => '2031-06-30',
        ],
    );

    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Tahun Filter');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2021-03-10', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2023-11-05', [
        ['payment_type_id' => $type->id, 'amount' => 200_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-14', [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2019-01-01', [
        ['payment_type_id' => $type->id, 'amount' => 400_000],
    ], status: Payment::STATUS_CANCELLED);

    $options = app(ReportYearOptionsService::class)->options();

    expect($options)
        ->toBe([2021, 2023, 2026, 2030, 2031])
        ->and(array_unique($options))->toHaveCount(count($options));
});

it('renders academic year options and July to June month labels', function () {
    createMonthlyReportAcademicYear('2026/2027', isActive: true);
    createMonthlyReportAcademicYear('2027/2028');

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'monthly',
            'reportMonth' => 9,
            'monthlyAcademicYear' => '2026/2027',
        ])
        ->assertSet('reportYear', 2026)
        ->assertSeeHtml('id="report-academic-year"')
        ->assertDontSeeHtml('id="report-year"')
        ->assertSeeHtml('<option value="2027/2028">2027/2028</option>')
        ->assertSeeHtml('<option value="2026/2027">2026/2027</option>')
        ->assertSee('Juli 2026')
        ->assertSee('Desember 2026')
        ->assertSee('Januari 2027')
        ->assertSee('Juni 2027')
        ->assertDontSee('Januari 2026')
        ->set('monthlyAcademicYear', '2027/2028')
        ->assertSet('reportMonth', 9)
        ->assertSet('reportYear', 2027)
        ->assertSee('September 2027')
        ->assertSee('Juni 2028');
});

it('derives the report year from the academic year and month', function () {
    createMonthlyReportAcademicYear('2026/2027', isActive: true);
    createMonthlyReportAcademicYear('2027/2028');

    $component = Livewire::actingAs(User::factory()->create())->test(SchoolDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 9,
        'monthlyAcademicYear' => '2026/2027',
    ]);

    $component
        ->assertSet('reportMonth', 9)
        ->assertSet('reportYear', 2026)
        ->set('reportMonth', 1)
        ->assertSet('monthlyAcademicYear', '2026/2027')
        ->assertSet('reportYear', 2027)
        ->set('monthlyAcademicYear', '2027/2028')
        ->assertSet('reportMonth', 1)
        ->assertSet('reportYear', 2028)
        ->set('reportMonth', 12)
        ->assertSet('monthlyAcademicYear', '2027/2028')
        ->assertSet('reportYear', 2027)
        ->set('monthlyAcademicYear', '2027/2028')
        ->assertSet('monthlyAcademicYear', '2027/2028')
        ->assertSet('reportMonth', 12)
        ->assertSet('reportYear', 2027)
        ->set('monthlyAcademicYear', 'invalid')
        ->assertSet('monthlyAcademicYear', '2026/2027')
        ->assertSet('reportMonth', 12)
        ->assertSet('reportYear', 2026);
});

it('defaults to the active academic year and current month', function () {
    $this->travelTo('2026-09-14');
    createMonthlyReportAcademicYear('2026/2027', isActive: true);

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, ['activeTab' => 'monthly'])
        ->assertSet('monthlyAcademicYear', '2026/2027')
        ->assertSet('reportMonth', 9)
        ->assertSet('reportYear', 2026)
        ->assertSee('September 2026');
});

it('falls back to the latest academic year when no academic year is active', function () {
    $this->travelTo('2026-01-14');
    AcademicYear::deactivateAll();
    createMonthlyReportAcademicYear('2025/2026');
    createMonthlyReportAcademicYear('2026/2027');

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, ['activeTab' => 'monthly'])
        ->assertSet('monthlyAcademicYear', '2026/2027')
        ->assertSet('reportMonth', 1)
        ->assertSet('reportYear', 2027)
        ->assertSee('Januari 2027');
});

it('normalizes conflicting legacy monthly period query values', function () {
    createMonthlyReportAcademicYear('2026/2027', isActive: true);
    $this->actingAs(User::factory()->create());

    Livewire::withQueryParams([
        'bulan' => 1,
        'tahun' => 2026,
        'tahun_ajaran' => '2026/2027',
    ])->test(SchoolDailyReport::class, ['activeTab' => 'monthly'])
        ->assertSet('reportMonth', 1)
        ->assertSet('monthlyAcademicYear', '2026/2027')
        ->assertSet('reportYear', 2027)
        ->assertSee('Januari 2027')
        ->assertSee(route('laporan.bulanan.export', [
            'month' => 1,
            'year' => 2027,
            'school_level' => 'all',
        ]));
});

it('keeps the academic year period and school level consistent across monthly modes', function () {
    createMonthlyReportAcademicYear('2026/2027', isActive: true);

    $component = Livewire::actingAs(User::factory()->create())->test(SchoolDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 9,
        'monthlyAcademicYear' => '2026/2027',
        'schoolLevel' => 'SMP',
    ]);

    $component
        ->assertSeeHtml('id="report-academic-year"')
        ->assertSeeHtml('id="report-month"')
        ->assertSeeHtml('id="report-level"')
        ->assertDontSeeHtml('id="report-year"')
        ->assertSee(route('laporan.bulanan.export', [
            'month' => 9,
            'year' => 2026,
            'school_level' => 'SMP',
        ]))
        ->call('setMonthlyMode', 'by_level')
        ->assertSet('monthlyAcademicYear', '2026/2027')
        ->assertSet('reportMonth', 9)
        ->assertSet('reportYear', 2026)
        ->assertSet('schoolLevel', 'SMP')
        ->assertSeeHtml('id="report-academic-year"')
        ->assertSeeHtml('id="report-month"')
        ->assertDontSeeHtml('id="report-level"')
        ->assertDontSeeHtml('id="report-year"')
        ->assertSee(route('laporan.jenjang.export', [
            'month' => 9,
            'year' => 2026,
        ]))
        ->assertDontSee(route('laporan.jenjang.export', [
            'month' => 9,
            'year' => 2026,
            'school_level' => 'SMP',
        ]))
        ->call('setMonthlyMode', 'all_units')
        ->assertSet('monthlyAcademicYear', '2026/2027')
        ->assertSet('reportMonth', 9)
        ->assertSet('reportYear', 2026)
        ->assertSet('schoolLevel', 'SMP')
        ->assertSeeHtml('id="report-academic-year"')
        ->assertSeeHtml('id="report-month"')
        ->assertDontSeeHtml('id="report-level"')
        ->assertDontSeeHtml('id="report-year"')
        ->assertSee(route('laporan.seluruh-unit.export', [
            'month' => 9,
            'year' => 2026,
        ]));
});

it('leaves the monthly report calculation unchanged for the same month and year pair', function () {
    $this->travelTo('2026-09-14');

    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Paritas');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-17', [
        ['payment_type_id' => $type->id, 'amount' => 250_000],
        ['payment_type_id' => $type->id, 'amount' => 375_000],
    ], headerTotal: 625_000);

    $options = app(ReportYearOptionsService::class)->options();
    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);

    expect($options)->toContain(2026)
        ->and($report['grand_total'])->toBe(625_000.0)
        ->and($report['transaction_count'])->toBe(1);
});
