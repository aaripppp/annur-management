<?php

use App\Enums\SchoolLevel;
use App\Livewire\SchoolDailyReport;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\SchoolMonthlyReportService;
use App\Support\SchoolReportLevel;
use Livewire\Livewire;

it('aggregates active school payment details by channel category and date matrix', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Bulanan']);
    $cash = Bank::factory()->cash()->create();
    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bri = Bank::factory()->create(['name' => 'BRI', 'account_number' => '222222']);
    $spp = makeBillType('SPP Bulanan');
    $infaq = makeBillType('Infaq Bulanan');
    $sppBill = makeMonthlyBill($student, $spp, 300_000, 8, 2026);

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-03', [
        ['payment_type_id' => $infaq->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bsi, $user, '2026-08-03', [
        ['bill_id' => $sppBill->id, 'payment_type_id' => null, 'period_month' => 8, 'period_year' => 2026, 'amount' => 300_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bri, $user, '2026-08-17', [
        ['payment_type_id' => $infaq->id, 'amount' => 50_000],
        ['payment_type_id' => null, 'description' => 'Kegiatan Bulanan', 'amount' => 25_000],
    ], Payment::KIND_MANUAL);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);

    expect($report['month_label_upper'])->toBe('AGUSTUS 2026')
        ->and($report['channels']['cash']['total'])->toBe(100_000.0)
        ->and($report['channels']['transfer']['total'])->toBe(375_000.0)
        ->and($report['grand_total'])->toBe(475_000.0)
        ->and(collect($report['detail_rows'])->sum('amount'))->toBe(475_000.0)
        ->and($report['transaction_count'])->toBe(3)
        ->and($report['detail_count'])->toBe(4)
        ->and($report['channels']['cash']['rows'])->toHaveCount(1)
        ->and($report['channels']['cash']['rows'][0]['day_name'])->toBe('Senin')
        ->and($report['channels']['transfer']['rows'])->toHaveCount(2)
        ->and($report['channels']['transfer']['rows'][1]['day_name'])->toBe('Senin')
        ->and(collect($report['detail_rows'])->pluck('payment_kind'))->toContain(Payment::KIND_MANUAL);
});

it('reconciles every active school detail amount into channel and grand totals', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BNI', 'account_number' => '333333']);
    $spp = makeBillType('SPP Rekonsiliasi');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-05', [
        ['payment_type_id' => $spp->id, 'amount' => 450_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-08-12', [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ], headerTotal: 999_999);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $sumDetailed = PaymentDetail::query()
        ->whereHas('payment', fn ($query) => $query
            ->where('status', Payment::STATUS_ACTIVE)
            ->whereDate('created_at', '>=', '2026-08-01')
            ->whereDate('created_at', '<=', '2026-08-31'))
        ->sum('amount');

    expect((float) $sumDetailed)->toBe($report['grand_total'])
        ->and($report['channels']['cash']['total'] + $report['channels']['transfer']['total'])->toBe($report['grand_total'])
        ->and($report['detail_rows'])->toHaveCount(2);
});

it('excludes cancelled and out-of-month payments from the report', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Pengecualian');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-10', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ], status: Payment::STATUS_CANCELLED);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-07-31', [
        ['payment_type_id' => $type->id, 'amount' => 200_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-01', [
        ['payment_type_id' => $type->id, 'amount' => 300_000],
    ]);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);

    expect($report['grand_total'])->toBe(0)
        ->and($report['detail_count'])->toBe(0);
});

