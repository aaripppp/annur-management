<?php

use App\Livewire\PaymentCorrection;
use App\Livewire\SchoolDailyReport;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\PaymentCorrectionLog;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolBankRecapService;
use App\Services\SchoolBankRecapSpreadsheet;
use App\Services\SchoolDailyReportService;
use App\Services\SchoolMonthlyReportService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

function createBankRecapDaycarePayment(
    DaycareChild $child,
    Bank $bank,
    User $user,
    string $paymentDate,
    string $recordedAt,
    int $amount,
): DaycarePayment {
    $payment = DaycarePayment::query()->create([
        'receipt_number' => 'KWT-DC-BANK-'.uniqid(),
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'notes' => 'Pembayaran pengujian rekap bank',
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $recordedAt, 'updated_at' => $recordedAt])->saveQuietly();

    return $payment->refresh();
}

function createBankRecapProspectivePayment(
    ProspectiveStudent $prospectiveStudent,
    Bank $bank,
    User $user,
    string $paymentDate,
    string $recordedAt,
    int $amount,
): ProspectiveStudentPayment {
    $payment = ProspectiveStudentPayment::query()->create([
        'receipt_number' => 'KWT-PV-BANK-'.uniqid(),
        'prospective_student_id' => $prospectiveStudent->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'description' => 'Pembayaran pengujian rekap bank',
        'status' => ProspectiveStudentPayment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    $payment->forceFill(['created_at' => $recordedAt, 'updated_at' => $recordedAt])->saveQuietly();

    return $payment->refresh();
}

function readBankRecapWorkbookValues(string $path): array
{
    $reader = new Reader;
    $values = [];

    try {
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->toArray() as $value) {
                    $values[] = (string) $value;
                }
            }
        }
    } finally {
        $reader->close();
    }

    return $values;
}

it('stores an actual payment date independently from its recorded timestamp', function () {
    $payment = createBankRecapPayment(
        Student::factory()->create(),
        Bank::factory()->create(),
        User::factory()->create(),
        '2026-09-01',
        '2026-09-02 09:30:00',
        1_000_000,
    );

    expect($payment->payment_date->toDateString())->toBe('2026-09-01')
        ->and($payment->created_at->format('Y-m-d H:i:s'))->toBe('2026-09-02 09:30:00');
});

it('uses recorded date for daily and monthly reports but payment date for bank recap', function () {
    $payment = createBankRecapPayment(
        Student::factory()->create(),
        Bank::factory()->create(),
        User::factory()->create(),
        '2026-09-01',
        '2026-09-02 09:30:00',
        1_000_000,
    );

    expect(collect(app(SchoolDailyReportService::class)->generate('2026-09-01')['detail_rows'])->pluck('payment_id'))->not->toContain($payment->id)
        ->and(collect(app(SchoolDailyReportService::class)->generate('2026-09-02')['detail_rows'])->pluck('payment_id'))->toContain($payment->id)
        ->and(collect(app(SchoolMonthlyReportService::class)->generate(2026, 9)['detail_rows'])->pluck('payment_id'))->toContain($payment->id)
        ->and(collect(app(SchoolBankRecapService::class)->generate('2026-09-01')['detail_rows'])->pluck('payment_id'))->toContain($payment->id)
        ->and(collect(app(SchoolBankRecapService::class)->generate('2026-09-02')['detail_rows'])->pluck('payment_id'))->not->toContain($payment->id);
});

it('keeps bank recap ordered by payment_date instead of created_at', function () {
    $student = Student::factory()->create();
    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    $laterPaymentDate = createBankRecapPayment(
        $student,
        $bank,
        $user,
        '2026-09-02',
        '2026-09-02 08:00:00',
        200_000,
    );
    $earlierPaymentDate = createBankRecapPayment(
        $student,
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 10:00:00',
        100_000,
    );

    $paymentIds = collect(app(SchoolBankRecapService::class)
        ->generate('2026-09-01', '2026-09-02')['detail_rows'])
        ->pluck('payment_id')
        ->all();

    expect($paymentIds)->toBe([$earlierPaymentDate->id, $laterPaymentDate->id]);
});

it('groups cash by type and bank transfers by their actual bank ids merging daycare payments', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $firstBank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $secondBank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']);

    createBankRecapPayment($student, $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapPayment($student, $firstBank, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);
    createBankRecapPayment($student, $secondBank, $user, '2026-09-01', '2026-09-02 10:00:00', 300_000);
    createBankRecapDaycarePayment(
        DaycareChild::factory()->create(['nama_lengkap' => 'Anak Daycare BSI']),
        $firstBank,
        $user,
        '2026-09-01',
        '2026-09-02 11:00:00',
        900_000,
    );

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $firstBankSection = collect($report['bank_sections'])
        ->first(fn (array $section): bool => $section['bank_id'] === $firstBank->id);

    expect($report['cash_section']['transaction_count'])->toBe(1)
        ->and($report['cash_total'])->toBe(100_000.0)
        ->and($report['bank_total'])->toBe(1_400_000.0)
        ->and($report['grand_total'])->toBe(1_500_000.0)
        ->and(collect($report['bank_sections'])->pluck('bank_id')->all())->toEqualCanonicalizing([$firstBank->id, $secondBank->id])
        ->and(collect($report['bank_sections'])->pluck('bank_label')->all())->toEqualCanonicalizing([
            'BSI — 111111',
            'BSI — 222222',
        ])
        ->and($report['bank_sections'])->toHaveCount(2)
        ->and(collect($report['detail_rows'])->pluck('bank_type')->unique()->all())->toEqualCanonicalizing([Bank::TYPE_CASH, Bank::TYPE_BANK])
        ->and($firstBankSection['total'])->toBe(1_100_000.0)
        ->and($firstBankSection['rows'][0]['transaction_count'])->toBe(2)
        ->and($firstBankSection['rows'][0]['total'])->toBe(1_100_000.0)
        ->and(collect($firstBankSection['rows'][0]['details'])->pluck('source')->all())->toEqualCanonicalizing(['student', 'daycare']);
});

