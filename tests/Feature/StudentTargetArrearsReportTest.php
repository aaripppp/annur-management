<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentRate;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\StudentTargetArrearsReportService;
use App\Services\StudentTargetArrearsReportSpreadsheet;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function createTargetReportAcademicYear(string $year, bool $isActive = true): AcademicYear
{
    $startYear = (int) explode('/', $year, 2)[0];

    return AcademicYear::query()->firstOrCreate(
        ['year' => $year],
        [
            'start_date' => $startYear.'-07-01',
            'end_date' => ($startYear + 1).'-06-30',
            'is_active' => $isActive,
        ],
    );
}

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

/**
 * Kelas pada level tertentu.
 *
 * Nama tidak boleh diteruskan dari test: SchoolClassFactory::newModel()
 * selalu menurunkan ulang nama dari level dan huruf section acak, sehingga
 * nilai nama harus dibaca dari model, bukan diasumsikan.
 */
function createTargetReportClass(int $level): SchoolClass
{
    return SchoolClass::factory()->create(['level' => $level]);
}

/**
 * Siswa dengan enrollment pada kelas tertentu di tahun ajaran tertentu.
 *
 * Memakai kelas yang sama untuk students.class_id dan enrollment supaya test
 * tidak lulus karena alasan tak sengaja. Test historis sengaja memakai kelas
 * "saat ini" yang berbeda agar terlihat bahwa filter mengikuti enrollment.
 */
