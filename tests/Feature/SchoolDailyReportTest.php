<?php

use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\SchoolDailyReportService;
use App\Services\SchoolDailyReportSpreadsheet;
use App\Support\SchoolReportLevel;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

it('aggregates active school payment details by channel bank and normalized category', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Laporan']);
    $cash = Bank::factory()->cash()->create();
    $firstBank = Bank::factory()->create([
        'name' => 'BSI',
        'account_number' => '111111',
    ]);
    $secondBank = Bank::factory()->create([
        'name' => 'BSI',
        'account_number' => '222222',
    ]);
    $infaq = makeBillType('Infaq');
    $spp = makeBillType('SPP Laporan');
    $sppBill = makeMonthlyBill($student, $spp, 300_000, 8, 2026);

    createSchoolDailyReportPayment($student, $cash, $user, $date, [
        ['payment_type_id' => $infaq->id, 'amount' => 100_000],
        ['payment_type_id' => $spp->id, 'amount' => 50_000],
    ], headerTotal: 999_999);
    createSchoolDailyReportPayment($student, $cash, $user, $date, [
        ['payment_type_id' => null, 'description' => '  infaq  ', 'amount' => 200_000],
    ], Payment::KIND_MANUAL);
    createSchoolDailyReportPayment($student, $firstBank, $user, $date, [
        ['bill_id' => $sppBill->id, 'payment_type_id' => null, 'period_month' => 8, 'period_year' => 2026, 'amount' => 300_000],
    ]);
    createSchoolDailyReportPayment($student, $secondBank, $user, $date, [
        ['payment_type_id' => null, 'description' => 'Dana Kegiatan', 'amount' => 400_000],
    ], Payment::KIND_MANUAL);

    createSchoolDailyReportPayment($student, $cash, $user, $date, [
        ['payment_type_id' => $infaq->id, 'amount' => 500_000],
    ], status: Payment::STATUS_CANCELLED);
    createSchoolDailyReportPayment($student, $cash, $user, '2026-08-26', [
        ['payment_type_id' => $infaq->id, 'amount' => 600_000],
    ]);

    $daycarePayment = DaycarePayment::factory()->create([
        'bank_id' => $cash->id,
        'payment_date' => $date,
        'total_amount' => 700_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycarePayment->id,
        'description' => 'Daycare Tunai',
        'amount' => 700_000,
    ]);

    $report = app(SchoolDailyReportService::class)->generate($date);
    $cashCategories = collect($report['channels']['cash']['banks'][0]['categories'])->keyBy('key');
    $transferBankIds = collect($report['channels']['transfer']['banks'])->pluck('bank_id')->all();

    expect($report['is_single_day'])->toBeTrue()
        ->and($report['period_title'])->toBe('Tanggal')
        ->and($report['period_label'])->toBe('27 Agustus 2026')
        ->and($report['transaction_count'])->toBe(4)
        ->and($report['detail_count'])->toBe(5)
        ->and($report['channels']['cash']['total'])->toBe(350_000.0)
        ->and($report['channels']['transfer']['total'])->toBe(700_000.0)
        ->and($report['grand_total'])->toBe(1_050_000.0)
        ->and(collect($report['detail_rows'])->sum('amount'))->toBe(1_050_000.0)
        ->and($cashCategories['infaq']['total'])->toBe(300_000.0)
        ->and($cashCategories['infaq']['details'])->toHaveCount(2)
        ->and($transferBankIds)->toContain($firstBank->id, $secondBank->id)
        ->and($transferBankIds)->toHaveCount(2)
        ->and(collect($report['detail_rows'])->pluck('category_name'))->toContain('SPP Laporan')
        ->and(collect($report['detail_rows'])->pluck('detail_label'))->toContain('SPP Laporan Agustus')
        ->and(collect($report['detail_rows'])->pluck('detail_label'))->not->toContain('Daycare Tunai');
});