it('shows the bank recap tab and validates its inclusive date range', function () {
    Livewire::actingAs(User::factory()->create());

    Livewire::test(SchoolDailyReport::class, ['activeTab' => 'bank'])
        ->assertSee('Rekap Bank')
        ->set('bankStartDate', '2026-09-05')
        ->set('bankEndDate', '2026-09-01')
        ->assertHasErrors(['bankEndDate' => 'after_or_equal']);
});

it('aligns summary columns removes the footer count and numbers expanded details', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '7123456789']);
    createBankRecapPayment(
        Student::factory()->create(['nama_lengkap' => 'Siswa Nomor Satu']),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 13:52:00',
        1_535_000,
    );
    createBankRecapPayment(
        Student::factory()->create(['nama_lengkap' => 'Siswa Nomor Dua']),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 14:05:00',
        1_035_000,
    );

    $html = Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'bank',
            'bankStartDate' => '2026-09-01',
            'bankEndDate' => '2026-09-01',
        ])
        ->assertSee('Transaksi')
        ->assertSee('No.')
        ->assertSee('Rp 2.570.000')
        ->html();

    expect($html)
        ->toContain('annur-report-page')
        ->toContain('<col class="w-[120px]">')
        ->not->toContain('grid-cols-[1fr_120px_180px]')
        ->toMatch('/01 Sep 2026\s*<\/span>\s*<\/td>\s*<td[^>]*>2<\/td>/')
        ->toMatch('/<td[^>]*>1<\/td>\s*<td[^>]*>.*Siswa Nomor Satu/s')
        ->toMatch('/<td[^>]*>2<\/td>\s*<td[^>]*>.*Siswa Nomor Dua/s')
        ->toMatch('/<td colspan="2"[^>]*>TOTAL BSI<\/td>\s*<td[^>]*>Rp 2\.570\.000<\/td>/')
        ->not->toMatch('/TOTAL BSI<\/td>\s*<td[^>]*>2<\/td>/');
});

it('includes daycare bank payments in the bank recap merged with student payments', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $studentPayment = createBankRecapPayment(
        Student::factory()->create(['nama_lengkap' => 'Siswa Sekolah']),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 09:00:00',
        200_000,
    );
    $daycarePayment = createBankRecapDaycarePayment(
        DaycareChild::factory()->create(['nama_lengkap' => 'Anak Daycare Satu']),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 10:00:00',
        900_000,
    );

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');

    expect($report['transaction_count'])->toBe(2)
        ->and($report['bank_total'])->toBe(1_100_000.0)
        ->and($report['grand_total'])->toBe(1_100_000.0)
        ->and(collect($report['detail_rows'])->pluck('receipt_number')->all())->toEqualCanonicalizing([$studentPayment->receipt_number, $daycarePayment->receipt_number])
        ->and(collect($report['detail_rows'])->pluck('source')->all())->toEqualCanonicalizing(['student', 'daycare']);
});

it('keeps daycare and student payments in their own bank account sections even with identical bank names', function () {
    $user = User::factory()->create();
    $firstBank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $secondBank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $firstBank, $user, '2026-09-01', '2026-09-02 08:00:00', 200_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $secondBank, $user, '2026-09-01', '2026-09-02 09:00:00', 300_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');

    expect(collect($report['bank_sections'])->pluck('bank_id')->all())->toEqualCanonicalizing([$firstBank->id, $secondBank->id])
        ->and(collect($report['bank_sections'])->first(fn (array $section): bool => $section['bank_id'] === $firstBank->id)['total'])->toBe(200_000.0)
        ->and(collect($report['bank_sections'])->first(fn (array $section): bool => $section['bank_id'] === $secondBank->id)['total'])->toBe(300_000.0);
});

it('places daycare cash payments into the cash section alongside student cash', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 09:00:00', 500_000);
    createBankRecapPayment(Student::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');

    expect($report['cash_section']['transaction_count'])->toBe(2)
        ->and($report['cash_section']['total'])->toBe(600_000.0)
        ->and($report['cash_total'])->toBe(600_000.0)
        ->and($report['bank_total'])->toBe(0);
});

it('groups daycare payments by their actual payment date instead of recorded time', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $payment = createBankRecapDaycarePayment(
        DaycareChild::factory()->create(),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 09:30:00',
        750_000,
    );

    $onDate = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $offDate = app(SchoolBankRecapService::class)->generate('2026-09-02');
    $bankSection = collect($onDate['bank_sections'])
        ->first(fn (array $section): bool => $section['bank_id'] === $bank->id);

    expect(collect($onDate['detail_rows'])->pluck('payment_id'))->toContain($payment->id)
        ->and($onDate['detail_rows'][0]['payment_date']->toDateString())->toBe('2026-09-01')
        ->and($bankSection['rows'][0]['date']->toDateString())->toBe('2026-09-01')
        ->and($bankSection['rows'][0]['details'])->toHaveCount(1)
        ->and(collect($offDate['detail_rows'])->pluck('payment_id'))->not->toContain($payment->id);
});

