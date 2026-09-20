<?php

use App\Enums\SchoolLevel;
use App\Livewire\PaymentIndex;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DashboardOperationalMetricsService;
use App\Services\ReportYearOptionsService;
use App\Services\SchoolBankRecapService;
use App\Services\SchoolDailyReportService;
use App\Services\SchoolMonthlyAllUnitsReportService;
use App\Services\SchoolMonthlyByLevelReportService;
use App\Services\SchoolMonthlyReportService;
use App\Services\TransactionHistoryService;
use Livewire\Livewire;

function makeProspectiveReportPayment(
    ProspectiveStudent $prospect,
    Bank $bank,
    PaymentType $type,
    string $createdAt,
    int $amount = 100000,
    string $paymentDate = '2026-09-10',
    string $status = ProspectiveStudentPayment::STATUS_ACTIVE,
): ProspectiveStudentPayment {
    $typeId = $type->id;
    $bill = ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $prospect->id,
        'payment_type_id' => $typeId,
        'amount' => $amount,
        'academic_year' => '2027/2028',
    ]);

    $payment = ProspectiveStudentPayment::query()->create([
        'receipt_number' => 'KWT-REG-INTEG-'.uniqid(),
        'prospective_student_id' => $prospect->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => $amount,
        'status' => $status,
        'created_by' => User::factory()->create()->id,
    ]);

    ProspectiveStudentPaymentDetail::query()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $typeId,
        'amount' => $amount,
    ]);

    $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

    return $payment->refresh();
}

function makeProspectiveReportProspect(int $classLevel, string $nama): ProspectiveStudent
{
    $class = SchoolClass::factory()->create(['level' => $classLevel]);

    return ProspectiveStudent::factory()->create([
        'nama_lengkap' => $nama,
        'nama_panggilan' => $nama,
        'school_class_id' => $class->id,
    ]);
}

it('includes active prospective payments as prospect detail rows in the daily report', function () {
    $prospect = makeProspectiveReportProspect(8, 'Budi Calon SMP');
    $bank = Bank::factory()->create(['name' => 'BCA Integrasi']);
    $type = PaymentType::factory()->create(['name' => 'Daftar Ulang Integrasi']);
    makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-15 08:00:00', 250000);

    $report = app(SchoolDailyReportService::class)->generate('2026-09-15');

    $prospectRows = collect($report['detail_rows'])->where('source', 'prospective')->values();

    expect($report['grand_total'])->toBe(250000.0)
        ->and($report['transaction_count'])->toBe(1)
        ->and($prospectRows)->toHaveCount(1);
    $row = $prospectRows->first();

    expect($row['student_name'])->toBe('Budi Calon SMP')
        ->and($row['detail_label'])->toBe('Daftar Ulang Integrasi')
        ->and($row['amount'])->toBe(250000.0)
        ->and($row['payment_id'])->toBeString()
        ->and($row['payment_id'])->toStartWith('prospect-')
        ->and($row['report_level'])->toBe(SchoolLevel::SMP->value)
        ->and($row['source'])->toBe('prospective');
});

it('excludes cancelled prospect payments from the daily report', function () {
    $prospect = makeProspectiveReportProspect(8, 'Siti Batal Bayar');
    $bank = Bank::factory()->create();
    $type = PaymentType::factory()->create(['name' => 'Pendaftaran Batal']);
    makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-15 09:00:00', 500000, status: ProspectiveStudentPayment::STATUS_CANCELLED);

    $report = app(SchoolDailyReportService::class)->generate('2026-09-15');

    expect(collect($report['detail_rows'])->where('source', 'prospective'))->toHaveCount(0)
        ->and($report['grand_total'])->toBe(0);
});

it('uses recorded created_at for the daily report and payment basis for bank recap', function () {
    $prospect = makeProspectiveReportProspect(8, 'Rina Dua Tanggal');
    $bank = Bank::factory()->create();
    $type = PaymentType::factory()->create(['name' => 'Formulir Dua Tanggal']);
    $payment = makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-02 10:00:00', 300000, paymentDate: '2026-09-01');

    expect(collect(app(SchoolDailyReportService::class)->generate('2026-09-01')['detail_rows'])->pluck('payment_id'))->not->toContain('prospect-'.$payment->id)
        ->and(collect(app(SchoolDailyReportService::class)->generate('2026-09-02')['detail_rows'])->pluck('payment_id'))->toContain('prospect-'.$payment->id)
        ->and(collect(app(SchoolBankRecapService::class)->generate('2026-09-01')['detail_rows'])->pluck('payment_id'))->toContain($payment->id)
        ->and(collect(app(SchoolBankRecapService::class)->generate('2026-09-02')['detail_rows'])->pluck('payment_id'))->not->toContain($payment->id);
});

