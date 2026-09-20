<?php

use App\Enums\SchoolLevel;
use App\Livewire\DaycareDailyReport;
use App\Livewire\SchoolDailyReport;
use App\Models\Bank;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

function createReactiveBankPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $paymentDate,
    int $amount,
): void {
    createBankRecapPayment($student, $bank, $user, $paymentDate, $paymentDate.' 09:00:00', $amount);
}

it('does not render the tampilkan button on any school report tab', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, ['activeTab' => 'daily'])
        ->assertDontSee('Tampilkan')
        ->call('setActiveTab', 'monthly')
        ->assertDontSee('Tampilkan')
        ->call('setActiveTab', 'bank')
        ->assertDontSee('Tampilkan')
        ->call('setActiveTab', 'target')
        ->assertDontSee('Tampilkan')
        ->call('setActiveTab', 'class')
        ->assertDontSee('Tampilkan');
});

it('keeps the daily report fields reactive without a submit button', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Reaktif Harian');

    createSchoolDailyReportPayment($student, $cash, $user, '2026-08-26', [
        ['payment_type_id' => $spp->id, 'amount' => 100_000],
    ]);
    createSchoolDailyReportPayment($student, $cash, $user, '2026-08-28', [
        ['payment_type_id' => $spp->id, 'amount' => 200_000],
    ]);

    $component = Livewire::actingAs($user)->test(SchoolDailyReport::class, [
        'reportStartDate' => '2026-08-26',
        'reportEndDate' => '2026-08-28',
    ]);

    expect($component->viewData('report')['grand_total'])->toBe(300_000.0);

    $component->set('reportStartDate', '2026-08-28');

    expect($component->viewData('report')['grand_total'])->toBe(200_000.0);

    $component->set('reportEndDate', '2026-08-26');
    expect($component->viewData('report')['grand_total'])->toBe(200_000.0);

    $component->set('reportStartDate', '2026-08-26');
    $component->set('reportEndDate', '2026-08-26');
    expect($component->viewData('report')['grand_total'])->toBe(100_000.0);
});

it('keeps the bank recap filters reactive without a submit button', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    createReactiveBankPayment(Student::factory()->create(), $bank, $user, '2026-09-01', 100_000);
    createReactiveBankPayment(Student::factory()->create(), $bank, $user, '2026-09-05', 200_000);

    $component = Livewire::actingAs($user)->test(SchoolDailyReport::class, [
        'activeTab' => 'bank',
        'bankStartDate' => '2026-09-01',
        'bankEndDate' => '2026-09-05',
    ]);

    expect($component->viewData('bankReport')['grand_total'])->toBe(300_000.0);

    $component->set('bankStartDate', '2026-09-05');
    expect($component->viewData('bankReport')['grand_total'])->toBe(200_000.0);

    $component->set('bankStartDate', '2026-09-01');
    $component->set('bankEndDate', '2026-09-01');
    expect($component->viewData('bankReport')['grand_total'])->toBe(100_000.0);
});

it('keeps the monthly report filters reactive without a submit button', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Reaktif Bulanan');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-17', [
        ['payment_type_id' => $spp->id, 'amount' => 500_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-05', [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ]);

    $component = Livewire::actingAs($user)->test(SchoolDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 8,
        'reportYear' => 2026,
    ]);

    expect($component->viewData('monthlyReport')['grand_total'])->toBe(500_000.0);

    $component->set('reportMonth', 9);
    expect($component->viewData('monthlyReport')['grand_total'])->toBe(300_000.0);

    $component->set('reportYear', 2027);
    expect($component->viewData('monthlyReport')['grand_total'])->toBeIn([0, 0.0]);
});

it('keeps the target of tunggakan filters reactive without a submit button', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $spp = makeBillType('SPP Reaktif Target');
    makeMonthlyBill($student, $spp, 500_000, 9, 2026);
    makeMonthlyBill($student, $spp, 700_000, 10, 2026);

    $component = Livewire::actingAs($user)->test(SchoolDailyReport::class, [
        'activeTab' => 'target',
        'targetMonth' => 9,
        'targetYear' => 2026,
        'targetAcademicYear' => '2026/2027',
    ]);

    expect($component->viewData('targetReport')['totals']['target'])->toBe(500_000.0);

    $component->set('targetMonth', 10);
    expect($component->viewData('targetReport')['totals']['target'])->toBe(700_000.0);

    $component->set('targetMonth', 9);
    $component->set('targetYear', 2027);
    expect($component->viewData('targetReport')['totals']['target'])->toBeIn([0, 0.0]);
});