it('excludes cancelled student payments while keeping daycare payments in the bank recap', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $cancelled = Payment::query()->create([
        'receipt_number' => 'KWT-CANCELLED',
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => Student::factory()->create()->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-01',
        'total_amount' => 500_000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Koreksi pembayaran',
        'created_by' => $user->id,
    ]);
    $daycarePayment = createBankRecapDaycarePayment(
        DaycareChild::factory()->create(),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 09:00:00',
        600_000,
    );

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');

    expect(collect($report['detail_rows'])->pluck('receipt_number'))->toContain($daycarePayment->receipt_number)
        ->and(collect($report['detail_rows'])->pluck('receipt_number'))->not->toContain($cancelled->receipt_number)
        ->and($report['transaction_count'])->toBe(1)
        ->and($report['grand_total'])->toBe(600_000.0)
        ->and(collect($report['detail_rows'])->pluck('source')->all())->toBe(['daycare']);
});

it('attributes an explicit source and normalized fields to every bank recap detail row', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Satu']), $bank, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(['nama_lengkap' => 'Anak Satu']), $bank, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');

    expect(collect($report['detail_rows'])->pluck('source')->all())->toEqualCanonicalizing([
        SchoolBankRecapService::SOURCE_STUDENT,
        SchoolBankRecapService::SOURCE_DAYCARE,
    ])
        ->and(collect($report['detail_rows'])->pluck('name')->all())->toEqualCanonicalizing(['Siswa Satu', 'Anak Satu'])
        ->and($report['detail_rows'])->each->toHaveKeys([
            'source',
            'name',
            'payment_date',
            'recorded_at',
            'receipt_number',
            'bank_id',
            'bank_name',
            'bank_label',
            'bank_type',
            'amount',
        ]);
});

it('reconciles bank section totals against merged daycare and student payments', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '1234567890']);
    createBankRecapPayment(Student::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 08:00:00', 250_000);
    createBankRecapPayment(Student::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 09:00:00', 150_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 10:00:00', 400_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $bank, $user, '2026-09-02', '2026-09-02 11:00:00', 100_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-02');
    $section = collect($report['bank_sections'])
        ->first(fn (array $section): bool => $section['bank_id'] === $bank->id);

    expect($section['transaction_count'])->toBe(4)
        ->and($section['total'])->toBe(900_000.0)
        ->and(collect($section['rows'])->pluck('transaction_count')->all())->toBe([3, 1])
        ->and(collect($section['rows'])->pluck('total')->all())->toBe([800_000.0, 100_000.0])
        ->and($report['bank_total'])->toBe(900_000.0)
        ->and($report['grand_total'])->toBe(900_000.0);
});

it('renders source badges inside the aligned recapkan detail rows without adding a unit column', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Berlencana']), $bank, $user, '2026-09-01', '2026-09-02 08:00:00', 150_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(['nama_lengkap' => 'Anak Berlencana']), $bank, $user, '2026-09-01', '2026-09-02 09:00:00', 300_000);

    $html = Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'bank',
            'bankStartDate' => '2026-09-01',
            'bankEndDate' => '2026-09-01',
        ])
        ->html();

    expect($html)
        ->toContain('>No.</th>')
        ->toContain('>Nama</th>')
        ->toContain('>Tanggal Pembayaran</th>')
        ->toContain('>Tanggal Dicatat</th>')
        ->toContain('>No. Kwitansi</th>')
        ->toContain('>Nominal</th>')
        ->toContain('<col class="w-[6%]">')
        ->toContain('<col class="w-[14%]">')
        ->toContain('bg-slate-100 text-slate-700')
        ->toContain('bg-indigo-100 text-indigo-800')
        ->toContain('>SISWA<')
        ->toContain('>DAYCARE<')
        ->not->toContain('>Unit</th>');
});

it('renders the recapkan pdf with daycare and student totals reconciled', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 08:00:00', 400_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 09:00:00', 600_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('Rp 1.000.000')
        ->toContain('GRAND TOTAL');
});

it('lists each bank recap transaction with source badges and aligned detail columns in the pdf', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '7123456789']);
    $studentPayment = createBankRecapPayment(
        Student::factory()->create(['nama_lengkap' => 'Siswa Detail']),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 13:52:00',
        1_535_000,
    );
    $daycarePayment = createBankRecapDaycarePayment(
        DaycareChild::factory()->create(['nama_lengkap' => 'Anak Detail']),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 14:05:00',
        900_000,
    );

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('<col class="col-no">')
        ->toContain('<col class="col-name">')
        ->toContain('<col class="col-source">')
        ->toContain('<col class="col-transfer-date">')
        ->toContain('<col class="col-recorded-at">')
        ->toContain('<col class="col-receipt">')
        ->toContain('<col class="col-amount">')
        ->toContain('>No.</th>')
        ->toContain('>Nama</th>')
        ->toContain('>Kategori</th>')
        ->toContain('>Tanggal TF</th>')
        ->toContain('>Tanggal Dicatat</th>')
        ->toContain('>No. Kwitansi</th>')
        ->toContain('>Nominal</th>')
        ->toContain('>Siswa Detail</td>')
        ->toContain('>Anak Detail</td>')
        ->toContain('<span class="badge badge-student">SISWA</span>')
        ->toContain('<span class="badge badge-daycare">DAYCARE</span>')
        ->toContain('>01 Sep 2026</td>')
        ->toContain('>02 Sep 2026 13:52</td>')
        ->toContain('>02 Sep 2026 14:05</td>')
        ->toContain(">{$studentPayment->receipt_number}</td>")
        ->toContain(">{$daycarePayment->receipt_number}</td>")
        ->toContain('>Rp 1.535.000</td>')
        ->toContain('>Rp 900.000</td>')
        ->toContain('>TOTAL BSI</td>')
        ->toContain('>Rp 2.435.000</td>');
});