function createTargetReportClassStudent(
    SchoolClass $schoolClass,
    string $year,
    string $studentName,
    ?SchoolClass $currentClass = null,
): Student {
    $academicYear = createTargetReportAcademicYear($year);
    $student = Student::factory()->create([
        'nama_lengkap' => $studentName,
        'class_id' => ($currentClass ?? $schoolClass)->id,
    ]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return $student;
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
    createTargetReportAcademicYear('2026/2027');
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
        ->assertSee('September 2026')
        ->assertSeeHtml('id="target-month"')
        ->assertDontSeeHtml('id="target-year"')
        ->assertSeeHtml('id="target-academic-year"');

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
    createTargetReportAcademicYear('2026/2027');

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
    $document = [
        'unit' => $report['unit_name'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE, MM",
            'reviewer_title' => 'Kepala TU Yayasan',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 30 September 2026',
            'report_creator_title' => 'TU SD IT An-Nur',
            'report_creator_name' => 'Petugas Target Dengan Nama Panjang',
        ],
    ];
    $pdfHtml = view('reports.student-target-arrears-pdf', compact('report', 'document'))->render();
    $signatureCellCounts = collect(['heading', 'spacer', 'name'])->map(function (string $row) use ($pdfHtml): int {
        preg_match('/<tr class="signature-'.$row.'-row">(.*?)<\/tr>/s', $pdfHtml, $matches);

        return substr_count($matches[1] ?? '', '<td');
    })->all();

    expect($pdfHtml)
        ->toContain('Bekasi, 30 September 2026', 'TU SD IT An-Nur', 'Petugas Target Dengan Nama Panjang')
        ->not->toContain('class="signature-space"')
        ->and($signatureCellCounts)->toBe([3, 3, 3]);

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

it('defaults the target period to the active academic year and current month', function () {
    $this->travelTo('2026-09-15 12:00:00');
    createTargetReportAcademicYear('2026/2027');
    createTargetReportAcademicYear('2027/2028', false);

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, ['activeTab' => 'target'])
        ->assertSet('targetAcademicYear', '2026/2027')
        ->assertSet('targetMonth', 9)
        ->assertSet('targetYear', 2026);
});

it('derives the target year from the academic year and month boundaries', function () {
    createTargetReportAcademicYear('2026/2027');
    createTargetReportAcademicYear('2027/2028', false);

    $component = Livewire::actingAs(User::factory()->create())->test(SchoolDailyReport::class, [
        'activeTab' => 'target',
        'targetAcademicYear' => '2026/2027',
        'targetMonth' => 1,
    ]);

    foreach (array_merge(range(1, 6), range(7, 12)) as $month) {
        $component->set('targetMonth', $month)
            ->assertSet('targetYear', $month >= 7 ? 2026 : 2027);
    }

    $component->set('targetAcademicYear', '2027/2028')
        ->assertSet('targetMonth', 12)
        ->assertSet('targetYear', 2027);
});

it('builds target month options from the selected academic year', function () {
    createTargetReportAcademicYear('2026/2027');
    createTargetReportAcademicYear('2027/2028', false);

    $component = Livewire::actingAs(User::factory()->create())->test(SchoolDailyReport::class, [
        'activeTab' => 'target',
        'targetAcademicYear' => '2026/2027',
        'targetMonth' => 9,
    ]);

    expect($component->viewData('targetMonthOptions'))->toBe([
        ['value' => 7, 'label' => 'Juli 2026'],
        ['value' => 8, 'label' => 'Agustus 2026'],
        ['value' => 9, 'label' => 'September 2026'],
        ['value' => 10, 'label' => 'Oktober 2026'],
        ['value' => 11, 'label' => 'November 2026'],
        ['value' => 12, 'label' => 'Desember 2026'],
        ['value' => 1, 'label' => 'Januari 2027'],
        ['value' => 2, 'label' => 'Februari 2027'],
        ['value' => 3, 'label' => 'Maret 2027'],
        ['value' => 4, 'label' => 'April 2027'],
        ['value' => 5, 'label' => 'Mei 2027'],
        ['value' => 6, 'label' => 'Juni 2027'],
    ]);

    $component->set('targetAcademicYear', '2027/2028');

    expect($component->viewData('targetMonthOptions'))->toBe([
        ['value' => 7, 'label' => 'Juli 2027'],
        ['value' => 8, 'label' => 'Agustus 2027'],
        ['value' => 9, 'label' => 'September 2027'],
        ['value' => 10, 'label' => 'Oktober 2027'],
        ['value' => 11, 'label' => 'November 2027'],
        ['value' => 12, 'label' => 'Desember 2027'],
        ['value' => 1, 'label' => 'Januari 2028'],
        ['value' => 2, 'label' => 'Februari 2028'],
        ['value' => 3, 'label' => 'Maret 2028'],
        ['value' => 4, 'label' => 'April 2028'],
        ['value' => 5, 'label' => 'Mei 2028'],
        ['value' => 6, 'label' => 'Juni 2028'],
    ]);
});

it('shows the academic year in every target mode and the month only when needed', function () {
    createTargetReportAcademicYear('2026/2027');

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, ['activeTab' => 'target'])
        ->assertSeeHtml('id="target-academic-year"')
        ->assertSeeHtml('id="target-month"')
        ->assertDontSeeHtml('id="target-year"')
        ->call('setTargetMode', 'yearly')
        ->assertSeeHtml('id="target-academic-year"')
        ->assertDontSeeHtml('id="target-month"')
        ->call('setTargetMode', 'one_time')
        ->assertSeeHtml('id="target-academic-year"')
        ->assertDontSeeHtml('id="target-month"')
        ->call('setTargetMode', 'all')
        ->assertSeeHtml('id="target-academic-year"')
        ->assertSeeHtml('id="target-month"');
});

it('normalizes conflicting legacy target period query values before rendering', function () {
    createTargetReportAcademicYear('2026/2027');
    $this->actingAs(User::factory()->create());

    $component = Livewire::withQueryParams([
        'target_month' => 2,
        'target_year' => 2026,
        'target_academic_year' => '2026/2027',
    ])->test(SchoolDailyReport::class, [
        'activeTab' => 'target',
        'targetMode' => 'monthly',
    ]);

    $component
        ->assertSet('targetMonth', 2)
        ->assertSet('targetAcademicYear', '2026/2027')
        ->assertSet('targetYear', 2027)
        ->assertSee('Februari 2027')
        ->assertSee(route('laporan.target.export', [
            'mode' => 'monthly',
            'month' => 2,
            'year' => 2027,
            'academic_year' => '2026/2027',
            'school_level' => 'all',
        ]))
        ->assertSee(route('laporan.target.pdf', [
            'mode' => 'monthly',
            'month' => 2,
            'year' => 2027,
            'academic_year' => '2026/2027',
            'school_level' => 'all',
        ]));
});

/*
|--------------------------------------------------------------------------
| Filter kelas pada Target & Tunggakan
|--------------------------------------------------------------------------
*/

