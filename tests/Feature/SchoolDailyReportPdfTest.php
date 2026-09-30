<?php

use App\Enums\SchoolLevel;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolDailyReportService;

/** @param list<array<string, mixed>> $details */
function createPdfReportPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $date,
    array $details,
    string $kind = Payment::KIND_BILL,
    string $status = Payment::STATUS_ACTIVE,
    ?float $headerTotal = null,
): Payment {
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-PDF-'.uniqid(),
        'payment_kind' => $kind,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $date,
        'total_amount' => $headerTotal ?? array_sum(array_column($details, 'amount')),
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $date.' 09:00:00', 'updated_at' => $date.' 09:00:00'])->saveQuietly();

    foreach ($details as $detail) {
        PaymentDetail::query()->create(array_merge($detail, ['payment_id' => $payment->id]));
    }

    return $payment;
}

it('protects and validates the daily report PDF route', function () {
    $this->get(route('laporan.harian.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.harian.pdf', ['start_date' => 'not-a-date', 'end_date' => '2026-08-27']))
        ->assertSessionHasErrors('start_date');
});

it('streams a valid F4B PDF inline with the selected date filename and logo', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('laporan.harian.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-harian-sekolah-2026-08-27.pdf');

    expect($response->getContent())->toStartWith('%PDF-')
        ->and(public_path('images/annur_logo2.png'))->toBeFile();
});

it('renders the accounting form sections, per-section categories, totals and signatures', function () {
    $user = User::factory()->create(['name' => 'Petugas PDF']);
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa PDF']);
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BNI', 'account_number' => '111111']);
    $spp = makeBillType('SPP PDF');
    $ekskul = makeBillType('Ekskul PDF');

    createPdfReportPayment($student, $cash, $user, '2026-08-26', [
        ['payment_type_id' => $spp->id, 'amount' => 500_000],
    ]);
    createPdfReportPayment($student, $bank, $user, '2026-08-28', [
        ['payment_type_id' => $ekskul->id, 'amount' => 150_000],
    ]);

    $report = app(SchoolDailyReportService::class)->generate('2026-08-26', '2026-08-28');
    $localizedDate = $report['end_date']->settings(['locale' => 'id']);
    $document = [
        'unit' => $report['unit_name'],
        'period_title' => $report['period_title'],
        'period_label' => $report['period_label'],
        'approval' => [
            'admin_name' => $user->name,
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, '.$localizedDate->translatedFormat('d F Y'),
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $html = view('reports.school-daily-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('LAPORAN KAS HARIAN')
        ->toContain('An-Nur')
        ->toContain('Periode')
        ->toContain('26 Agustus 2026 s.d. 28 Agustus 2026')
        ->toContain('TUNAI')
        ->toContain('BNI 111111')
        ->toContain('SPP PDF')
        ->toContain('Ekskul PDF')
        ->toContain('Rp 500.000')
        ->toContain('Rp 150.000')
        ->toContain('Rp 650.000')
        ->toContain('white-space: nowrap; font-variant-numeric: tabular-nums;')
        ->toContain('.report-table th.col-amount { text-align: center; }')
        ->toContain('Menyetujui,')
        ->toContain('Direktur Keuangan')
        ->toContain('Nova Rabi\'ah Nurrohmah, SE')
        ->toContain('Mengetahui,')
        ->toContain('Kepala Tata Usaha')
        ->toContain('TU An-Nur')
        ->toContain('Windiarti, SE')
        ->toContain('Bekasi, 28 Agustus 2026')
        ->toContain('Arif Hamdani');

    $this->actingAs($user)
        ->get(route('laporan.harian.pdf', [
            'start_date' => '2026-08-26',
            'end_date' => '2026-08-28',
        ]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-harian-sekolah-2026-08-26-sampai-2026-08-28.pdf');
});

it('renders the unit meta on the daily PDF for a scoped report', function () {
    $date = '2026-08-27';
    $user = User::factory()->create(['name' => 'Petugas Jenjang']);
    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP PDF Jenjang');

    createPdfReportPayment($sdStudent, $cash, $user, $date, [
        ['payment_type_id' => $spp->id, 'amount' => 250_000],
    ]);

    $report = app(SchoolDailyReportService::class)->generate($date, SchoolLevel::SD);
    $localizedDate = $report['date']->settings(['locale' => 'id']);
    $document = [
        'unit' => $report['unit_name'],
        'period_title' => $report['period_title'],
        'period_label' => $report['period_label'],
        'approval' => [
            'admin_name' => $user->name,
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, '.$localizedDate->translatedFormat('d F Y'),
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
    $html = view('reports.school-daily-pdf', compact('report', 'document'))->render();

    expect($html)
        ->toContain('Tanggal')
        ->toContain('27 Agustus 2026')
        ->toContain('SD An-Nur')
        ->toContain('250.000');

    $this->actingAs($user)
        ->get(route('laporan.harian.pdf', ['start_date' => $date, 'end_date' => $date, 'school_level' => 'SD']))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=laporan-harian-sekolah-2026-08-27.pdf');
});

/** @return array<string, mixed> */
function dailyPdfDocument(array $report, User $user): array
{
    $localizedDate = $report['end_date']->settings(['locale' => 'id']);

    return [
        'unit' => $report['unit_name'],
        'period_title' => $report['period_title'],
        'period_label' => $report['period_label'],
        'approval' => [
            'admin_name' => $user->name,
            'reviewer_title' => 'Kepala Tata Usaha',
            'reviewer_name' => 'Windiarti, SE',
            'city_and_date' => 'Bekasi, '.$localizedDate->translatedFormat('d F Y'),
            'report_creator_name' => 'Arif Hamdani',
        ],
    ];
}

/**
 * @return list<array{key: string, bank_id: int|null, name: string, categories: list<array{name: string}>, total: float}>
 */
function dailyPdfSections(array $report): array
{
    return $report['form_sections'];
}

it('omits banks that have no amount on the daily PDF', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $funded = Bank::factory()->create(['name' => 'BNI', 'account_number' => '111111']);
    Bank::factory()->create(['name' => 'Mandiri Nol', 'account_number' => '222222']);
    $spp = makeBillType('SPP Hide Bank');

    createPdfReportPayment($student, $funded, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ]);

    $report = app(SchoolDailyReportService::class)->generate('2026-08-27', '2026-08-27');
    $names = array_column(dailyPdfSections($report), 'name');

    expect($names)->toBe(['BNI 111111'])
        ->and($report['grand_total'])->toBe(300_000.0);
});

it('keeps payment types per section so a type only shows on banks that received it', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'Bank A', 'account_number' => 'A1']);
    $bankB = Bank::factory()->create(['name' => 'Bank B', 'account_number' => 'B1']);
    $spp = makeBillType('SPP Per Section');
    $ekskul = makeBillType('Ekskul Per Section');

    createPdfReportPayment($student, $bankA, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 400_000],
    ]);
    createPdfReportPayment($student, $bankB, $user, '2026-08-27', [
        ['payment_type_id' => $ekskul->id, 'amount' => 175_000],
    ]);

    $report = app(SchoolDailyReportService::class)->generate('2026-08-27', '2026-08-27');
    $byName = collect(dailyPdfSections($report))->keyBy('name');

    expect($byName)->toHaveKeys(['Bank A A1', 'Bank B B1'])
        ->and(array_column($byName['Bank A A1']['categories'], 'name'))->toBe(['SPP Per Section'])
        ->and(array_column($byName['Bank B B1']['categories'], 'name'))->toBe(['Ekskul Per Section'])
        ->and($byName['Bank A A1']['total'])->toBe(400_000.0)
        ->and($byName['Bank B B1']['total'])->toBe(175_000.0)
        ->and($report['grand_total'])->toBe(575_000.0);
});