it('groups pdf transactions by transfer date inside each bank section rather than recorded time', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Telat']), $bank, $user, '2026-09-01', '2026-09-02 07:00:00', 200_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Hari Pertama']), $bank, $user, '2026-09-01', '2026-09-02 08:00:00', 300_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Hari Kedua']), $bank, $user, '2026-09-02', '2026-09-02 09:00:00', 100_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(['nama_lengkap' => 'Anak Hari Kedua']), $bank, $user, '2026-09-02', '2026-09-02 10:00:00', 600_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-02');
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('Tanggal Transfer: 01 September 2026')
        ->toContain('Tanggal Transfer: 02 September 2026')
        ->toContain('Total 01 September 2026: Rp 500.000')
        ->toContain('Total 02 September 2026: Rp 700.000')
        ->toContain('>TOTAL BSI</td>')
        ->toContain('>Rp 1.200.000</td>')
        ->toMatch('/Tanggal Transfer: 01 September 2026.*?Siswa Telat.*?Siswa Hari Pertama.*?Tanggal Transfer: 02 September 2026.*?Siswa Hari Kedua.*?Anak Hari Kedua/s');
});

it('keeps pdf sections separate for identically named banks by their actual accounts', function () {
    $user = User::factory()->create();
    $firstBank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $secondBank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $firstBank, $user, '2026-09-01', '2026-09-02 09:00:00', 100_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $secondBank, $user, '2026-09-01', '2026-09-02 10:00:00', 200_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('<h2 class="section-title">BSI <span class="section-note">(BSI — 111111)</span></h2>')
        ->toContain('<h2 class="section-title">BSI <span class="section-note">(BSI — 222222)</span></h2>')
        ->toContain('>Rp 100.000</td>')
        ->toContain('>Rp 200.000</td>')
        ->and(substr_count($pdfHtml, '>TOTAL BSI</td>'))->toBe(2);
});

it('omits cancelled student transactions from the pdf while keeping daycare rows', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $cancelled = Payment::query()->create([
        'receipt_number' => 'KWT-CANCELLED',
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => Student::factory()->create()->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-01',
        'total_amount' => 500_000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Koreksi pembayaran',
        'created_by' => $user->id,
    ]);
    $daycarePayment = createBankRecapDaycarePayment(
        DaycareChild::factory()->create(['nama_lengkap' => 'Anak Aktif']),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 09:00:00',
        600_000,
    );

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('>Anak Aktif</td>')
        ->and($pdfHtml)->toContain(">{$daycarePayment->receipt_number}</td>")
        ->and($pdfHtml)->not->toContain('>KWT-CANCELLED</td>')
        ->and($pdfHtml)->not->toContain('Rp 500.000')
        ->and($pdfHtml)->toContain('>Rp 600.000</td>');
});

it('applies the range filter to pdf details and keeps the cash section', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Dalam Rentang']), $bank, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Di Luar Rentang']), $bank, $user, '2026-09-02', '2026-09-02 09:00:00', 200_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(['nama_lengkap' => 'Anak Tunai']), $cash, $user, '2026-09-01', '2026-09-02 10:00:00', 300_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('TUNAI / CASH')
        ->toContain('Tanggal Transfer: 01 September 2026')
        ->toContain('Total 01 September 2026: Rp 300.000')
        ->toContain('Total 01 September 2026: Rp 100.000')
        ->toContain('>Siswa Dalam Rentang</td>')
        ->toContain('>Anak Tunai</td>')
        ->toContain('>TOTAL TUNAI / CASH</td>')
        ->toContain('>Rp 400.000</td>')
        ->not->toContain('Siswa Di Luar Rentang')
        ->not->toContain('Tanggal Transfer: 02 September 2026');
});

it('keeps long rupiah amounts on one line with every header centered in the pdf', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(
        Student::factory()->create(['nama_lengkap' => 'Siswa Nominal Besar']),
        $bank,
        $user,
        '2026-09-01',
        '2026-09-02 12:00:00',
        286_197_250,
    );

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('>Rp 286.197.250</td>')
        ->toContain('white-space: nowrap; font-variant-numeric: tabular-nums;')
        ->toContain('.report-table th { text-align: center; }')
        ->toContain('.report-table th.amount { text-align: center; }');
});

