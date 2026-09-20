<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentRate;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\StudentTargetArrearsReportService;
use App\Services\StudentTargetArrearsReportSpreadsheet;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function createTargetReportAllocation(
    StudentBill $bill,
    User $user,
    Bank $bank,
    float $amount,
    string $paymentDate,
    string $recordedAt,
    string $status = Payment::STATUS_ACTIVE,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-TARGET-'.uniqid(),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $recordedAt, 'updated_at' => $recordedAt])->saveQuietly();

    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);

    return $payment;
}

it('uses persisted monthly bills and active allocations regardless of payment timing', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $spp = makeBillType('SPP Target');
    $septemberBill = makeMonthlyBill($student, $spp, 500_000, 9, 2026);
    makeMonthlyBill($student, $spp, 600_000, 10, 2026);

    createTargetReportAllocation(
        $septemberBill,
        $user,
        $bank,
        300_000,
        '2026-10-15',
        '2026-10-15 10:00:00',
    );

    PaymentRate::factory()->create([
        'payment_type_id' => $spp->id,
        'class_level' => 8,
        'amount' => 550_000,
        'billing_frequency' => BillFrequency::Monthly,
        'effective_from' => '2026-10-01',
    ]);

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
    );

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0]['target'])->toBe(500_000.0)
        ->and($report['rows'][0]['paid'])->toBe(300_000.0)
        ->and($report['rows'][0]['outstanding'])->toBe(200_000.0)
        ->and($report['rows'][0]['achievement_percentage'])->toBe(60.0)
        ->and($report['totals']['target'])->toBe(500_000.0)
        ->and($report['totals']['paid'])->toBe(300_000.0)
        ->and($report['totals']['outstanding'])->toBe(200_000.0)
        ->and($report['period_label'])->toBe('September 2026');
});

it('separates yearly and one-time bills by frequency rather than payment type name', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SD);
    $laboratory = makeBillType('Laboratorium');
    $annual = makeBillType('Uang Pangkal');
    makeOneTimeBill($student, $laboratory, 1_200_000);
    makeYearlyBill($student, $annual, 700_000);

    $yearly = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_YEARLY,
        9,
        2026,
        '2026/2027',
    );
    $oneTime = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_ONE_TIME,
        9,
        2026,
        '2026/2027',
    );

    expect(collect($yearly['rows'])->pluck('payment_type_name')->all())->toBe(['Uang Pangkal'])
        ->and($yearly['totals']['target'])->toBe(700_000.0)
        ->and(collect($oneTime['rows'])->pluck('payment_type_name')->all())->toBe(['Laboratorium'])
        ->and($oneTime['totals']['target'])->toBe(1_200_000.0);
});

it('excludes cancelled allocations and reconciles outstanding detail to each payment type', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    [$studentA] = makeEnrolledStudent(SchoolLevel::SMP);
    [$studentB] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeBillType('SPP Detail Target');
    $billA = makeMonthlyBill($studentA, $type, 500_000, 9, 2026);
    $billB = makeMonthlyBill($studentB, $type, 500_000, 9, 2026);
    createTargetReportAllocation($billA, $user, $bank, 500_000, '2026-09-10', '2026-09-10 09:00:00');
    createTargetReportAllocation($billB, $user, $bank, 200_000, '2026-09-11', '2026-09-11 09:00:00');
    createTargetReportAllocation($billB, $user, $bank, 300_000, '2026-09-12', '2026-09-12 09:00:00', Payment::STATUS_CANCELLED);

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
    );
    $row = $report['rows'][0];

    expect($row['target'])->toBe(1_000_000.0)
        ->and($row['paid'])->toBe(700_000.0)
        ->and($row['outstanding'])->toBe(300_000.0)
        ->and($row['achievement_percentage'])->toBe(70.0)
        ->and($row['details'])->toHaveCount(1)
        ->and($row['details'][0]['bill_id'])->toBe($billB->id)
        ->and($row['details'][0]['target'])->toBe(500_000.0)
        ->and($row['details'][0]['paid'])->toBe(200_000.0)
        ->and($row['details'][0]['outstanding'])->toBe(300_000.0)
        ->and(collect($row['details'])->sum('outstanding'))->toBe($row['outstanding']);
});