it('aggregates inclusive student range boundaries into one cash and bank report', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $spp = makeBillType('SPP Rentang');
    $ekskul = makeBillType('Ekskul Rentang');

    createSchoolDailyReportPayment($student, $cash, $user, '2026-08-25', [
        ['payment_type_id' => $spp->id, 'amount' => 900_000],
    ]);
    createSchoolDailyReportPayment($student, $cash, $user, '2026-08-26', [
        ['payment_type_id' => $spp->id, 'amount' => 100_000],
    ]);
    createSchoolDailyReportPayment($student, $cash, $user, '2026-08-27', [
        ['payment_type_id' => $spp->id, 'amount' => 200_000],
    ]);
    createSchoolDailyReportPayment($student, $bsi, $user, '2026-08-28', [
        ['payment_type_id' => $ekskul->id, 'amount' => 300_000],
    ]);
    createSchoolDailyReportPayment($student, $bsi, $user, '2026-08-29', [
        ['payment_type_id' => $ekskul->id, 'amount' => 800_000],
    ]);

    $report = app(SchoolDailyReportService::class)->generate('2026-08-26', '2026-08-28');
    $cashCategory = collect($report['channels']['cash']['banks'][0]['categories'])->firstWhere('name', 'SPP Rentang');
    $bankCategory = collect($report['channels']['transfer']['banks'][0]['categories'])->firstWhere('name', 'Ekskul Rentang');

    expect($report['is_single_day'])->toBeFalse()
        ->and($report['period_title'])->toBe('Periode')
        ->and($report['period_label'])->toBe('26 Agustus 2026 s.d. 28 Agustus 2026')
        ->and($report['transaction_count'])->toBe(3)
        ->and($report['detail_count'])->toBe(3)
        ->and($report['channels']['cash']['total'])->toBe(300_000.0)
        ->and($report['channels']['transfer']['total'])->toBe(300_000.0)
        ->and($cashCategory['total'])->toBe(300_000.0)
        ->and($cashCategory['details'])->toHaveCount(2)
        ->and($bankCategory['total'])->toBe(300_000.0)
        ->and($bankCategory['details'])->toHaveCount(1)
        ->and($report['grand_total'])->toBe(600_000.0);

    $component = Livewire::test(SchoolDailyReport::class, [
        'reportStartDate' => '2026-08-26',
        'reportEndDate' => '2026-08-28',
    ])
        ->assertSee('26 Agustus 2026 s.d. 28 Agustus 2026')
        ->assertSee('Rp 600.000');

    expect($component->viewData('report')['grand_total'])->toBe(600_000.0);
});

it('protects the report page and spreadsheet endpoint with authentication', function () {
    $this->get(route('laporan.index'))->assertRedirect(route('login'));
    $this->get(route('laporan.harian.export', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27']))
        ->assertRedirect(route('login'));
});

it('renders the daily empty state and the monthly empty state', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SchoolDailyReport::class, ['reportDate' => '2026-08-27', 'reportMonth' => 8, 'reportYear' => 2026])
        ->assertSee('Laporan Harian')
        ->assertSee('Belum ada transaksi sekolah pada periode ini.')
        ->assertSee('Rp 0')
        ->call('setActiveTab', 'monthly')
        ->assertSet('activeTab', 'monthly')
        ->assertSee('Periode Laporan')
        ->assertSee('Belum ada transaksi sekolah pada bulan ini.')
        ->assertSee('Rp 0');
});

it('uses the shared responsive report layout across all student report tabs', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(SchoolDailyReport::class, ['reportDate' => '2026-08-27']);

    expect($component->html())
        ->toContain('annur-report-page')
        ->toContain('sm:grid-cols-2 lg:grid-cols-3')
        ->toContain('sm:grid-cols-2 xl:grid-cols-3')
        ->toContain('h-11 w-full')
        ->not->toContain('Tampilkan')
        ->toContain('overflow-x-auto');

    $component->call('setActiveTab', 'monthly');

    expect($component->html())
        ->toContain('sm:grid-cols-2 lg:grid-cols-3')
        ->not->toContain('Tampilkan');

    $component->call('setActiveTab', 'bank');

    expect($component->html())
        ->toContain('sm:grid-cols-2 lg:grid-cols-3')
        ->not->toContain('Tampilkan')
        ->toContain('sm:grid-cols-2 xl:grid-cols-3');
});