it('builds the recorded-date bank payment-type matrix without double counting', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create(['name' => 'BSI']);
    $secondCash = Bank::factory()->cash()->create(['name' => 'Loket Tunai']);
    $bankA = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']);
    $bankNamedCash = Bank::factory()->create(['name' => 'Tunai', 'account_number' => '333333']);
    $spp = makeBillType('SPP Matriks Bank');
    $ekskul = makeBillType('Ekskul Matriks Bank');
    $osis = makeBillType('OSIS Matriks Bank');

    $mismatchedDatePayment = createSchoolMonthlyReportPayment($student, $bankA, $user, '2026-09-01', [
        ['payment_type_id' => $spp->id, 'amount' => 2_000_000],
        ['payment_type_id' => $ekskul->id, 'amount' => 300_000],
        ['payment_type_id' => $osis->id, 'amount' => 200_000],
    ], headerTotal: 9_999_999, paymentDate: '2026-08-31', recordedAt: '2026-09-01 00:30:00');
    createSchoolMonthlyReportPayment($student, $bankB, $user, '2026-09-01', [
        ['payment_type_id' => $spp->id, 'amount' => 1_500_000],
        ['payment_type_id' => $ekskul->id, 'amount' => 200_000],
        ['payment_type_id' => $osis->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-01', [
        ['payment_type_id' => $spp->id, 'amount' => 500_000],
        ['payment_type_id' => $ekskul->id, 'amount' => 100_000],
    ]);
    $mismatchedCashPayment = createSchoolMonthlyReportPayment($student, $secondCash, $user, '2026-09-01', [
        ['payment_type_id' => $osis->id, 'amount' => 50_000],
    ], paymentDate: '2026-08-31', recordedAt: '2026-09-01 01:00:00');
    createSchoolMonthlyReportPayment($student, $bankNamedCash, $user, '2026-09-02', [
        ['payment_type_id' => $spp->id, 'amount' => 700_000],
    ]);

    $paymentCount = Payment::query()->count();
    $report = app(SchoolMonthlyReportService::class)->generate(2026, 9);
    $augustReport = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    [$firstDate, $secondDate] = $report['bank']['dates'];
    [$firstCashDate] = $report['cash']['dates'];
    $firstDateBanks = collect($firstDate['banks']);
    $firstBsi = $firstDateBanks->firstWhere('bank_id', $bankA->id);
    $secondBsi = $firstDateBanks->firstWhere('bank_id', $bankB->id);
    $namedCashBankRow = collect($secondDate['banks'])->firstWhere('bank_id', $bankNamedCash->id);
    $categoryKeys = collect($report['categories'])->pluck('key', 'name');
    $sppKey = $categoryKeys['SPP Matriks Bank'];
    $ekskulKey = $categoryKeys['Ekskul Matriks Bank'];
    $osisKey = $categoryKeys['OSIS Matriks Bank'];
    $matrixTotal = collect($report['bank']['dates'])
        ->flatMap(fn (array $dateGroup): array => $dateGroup['banks'])
        ->sum('total') + collect($report['cash']['dates'])->sum('total');

    expect(array_keys($report['channels']))->toBe(['cash', 'transfer'])
        ->and($report['channels']['cash']['total'])->toBe(650_000.0)
        ->and($report['channels']['cash']['rows'])->toHaveCount(1)
        ->and($report['channels']['transfer']['total'])->toBe(5_000_000.0)
        ->and($report['bank']['dates'])->toHaveCount(2)
        ->and($report['cash']['dates'])->toHaveCount(1)
        ->and($report['overall']['dates'])->toHaveCount(2)
        ->and($firstDate['date']->toDateString())->toBe('2026-09-01')
        ->and($firstDate['date_label'])->toBe('Selasa, 01 September 2026')
        ->and(collect($report['detail_rows'])->firstWhere('payment_id', $mismatchedDatePayment->id)['payment_date']->toDateString())->toBe('2026-08-31')
        ->and(collect($report['detail_rows'])->firstWhere('payment_id', $mismatchedDatePayment->id)['recorded_at']->toDateString())->toBe('2026-09-01')
        ->and(collect($report['detail_rows'])->firstWhere('payment_id', $mismatchedCashPayment->id)['payment_date']->toDateString())->toBe('2026-08-31')
        ->and(collect($report['detail_rows'])->firstWhere('payment_id', $mismatchedCashPayment->id)['recorded_at']->toDateString())->toBe('2026-09-01')
        ->and(collect($augustReport['detail_rows'])->pluck('payment_id'))->not->toContain($mismatchedDatePayment->id, $mismatchedCashPayment->id)
        ->and($firstDate['subtotal_label'])->toBe('TOTAL 01 SEPTEMBER')
        ->and($firstDate['banks'])->toHaveCount(3)
        ->and($firstDate['bank_count'])->toBe(3)
        ->and($secondDate['banks'])->toHaveCount(3)
        ->and(collect($firstDate['banks'])->pluck('bank_id')->all())->toBe(collect($secondDate['banks'])->pluck('bank_id')->all())
        ->and(collect($firstDate['banks'])->pluck('bank_id')->all())->toBe([$bankA->id, $bankB->id, $bankNamedCash->id])
        ->and(collect($firstDate['banks'])->pluck('bank_id'))->not->toContain($cash->id, $secondCash->id)
        ->and($firstBsi['bank_id'])->toBe($bankA->id)
        ->and($secondBsi['bank_id'])->toBe($bankB->id)
        ->and($firstBsi['bank_name'])->toBe('BSI')
        ->and($secondBsi['bank_name'])->toBe('BSI')
        ->and($firstBsi['bank_label'])->not->toBe($secondBsi['bank_label'])
        ->and($namedCashBankRow['bank_type'])->toBe(Bank::TYPE_BANK)
        ->and($namedCashBankRow['bank_name'])->toBe('Tunai')
        ->and($namedCashBankRow['total'])->toBe(700_000.0)
        ->and($firstDateBanks->firstWhere('bank_id', $bankNamedCash->id)['total'])->toBe(0.0)
        ->and(collect($secondDate['banks'])->firstWhere('bank_id', $bankA->id)['total'])->toBe(0.0)
        ->and($firstBsi['amounts'][$sppKey])->toBe(2_000_000.0)
        ->and($firstBsi['amounts'][$ekskulKey])->toBe(300_000.0)
        ->and($firstBsi['amounts'][$osisKey])->toBe(200_000.0)
        ->and($firstBsi['total'])->toBe(2_500_000.0)
        ->and($secondBsi['total'])->toBe(1_800_000.0)
        ->and($firstCashDate['amounts'][$sppKey])->toBe(500_000.0)
        ->and($firstCashDate['amounts'][$ekskulKey])->toBe(100_000.0)
        ->and($firstCashDate['amounts'][$osisKey])->toBe(50_000.0)
        ->and($firstCashDate['total'])->toBe(650_000.0)
        ->and($firstDate['category_totals'][$sppKey])->toBe(3_500_000.0)
        ->and($firstDate['category_totals'][$ekskulKey])->toBe(500_000.0)
        ->and($firstDate['category_totals'][$osisKey])->toBe(300_000.0)
        ->and($firstDate['total'])->toBe(4_300_000.0)
        ->and($report['overall']['dates'][0]['total'])->toBe(4_950_000.0)
        ->and($firstDate['total'] + $firstCashDate['total'])->toBe($report['overall']['dates'][0]['total'])
        ->and($report['bank']['category_totals'][$sppKey])->toBe(4_200_000.0)
        ->and($report['cash']['category_totals'][$sppKey])->toBe(500_000.0)
        ->and($report['grand_category_totals'][$sppKey])->toBe(4_700_000.0)
        ->and($report['bank']['category_totals'][$sppKey] + $report['cash']['category_totals'][$sppKey])->toBe($report['grand_category_totals'][$sppKey])
        ->and($report['bank']['total'])->toBe(5_000_000.0)
        ->and($report['cash']['total'])->toBe(650_000.0)
        ->and($report['grand_total'])->toBe(5_650_000.0)
        ->and($report['bank']['total'] + $report['cash']['total'])->toBe($report['grand_total'])
        ->and($report['transaction_count'])->toBe(5)
        ->and($report['detail_count'])->toBe(10)
        ->and(collect($report['detail_rows'])->pluck('detail_id')->unique())->toHaveCount(10)
        ->and(collect($report['detail_rows'])->sum('amount'))->toBe(5_650_000.0)
        ->and($matrixTotal)->toBe(5_650_000.0)
        ->and(Payment::query()->count())->toBe($paymentCount)
        ->and($firstBsi['total'])->not->toBe(9_999_999.0);
});

