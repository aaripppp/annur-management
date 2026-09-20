<?php

use App\Enums\SchoolLevel;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolMonthlyReportService;
use App\Services\StudentTargetArrearsReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** @param list<array<string, mixed>> $details */
function createMonthlyPdfPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $date,
    array $details,
    string $kind = Payment::KIND_BILL,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-PDF-BLN-'.uniqid(),
        'payment_kind' => $kind,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => array_sum(array_column($details, 'amount')),
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $date.' 09:00:00', 'updated_at' => $date.' 09:00:00'])->saveQuietly();

    foreach ($details as $detail) {
        PaymentDetail::query()->create(array_merge($detail, ['payment_id' => $payment->id]));
    }

    return $payment;
}

/** @param array<string, mixed> $report */
function monthlyPdfComparison(array $report): array
{
    $targetReport = app(StudentTargetArrearsReportService::class)->generateMonthlySummary(
        $report['month'],
        $report['year'],
        $report['school_level'],
    );

    return [
        'target_label' => 'TARGET BULAN '.$report['month_label_upper'],
        'target' => $targetReport['totals']['target'],
        'income' => $report['grand_total'],
        'outstanding' => max($targetReport['totals']['target'] - $report['grand_total'], 0),
    ];
}

function fakeMonthlyPdfRenderer(): object
{
    $renderer = new class
    {
        public string $view = '';

        /** @var array<string, mixed> */
        public array $viewData = [];

        /** @param array<string, mixed> $data */
        public function loadView(string $view, array $data): self
        {
            $this->view = $view;
            $this->viewData = $data;

            return $this;
        }

        /** @param array<string, mixed> $options */
        public function setOption(array $options): self
        {
            return $this;
        }

        public function setPaper(array|string $paper, string $orientation = 'portrait'): self
        {
            return $this;
        }

        public function stream(string $filename): Response
        {
            return response('PDF', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename='.$filename,
            ]);
        }
    };

    Pdf::swap($renderer);

    return $renderer;
}

it('protects and validates the monthly report PDF route', function () {
    $this->get(route('laporan.bulanan.pdf', ['month' => 8, 'year' => 2026]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.pdf', ['month' => 0, 'year' => 2026]))
        ->assertSessionHasErrors('month');

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.pdf', ['month' => 8, 'year' => 1999]))
        ->assertSessionHasErrors('year');
});

it('streams a valid landscape F4B PDF inline with the monthly filename and logo', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.pdf', ['month' => 8, 'year' => 2026]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-bulanan-sekolah-2026-08.pdf');

    expect($response->getContent())->toStartWith('%PDF-')
        ->and(public_path('images/annur_logo2.png'))->toBeFile();
});

it('builds the PDF comparison from the selected monthly target and created-at receipt total', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Perbandingan PDF');

    makeMonthlyBill($student, $spp, 100_000_000, 9, 2026);
    makeMonthlyBill($student, $spp, 1_000_000, 10, 2026);

    $includedByRecordedDate = createSchoolMonthlyReportPayment(
        $student,
        $cash,
        $user,
        '2026-10-01',
        [['payment_type_id' => $spp->id, 'amount' => 65_376_250]],
        paymentDate: '2026-10-01',
        recordedAt: '2026-09-30 23:59:59',
    );
    $excludedByRecordedDate = createSchoolMonthlyReportPayment(
        $student,
        $cash,
        $user,
        '2026-09-30',
        [['payment_type_id' => $spp->id, 'amount' => 2_000_000]],
        paymentDate: '2026-09-30',
        recordedAt: '2026-10-01 00:00:00',
    );

    $renderer = fakeMonthlyPdfRenderer();

    $this->actingAs($user)
        ->get(route('laporan.bulanan.pdf', ['month' => 9, 'year' => 2026]))
        ->assertOk();

    $septemberData = $renderer->viewData;
    $septemberReport = $septemberData['report'];
    $septemberComparison = $septemberData['monthlyComparison'];
    $septemberHtml = view($renderer->view, $septemberData)->render();

    expect($septemberComparison['target_label'])->toBe('TARGET BULAN SEPTEMBER 2026')
        ->and($septemberComparison['target'])->toBe(100_000_000.0)
        ->and($septemberComparison['income'])->toBe(65_376_250.0)
        ->and($septemberComparison['income'])->toBe($septemberReport['grand_total'])
        ->and($septemberComparison['outstanding'])->toBe(34_623_750.0)
        ->and(collect($septemberReport['detail_rows'])->pluck('payment_id'))->toContain($includedByRecordedDate->id)
        ->and(collect($septemberReport['detail_rows'])->pluck('payment_id'))->not->toContain($excludedByRecordedDate->id)
        ->and($septemberHtml)->toContain('TARGET BULAN SEPTEMBER 2026')
        ->toContain('PEMASUKAN YANG MASUK')
        ->toContain('Rp 65.376.250')
        ->toContain('TUNGGAKAN')
        ->toContain('Rp 34.623.750');

    $this->get(route('laporan.bulanan.pdf', ['month' => 10, 'year' => 2026]))
        ->assertOk();

    $octoberComparison = $renderer->viewData['monthlyComparison'];
    $octoberHtml = view($renderer->view, $renderer->viewData)->render();

    expect($octoberComparison['target_label'])->toBe('TARGET BULAN OKTOBER 2026')
        ->and($octoberComparison['target'])->toBe(1_000_000.0)
        ->and($octoberComparison['income'])->toBe(2_000_000.0)
        ->and($octoberComparison['outstanding'])->toBe(0.0)
        ->and($octoberHtml)->toContain('TARGET BULAN OKTOBER 2026')
        ->not->toContain('TARGET BULAN SEPTEMBER 2026');
});