it('omits the cash section when cash has no amount on the daily PDF', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BRI', 'account_number' => '333333']);
    $spp = makeBillType('SPP Tanpa Tunai');

    createPdfReportPayment($student, $bank, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 275_000],
    ]);

    $report = app(SchoolDailyReportService::class)->generate('2026-08-27', '2026-08-27');
    $keys = array_column(dailyPdfSections($report), 'key');

    expect($keys)->toBe(['bank-'.$bank->id])
        ->and($report['channels']['cash']['total'])->toEqual(0.0)
        ->and($report['grand_total'])->toBe(275_000.0);
});

it('keeps only the cash section when the daily report has cash alone', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Tunai Saja');

    createPdfReportPayment($student, $cash, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 120_000],
    ]);

    $report = app(SchoolDailyReportService::class)->generate('2026-08-27', '2026-08-27');
    $sections = dailyPdfSections($report);

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['key'])->toBe('cash')
        ->and($sections[0]['name'])->toBe('TUNAI')
        ->and($sections[0]['total'])->toBe(120_000.0)
        ->and(array_column($sections[0]['categories'], 'name'))->toBe(['SPP Tunai Saja']);
});

it('renders a clean empty state on the daily PDF when there is no transaction', function () {
    $user = User::factory()->create(['name' => 'Petugas Kosong']);
    Bank::factory()->create(['name' => 'Bank Tanpa Transaksi', 'account_number' => '999999']);
    Bank::factory()->cash()->create();

    $report = app(SchoolDailyReportService::class)->generate('2026-08-27', '2026-08-27');
    $html = view('reports.school-daily-pdf', [
        'report' => $report,
        'document' => dailyPdfDocument($report, $user),
    ])->render();

    expect(dailyPdfSections($report))->toBe([])
        ->and($report['grand_total'])->toEqual(0.0)
        ->and($html)->toContain('Tidak ada transaksi pada periode/filter ini.')
        ->toContain('LAPORAN KAS HARIAN')
        ->toContain('27 Agustus 2026')
        ->toContain('TOTAL PENERIMAAN (DEBET/TRANSFER)')
        ->not->toContain('Bank Tanpa Transaksi');
});