it('filters bills using the historical enrollment for the billing academic year', function () {
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD, '2025/2026', '2025-07-01', '2026-06-30');
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027', '2026-07-01', '2027-06-30');
    $type = makeBillType('SPP Jenjang Target');
    makeMonthlyBill($sdStudent, $type, 400_000, 1, 2026);
    makeMonthlyBill($smpStudent, $type, 600_000, 9, 2026);

    $sdJanuary = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        1,
        2026,
        '2025/2026',
        SchoolLevel::SD,
    );
    $smpSeptember = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
        SchoolLevel::SMP,
    );
    $wrongLevel = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        1,
        2026,
        '2025/2026',
        SchoolLevel::SMP,
    );

    expect($sdJanuary['totals']['target'])->toBe(400_000.0)
        ->and($smpSeptember['totals']['target'])->toBe(600_000.0)
        ->and($wrongLevel['totals']['target'])->toBe(0)
        ->and($wrongLevel['rows'])->toBe([]);
});

it('caps recognized paid allocations at the bill target and never reports negative arrears', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SMA);
    $type = makeBillType('Tagihan Lebih Bayar');
    $bill = makeMonthlyBill($student, $type, 500_000, 9, 2026);
    createTargetReportAllocation($bill, $user, $bank, 600_000, '2026-09-10', '2026-09-10 09:00:00');

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
    );

    expect($report['totals']['target'])->toBe(500_000.0)
        ->and($report['totals']['paid'])->toBe(500_000.0)
        ->and($report['totals']['outstanding'])->toBe(0.0)
        ->and($report['totals']['achievement_percentage'])->toBe(100.0);
});

it('uses canonical effective bill amounts and reconciles payment type totals', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::TK);
    $spp = makeBillType('SPP Penyesuaian Target');
    $activity = makeBillType('Kegiatan Penyesuaian Target');
    $sppBill = makeMonthlyBill($student, $spp, 500_000, 9, 2026);
    makeMonthlyBill($student, $activity, 300_000, 9, 2026);
    BillAdjustment::query()->create([
        'bill_id' => $sppBill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -100_000,
        'reason' => 'Potongan resmi',
        'created_by' => $user->id,
    ]);

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
    );
    $rows = collect($report['rows'])->keyBy('payment_type_name');

    expect($rows['SPP Penyesuaian Target']['target'])->toBe(400_000.0)
        ->and($rows['Kegiatan Penyesuaian Target']['target'])->toBe(300_000.0)
        ->and($rows->sum('target'))->toBe($report['totals']['target'])
        ->and($rows->sum('paid'))->toBe($report['totals']['paid'])
        ->and($rows->sum('outstanding'))->toBe($report['totals']['outstanding'])
        ->and($report['totals']['target'])->toBe(700_000.0)
        ->and($report['totals']['outstanding'])->toBe(700_000.0);
});