it('uses Asia Jakarta for system timestamps without shifting payment dates or local report days', function () {
    $payment = createBankRecapPayment(
        Student::factory()->create(),
        Bank::factory()->create(),
        User::factory()->create(),
        '2026-09-01',
        '2026-09-02 00:30:00',
        300_000,
    );
    $wibTimestamp = CarbonImmutable::create(2026, 9, 2, 6, 52, 0, 'UTC')
        ->setTimezone('Asia/Jakarta');

    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(date_default_timezone_get())->toBe('Asia/Jakarta')
        ->and($wibTimestamp->format('Y-m-d H:i'))->toBe('2026-09-02 13:52')
        ->and($wibTimestamp->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'))->toBe('2026-09-02 13:52')
        ->and($payment->payment_date->toDateString())->toBe('2026-09-01')
        ->and($payment->created_at->timezoneName)->toBe('Asia/Jakarta')
        ->and(collect(app(SchoolDailyReportService::class)->generate('2026-09-01')['detail_rows'])->pluck('payment_id'))->not->toContain($payment->id)
        ->and(collect(app(SchoolDailyReportService::class)->generate('2026-09-02')['detail_rows'])->pluck('payment_id'))->toContain($payment->id)
        ->and(collect(app(SchoolMonthlyReportService::class)->generate(2026, 9)['detail_rows'])->pluck('payment_id'))->toContain($payment->id)
        ->and(collect(app(SchoolBankRecapService::class)->generate('2026-09-01')['detail_rows'])->pluck('payment_id'))->toContain($payment->id);
});

it('protects and validates bank recap exports', function () {
    $parameters = ['start_date' => '2026-09-01', 'end_date' => '2026-09-01'];

    $this->get(route('laporan.bank.export', $parameters))->assertRedirect(route('login'));
    $this->get(route('laporan.bank.pdf', $parameters))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bank.export', ['start_date' => '2026-09-02', 'end_date' => '2026-09-01']))
        ->assertSessionHasErrors('end_date');
});

it('exports the canonical bank recap as xlsx and pdf', function () {
    $user = User::factory()->create();
    createBankRecapPayment(
        Student::factory()->create(['nama_lengkap' => 'Siswa Rekonsiliasi']),
        Bank::factory()->create(['name' => 'Bank Rekonsiliasi']),
        $user,
        '2026-09-01',
        '2026-09-02 09:30:00',
        425_000,
    );
    $report = app(SchoolBankRecapService::class)->generate('2026-09-01');
    $path = app(SchoolBankRecapSpreadsheet::class)->create($report);
    $reader = new Reader;

    try {
        $reader->open($path);
        $sheetNames = collect(iterator_to_array($reader->getSheetIterator()))
            ->map(fn ($sheet): string => $sheet->getName())
            ->values()
            ->all();

        $archive = new ZipArchive;
        expect($archive->open($path))->toBeTrue();
        $stylesXml = $archive->getFromName('xl/styles.xml');
        $summaryXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $detailXml = $archive->getFromName('xl/worksheets/sheet2.xml');
        $archive->close();
    } finally {
        $reader->close();
        unlink($path);
    }

    expect($sheetNames)->toBe(['Rekap Bank', 'Rincian Transaksi'])
        ->and($stylesXml)->toContain('<xf numFmtId="3"')
        ->and($summaryXml)->toMatch('/<c r="D7" s="\d+"[^>]*><v>425000<\/v><\/c>/')
        ->and($detailXml)->toMatch('/<c r="G5" s="\d+"[^>]*><v>425000<\/v><\/c>/');

    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('Rp 425.000')
        ->toContain('white-space: nowrap; font-variant-numeric: tabular-nums;')
        ->toContain('.report-table th.amount { text-align: center; }');

    $this->actingAs($user)
        ->get(route('laporan.bank.pdf', ['start_date' => '2026-09-01', 'end_date' => '2026-09-01']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=rekap-bank-sekolah-2026-09-01.pdf');
});

it('records payment date corrections in the existing audit snapshots', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);
    $student = makeBillStudent();
    $type = makeBillType('SPP Koreksi Tanggal');
    $bill = makeMonthlyBill($student, $type, 250_000, 9, 2026);
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-CORRECTION-DATE',
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-09-01',
        'total_amount' => 250_000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 9,
        'period_year' => 2026,
        'amount' => 250_000,
    ]);

    Livewire::test(PaymentCorrection::class, ['id' => $payment->id])
        ->set('payment_date', '2026-08-31')
        ->call('gotoConfirm')
        ->set('reason', 'Tanggal transfer perlu diperbaiki')
        ->call('save');

    $log = PaymentCorrectionLog::query()->where('payment_id', $payment->id)->sole();

    expect($payment->refresh()->payment_date->toDateString())->toBe('2026-08-31')
        ->and($log->before_data['payment_date'])->toBe('2026-09-01')
        ->and($log->after_data['payment_date'])->toBe('2026-08-31');
});

it('shows the bank channel filter on the bank recap tab defaulting to all banks', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'bank',
            'bankStartDate' => '2026-09-01',
            'bankEndDate' => '2026-09-01',
        ])
        ->assertSet('bankFilter', 'all')
        ->assertSee('Bank / Channel')
        ->assertSee('Semua Bank')
        ->assertSee('Tunai / Cash');
});

it('lists live bank masters as channel options without duplicating cash', function () {
    $user = User::factory()->create();
    $first = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $second = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    Bank::factory()->cash()->create(['name' => 'Loket Kas']);

    $html = Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, ['activeTab' => 'bank'])
        ->html();

    expect($html)
        ->toContain('<option value="all">Semua Bank</option>')
        ->toContain('<option value="cash">Tunai / Cash</option>')
        ->toContain('<option value="'.$first->id.'">BSI • 111111</option>')
        ->toContain('<option value="'.$second->id.'">Mandiri • 222222</option>')
        ->and(substr_count($html, '<option value="cash">Tunai / Cash</option>'))->toBe(1);
});

it('shows a dash fallback for bank channel options without an account number', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BCA', 'account_number' => null]);

    $html = Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, ['activeTab' => 'bank'])
        ->html();

    expect($html)->toContain('<option value="'.$bank->id.'">BCA • -</option>');
});