it('shows only the selected class within one school level', function () {
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    $classB = createTargetReportClass(7);
    $type = makeBillType('SPP Filter Kelas');
    $studentA = createTargetReportClassStudent($classA, '2026/2027', 'Rina VII A');
    $studentB = createTargetReportClassStudent($classB, '2026/2027', 'Budi VII B');
    makeMonthlyBill($studentA, $type, 500_000, 9, 2026);
    makeMonthlyBill($studentB, $type, 700_000, 9, 2026);

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
        SchoolLevel::SMP,
        $classA->id,
    );

    expect($report['totals']['target'])->toBe(500_000.0)
        ->and($report['rows'][0]['details'])->toHaveCount(1)
        ->and($report['rows'][0]['details'][0]['student_name'])->toBe('Rina VII A')
        ->and($report['rows'][0]['details'][0]['class_name'])->toBe($classA->name);
});

it('applies school level and class filters together', function () {
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    // Level 8 masih SMP (7-9), jadi jenjang lain yang benar dipakai SMA (10-12).
    $otherLevel = createTargetReportClass(10);
    $type = makeBillType('SPP Level dan Kelas');
    $studentA = createTargetReportClassStudent($classA, '2026/2027', 'Rina VII A');
    $studentOther = createTargetReportClassStudent($otherLevel, '2026/2027', 'Budi X-A');
    makeMonthlyBill($studentA, $type, 500_000, 9, 2026);
    makeMonthlyBill($studentOther, $type, 900_000, 9, 2026);

    $service = app(StudentTargetArrearsReportService::class);

    $withBoth = $service->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
        SchoolLevel::SMP,
        $classA->id,
    );
    $levelOnly = $service->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
        SchoolLevel::SMP,
    );

    expect($withBoth['totals']['target'])->toBe(500_000.0)
        ->and($levelOnly['totals']['target'])->toBe(500_000.0)
        ->and($service->generate(
            StudentTargetArrearsReportService::MODE_MONTHLY,
            9,
            2026,
            '2026/2027',
            null,
        )['totals']['target'])->toBe(1_400_000.0);
});

it('applies the class filter to every target mode', function () {
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    $classB = createTargetReportClass(7);
    $type = makeBillType('SPP Semua Mode');
    $studentA = createTargetReportClassStudent($classA, '2026/2027', 'Rina VII A');
    $studentB = createTargetReportClassStudent($classB, '2026/2027', 'Budi VII B');
    makeMonthlyBill($studentA, $type, 500_000, 9, 2026);
    makeMonthlyBill($studentB, $type, 700_000, 9, 2026);
    makeYearlyBill($studentA, $type, 6_000_000);
    makeYearlyBill($studentB, $type, 7_000_000);
    makeOneTimeBill($studentA, $type, 2_000_000);
    makeOneTimeBill($studentB, $type, 3_000_000);

    $service = app(StudentTargetArrearsReportService::class);
    $expected = [
        StudentTargetArrearsReportService::MODE_MONTHLY => 500_000.0,
        StudentTargetArrearsReportService::MODE_YEARLY => 6_000_000.0,
        StudentTargetArrearsReportService::MODE_ONE_TIME => 2_000_000.0,
        StudentTargetArrearsReportService::MODE_ALL => 8_500_000.0,
    ];

    foreach ($expected as $mode => $target) {
        $report = $service->generate($mode, 9, 2026, '2026/2027', SchoolLevel::SMP, $classA->id);

        expect($report['totals']['target'])->toBe($target, "Mode {$mode} tidak terfilter kelas.");
    }
});

it('follows the historical enrollment class rather than the current student class', function () {
    createTargetReportAcademicYear('2025/2026');
    createTargetReportAcademicYear('2026/2027');
    $classSeven = createTargetReportClass(7);
    $classEight = createTargetReportClass(8);
    $type = makeBillType('SPP Kenaikan Kelas');

    // Siswa naik kelas: enrollment 2025/2026 di VII A, kelas saat ini VIII A.
    $student = createTargetReportClassStudent($classSeven, '2025/2026', 'Rina Naik Kelas', $classEight);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => createTargetReportAcademicYear('2026/2027')->id,
        'school_class_id' => $classEight->id,
        'status' => 'active',
    ]);
    makeMonthlyBill($student, $type, 500_000, 9, 2025);

    $service = app(StudentTargetArrearsReportService::class);

    // Tagihan Sep-2025 milik VII A walaupun kelas saat ini VIII A.
    $historical = $service->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2025,
        '2026/2027',
        SchoolLevel::SMP,
        $classSeven->id,
    );
    $currentClassScope = $service->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2025,
        '2026/2027',
        SchoolLevel::SMP,
        $classEight->id,
    );

    expect($historical['totals']['target'])->toBe(500_000.0)
        ->and($currentClassScope['totals']['target'])->toBe(0)
        ->and($currentClassScope['rows'])->toBe([]);
});