it('filters prospect rows by target class level in the daily report', function () {
    $smp = makeProspectiveReportProspect(8, 'Andi Jenjang SMP');
    $sd = makeProspectiveReportProspect(3, 'Bima Jenjang SD');
    $bank = Bank::factory()->create();
    $type = PaymentType::factory()->create(['name' => 'Pendaftaran Jenjang']);
    makeProspectiveReportPayment($smp, $bank, $type, '2026-09-15 07:00:00', 110000);
    makeProspectiveReportPayment($sd, $bank, $type, '2026-09-15 07:30:00', 120000);

    $smpReport = app(SchoolDailyReportService::class)->generate('2026-09-15', SchoolLevel::SMP);
    $sdReport = app(SchoolDailyReportService::class)->generate('2026-09-15', SchoolLevel::SD);

    expect(collect($smpReport['detail_rows'])->pluck('student_name')->all())->toBe(['Andi Jenjang SMP'])
        ->and(collect($sdReport['detail_rows'])->pluck('student_name')->all())->toBe(['Bima Jenjang SD']);
});

it('includes prospective payments in the monthly report with payment_kind and bank_type', function () {
    $prospect = makeProspectiveReportProspect(8, 'Caca Bulanan');
    $bank = Bank::factory()->create(['name' => 'Mandiri Bulanan']);
    $type = PaymentType::factory()->create(['name' => 'SPP Calon Bulanan']);
    makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-20 09:00:00', 175000);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 9);

    $row = collect($report['detail_rows'])->where('source', 'prospective')->first();

    expect($report['grand_total'])->toBe(175000.0)
        ->and($row['payment_kind'])->toBe('bill')
        ->and($row['bank_type'])->toBe(Bank::TYPE_BANK);
});

it('includes prospective totals in all-units and by-level monthly reports', function () {
    $prospect = makeProspectiveReportProspect(8, 'Dedi Semua Unit');
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BNI Jenjang']);
    $type = PaymentType::factory()->create(['name' => 'Uang Pangkal Jenjang']);
    makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-21 09:00:00', 200000);

    $allUnits = app(SchoolMonthlyAllUnitsReportService::class)->generate(2026, 9, SchoolLevel::SMP);
    $byLevel = app(SchoolMonthlyByLevelReportService::class)->generate(2026, 9);

    expect($allUnits['grand_total'])->toBe(200000.0);
    $smpLevel = collect($byLevel['bank']['levels'])->firstWhere('level_key', SchoolLevel::SMP->value);
    expect($smpLevel)->not->toBeNull()
        ->and($smpLevel['bank_total'])->toBe(200000.0);
    $nullCash = collect($byLevel['cash']['levels'])->map->level_key;

    expect($nullCash)->not->toContain(null);
});

it('adds prospective payments into bank recap sections and grand total', function () {
    $prospect = makeProspectiveReportProspect(8, 'Eko Rekap Bank');
    $bank = Bank::factory()->create(['name' => 'BCA Rekap']);
    $type = PaymentType::factory()->create(['name' => 'Pendaftaran Rekap']);
    makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-15 08:00:00', 310000, paymentDate: '2026-09-15');

    $recap = app(SchoolBankRecapService::class)->generate('2026-09-15');

    $prospectRow = collect($recap['detail_rows'])->firstWhere('source', SchoolBankRecapService::SOURCE_PROSPECTIVE);

    expect($recap['grand_total'])->toBe(310000.0)
        ->and($recap['transaction_count'])->toBe(1)
        ->and($prospectRow['name'])->toBe('Eko Rekap Bank')
        ->and($prospectRow['amount'])->toBe(310000.0)
        ->and($prospectRow['bank_label'])->toBe($bank->optionLabel());
});

it('shows prospective rows in the transaction history with a namespaced id and prospective source', function () {
    $prospect = makeProspectiveReportProspect(8, 'Fajar Riwayat');
    $bank = Bank::factory()->create(['name' => 'BNI Riwayat']);
    $type = PaymentType::factory()->create(['name' => 'Formulir Riwayat']);
    $payment = makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-15 09:00:00', 220000);

    $result = app(TransactionHistoryService::class)->getHistory();

    $row = collect($result->items())->firstWhere('id', 'prospect-'.$payment->id);

    expect($row)->not->toBeNull()
        ->and($row->name)->toBe('Fajar Riwayat')
        ->and($row->source)->toBe('prospective')
        ->and($row->detailUrl)->toContain(route('pembayaran.prospective.show', $payment))
        ->and($row->editUrl)->not->toBeNull();
});