it('loads report drill-down data without per-payment queries', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $bank = Bank::factory()->create();
    $paymentType = makeBillType('Query Test');

    foreach (range(1, 15) as $index) {
        createSchoolDailyReportPayment($student, $bank, $user, '2026-08-27', [
            ['payment_type_id' => $paymentType->id, 'amount' => $index * 1_000],
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $report = app(SchoolDailyReportService::class)->generate('2026-08-27');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($report['detail_rows'])->toHaveCount(15)
        ->and($queryCount)->toBeLessThanOrEqual(9);
});

it('exports reconciled range summary and PaymentDetail audit sheets with numeric amounts', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Excel']);
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BCA Laporan']);
    $spp = makeBillType('SPP Excel');
    $infaq = makeBillType('Infaq Excel');

    createSchoolDailyReportPayment($student, $cash, $user, '2026-08-26', [
        ['payment_type_id' => $spp->id, 'amount' => 125_000],
        ['payment_type_id' => $infaq->id, 'amount' => 75_000],
    ], headerTotal: 900_000);
    createSchoolDailyReportPayment($student, $bank, $user, '2026-08-28', [
        ['payment_type_id' => null, 'description' => 'Kegiatan Excel', 'amount' => 300_000],
    ], Payment::KIND_MANUAL);

    $report = app(SchoolDailyReportService::class)->generate('2026-08-26', '2026-08-28');
    $path = app(SchoolDailyReportSpreadsheet::class)->create($report);
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    try {
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    $summaryGrandTotal = collect($sheets['Laporan Harian'])
        ->first(fn (array $row): bool => ($row[1] ?? null) === 'TOTAL PENERIMAAN')[2];
    $summaryPeriod = collect($sheets['Laporan Harian'])
        ->first(fn (array $row): bool => ($row[0] ?? null) === 'Periode')[1];
    $detailRows = collect($sheets['Rincian Transaksi']);
    $detailGrandTotal = $detailRows
        ->first(fn (array $row): bool => ($row[8] ?? null) === 'TOTAL')[9];
    $exportedDetailTotal = $detailRows
        ->filter(fn (array $row): bool => str_starts_with((string) ($row[2] ?? ''), 'KWT-RPT-'))
        ->sum(9);

    expect(array_keys($sheets))->toBe(['Laporan Harian', 'Rincian Transaksi'])
        ->and($summaryPeriod)->toBe('26 Agustus 2026 s.d. 28 Agustus 2026')
        ->and($summaryGrandTotal)->toBe(500_000)
        ->and($detailGrandTotal)->toBe(500_000)
        ->and($exportedDetailTotal)->toBe(500_000)
        ->and((float) $summaryGrandTotal)->toBe($report['grand_total'])
        ->and($detailRows->filter(fn (array $row): bool => str_starts_with((string) ($row[2] ?? ''), 'KWT-RPT-')))->toHaveCount(3)
        ->and($detailRows->first(fn (array $row): bool => str_starts_with((string) ($row[2] ?? ''), 'KWT-RPT-'))[9])->toBeInt();

    $this->actingAs($user)
        ->get(route('laporan.harian.export', ['start_date' => '2026-08-26', 'end_date' => '2026-08-28']))
        ->assertOk()
        ->assertDownload('laporan-harian-sekolah-2026-08-26-sampai-2026-08-28.xlsx')
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('filters daily payments to a specific jenjang while keeping Semua Jenjang intact', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Jenjang Harian');

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);
    [$smaStudent] = makeEnrolledStudent(SchoolLevel::SMA);

    createSchoolDailyReportPayment($sdStudent, $cash, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);
    createSchoolDailyReportPayment($smpStudent, $cash, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 200_000],
    ]);
    createSchoolDailyReportPayment($smaStudent, $cash, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);

    $all = app(SchoolDailyReportService::class)->generate($date);
    $sd = app(SchoolDailyReportService::class)->generate($date, SchoolLevel::SD);

    expect($all['grand_total'])->toBe(600_000.0)
        ->and($all['transaction_count'])->toBe(3)
        ->and($sd['grand_total'])->toBe(100_000.0)
        ->and($sd['transaction_count'])->toBe(1)
        ->and(collect($sd['detail_rows'])->pluck('student_name'))->toContain($sdStudent->nama_lengkap)
        ->and(collect($sd['detail_rows'])->pluck('student_name'))->not->toContain($smpStudent->nama_lengkap)
        ->and($sd['school_level'])->toBe(SchoolLevel::SD)
        ->and($sd['school_level_label'])->toBe('SD');
});

