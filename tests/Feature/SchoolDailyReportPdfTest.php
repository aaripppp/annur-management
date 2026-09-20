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

it('renders the accounting form sections, blank empty categories, totals and signatures', function () {
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