it('uses the period academic year for monthly mode when it differs from the selected one', function () {
    createTargetReportAcademicYear('2025/2026');
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    $type = makeBillType('SPP Periode Berbeda');
    // Enrollment sengaja pada 2025/2026, yaitu tahun periode tagihan, bukan
    // tahun ajaran terpilih.
    $student = createTargetReportClassStudent($classA, '2025/2026', 'Rina Periode');
    makeMonthlyBill($student, $type, 500_000, 1, 2026);

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        1,
        2026,
        '2026/2027',
        SchoolLevel::SMP,
        $classA->id,
    );

    // Jan-2026 berada pada 2025/2026, sedangkan filter Tahun Ajaran 2026/2027.
    expect($report['period_academic_year'])->toBe('2025/2026')
        ->and($report['totals']['target'])->toBe(500_000.0);
});

it('excludes students without an enrollment in the period academic year', function () {
    createTargetReportAcademicYear('2025/2026');
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    $type = makeBillType('SPP Tanpa Enrollment');
    $enrolled = createTargetReportClassStudent($classA, '2026/2027', 'Rina Terdaftar');

    // Siswa tanpa enrollment sama sekali di tahun periode.
    $orphan = Student::factory()->create(['nama_lengkap' => 'Budi Tanpa Enrollment']);
    makeMonthlyBill($enrolled, $type, 500_000, 9, 2026);
    makeMonthlyBill($orphan, $type, 800_000, 9, 2026);

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
        SchoolLevel::SMP,
        $classA->id,
    );

    expect($report['totals']['target'])->toBe(500_000.0)
        ->and($report['rows'][0]['details'])->toHaveCount(1)
        ->and($report['rows'][0]['details'][0]['student_name'])->toBe('Rina Terdaftar');
});

it('keeps the all-levels behaviour identical when no class is selected', function () {
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    $classEight = createTargetReportClass(8);
    $type = makeBillType('SPP Semua Jenjang');
    $studentA = createTargetReportClassStudent($classA, '2026/2027', 'Rina VII A');
    $studentEight = createTargetReportClassStudent($classEight, '2026/2027', 'Budi VIII A');
    makeMonthlyBill($studentA, $type, 500_000, 9, 2026);
    makeMonthlyBill($studentEight, $type, 900_000, 9, 2026);

    $service = app(StudentTargetArrearsReportService::class);
    $unrestricted = $service->generate(StudentTargetArrearsReportService::MODE_MONTHLY, 9, 2026, '2026/2027');
    $explicitAll = $service->generate(StudentTargetArrearsReportService::MODE_MONTHLY, 9, 2026, '2026/2027', null, null);

    expect($explicitAll['totals'])->toBe($unrestricted['totals'])
        ->and($explicitAll['totals']['target'])->toBe(1_400_000.0)
        ->and($explicitAll['bill_count'])->toBe(2);
});

it('scopes summary totals and the detail list to the same selected class', function () {
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    $classB = createTargetReportClass(7);
    $type = makeBillType('SPP Kartu Ringkas');
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['is_active' => true]);
    $studentA = createTargetReportClassStudent($classA, '2026/2027', 'Rina VII A');
    $studentB = createTargetReportClassStudent($classB, '2026/2027', 'Budi VII B');
    $billA = makeMonthlyBill($studentA, $type, 500_000, 9, 2026);
    $billB = makeMonthlyBill($studentB, $type, 700_000, 9, 2026);
    // Detail hanya memuat tagihan yang masih punya sisa tunggakan, jadi kelas A
    // dibayar sebagian dan kelas B (terfilter) dibayar penuh.
    createTargetReportAllocation($billA, $user, $bank, 200_000, '2026-09-10', '2026-09-10 09:00:00');
    createTargetReportAllocation($billB, $user, $bank, 700_000, '2026-09-10', '2026-09-10 09:00:00');

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
        SchoolLevel::SMP,
        $classA->id,
    );
    $details = $report['rows'][0]['details'];
    $detailTotal = array_sum(array_column($details, 'target'));
    $detailOutstanding = array_sum(array_column($details, 'outstanding'));

    expect($report['totals']['target'])->toBe(500_000.0)
        ->and($report['totals']['paid'])->toBe(200_000.0)
        ->and($report['totals']['outstanding'])->toBe(300_000.0)
        ->and($report['totals']['achievement_label'])->toBe('40%')
        ->and($details)->toHaveCount(1)
        ->and($detailTotal)->toBe($report['totals']['target'])
        ->and($detailOutstanding)->toBe($report['totals']['outstanding']);
});