it('matches the Semua Jenjang scope to the unfiltered report reference', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BCA', 'account_number' => '111111']);
    $type = makeBillType('SPP Scope');

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);

    createSchoolDailyReportPayment($sdStudent, $cash, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 10_000],
    ]);
    createSchoolDailyReportPayment($smpStudent, $bank, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 20_000],
    ]);

    $reference = app(SchoolDailyReportService::class)->generate($date);
    $allScope = app(SchoolDailyReportService::class)->generate(
        $date,
        SchoolReportLevel::fromValue(SchoolReportLevel::OPTION_ALL)
    );

    expect($allScope)->toMatchArray([
        'grand_total' => 30_000.0,
        'detail_count' => 2,
        'school_level' => null,
        'school_level_label' => 'Semua Jenjang',
    ])->and($allScope['detail_rows'])->toEqual($reference['detail_rows']);
});

it('applies the jenjang filter to manual payments following the student class level', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);

    createSchoolDailyReportPayment($sdStudent, $cash, $user, $date, [
        ['payment_type_id' => null, 'description' => 'Dana Kegiatan SD', 'amount' => 40_000],
    ], Payment::KIND_MANUAL);
    createSchoolDailyReportPayment($smpStudent, $cash, $user, $date, [
        ['payment_type_id' => null, 'description' => 'Dana Kegiatan SMP', 'amount' => 90_000],
    ], Payment::KIND_MANUAL);

    $sd = app(SchoolDailyReportService::class)->generate($date, SchoolLevel::SD);
    $smp = app(SchoolDailyReportService::class)->generate($date, SchoolLevel::SMP);

    expect($sd['grand_total'])->toBe(40_000.0)
        ->and(collect($sd['detail_rows'])->pluck('detail_label'))->toContain('Dana Kegiatan SD')
        ->and(collect($sd['detail_rows'])->pluck('detail_label'))->not->toContain('Dana Kegiatan SMP')
        ->and($smp['grand_total'])->toBe(90_000.0);
});

it('excludes cancelled payments and daycare transactions from a jenjang scope', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);

    createSchoolDailyReportPayment($sdStudent, $cash, $user, $date, [
        ['payment_type_id' => null, 'description' => 'Dibatalkan', 'amount' => 25_000],
    ], status: Payment::STATUS_CANCELLED);

    $daycare = DaycarePayment::factory()->create([
        'bank_id' => $cash->id,
        'payment_date' => $date,
        'total_amount' => 700_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycare->id,
        'description' => 'Daycare Harian',
        'amount' => 700_000,
    ]);

    $sd = app(SchoolDailyReportService::class)->generate($date, SchoolLevel::SD);

    expect($sd['grand_total'])->toBe(0)
        ->and($sd['detail_count'])->toBe(0)
        ->and(collect($sd['detail_rows'])->pluck('detail_label'))->not->toContain('Daycare Harian');
});

it('classifies historical payments by the enrollment active at payment date not the current class', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Promosi');

    $smpClass = SchoolClass::factory()->create(['level' => 8]);
    $sdClass = SchoolClass::factory()->create(['level' => 5]);
    $student = Student::factory()->create(['class_id' => $smpClass->id, 'nama_lengkap' => 'Siswa Promosi']);

    $lastYear = AcademicYear::firstOrCreate(
        ['year' => '2025/2026'],
        ['is_active' => false, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']
    );
    $currentYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $lastYear->id,
        'school_class_id' => $sdClass->id,
        'status' => 'active',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $currentYear->id,
        'school_class_id' => $smpClass->id,
        'status' => 'active',
    ]);

    createSchoolDailyReportPayment($student, $cash, $user, '2026-01-15', [
        ['payment_type_id' => $type->id, 'amount' => 60_000],
    ]);
    createSchoolDailyReportPayment($student, $cash, $user, '2026-08-15', [
        ['payment_type_id' => $type->id, 'amount' => 70_000],
    ]);

    $sdJanuary = app(SchoolDailyReportService::class)->generate('2026-01-15', SchoolLevel::SD);
    $sdAugust = app(SchoolDailyReportService::class)->generate('2026-08-15', SchoolLevel::SD);
    $smpJanuary = app(SchoolDailyReportService::class)->generate('2026-01-15', SchoolLevel::SMP);
    $smpAugust = app(SchoolDailyReportService::class)->generate('2026-08-15', SchoolLevel::SMP);

    expect($sdJanuary['grand_total'])->toBe(60_000.0)
        ->and($smpJanuary['grand_total'])->toBe(0)
        ->and($smpAugust['grand_total'])->toBe(70_000.0)
        ->and($sdAugust['grand_total'])->toBe(0);
});