it('renders bank channel options from the database only', function () {
    $user = User::factory()->create();
    Bank::factory()->create(['name' => 'Bank Unik Terdaftar']);

    $html = Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, ['activeTab' => 'bank'])
        ->html();

    expect($html)
        ->toContain('Bank Unik Terdaftar')
        ->not->toContain('Bank Tak Terdaftar');
});

it('does not create bank master rows when the recap page is opened', function () {
    $user = User::factory()->create();
    Bank::factory()->count(3)->create();

    $beforeCount = Bank::query()->count();

    Livewire::actingAs($user)->test(SchoolDailyReport::class, ['activeTab' => 'bank']);

    expect(Bank::query()->count())->toBe($beforeCount);
});

it('resets an unknown bank channel selection to all banks', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, ['activeTab' => 'bank', 'bankFilter' => '999999'])
        ->assertSet('bankFilter', 'all')
        ->set('bankFilter', 'bogus')
        ->assertSet('bankFilter', 'all');
});

it('accepts a real bank id as the bank channel selection', function () {
    $bank = Bank::factory()->create(['name' => 'BNI', 'account_number' => '333333']);

    Livewire::actingAs(User::factory()->create())
        ->test(SchoolDailyReport::class, ['activeTab' => 'bank', 'bankFilter' => (string) $bank->id])
        ->assertSet('bankFilter', (string) $bank->id);
});

it('filters the bank recap to cash transactions only', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Tunai']), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 300_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Transfer']), $bank, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', 'cash');

    expect($report['cash_total'])->toBe(300_000.0)
        ->and($report['bank_total'])->toBe(0)
        ->and($report['grand_total'])->toBe(300_000.0)
        ->and($report['transaction_count'])->toBe(1)
        ->and(collect($report['detail_rows'])->pluck('bank_id')->all())->toBe([$cash->id])
        ->and(collect($report['sections'])->pluck('bank_id')->all())->toBe([null]);
});

it('counts only cash payments when filtering to the cash channel', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapPayment(Student::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);
    createBankRecapPayment(Student::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 10:00:00', 300_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', 'cash');

    expect($report['transaction_count'])->toBe(2)
        ->and($report['cash_section']['transaction_count'])->toBe(2)
        ->and($report['cash_section']['total'])->toBe(300_000.0);
});

it('keeps daycare and prospective cash payments when filtering to the cash channel', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 400_000);
    createBankRecapProspectivePayment(ProspectiveStudent::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 09:00:00', 500_000);
    createBankRecapPayment(Student::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 10:00:00', 999_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', 'cash');

    expect($report['transaction_count'])->toBe(2)
        ->and($report['grand_total'])->toBe(900_000.0)
        ->and(collect($report['detail_rows'])->pluck('source')->all())->toEqualCanonicalizing(['daycare', 'prospective']);
});

it('reflects the cash channel selection in the recap summary cards', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 250_000);
    createBankRecapPayment(Student::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 09:00:00', 750_000);

    Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'bank',
            'bankStartDate' => '2026-09-01',
            'bankEndDate' => '2026-09-01',
        ])
        ->set('bankFilter', 'cash')
        ->assertSee('Rp 250.000')
        ->assertDontSee('Rp 750.000')
        ->assertSee('1 transaksi')
        ->assertSeeHtml('<option value="cash">Tunai / Cash</option>');
});

it('filters the bank recap to a single selected bank', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 400_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 09:00:00', 600_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', (string) $bankA->id);

    expect($report['bank_total'])->toBe(400_000.0)
        ->and($report['cash_total'])->toBe(0)
        ->and($report['grand_total'])->toBe(400_000.0)
        ->and($report['transaction_count'])->toBe(1)
        ->and(collect($report['bank_sections'])->pluck('bank_id')->all())->toBe([$bankA->id])
        ->and(collect($report['detail_rows'])->pluck('bank_id')->all())->toBe([$bankA->id]);
});

it('excludes other banks and cash when a specific bank is selected', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 10:00:00', 300_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', (string) $bankB->id);

    expect($report['cash_total'])->toBe(0)
        ->and($report['bank_total'])->toBe(300_000.0)
        ->and($report['grand_total'])->toBe(300_000.0)
        ->and($report['transaction_count'])->toBe(1)
        ->and(collect($report['bank_sections'])->pluck('bank_id')->all())->toBe([$bankB->id])
        ->and(collect($report['detail_rows'])->pluck('bank_id')->all())->toBe([$bankB->id])
        ->and($report['cash_section']['transaction_count'])->toBe(0);
});

it('shows only the selected bank totals in the recap summary cards', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'BANK TERPILIH', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'BANK DIABAIKAN', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 429_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 09:00:00', 857_000);

    Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'bank',
            'bankStartDate' => '2026-09-01',
            'bankEndDate' => '2026-09-01',
        ])
        ->set('bankFilter', (string) $bankA->id)
        ->assertSee('Rp 429.000')
        ->assertDontSee('Rp 857.000');
});

it('reports the selected bank channel label and kind', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', (string) $bank->id);

    expect($report['bank_filter_kind'])->toBe('bank')
        ->and($report['bank_filter_label'])->toBe('BSI — 111111');
});

it('reports the cash channel label and kind', function () {
    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', 'cash');

    expect($report['bank_filter_kind'])->toBe('cash')
        ->and($report['bank_filter_label'])->toBe('Tunai / Cash');
});

it('defaults the applied bank channel to all banks', function () {
    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01');

    expect($report['bank_filter_kind'])->toBe('all')
        ->and($report['bank_filter_label'])->toBe('Semua Bank');
});

