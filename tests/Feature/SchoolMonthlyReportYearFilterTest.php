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

it('derives year options from transactions, current year and active academic year without a hardcoded range', function () {
    $this->travelTo('2026-09-14');

    AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

    AcademicYear::create([
        'year' => '2030/2031',
        'is_active' => true,
        'start_date' => '2030-07-01',
        'end_date' => '2031-06-30',
    ]);

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

it('renders the dynamic calendar years in the monthly report year dropdown', function () {
    $this->travelTo('2026-09-14');

    AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

    AcademicYear::create([
        'year' => '2030/2031',
        'is_active' => true,
        'start_date' => '2030-07-01',
        'end_date' => '2031-06-30',
    ]);

    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Tahun Dropdown');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2021-03-10', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2030-08-05', [
        ['payment_type_id' => $type->id, 'amount' => 200_000],
    ]);

    $this->actingAs($user);

    Livewire::test(SchoolDailyReport::class, ['activeTab' => 'monthly', 'reportMonth' => 9, 'reportYear' => 2026])
        ->assertSet('reportYear', 2026)
        ->assertSeeHtml('<option value="2021">2021</option>')
        ->assertSeeHtml('<option value="2026">2026</option>')
        ->assertSeeHtml('<option value="2030">2030</option>')
        ->assertSeeHtml('<option value="2031">2031</option>')
        ->assertDontSeeHtml('<option value="2025">2025</option>')
        ->assertDontSeeHtml('<option value="2027">2027</option>')
        ->assertDontSeeHtml('<option value="2019">2019</option>');
});

it('keeps the same calendar year options for every monthly report mode', function () {
    $this->travelTo('2026-09-14');

    AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

    AcademicYear::create([
        'year' => '2030/2031',
        'is_active' => true,
        'start_date' => '2030-07-01',
        'end_date' => '2031-06-30',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(SchoolDailyReport::class, ['activeTab' => 'monthly', 'reportMonth' => 9, 'reportYear' => 2026])
        ->assertSeeHtml('<option value="2030">2030</option>')
        ->assertSeeHtml('<option value="2031">2031</option>');

    foreach (['by_date', 'by_level', 'all_units'] as $mode) {
        $component->set('monthlyMode', $mode)
            ->assertSet('monthlyMode', $mode)
            ->assertSeeHtml('<option value="2030">2030</option>')
            ->assertSeeHtml('<option value="2031">2031</option>');
    }

    $component->set('reportYear', 2031)
        ->assertSet('reportYear', 2031);
});

it('preserves a selected year that is still valid instead of resetting it', function () {
    $this->travelTo('2026-09-14');

    AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

    AcademicYear::create([
        'year' => '2030/2031',
        'is_active' => true,
        'start_date' => '2030-07-01',
        'end_date' => '2031-06-30',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(SchoolDailyReport::class, ['activeTab' => 'monthly', 'reportMonth' => 9, 'reportYear' => 2031])
        ->assertSet('reportYear', 2031)
        ->assertSeeHtml('<option value="2031">2031</option>');
});

it('defaults to the current calendar year when no year is provided', function () {
    $this->travelTo('2026-09-14');

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(SchoolDailyReport::class, ['activeTab' => 'monthly', 'reportMonth' => 9])
        ->assertSet('reportYear', 2026)
        ->assertSeeHtml('<option value="2026">2026</option>');
});

it('leaves the monthly report calculation untouched while the year filter becomes dynamic', function () {
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