it('renders the formal monthly heading with month label and fixed signatures', function () {
    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE",
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 31 Agustus 2026',
            'footer_unit' => 'TU '.$report['unit_name'],
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $monthlyComparison = monthlyPdfComparison($report);
    $html = view('reports.school-monthly-pdf', compact('report', 'document', 'monthlyComparison'))->render();

    expect($html)
        ->toContain('REKAP PENERIMAAN UANG CASH DAN TRANSFER')
        ->toContain('(MONTHLY REPORT)')
        ->toContain('An-Nur')
        ->toContain('AGUSTUS 2026')
        ->toContain('Menyetujui')
        ->toContain('Nova Rabi&#039;ah Nurrohmah, SE')
        ->toContain('Windiarti, SE')
        ->toContain('Bekasi, 31 Agustus 2026')
        ->toContain('TU An-Nur')
        ->toContain('Arif Hamdani');
});

it('keeps every dynamic category in one compact table', function () {
    foreach (range(1, 5) as $index) {
        makeBillType('Kategori Lebar PDF '.$index);
    }

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE",
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 31 Agustus 2026',
            'footer_unit' => 'TU '.$report['unit_name'],
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $monthlyComparison = monthlyPdfComparison($report);
    $html = view('reports.school-monthly-pdf', compact('report', 'document', 'monthlyComparison'))->render();

    $categoryWidth = number_format((100 - 13 - 15 - 9 - 10) / 6, 6, '.', '').'%';
    $cashCategoryWidth = number_format((100 - 18 - 11) / 6, 6, '.', '').'%';

    expect(substr_count($html, 'width: '.$categoryWidth))->toBe(6)
        ->and(substr_count($html, 'width: '.$cashCategoryWidth))->toBe(6)
        ->and(substr_count($html, '<table class="report-table'))->toBe(2)
        ->and($html)->not->toContain('page-break-before: always')
        ->and($html)->toContain('@page { size: 330mm 216mm; margin: 6mm 6mm 8mm; }')
        ->and($html)->toContain('padding: 1px 1.5px')
        ->and($html)->toContain('<colgroup>')
        ->and($html)->toContain('word-wrap: break-word');
});

it('keeps a representative monthly matrix within two PDF pages', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $banks = [
        Bank::factory()->cash()->create(),
        Bank::factory()->create(['name' => 'BCA', 'account_number' => '111111']),
        Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']),
    ];
    $paymentTypes = collect(range(1, 10))
        ->map(fn (int $index) => PaymentType::query()->create([
            'name' => 'Kategori PDF '.$index,
            'is_active' => true,
            'is_auto_enrolled' => false,
            'is_required' => false,
        ]));

    foreach (range(1, 12) as $day) {
        $date = sprintf('2026-08-%02d', $day);

        foreach ($banks as $bank) {
            createMonthlyPdfPayment(
                $student,
                $bank,
                $user,
                $date,
                $paymentTypes->values()->map(fn ($type, int $index): array => [
                    'payment_type_id' => $type->id,
                    'amount' => [16_271_250, 42_486_250, 65_376_250][$index % 3],
                ])->all(),
            );
        }
    }

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE",
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 31 Agustus 2026',
            'footer_unit' => 'TU '.$report['unit_name'],
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $monthlyComparison = monthlyPdfComparison($report);
    $html = view('reports.school-monthly-pdf', compact('report', 'document', 'monthlyComparison'))->render();
    $pdf = app('dompdf.wrapper')->loadHTML($html)->setPaper([0, 0, 935.43, 612.28]);
    $pdf->render();

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBeLessThanOrEqual(2)
        ->and(substr_count($html, '<table class="report-table'))->toBe(2)
        ->and($html)->toContain('Rp 16.271.250')
        ->toContain('Rp 42.486.250')
        ->toContain('Rp 65.376.250')
        ->toContain('TARGET BULAN AGUSTUS 2026')
        ->toContain('PEMASUKAN YANG MASUK')
        ->toContain('TUNGGAKAN');
});