it('aggregates all modes from the three canonical category reports', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $monthlyType = makeBillType('SPP Semua Target');
    $yearlyType = makeBillType('Buku Semua Target');
    $oneTimeType = makeBillType('Pangkal Semua Target');
    $monthlyBill = makeMonthlyBill($student, $monthlyType, 500_000, 9, 2026);
    makeMonthlyBill($student, $monthlyType, 900_000, 10, 2026);
    $yearlyBill = makeYearlyBill($student, $yearlyType, 700_000, '2026/2027');
    makeYearlyBill($student, $yearlyType, 800_000, '2025/2026');
    makeOneTimeBill($student, $oneTimeType, 1_200_000, '2026/2027');
    createTargetReportAllocation($monthlyBill, $user, $bank, 200_000, '2026-10-05', '2026-10-05 08:00:00');
    createTargetReportAllocation($yearlyBill, $user, $bank, 300_000, '2026-09-10', '2026-09-10 08:00:00');

    $daycarePayment = DaycarePayment::factory()->create([
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-10',
        'total_amount' => 900_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycarePayment->id,
        'description' => 'Daycare tidak masuk target',
        'amount' => 900_000,
    ]);

    $service = app(StudentTargetArrearsReportService::class);
    $monthly = $service->generate(StudentTargetArrearsReportService::MODE_MONTHLY, 9, 2026, '2026/2027');
    $yearly = $service->generate(StudentTargetArrearsReportService::MODE_YEARLY, 9, 2026, '2026/2027');
    $oneTime = $service->generate(StudentTargetArrearsReportService::MODE_ONE_TIME, 9, 2026, '2026/2027');
    $all = $service->generate(StudentTargetArrearsReportService::MODE_ALL, 9, 2026, '2026/2027');

    expect(StudentTargetArrearsReportService::modes())->toContain(StudentTargetArrearsReportService::MODE_ALL)
        ->and($all['mode'])->toBe('all')
        ->and($all['mode_label'])->toBe('Semua')
        ->and($all['period_label'])->toBe('Bulanan: September 2026 • Tahunan/Sekali Bayar: Tahun Ajaran 2026/2027')
        ->and($all['sections']['monthly'])->toBe($monthly)
        ->and($all['sections']['yearly'])->toBe($yearly)
        ->and($all['sections']['one_time'])->toBe($oneTime)
        ->and($all['bill_count'])->toBe(3)
        ->and($all['totals'])->toMatchArray([
            'target' => 2_400_000.0,
            'paid' => 500_000.0,
            'outstanding' => 1_900_000.0,
            'achievement_percentage' => 20.8,
            'achievement_label' => '20,8%',
        ])
        ->and($all['totals']['target'])->toBe(array_sum(array_column(array_column($all['sections'], 'totals'), 'target')))
        ->and($all['totals']['paid'])->toBe(array_sum(array_column(array_column($all['sections'], 'totals'), 'paid')))
        ->and($all['totals']['outstanding'])->toBe(array_sum(array_column(array_column($all['sections'], 'totals'), 'outstanding')));
});

it('renders and exports all target modes as three category sections', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    makeMonthlyBill($student, makeBillType('Bulanan Semua Web'), 500_000, 9, 2026);
    makeYearlyBill($student, makeBillType('Tahunan Semua Web'), 700_000, '2026/2027');
    makeOneTimeBill($student, makeBillType('Sekali Semua Web'), 1_200_000, '2026/2027');
    $this->actingAs($user);

    Livewire::test(SchoolDailyReport::class, [
        'activeTab' => 'target',
        'targetMonth' => 9,
        'targetYear' => 2026,
        'targetAcademicYear' => '2026/2027',
    ])
        ->assertSee('Semua')
        ->call('setTargetMode', StudentTargetArrearsReportService::MODE_ALL)
        ->assertSet('targetMode', StudentTargetArrearsReportService::MODE_ALL)
        ->assertSee('TAGIHAN BULANAN')
        ->assertSee('TAGIHAN TAHUNAN')
        ->assertSee('TAGIHAN SEKALI BAYAR')
        ->assertSee('Bulanan Semua Web')
        ->assertSee('Tahunan Semua Web')
        ->assertSee('Sekali Semua Web')
        ->assertSee('Rp 2.400.000')
        ->assertSee('target-month')
        ->assertSee('target-year')
        ->assertSee('target-academic-year');

    $all = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_ALL,
        9,
        2026,
        '2026/2027',
    );
    $path = app(StudentTargetArrearsReportSpreadsheet::class)->create($all);
    $reader = new Reader;

    try {
        $reader->open($path);
        $summaryRows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Target dan Tunggakan') {
                $summaryRows = collect(iterator_to_array($sheet->getRowIterator()))
                    ->map(fn ($row): array => $row->toArray())
                    ->all();
            }
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    expect(collect($summaryRows)->first(fn (array $row): bool => ($row[0] ?? null) === 'GRAND TOTAL')[1] ?? null)
        ->toBe(2_400_000)
        ->and(collect($summaryRows)->pluck(0)->filter()->values()->all())
        ->toContain('TAGIHAN BULANAN', 'TAGIHAN TAHUNAN', 'TAGIHAN SEKALI BAYAR');

    foreach (['laporan.target.export', 'laporan.target.pdf'] as $routeName) {
        $this->get(route($routeName, [
            'mode' => 'all',
            'month' => 9,
            'year' => 2026,
            'academic_year' => '2026/2027',
            'school_level' => 'all',
        ]))->assertOk();
    }
});