it('keeps the target jenjang filter reactive without a submit button', function () {
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    $spp = makeBillType('SPP Reaktif Jenjang');
    makeMonthlyBill($sdStudent, $spp, 400_000, 9, 2026);
    makeMonthlyBill($smpStudent, $spp, 600_000, 9, 2026);

    $component = Livewire::actingAs(User::factory()->create())->test(SchoolDailyReport::class, [
        'activeTab' => 'target',
        'targetMonth' => 9,
        'targetYear' => 2026,
        'targetAcademicYear' => '2026/2027',
        'schoolLevel' => 'all',
    ]);

    expect($component->viewData('targetReport')['totals']['target'])->toBe(1_000_000.0);

    $component->set('schoolLevel', SchoolLevel::SD->value);
    expect($component->viewData('targetReport')['totals']['target'])->toBe(400_000.0);

    $component->set('schoolLevel', SchoolLevel::SMP->value);
    expect($component->viewData('targetReport')['totals']['target'])->toBe(600_000.0);
});

it('keeps the daily and bank export links in sync with the live filter state', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Export Reaktif');
    createSchoolDailyReportPayment(Student::factory()->create(), $cash, $user, '2026-08-28', [
        ['payment_type_id' => $spp->id, 'amount' => 200_000],
    ]);

    $component = Livewire::actingAs($user)->test(SchoolDailyReport::class, [
        'reportStartDate' => '2026-08-26',
        'reportEndDate' => '2026-08-28',
    ]);

    $component->set('reportStartDate', '2026-08-28');

    $component
        ->assertSee(route('laporan.harian.export', [
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-28',
            'school_level' => 'all',
        ]))
        ->assertSee(route('laporan.harian.pdf', [
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-28',
            'school_level' => 'all',
        ]));

    $component->call('setActiveTab', 'bank')
        ->set('bankStartDate', '2026-09-01')
        ->set('bankEndDate', '2026-09-05')
        ->assertSee(route('laporan.bank.export', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'bank' => 'all',
        ]))
        ->assertSee(route('laporan.bank.pdf', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'bank' => 'all',
        ]));
});

it('keeps the monthly and target export links in sync with the live filter state', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Export Bulanan');
    createSchoolMonthlyReportPayment(Student::factory()->create(), $cash, $user, '2026-09-05', [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ]);
    [$targetStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    makeMonthlyBill($targetStudent, $spp, 500_000, 10, 2026);

    $component = Livewire::actingAs($user)->test(SchoolDailyReport::class, [
        'activeTab' => 'monthly',
        'reportMonth' => 8,
        'reportYear' => 2026,
        'targetMonth' => 9,
        'targetYear' => 2026,
        'targetAcademicYear' => '2026/2027',
    ]);

    $component->set('reportMonth', 9)
        ->assertSee(route('laporan.bulanan.export', [
            'month' => 9,
            'year' => 2026,
            'school_level' => 'all',
        ]))
        ->assertSee(route('laporan.bulanan.pdf', [
            'month' => 9,
            'year' => 2026,
            'school_level' => 'all',
        ]));

    $component->call('setActiveTab', 'target')
        ->set('targetMonth', 10)
        ->assertSee(route('laporan.target.export', [
            'mode' => 'monthly',
            'month' => 10,
            'year' => 2026,
            'academic_year' => '2026/2027',
            'school_level' => 'all',
        ]))
        ->assertSee(route('laporan.target.pdf', [
            'mode' => 'monthly',
            'month' => 10,
            'year' => 2026,
            'academic_year' => '2026/2027',
            'school_level' => 'all',
        ]));
});

it('does not render the tampilkan button on daycare report tabs', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(DaycareDailyReport::class, ['reportDate' => '2026-08-27'])
        ->assertDontSee('Tampilkan')
        ->call('setActiveTab', 'monthly')
        ->assertDontSee('Tampilkan');
});

it('keeps the daycare report filters reactive and export links in sync', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Daycare Reaktif');

    $component = Livewire::actingAs($user)->test(DaycareDailyReport::class, [
        'reportDate' => '2026-08-27',
        'reportStartDate' => '2026-08-27',
        'reportEndDate' => '2026-08-28',
    ]);

    $component->set('reportStartDate', '2026-08-28')
        ->set('reportEndDate', '2026-08-28')
        ->assertSee(route('daycare.report.daily.export', [
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-28',
        ]))
        ->assertSee(route('daycare.report.daily.pdf', [
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-28',
        ]));

    $component->call('setActiveTab', 'monthly')
        ->set('reportMonth', 7)
        ->set('reportYear', 2026)
        ->assertSee(route('daycare.report.monthly.export', [
            'month' => 7,
            'year' => 2026,
        ]))
        ->assertSee(route('daycare.report.monthly.pdf', [
            'month' => 7,
            'year' => 2026,
        ]));
});