it('omits zero-total bank and cash dates while retaining all configured bank rows on active dates', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Filter Tanggal']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket']);
    $bankA = Bank::factory()->create(['name' => 'BCA', 'account_number' => '111111']);
    $bankB = Bank::factory()->create(['name' => 'BSI', 'account_number' => '222222']);
    $bankC = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '333333']);
    $spp = makeBillType('SPP Filter');
    $ekskul = makeBillType('Ekskul Filter');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-01', [
        ['payment_type_id' => $spp->id, 'amount' => 500_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bankB, $user, '2026-09-02', [
        ['payment_type_id' => $spp->id, 'amount' => 1_000_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bankA, $user, '2026-09-03', [
        ['payment_type_id' => $spp->id, 'amount' => 2_000_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-09-03', [
        ['payment_type_id' => $ekskul->id, 'amount' => 300_000],
    ]);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 9);

    $bankDates = collect($report['bank']['dates']);
    $cashDates = collect($report['cash']['dates']);

    expect($bankDates->pluck('date_key')->all())->toBe(['2026-09-02', '2026-09-03'])
        ->and($bankDates->pluck('date_key')->all())->not->toContain('2026-09-01')
        ->and($cashDates->pluck('date_key')->all())->toBe(['2026-09-01', '2026-09-03'])
        ->and($cashDates->pluck('date_key')->all())->not->toContain('2026-09-02');

    $sept2 = $bankDates->firstWhere('date_key', '2026-09-02');
    $bankIds = collect($sept2['banks'])->pluck('bank_id')->all();

    expect($bankIds)->toBe([$bankA->id, $bankB->id, $bankC->id])
        ->and(collect($sept2['banks'])->firstWhere('bank_id', $bankA->id)['total'])->toBe(0.0)
        ->and(collect($sept2['banks'])->firstWhere('bank_id', $bankC->id)['total'])->toBe(0.0)
        ->and(collect($sept2['banks'])->firstWhere('bank_id', $bankB->id)['total'])->toBe(1_000_000.0)
        ->and($sept2['total'])->toBe(1_000_000.0)
        ->and($report['bank']['total'])->toBe(3_000_000.0)
        ->and($report['cash']['total'])->toBe(800_000.0)
        ->and($report['grand_total'])->toBe(3_800_000.0)
        ->and($report['bank']['total'] + $report['cash']['total'])->toBe($report['grand_total'])
        ->and($report['overall']['dates'])->toHaveCount(3)
        ->and($bankDates->sum('total') + $cashDates->sum('total'))->toBe($report['grand_total']);
});