it('exports the class scoped report through the pdf and excel controllers', function () {
    $user = User::factory()->create();
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    $classB = createTargetReportClass(7);
    $type = makeBillType('SPP Export Kelas');
    $studentA = createTargetReportClassStudent($classA, '2026/2027', 'Rina VII A');
    $studentB = createTargetReportClassStudent($classB, '2026/2027', 'Budi VII B');
    makeMonthlyBill($studentA, $type, 500_000, 9, 2026);
    makeMonthlyBill($studentB, $type, 700_000, 9, 2026);

    $scoped = $this->actingAs($user)
        ->get(route('laporan.target.export', [
            'mode' => 'monthly', 'month' => 9, 'year' => 2026, 'academic_year' => '2026/2027',
            'school_level' => 'SMP', 'class_id' => $classA->id,
        ]));

    $scoped->assertOk();

    // streamedContent() mengembalikan isi file, sedangkan Reader butuh path.
    $path = tempnam(sys_get_temp_dir(), 'target-kelas-').'.xlsx';
    file_put_contents($path, $scoped->streamedContent());
    $reader = new Reader;
    $rows = [];

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $totalRow = collect($rows['Target dan Tunggakan'])
        ->first(fn (array $row): bool => ($row[0] ?? null) === 'TOTAL');
    $detailNames = collect($rows['Detail Tunggakan'])
        ->pluck(1)
        ->reject(fn ($value): bool => $value === null || $value === '');

    // Ringkasan dan detail harus sama-sama terfilter kelas yang sama.
    expect($totalRow[1])->toBe(500_000)
        ->and($totalRow[2])->toBe(0)
        ->and($detailNames->contains('Rina VII A'))->toBeTrue()
        ->and($detailNames->contains('Budi VII B'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('laporan.target.pdf', [
            'mode' => 'monthly', 'month' => 9, 'year' => 2026, 'academic_year' => '2026/2027',
            'school_level' => 'SMP', 'class_id' => $classA->id,
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('ignores a class filter when the all-levels option is selected', function () {
    $user = User::factory()->create();
    createTargetReportAcademicYear('2026/2027');
    $classOther = createTargetReportClass(8);
    $type = makeBillType('SPP Semua Jenjang Kelas');
    $student = createTargetReportClassStudent($classOther, '2026/2027', 'Budi VIII A');
    makeMonthlyBill($student, $type, 900_000, 9, 2026);

    // Livewire sudah menormalkan kelas menjadi null saat jenjang "all", dan
    // service menerapkan invarian yang sama supaya URL yang disunting manual
    // tidak bisa memakai kelas bersama "Semua Jenjang".
    $response = $this->actingAs($user)
        ->get(route('laporan.target.export', [
            'mode' => 'monthly', 'month' => 9, 'year' => 2026, 'academic_year' => '2026/2027',
            'school_level' => 'all', 'class_id' => $classOther->id,
        ]));

    $response->assertOk();

    $report = app(StudentTargetArrearsReportService::class)->generate(
        StudentTargetArrearsReportService::MODE_MONTHLY,
        9,
        2026,
        '2026/2027',
        null,
        $classOther->id,
    );

    expect($report['totals']['target'])->toBe(900_000.0)
        ->and($report['rows'][0]['details'][0]['student_name'])->toBe('Budi VIII A');
});

it('rejects a class id that does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('laporan.target.export', [
            'mode' => 'monthly', 'month' => 9, 'year' => 2026, 'academic_year' => '2026/2027',
            'school_level' => 'SMP', 'class_id' => 987654,
        ]))
        ->assertSessionHasErrors('class_id');
});

it('resets the class when the school level changes', function () {
    createTargetReportAcademicYear('2026/2027');
    $classSeven = createTargetReportClass(7);

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'target',
            'schoolLevel' => 'SMP',
            'targetSchoolClassId' => (string) $classSeven->id,
        ])
        ->assertSet('targetSchoolClassId', (string) $classSeven->id)
        ->set('schoolLevel', 'SMA')
        ->assertSet('targetSchoolClassId', null)
        // Perubahan jenjang tidak boleh menyentuh filter periode.
        ->assertSet('targetAcademicYear', '2026/2027');
});