it('renders the target tab with canonical totals and outstanding student detail', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    [$student, $schoolClass] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['nama_lengkap' => 'Ahmad Target']);
    $type = makeBillType('SPP Tampilan Target');
    $bill = makeMonthlyBill($student, $type, 500_000, 9, 2026);
    createTargetReportAllocation($bill, $user, $bank, 300_000, '2026-10-04', '2026-10-04 09:00:00');

    $this->actingAs($user);

    Livewire::test(SchoolDailyReport::class, [
        'activeTab' => 'target',
        'targetMonth' => 9,
        'targetYear' => 2026,
        'targetAcademicYear' => '2026/2027',
    ])
        ->assertSee('Target & Tunggakan')
        ->assertSee('Pantau target, pembayaran, dan sisa tagihan siswa.')
        ->assertSee('Bulanan')
        ->assertSee('Tahunan')
        ->assertSee('Sekali Bayar')
        ->assertSee('Semua')
        ->assertSee('TOTAL TARGET')
        ->assertSee('Rp 500.000')
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 200.000')
        ->assertSee('60%')
        ->assertSee('SPP Tampilan Target')
        ->assertSee('Ahmad Target')
        ->assertSee($schoolClass->name)
        ->assertSee('target-tunggakan.xlsx')
        ->assertSee('target-tunggakan.pdf');
});

it('exports the same canonical target totals to xlsx and pdf', function () {
    $user = User::factory()->create();
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeBillType('SPP Export Target');
    makeMonthlyBill($student, $type, 750_000, 9, 2026);

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
    );
    $path = app(StudentTargetArrearsReportSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Target dan Tunggakan') {
                $rows = collect(iterator_to_array($sheet->getRowIterator()))
                    ->map(fn ($row): array => $row->toArray())
                    ->all();
            }
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $totalRow = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'TOTAL');

    expect($totalRow[1])->toBe(750_000)
        ->and($totalRow[2])->toBe(0)
        ->and($totalRow[3])->toBe(750_000)
        ->and($totalRow[4])->toBe(0);

    $this->actingAs($user)
        ->get(route('laporan.target.export', [
            'mode' => 'monthly', 'month' => 9, 'year' => 2026, 'academic_year' => '2026/2027', 'school_level' => 'all',
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAs($user)
        ->get(route('laporan.target.pdf', [
            'mode' => 'monthly', 'month' => 9, 'year' => 2026, 'academic_year' => '2026/2027', 'school_level' => 'all',
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('protects and validates target report exports', function () {
    $this->get(route('laporan.target.export', [
        'mode' => 'monthly', 'month' => 9, 'year' => 2026,
    ]))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.target.pdf', [
            'mode' => 'invalid', 'month' => 13, 'year' => 2026,
        ]))
        ->assertSessionHasErrors(['mode', 'month']);

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.target.export', [
            'mode' => 'all', 'month' => 9, 'year' => 2026,
        ]))
        ->assertSessionHasErrors('academic_year');
});

it('excludes daycare payments from student targets and arrears', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->cash()->create();
    $daycarePayment = DaycarePayment::factory()->create([
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-10',
        'total_amount' => 900_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycarePayment->id,
        'description' => 'Daycare September',
        'amount' => 900_000,
    ]);

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
    );

    expect($report['rows'])->toBe([])
        ->and($report['totals']['target'])->toBe(0)
        ->and($report['totals']['paid'])->toBe(0)
        ->and($report['totals']['outstanding'])->toBe(0);
});