it('blanks zero monetary cells in the monthly web view while keeping numeric data intact', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Blank UI']);
    $cash = Bank::factory()->cash()->create(['name' => 'Loket']);
    $bank = Bank::factory()->create(['name' => 'BCA', 'account_number' => '111111']);
    $zeroBank = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '333333']);
    $spp = makeBillType('SPP Blank');
    $ekskul = makeBillType('Ekskul Blank');

    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-08-11', [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ]);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);

    expect($report['bank']['total'])->toBe(300_000.0)
        ->and($report['cash']['total'])->toBe(0)
        ->and($report['bank']['dates'])->toHaveCount(1)
        ->and(count($report['bank']['dates'][0]['banks']))->toBe(2);

    $this->actingAs($user);

    $component = Livewire::test(SchoolDailyReport::class, ['reportMonth' => 8, 'reportYear' => 2026])
        ->set('activeTab', 'monthly')
        ->assertSee('PENERIMAAN BANK')
        ->assertSee('PENERIMAAN TUNAI')
        ->assertSee('Rp 300.000')
        ->assertSee('Tidak ada transaksi tunai pada bulan ini.');

    expect($component->html())
        ->not->toContain('Rp 0')
        ->toContain($zeroBank->optionLabel())
        ->toContain($bank->optionLabel());
});

it('merges manual descriptions into configured categories when they normalize equal', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $infaq = makeBillType('Infaq Bulanan');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-06', [
        ['payment_type_id' => $infaq->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-06', [
        ['payment_type_id' => null, 'description' => '  INFaq bULAnan  ', 'amount' => 50_000],
    ], Payment::KIND_MANUAL);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $categories = collect($report['categories']);

    $infaqKey = $categories->firstWhere('name', 'Infaq Bulanan')['key'];

    expect($report['detail_rows'])->toHaveCount(2)
        ->and(collect($report['detail_rows'])->pluck('category_key'))->toContain($infaqKey)
        ->and($report['channels']['cash']['category_totals'][$infaqKey])->toBe(150_000.0)
        ->and($report['channels']['cash']['rows'][0]['amounts'][$infaqKey])->toBe(150_000.0);
});

it('appends discovered manual-only categories after configured payment types', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $spp = makeBillType('SPP Urutan');
    $infaq = makeBillType('Infaq Urutan');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-07', [
        ['payment_type_id' => $infaq->id, 'amount' => 10_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-08', [
        ['payment_type_id' => null, 'description' => 'Dana Sosial Urutan', 'amount' => 20_000],
    ], Payment::KIND_MANUAL);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $names = collect($report['categories'])->pluck('name')->all();

    expect($names)->toBe(['Formulir Pendaftaran', 'SPP Urutan', 'Infaq Urutan', 'Dana Sosial Urutan'])
        ->and($report['channels']['cash']['rows'])->toHaveCount(2)
        ->and(collect($report['detail_rows'])->pluck('category_name'))->toContain('Dana Sosial Urutan');
});