it('treats an unknown bank channel as all banks when generating the report', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 200_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 09:00:00', 300_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', '999999');

    expect($report['bank_filter_kind'])->toBe('all')
        ->and($report['transaction_count'])->toBe(2)
        ->and($report['grand_total'])->toBe(500_000.0);
});

it('includes all channels and banks when no channel filter is applied', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 10:00:00', 300_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01');

    expect($report['cash_total'])->toBe(100_000.0)
        ->and($report['bank_total'])->toBe(500_000.0)
        ->and($report['grand_total'])->toBe(600_000.0)
        ->and($report['transaction_count'])->toBe(3)
        ->and(collect($report['bank_sections'])->pluck('bank_id')->all())->toEqualCanonicalizing([$bankA->id, $bankB->id])
        ->and(count($report['sections']))->toBe(3);
});

it('carries the selected bank channel into the export links', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $defaultHtml = Livewire::test(SchoolDailyReport::class, [
        'activeTab' => 'bank',
        'bankStartDate' => '2026-09-01',
        'bankEndDate' => '2026-09-01',
    ])->html();

    $cashHtml = Livewire::test(SchoolDailyReport::class, [
        'activeTab' => 'bank',
        'bankStartDate' => '2026-09-01',
        'bankEndDate' => '2026-09-01',
    ])->set('bankFilter', 'cash')->html();

    expect($defaultHtml)
        ->toContain('&amp;bank=all')
        ->and($cashHtml)->toContain('&amp;bank=cash');
});

it('applies a single bank filter across student daycare and prospective payments', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);
    createBankRecapProspectivePayment(ProspectiveStudent::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 10:00:00', 300_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 11:00:00', 999_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', (string) $bankA->id);

    expect($report['transaction_count'])->toBe(3)
        ->and($report['grand_total'])->toBe(600_000.0)
        ->and(collect($report['detail_rows'])->pluck('source')->all())->toEqualCanonicalizing([
            SchoolBankRecapService::SOURCE_STUDENT,
            SchoolBankRecapService::SOURCE_DAYCARE,
            SchoolBankRecapService::SOURCE_PROSPECTIVE,
        ])
        ->and(collect($report['detail_rows'])->pluck('bank_id')->unique()->all())->toBe([$bankA->id]);
});

it('applies the cash channel across every payment source', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);
    createBankRecapProspectivePayment(ProspectiveStudent::factory()->create(), $cash, $user, '2026-09-01', '2026-09-02 10:00:00', 300_000);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 11:00:00', 999_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', 'cash');

    expect($report['transaction_count'])->toBe(3)
        ->and($report['grand_total'])->toBe(600_000.0)
        ->and(collect($report['bank_sections']))->toBeEmpty()
        ->and(collect($report['detail_rows'])->pluck('source')->all())->toEqualCanonicalizing([
            SchoolBankRecapService::SOURCE_STUDENT,
            SchoolBankRecapService::SOURCE_DAYCARE,
            SchoolBankRecapService::SOURCE_PROSPECTIVE,
        ]);
});

it('shows student daycare and prospective source badges for the selected bank', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa BSI']), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(['nama_lengkap' => 'Anak BSI']), $bankA, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);
    createBankRecapProspectivePayment(ProspectiveStudent::factory()->create(['nama_lengkap' => 'Calon BSI']), $bankA, $user, '2026-09-01', '2026-09-02 10:00:00', 300_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Mandiri']), $bankB, $user, '2026-09-01', '2026-09-02 11:00:00', 999_000);

    Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'bank',
            'bankStartDate' => '2026-09-01',
            'bankEndDate' => '2026-09-01',
        ])
        ->set('bankFilter', (string) $bankA->id)
        ->assertSee('Siswa BSI')
        ->assertSee('Anak BSI')
        ->assertSee('Calon BSI')
        ->assertSeeHtml('>SISWA<')
        ->assertSeeHtml('>DAYCARE<')
        ->assertSeeHtml('>CALON SISWA<')
        ->assertDontSee('Siswa Mandiri');
});

it('combines the bank channel filter with the date range', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-05', '2026-09-05 09:00:00', 200_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 10:00:00', 500_000);

    $fullRange = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-05', (string) $bankA->id);
    $subRange = app(SchoolBankRecapService::class)->generate('2026-09-03', '2026-09-05', (string) $bankA->id);
    $noFilter = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-05');

    expect($fullRange['transaction_count'])->toBe(2)
        ->and($fullRange['grand_total'])->toBe(300_000.0)
        ->and($subRange['transaction_count'])->toBe(1)
        ->and($subRange['grand_total'])->toBe(200_000.0)
        ->and($noFilter['transaction_count'])->toBe(3)
        ->and($noFilter['grand_total'])->toBe(800_000.0);
});

it('applies the bank channel together with the applied date range in the ui', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222']);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 100_000);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-05', '2026-09-05 09:00:00', 200_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 10:00:00', 500_000);

    Livewire::actingAs($user)
        ->test(SchoolDailyReport::class, [
            'activeTab' => 'bank',
            'bankStartDate' => '2026-09-01',
            'bankEndDate' => '2026-09-05',
        ])
        ->set('bankFilter', (string) $bankA->id)
        ->assertSee('Rp 300.000')
        ->assertDontSee('Rp 500.000');
});