it('renders cash and transfer sections with totals and a reconciled grand total', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa PDF Bulanan']);
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BNI', 'account_number' => '111111']);
    $spp = makeBillType('SPP PDF Bulanan');
    $infaq = makeBillType('Infaq PDF Bulanan');

    createMonthlyPdfPayment($student, $cash, $user, $date, [
        ['payment_type_id' => $spp->id, 'amount' => 500_000],
    ]);
    createMonthlyPdfPayment($student, $bank, $user, $date, [
        ['payment_type_id' => $infaq->id, 'amount' => 150_000],
    ]);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE",
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 31 Agustus 2026',
            'footer_unit' => 'TU '.$report['unit_name'],
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $monthlyComparison = monthlyPdfComparison($report);
    $html = view('reports.school-monthly-pdf', compact('report', 'document', 'monthlyComparison'))->render();
    $bankTable = explode('</table>', explode('<table class="report-table bank-table">', $html, 2)[1], 2)[0];

    expect($html)
        ->toContain('PENERIMAAN BANK')
        ->toContain('PENERIMAAN TUNAI')
        ->toContain('RINGKASAN TOTAL')
        ->toContain('Tanggal')
        ->toContain('Bank')
        ->toContain('Kamis, 27 Agt 2026')
        ->toContain($bank->optionLabel())
        ->not->toContain('Tunai / Cash')
        ->toContain('SPP PDF Bulanan')
        ->toContain('Infaq PDF Bulanan')
        ->toContain('Rp 500.000')
        ->toContain('Rp 150.000')
        ->toContain('Rp 650.000')
        ->toContain('Total Harian')
        ->toContain('white-space: nowrap; font-variant-numeric: tabular-nums;')
        ->toContain('.report-table th.col-category, .report-table th.col-total, .report-table th.col-daily-total, .report-table th.cash-total { text-align: center; }')
        ->toContain('GRAND TOTAL')
        ->not->toContain('TOTAL 27 AGT');

    expect($bankTable)->toContain($bank->optionLabel())
        ->not->toContain($cash->optionLabel())
        ->and(substr_count($html, 'Kamis, 27 Agt 2026'))->toBe(2)
        ->and(substr_count($html, 'rowspan="1"'))->toBe(2)
        ->and(substr_count($html, '>GRAND TOTAL<'))->toBe(1);
});

it('renders a valid empty report with zero totals for an empty month', function () {
    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE",
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 31 Agustus 2026',
            'footer_unit' => 'TU '.$report['unit_name'],
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $monthlyComparison = monthlyPdfComparison($report);
    $html = view('reports.school-monthly-pdf', compact('report', 'document', 'monthlyComparison'))->render();

    $reportTables = explode('<table class="summary-table">', $html, 2)[0];

    expect($html)
        ->toContain('Tidak ada transaksi')
        ->toContain('Rp 0')
        ->toContain('GRAND TOTAL');
    expect($reportTables)->not->toContain('Rp 0');

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.pdf', ['month' => 8, 'year' => 2026]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('renders the supplied report creator in the right monthly signature', function () {
    $user = User::factory()->create(['name' => 'Petugas Dinamis']);
    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $localizedDate = $report['last_day']->settings(['locale' => 'id']);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE",
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, '.$localizedDate->translatedFormat('d F Y'),
            'footer_unit' => 'TU '.$report['unit_name'],
            'report_creator_name' => $user->name,
        ],
    ];
    $monthlyComparison = monthlyPdfComparison($report);
    $monthly = view('reports.school-monthly-pdf', compact('report', 'document', 'monthlyComparison'))->render();

    expect($monthly)
        ->toContain($user->name)
        ->toContain('Windiarti, SE')
        ->toContain('Bekasi, 31 Agustus 2026');
});

it('renders the unit meta on the monthly PDF for a scoped report', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP PDF Bulanan Jenjang');

    createMonthlyPdfPayment($smpStudent, $cash, $user, $date, [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ]);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SMP);
    $document = [
        'unit' => $report['unit_name'],
        'month_label' => $report['month_label_upper'],
        'approval' => [
            'approver_title' => 'Direktur Keuangan',
            'approver_name' => "Nova Rabi'ah Nurrohmah, SE",
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, 31 Agustus 2026',
            'footer_unit' => 'TU '.$report['unit_name'],
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $monthlyComparison = monthlyPdfComparison($report);
    $html = view('reports.school-monthly-pdf', compact('report', 'document', 'monthlyComparison'))->render();

    expect($html)
        ->toContain('SMP An-Nur')
        ->toContain('300.000');

    $this->actingAs($user)
        ->get(route('laporan.bulanan.pdf', ['month' => 8, 'year' => 2026, 'school_level' => 'SMP']))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-bulanan-sekolah-2026-08.pdf');
});