it('orders matrix rows ascending by date with per-date Indonesian day names', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Urutan Tanggal');

    foreach (['2026-08-19', '2026-08-05', '2026-08-26'] as $date) {
        createSchoolMonthlyReportPayment($student, $cash, $user, $date, [
            ['payment_type_id' => $type->id, 'amount' => 10_000],
        ]);
    }

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $dates = collect($report['channels']['cash']['rows'])->map(fn ($row) => $row['date']->toDateString())->all();
    $dayNames = collect($report['channels']['cash']['rows'])->pluck('day_name')->all();

    expect($dates)->toBe(['2026-08-05', '2026-08-19', '2026-08-26'])
        ->and($dayNames)->toBe(['Rabu', 'Rabu', 'Rabu']);
});

it('returns a valid zero report for an empty month', function () {
    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);

    expect($report['month_label'])->toBe('Agustus 2026')
        ->and($report['grand_total'])->toBe(0)
        ->and($report['channels']['cash']['total'])->toBe(0)
        ->and($report['channels']['transfer']['total'])->toBe(0)
        ->and($report['channels']['cash']['rows'])->toBe([])
        ->and($report['channels']['transfer']['rows'])->toBe([])
        ->and($report['date_groups'])->toBe([])
        ->and($report['detail_rows'])->toBe([])
        ->and($report['last_day']->toDateString())->toBe('2026-08-31');
});

it('excludes daycare transactions and cancelled school payments from totals', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Daycare');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-20', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ], status: Payment::STATUS_CANCELLED);

    $daycare = DaycarePayment::factory()->create([
        'bank_id' => $cash->id,
        'payment_date' => '2026-08-20',
        'total_amount' => 700_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycare->id,
        'description' => 'Daycare Bulanan',
        'amount' => 700_000,
    ]);

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 8);

    expect($report['grand_total'])->toBe(0)
        ->and(collect($report['detail_rows'])->pluck('detail_label'))->not->toContain('Daycare Bulanan');
});

it('protects the report page and monthly endpoints with authentication', function () {
    $this->get(route('laporan.index'))->assertRedirect(route('login'));
    $this->get(route('laporan.bulanan.export', ['month' => 8, 'year' => 2026]))->assertRedirect(route('login'));
    $this->get(route('laporan.bulanan.pdf', ['month' => 8, 'year' => 2026]))->assertRedirect(route('login'));
});

it('validates the monthly period on the export endpoints', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.export', ['month' => 13, 'year' => 2026]))
        ->assertSessionHasErrors('month');
    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.pdf', ['month' => 8, 'year' => 1900]))
        ->assertSessionHasErrors('year');
});

it('renders the compact monthly matrix and period filter', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa UI']);
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BCA', 'account_number' => '111111']);
    $spp = makeBillType('SPP UI');

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-11', [
        ['payment_type_id' => $spp->id, 'amount' => 200_000],
    ]);
    createSchoolMonthlyReportPayment($student, $bank, $user, '2026-08-11', [
        ['payment_type_id' => $spp->id, 'amount' => 300_000],
    ]);

    $this->actingAs($user);

    $component = Livewire::test(SchoolDailyReport::class, ['reportMonth' => 8, 'reportYear' => 2026])
        ->set('activeTab', 'monthly')
        ->assertSee('Periode Laporan')
        ->assertSee('PENERIMAAN BANK')
        ->assertSee('PENERIMAAN TUNAI')
        ->assertSee('RINGKASAN TOTAL')
        ->assertSee('Selasa, 11 Agustus 2026')
        ->assertSee($bank->optionLabel())
        ->assertDontSee('Tunai / Cash')
        ->assertSee('Bulan')
        ->assertSee('Tahun')
        ->assertSee('Rp 200.000')
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 500.000')
        ->assertSee('laporan/bulanan.xlsx')
        ->assertSee('laporan/bulanan/pdf')
        ->assertSeeHtml('rowspan="1"');

    expect($component->html())
        ->toContain('min-w-[110px] px-3 py-2 text-right')
        ->toContain('<caption class="sr-only">Penerimaan Bank per Tanggal</caption>')
        ->toContain('<caption class="sr-only">Penerimaan Tunai per Tanggal</caption>')
        ->toContain('Total Harian')
        ->not->toContain('TOTAL 11 AGUSTUS')
        ->not->toContain('text-headline-md font-headline-md text-on-primary-fixed')
        ->and(substr_count($component->html(), 'rowspan="1"'))->toBe(2);
});