it('exports the cash channel recap as xlsx', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapDaycarePayment(DaycareChild::factory()->create(['nama_lengkap' => 'Anak Tunai']), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 300_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Transfer']), $bank, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', 'cash');
    $path = app(SchoolBankRecapSpreadsheet::class)->create($report);

    try {
        $values = readBankRecapWorkbookValues($path);
    } finally {
        unlink($path);
    }

    expect($values)
        ->toContain('Bank / Channel')
        ->toContain('Tunai / Cash')
        ->toContain('TOTAL TUNAI / CASH')
        ->toContain('Anak Tunai')
        ->toContain('300000')
        ->not->toContain('Siswa Transfer')
        ->not->toContain('BSI')
        ->not->toContain('200000');
});

it('exports a specific bank recap as xlsx', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'Bank Ekspor Utama', 'account_number' => null]);
    $bankB = Bank::factory()->create(['name' => 'Bank Ekspor Lain', 'account_number' => null]);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Utama']), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 425_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Lain']), $bankB, $user, '2026-09-01', '2026-09-02 09:00:00', 900_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', (string) $bankA->id);
    $path = app(SchoolBankRecapSpreadsheet::class)->create($report);

    try {
        $values = readBankRecapWorkbookValues($path);
    } finally {
        unlink($path);
    }

    expect($values)
        ->toContain('Bank / Channel')
        ->toContain('Bank Ekspor Utama')
        ->toContain('TOTAL Bank Ekspor Utama')
        ->toContain('Siswa Utama')
        ->toContain('425000')
        ->not->toContain('Bank Ekspor Lain')
        ->not->toContain('Siswa Lain')
        ->not->toContain('900000');
});

it('omits the bank channel row from the default all-banks excel export', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(), $bank, $user, '2026-09-01', '2026-09-02 08:00:00', 200_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01');
    $path = app(SchoolBankRecapSpreadsheet::class)->create($report);

    try {
        $values = readBankRecapWorkbookValues($path);
    } finally {
        unlink($path);
    }

    expect($values)
        ->not->toContain('Bank / Channel')
        ->toContain('200000');
});

it('applies the cash channel to the exported pdf', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'Loket Sekolah']);
    $bank = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Tunai']), $cash, $user, '2026-09-01', '2026-09-02 08:00:00', 300_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Transfer']), $bank, $user, '2026-09-01', '2026-09-02 09:00:00', 200_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', 'cash');
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('Bank / Channel</td>')
        ->toContain('>Tunai / Cash</td>')
        ->toContain('TUNAI / CASH')
        ->toContain('>TOTAL TUNAI / CASH</td>')
        ->toContain('>Rp 300.000</td>')
        ->toContain('>Siswa Tunai</td>')
        ->not->toContain('Siswa Transfer')
        ->not->toContain('BSI')
        ->not->toContain('Rp 200.000');
});

it('applies a selected bank to the exported pdf', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'Bank Pdf Utama', 'account_number' => null]);
    $bankB = Bank::factory()->create(['name' => 'Bank Pdf Lain', 'account_number' => null]);

    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Pdf Utama']), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 425_000);
    createBankRecapPayment(Student::factory()->create(['nama_lengkap' => 'Siswa Pdf Lain']), $bankB, $user, '2026-09-01', '2026-09-02 09:00:00', 900_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', (string) $bankA->id);
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();

    expect($pdfHtml)
        ->toContain('Bank / Channel</td>')
        ->toContain('>Bank Pdf Utama</td>')
        ->toContain('>TOTAL BANK PDF UTAMA</td>')
        ->toContain('>Rp 425.000</td>')
        ->toContain('>Siswa Pdf Utama</td>')
        ->not->toContain('>Siswa Pdf Lain</td>')
        ->not->toContain('>TOTAL BANK PDF LAIN</td>')
        ->not->toContain('>Rp 900.000</td>');
});

it('keeps ui pdf and excel totals consistent for a filtered bank', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'Bank Konsisten', 'account_number' => null]);
    $bankB = Bank::factory()->create(['name' => 'Bank Lain', 'account_number' => null]);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 425_000);
    createBankRecapPayment(Student::factory()->create(), $bankB, $user, '2026-09-01', '2026-09-02 09:00:00', 900_000);

    $report = app(SchoolBankRecapService::class)->generate('2026-09-01', '2026-09-01', (string) $bankA->id);
    $document = ['city_and_date' => 'Bekasi, 1 September 2026', 'creator_name' => $user->name];
    $pdfHtml = view('reports.school-bank-recap-pdf', compact('report', 'document'))->render();
    $path = app(SchoolBankRecapSpreadsheet::class)->create($report);

    try {
        $values = readBankRecapWorkbookValues($path);
    } finally {
        unlink($path);
    }

    expect($report['grand_total'])->toBe(425_000.0)
        ->and($report['transaction_count'])->toBe(1)
        ->and($values)->toContain('425000')
        ->and($pdfHtml)->toContain('>Rp 425.000</td>')
        ->and($values)->not->toContain('900000')
        ->and($pdfHtml)->not->toContain('>Rp 900.000</td>');
});

it('export routes honor the bank channel parameter', function () {
    $user = User::factory()->create();
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    createBankRecapPayment(Student::factory()->create(), $bankA, $user, '2026-09-01', '2026-09-02 08:00:00', 200_000);
    $this->actingAs($user);

    $this->get(route('laporan.bank.pdf', ['start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'bank' => 'cash']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('laporan.bank.export', ['start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'bank' => (string) $bankA->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->get(route('laporan.bank.export', ['start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'bank' => '999999']))
        ->assertOk();
});