it('keeps the class when the month or academic year changes', function () {
    createTargetReportAcademicYear('2026/2027');
    $classSeven = createTargetReportClass(7);

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'target',
            'schoolLevel' => 'SMP',
            'targetSchoolClassId' => (string) $classSeven->id,
        ])
        ->set('targetMonth', 3)
        ->assertSet('targetSchoolClassId', (string) $classSeven->id)
        ->set('targetAcademicYear', '2026/2027')
        ->assertSet('targetSchoolClassId', (string) $classSeven->id);
});

it('offers only the classes belonging to the selected school level', function () {
    createTargetReportAcademicYear('2026/2027');
    $smpSeven = createTargetReportClass(7);
    $smpEight = createTargetReportClass(8);
    $smaTen = createTargetReportClass(10);

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, ['activeTab' => 'target', 'schoolLevel' => 'SMP'])
        ->assertSeeHtml($smpSeven->name)
        ->assertSeeHtml($smpEight->name)
        ->assertDontSeeHtml($smaTen->name)
        ->set('schoolLevel', 'all')
        ->assertSet('targetSchoolClassId', null)
        ->assertSeeHtml('Semua Kelas')
        ->assertDontSeeHtml($smpSeven->name);
});

it('normalizes a stale class id that does not match the selected school level', function () {
    createTargetReportAcademicYear('2026/2027');
    $smaTen = createTargetReportClass(10);
    $smpSeven = createTargetReportClass(7);

    // Kelas SMA yang tertinggal di URL lama harus dibuang, sedangkan kelas SMP
    // yang valid harus bertahan.
    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'target',
            'schoolLevel' => 'SMP',
            'targetSchoolClassId' => (string) $smaTen->id,
        ])
        ->assertSet('targetSchoolClassId', null)
        ->set('targetSchoolClassId', (string) $smpSeven->id)
        ->assertSet('targetSchoolClassId', (string) $smpSeven->id);
});

it('keeps the class filter query count bounded as students grow', function () {
    createTargetReportAcademicYear('2026/2027');
    $classA = createTargetReportClass(7);
    $classB = createTargetReportClass(7);
    $type = makeBillType('SPP Bounded Query');
    $service = app(StudentTargetArrearsReportService::class);

    $measure = function () use ($service, $classA): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->generate(
            StudentTargetArrearsReportService::MODE_MONTHLY,
            9,
            2026,
            '2026/2027',
            SchoolLevel::SMP,
            $classA->id,
        );
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $studentA = createTargetReportClassStudent($classA, '2026/2027', 'Rina VII A');
    makeMonthlyBill($studentA, $type, 500_000, 9, 2026);
    $before = $measure();

    for ($number = 2; $number <= 20; $number++) {
        $student = createTargetReportClassStudent(
            $number % 2 === 0 ? $classA : $classB,
            '2026/2027',
            'Siswa '.$number,
        );
        makeMonthlyBill($student, $type, 100_000, 9, 2026);
    }

    $after = $measure();

    // 19 siswa tambahan tidak menambah satu query pun.
    expect($after)->toBe($before);
});

it('carries the selected class into the export links', function () {
    createTargetReportAcademicYear('2026/2027');
    $classSeven = createTargetReportClass(7);

    $html = Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'target',
            'schoolLevel' => 'SMP',
            'targetSchoolClassId' => (string) $classSeven->id,
        ])
        ->html();

    $links = collect(preg_split('/\s+/', $html))
        ->filter(fn (string $chunk): bool => str_contains($chunk, 'laporan/target-tunggakan'));

    // Link Excel dan link PDF keduanya harus membawa class_id.
    expect($links)->toHaveCount(2)
        ->and($links->every(fn (string $link): bool => str_contains($link, 'class_id='.$classSeven->id)))->toBeTrue();
});