it('keeps the daily tab intact while the monthly tab is active', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SchoolDailyReport::class, ['reportDate' => '2026-08-27', 'reportMonth' => 8, 'reportYear' => 2026])
        ->assertSee('Belum ada transaksi sekolah pada periode ini.')
        ->call('setActiveTab', 'monthly')
        ->assertSee('Belum ada transaksi sekolah pada bulan ini.')
        ->call('setActiveTab', 'daily')
        ->assertSee('Belum ada transaksi sekolah pada periode ini.');
});

it('filters monthly payments to a specific jenjang while keeping Semua Jenjang intact', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Jenjang Bulanan');

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);

    createSchoolMonthlyReportPayment($sdStudent, $cash, $user, '2026-08-05', [
        ['payment_type_id' => $type->id, 'amount' => 100_000],
    ]);
    createSchoolMonthlyReportPayment($smpStudent, $cash, $user, '2026-08-06', [
        ['payment_type_id' => $type->id, 'amount' => 200_000],
    ]);

    $all = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $smp = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SMP);

    expect($all['grand_total'])->toBe(300_000.0)
        ->and($all['transaction_count'])->toBe(2)
        ->and($smp['grand_total'])->toBe(200_000.0)
        ->and($smp['transaction_count'])->toBe(1)
        ->and($smp['school_level'])->toBe(SchoolLevel::SMP)
        ->and($smp['school_level_label'])->toBe('SMP')
        ->and(collect($smp['detail_rows'])->pluck('student_name'))->toContain($smpStudent->nama_lengkap)
        ->and(collect($smp['detail_rows'])->pluck('student_name'))->not->toContain($sdStudent->nama_lengkap);
});

it('scopes the monthly channel matrix category totals and grand total on jenjang', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $bank = Bank::factory()->create(['name' => 'BCA', 'account_number' => '111111']);
    $spp = makeBillType('SPP Matriks');
    $infaq = makeBillType('Infaq Matriks');

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);

    createSchoolMonthlyReportPayment($sdStudent, $cash, $user, '2026-08-03', [
        ['payment_type_id' => $spp->id, 'amount' => 50_000],
    ]);
    createSchoolMonthlyReportPayment($sdStudent, $bank, $user, '2026-08-04', [
        ['payment_type_id' => $infaq->id, 'amount' => 25_000],
    ]);
    createSchoolMonthlyReportPayment($smpStudent, $cash, $user, '2026-08-10', [
        ['payment_type_id' => $spp->id, 'amount' => 150_000],
        ['payment_type_id' => $infaq->id, 'amount' => 75_000],
    ]);
    createSchoolMonthlyReportPayment($smpStudent, $bank, $user, '2026-08-10', [
        ['payment_type_id' => $infaq->id, 'amount' => 125_000],
    ]);

    $smp = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SMP);
    $sppKey = collect($smp['categories'])->firstWhere('name', 'SPP Matriks')['key'];

    expect($smp['channels']['cash']['total'])->toBe(225_000.0)
        ->and($smp['channels']['transfer']['total'])->toBe(125_000.0)
        ->and($smp['bank']['total'])->toBe(125_000.0)
        ->and($smp['cash']['total'])->toBe(225_000.0)
        ->and($smp['grand_total'])->toBe(350_000.0)
        ->and($smp['date_groups'])->toHaveCount(1)
        ->and($smp['date_groups'][0]['total'])->toBe(125_000.0)
        ->and($smp['cash']['dates'][0]['total'])->toBe(225_000.0)
        ->and($smp['overall']['dates'][0]['total'])->toBe(350_000.0)
        ->and($smp['channels']['cash']['rows'])->toHaveCount(1)
        ->and($smp['channels']['cash']['rows'][0]['amounts'][$sppKey])->toBe(150_000.0)
        ->and($smp['channels']['cash']['category_totals'][$sppKey])->toBe(150_000.0)
        ->and($smp['grand_category_totals'][$sppKey])->toBe(150_000.0);
});