it('searches prospective payments by prospect name and registration number', function () {
    $prospect = makeProspectiveReportProspect(8, 'Galih Dicari');
    $bank = Bank::factory()->create();
    $type = PaymentType::factory()->create(['name' => 'Pendaftaran Cari']);
    $payment = makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-15 09:00:00', 150000);

    $byName = app(TransactionHistoryService::class)->getHistory(search: 'Galih');
    $byRegistration = app(TransactionHistoryService::class)->getHistory(search: $prospect->registration_number);

    expect(collect($byName->items())->pluck('id'))->toContain('prospect-'.$payment->id)
        ->and(collect($byRegistration->items())->pluck('id'))->toContain('prospect-'.$payment->id);
});

it('excludes cancelled prospective payments from transaction history active filter', function () {
    $prospect = makeProspectiveReportProspect(8, 'Hana Dibatalkan');
    $bank = Bank::factory()->create();
    $type = PaymentType::factory()->create(['name' => 'Formulir Hapus']);
    $payment = makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-15 10:00:00', 250000, status: ProspectiveStudentPayment::STATUS_CANCELLED);

    $active = app(TransactionHistoryService::class)->getHistory(status: 'active');
    $cancelled = app(TransactionHistoryService::class)->getHistory(status: 'cancelled');

    expect(collect($active->items())->pluck('id'))->not->toContain('prospect-'.$payment->id)
        ->and(collect($cancelled->items())->pluck('id'))->toContain('prospect-'.$payment->id);
});

it('hides the delete button for prospective rows in the payment index', function () {
    $prospect = makeProspectiveReportProspect(8, 'Ira Tanpa Hapus');
    $bank = Bank::factory()->create(['name' => 'BCA Index']);
    $type = PaymentType::factory()->create(['name' => 'Pendaftaran Index']);
    $payment = makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-15 11:00:00', 180000);

    $component = Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history');
    $html = $component->html();

    expect($html)->toContain('Ira Tanpa Hapus')
        ->and($html)->not->toContain('confirmDelete(prospect-'.$payment->id.')');
});

it('includes prospective payments in dashboard money-in metrics but not for daycare unit', function () {
    $prospect = makeProspectiveReportProspect(8, 'Joko Dashboard');
    $bank = Bank::factory()->create(['name' => 'Mandiri Dashboard']);
    $type = PaymentType::factory()->create(['name' => 'Pendaftaran Dashboard']);
    makeProspectiveReportPayment($prospect, $bank, $type, '2026-09-15 08:00:00', 270000);

    $service = app(DashboardOperationalMetricsService::class);
    $all = $service->generate(DashboardOperationalMetricsService::UNIT_ALL, '2026-09-01', '2026-09-30');
    $smp = $service->generate(SchoolLevel::SMP->value, '2026-09-01', '2026-09-30');
    $smpBank = $service->generate(SchoolLevel::SMP->value, '2026-09-01', '2026-09-30');
    $daycare = $service->generate(DashboardOperationalMetricsService::UNIT_DAYCARE, '2026-09-01', '2026-09-30');

    expect($all['total_income'])->toBe(270000.0)
        ->and($all['prospective_transaction_count'])->toBe(1)
        ->and($smp['total_income'])->toBe(270000.0)
        ->and($smpBank['bank_totals'][$bank->id]['prospective_total'])->toBe(270000.0)
        ->and($smpBank['bank_totals'][$bank->id]['combined_total'])->toBe(270000.0)
        ->and($daycare['total_income'])->toBe(0.0);
});

it('adds prospective payment years to the report year options', function () {
    $prospect = makeProspectiveReportProspect(8, 'Kakak Tahun Lalu');
    $bank = Bank::factory()->create();
    $type = PaymentType::factory()->create(['name' => 'Pendaftaran Tahun']);
    makeProspectiveReportPayment($prospect, $bank, $type, '2025-03-10 08:00:00', 90000);

    $years = app(ReportYearOptionsService::class)->options();

    expect($years)->toContain(2025);
});

it('keeps student and daycare reports unchanged when no prospective payments exist', function () {
    $student = Student::factory()->create();
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'Giro Lama']);
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-REG-OLD-'.uniqid(),
        'payment_kind' => Payment::KIND_MANUAL,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-15',
        'total_amount' => 400000,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => $user->id,
    ]);
    $payment->details()->create([
        'description' => 'Pembayaran lama',
        'amount' => 400000,
    ]);
    $payment->forceFill(['created_at' => '2026-09-15 08:00:00', 'updated_at' => '2026-09-15 08:00:00'])->saveQuietly();

    $report = app(SchoolDailyReportService::class)->generate('2026-09-15');

    expect($report['grand_total'])->toBe(400000.0)
        ->and(collect($report['detail_rows'])->filter(fn (array $row): bool => ($row['source'] ?? 'student') === 'prospective'))->toHaveCount(0);
});