it('scopes channel bank and grand totals to the selected jenjang', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BNI', 'account_number' => '111111']);
    $type = makeBillType('SPP Kanal Jenjang');

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);

    createSchoolDailyReportPayment($sdStudent, $cash, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);
    createSchoolDailyReportPayment($sdStudent, $bank, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 200_000],
    ]);
    createSchoolDailyReportPayment($smpStudent, $bank, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);

    $sd = app(SchoolDailyReportService::class)->generate($date, SchoolLevel::SD);
    $sdBankTotal = collect($sd['channels']['transfer']['banks'])->firstWhere('bank_id', $bank->id)['total'];

    expect($sd['channels']['cash']['total'])->toBe(100_000.0)
        ->and($sd['channels']['transfer']['total'])->toBe(200_000.0)
        ->and($sdBankTotal)->toBe(200_000.0)
        ->and($sd['grand_total'])->toBe(300_000.0)
        ->and($sd['transaction_count'])->toBe(2);
});

it('exports the level-scoped daily workbook with a matching Unit row and no Jenjang metadata', function () {
    $date = '2026-08-27';
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Excel Jenjang');

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);

    createSchoolDailyReportPayment($sdStudent, $cash, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 120_000],
    ]);
    createSchoolDailyReportPayment($smpStudent, $cash, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => 220_000],
    ]);

    $report = app(SchoolDailyReportService::class)->generate($date, SchoolLevel::SD);
    $path = app(SchoolDailyReportSpreadsheet::class)->create($report);
    $reader = new Reader;
    $reader->open($path);

    try {
        $sheet = $reader->getSheetIterator()->current();
        $rows = collect(iterator_to_array($sheet->getRowIterator()))
            ->map(fn ($row): array => $row->toArray())
            ->all();
    } finally {
        $reader->close();
        unlink($path);
    }

    $unitRow = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'Unit');
    $jenjangRow = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'Jenjang');

    expect($unitRow)->not->toBeNull()
        ->and($unitRow[1])->toBe('SD An-Nur')
        ->and($jenjangRow)->toBeNull();
});

it('validates the jenjang filter on the daily endpoints', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('laporan.harian.export', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27', 'school_level' => 'SLTP']))
        ->assertSessionHasErrors('school_level');
    $this->actingAs(User::factory()->create())
        ->get(route('laporan.harian.pdf', ['start_date' => '2026-08-27', 'end_date' => '2026-08-27', 'school_level' => 'bogus']))
        ->assertSessionHasErrors('school_level');
});

it('rejects a student report end date before its start date', function () {
    Livewire::test(SchoolDailyReport::class, [
        'reportStartDate' => '2026-08-28',
        'reportEndDate' => '2026-08-28',
    ])
        ->set('reportEndDate', '2026-08-27')
        ->assertHasErrors(['reportEndDate' => 'after_or_equal'])
        ->assertSee('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');

    $this->actingAs(User::factory()->create())
        ->get(route('laporan.harian.export', [
            'start_date' => '2026-08-28',
            'end_date' => '2026-08-27',
        ]))
        ->assertSessionHasErrors('end_date');
});

it('wires the jenjang filter on the livewire report page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SchoolDailyReport::class, ['reportDate' => '2026-08-27', 'reportMonth' => 8, 'reportYear' => 2026])
        ->assertSet('schoolLevel', 'all')
        ->assertSee('Dari Tanggal')
        ->assertSee('Sampai Tanggal')
        ->assertSee('Tanggal:')
        ->assertSee('27 Agustus 2026')
        ->assertSee('Semua Jenjang')
        ->assertSee('Laporan Harian')
        ->call('setActiveTab', 'monthly')
        ->assertSee('Jenjang')
        ->assertSee('laporan/bulanan.xlsx')
        ->assertSee('laporan/bulanan/pdf')
        ->set('schoolLevel', 'SMP')
        ->assertSet('schoolLevel', 'SMP');
});