it('applies the monthly jenjang filter to manual payments', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);
    [$smpStudent] = makeEnrolledStudent(SchoolLevel::SMP);

    createSchoolMonthlyReportPayment($sdStudent, $cash, $user, '2026-08-05', [
        ['payment_type_id' => null, 'description' => 'Kegiatan Bulanan SD', 'amount' => 30_000],
    ], Payment::KIND_MANUAL);
    createSchoolMonthlyReportPayment($smpStudent, $cash, $user, '2026-08-06', [
        ['payment_type_id' => null, 'description' => 'Kegiatan Bulanan SMP', 'amount' => 60_000],
    ], Payment::KIND_MANUAL);

    $sd = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SD);
    $smp = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SMP);

    expect($sd['grand_total'])->toBe(30_000.0)
        ->and(collect($sd['detail_rows'])->pluck('detail_label'))->toContain('Kegiatan Bulanan SD')
        ->and(collect($sd['detail_rows'])->pluck('detail_label'))->not->toContain('Kegiatan Bulanan SMP')
        ->and($smp['grand_total'])->toBe(60_000.0);
});

it('excludes daycare transactions from a monthly jenjang scope', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);

    $daycare = DaycarePayment::factory()->create([
        'bank_id' => $cash->id,
        'payment_date' => '2026-08-20',
        'total_amount' => 700_000,
        'created_by' => $user->id,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $daycare->id,
        'description' => 'Daycare Bulanan',
        'amount' => 700_000,
    ]);

    $sd = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SD);

    expect($sd['grand_total'])->toBe(0)
        ->and(collect($sd['detail_rows'])->pluck('detail_label'))->not->toContain('Daycare Bulanan');
});

it('classifies monthly historical payments by the enrollment active at payment date', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Bulanan Promosi');

    $smpClass = SchoolClass::factory()->create(['level' => 8]);
    $sdClass = SchoolClass::factory()->create(['level' => 5]);
    $student = Student::factory()->create(['class_id' => $smpClass->id, 'nama_lengkap' => 'Siswa Bulanan Promosi']);

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

    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-01-15', [
        ['payment_type_id' => $type->id, 'amount' => 60_000],
    ]);
    createSchoolMonthlyReportPayment($student, $cash, $user, '2026-08-15', [
        ['payment_type_id' => $type->id, 'amount' => 70_000],
    ]);

    $sdJanuary = app(SchoolMonthlyReportService::class)->generate(2026, 1, SchoolLevel::SD);
    $smpJanuary = app(SchoolMonthlyReportService::class)->generate(2026, 1, SchoolLevel::SMP);
    $smpAugust = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SMP);
    $sdAugust = app(SchoolMonthlyReportService::class)->generate(2026, 8, SchoolLevel::SD);

    expect($sdJanuary['grand_total'])->toBe(60_000.0)
        ->and($smpJanuary['grand_total'])->toBe(0)
        ->and($smpAugust['grand_total'])->toBe(70_000.0)
        ->and($sdAugust['grand_total'])->toBe(0);
});

it('matches the monthly Semua Jenjang scope to the unfiltered report', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $type = makeBillType('SPP Bulanan Scope');

    [$sdStudent] = makeEnrolledStudent(SchoolLevel::SD);

    createSchoolMonthlyReportPayment($sdStudent, $cash, $user, '2026-08-11', [
        ['payment_type_id' => $type->id, 'amount' => 80_000],
    ]);

    $reference = app(SchoolMonthlyReportService::class)->generate(2026, 8);
    $allScope = app(SchoolMonthlyReportService::class)->generate(
        2026,
        8,
        SchoolReportLevel::fromValue(SchoolReportLevel::OPTION_ALL)
    );

    expect($allScope['detail_rows'])->toEqual($reference['detail_rows'])
        ->and($allScope['grand_total'])->toBe($reference['grand_total'])
        ->and($allScope['school_level_label'])->toBe('Semua Jenjang');
});

it('validates the jenjang filter on the monthly endpoints', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.export', ['month' => 8, 'year' => 2026, 'school_level' => 'SLTP']))
        ->assertSessionHasErrors('school_level');
    $this->actingAs(User::factory()->create())
        ->get(route('laporan.bulanan.pdf', ['month' => 8, 'year' => 2026, 'school_level' => 'bogus']))
        ->assertSessionHasErrors('school_level');
});
