<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentCreate;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentRate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\BillGenerationService;
use App\Support\BillbookPeriod;
use Carbon\Carbon;
use Livewire\Livewire;

function uiBillbookStudent(): Student
{
    $student = makeBillStudent(8);

    $student->paymentSettings()->update(['started_at' => '2026-01-01']);

    return $student;
}

it('menambah Jemputan dari kartu September hanya untuk September', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);

    makeMonthlyBill($student, $spp, 970000, 9, 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('openAddBillForMonth(9, 2026)')
        ->call('openAddBillForMonth', 9, 2026)
        ->assertSet('isAddOpen', true)
        ->assertSet('addLockedMonth', 9)
        ->assertSet('addLockedYear', 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->where('period_month', 9)
        ->where('period_year', 2026)
        ->exists())->toBeTrue()
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $jemputan->id)
            ->count())->toBe(1);
});

it('menambah Jemputan dari kartu Oktober hanya untuk Oktober', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);

    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('openAddBillForMonth(10, 2026)')
        ->call('openAddBillForMonth', 10, 2026)
        ->assertSet('isAddOpen', true)
        ->assertSet('addLockedMonth', 10)
        ->assertSet('addLockedYear', 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bills = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->get();

    expect($bills)->toHaveCount(1)
        ->and($bills->first()->period_month)->toBe(10)
        ->and($bills->first()->period_year)->toBe(2026);
});

it('siswa berbeda dapat memiliki nominal Jemputan yang berbeda', function () {
    $studentA = uiBillbookStudent();
    $studentB = uiBillbookStudent();

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);

    foreach ([$studentA, $studentB] as $student) {
        $spp = makeBillType('SPP');
        makeBillRate($spp, 8, 970000);
        makeActiveSetting($student, $spp);
        makeMonthlyBill($student, $spp, 970000, 9, 2026);
    }

    $amounts = ['550000', '600000'];

    foreach ([$studentA, $studentB] as $index => $student) {
        Livewire::test(StudentDetail::class, ['student' => $student])
            ->call('openAddBillForMonth', 9, 2026)
            ->set('addPaymentTypeId', (string) $jemputan->id)
            ->set('addAmount', $amounts[$index])
            ->call('saveAddBill')
            ->assertHasNoErrors();
    }

    $billA = StudentBill::where('student_id', $studentA->id)->where('payment_type_id', $jemputan->id)->first();
    $billB = StudentBill::where('student_id', $studentB->id)->where('payment_type_id', $jemputan->id)->first();

    expect((float) $billA->amount)->toBe(550000.0)
        ->and((float) $billB->amount)->toBe(600000.0);
});

it('section bulanan menampilkan tombol "+ Tambah Tagihan" dan periode terkunci', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeActiveSetting($student, $spp);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    $component = Livewire::test(StudentDetail::class, ['student' => $student]);

    foreach ([[2026, 9], [2026, 12], [2027, 6]] as [$year, $month]) {
        $component->assertSeeHtml("openAddBillForMonth($month, $year)");
    }

    $component
        ->call('openAddBillForMonth', 9, 2026)
        ->assertSet('addPeriodLocked', true)
        ->assertSet('addLockedMonth', 9)
        ->assertSet('addLockedYear', 2026)
        ->set('addPaymentTypeId', (string) $spp->id)
        ->assertSee('Periode diambil otomatis dari bagian tagihan yang dipilih.');
});

it('section tahunan menampilkan tombol "+ Tambah Tagihan" dan memakai tahun ajaran section', function () {
    $student = uiBillbookStudent();

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $buku);

    $kegiatan = makeBillType('Uang Kegiatan');
    PaymentRate::factory()->yearly()->create([
        'payment_type_id' => $kegiatan->id,
        'class_level' => 8,
        'amount' => 100000,
        'effective_from' => '2026-01-01',
    ]);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml("openAddBillForAcademicYear('2026/2027')")
        ->call('openAddBillForAcademicYear', '2026/2027')
        ->assertSet('isAddOpen', true)
        ->assertSet('addLockedAcademicYear', '2026/2027')
        ->set('addPaymentTypeId', (string) $kegiatan->id)
        ->assertSee('Tahun ajaran diambil otomatis dari bagian tagihan yang dipilih.')
        ->set('addAmount', '100000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $kegiatan->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->academic_year)->toBe('2026/2027')
        ->and($bill->period_month)->toBeNull();
});

it('section sekali bayar menampilkan tombol "+ Tambah Tagihan" tanpa periode', function () {
    $student = uiBillbookStudent();

    $pangkal = makeBillType('Uang Pangkal');
    PaymentRate::factory()->create([
        'payment_type_id' => $pangkal->id,
        'class_level' => 8,
        'amount' => 5000000,
        'is_monthly' => false,
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);
    makeLevelDefault($pangkal, SchoolLevel::SMP, required: false);
    makeActiveSetting($student, $pangkal);

    $lain = makeBillType('Lain-lain');
    PaymentRate::factory()->create([
        'payment_type_id' => $lain->id,
        'class_level' => 8,
        'amount' => 250000,
        'is_monthly' => false,
        'billing_frequency' => BillFrequency::OneTime,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '')
        ->assertSeeHtml('openAddBillForOneTime')
        ->call('openAddBillForOneTime')
        ->assertSet('isAddOpen', true)
        ->assertSet('addPeriodLocked', false)
        ->assertSet('addLockedMonth', null)
        ->set('addPaymentTypeId', (string) $lain->id)
        ->set('addAmount', '250000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $lain->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull()
        ->and($bill->academic_year)->toBeNull();
});

it('modal generate buku tagihan memakai bulan awal dan menyimpan konfigurasi', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeActiveSetting($student, $spp);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openBillbook')
        ->assertSet('isBillbookOpen', true)
        ->set('billbookStartMonth', '2026-09')
        ->call('generateBillbook')
        ->assertHasNoErrors();

    expect(Setting::get(BillbookPeriod::SETTING_START_MONTH))->toBe('2026-09')
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $spp->id)
            ->count())->toBe(10);
});

it('modal generate buku tagihan memakai bulan awal default saat tidak diubah', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeActiveSetting($student, $spp);

    $component = Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openBillbook')
        ->assertSet('isBillbookOpen', true);

    $defaultStart = BillbookPeriod::startDate()->format('Y-m');

    $component->assertSet('billbookStartMonth', $defaultStart)
        ->call('generateBillbook')
        ->assertHasNoErrors();

    $expectedMonths = count(BillbookPeriod::months(Carbon::parse($defaultStart)));

    expect(Setting::get(BillbookPeriod::SETTING_START_MONTH))->toBe($defaultStart)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $spp->id)
            ->count())->toBe($expectedMonths);
});

it('nominal tagihan yang diedit tetap persisten setelah generate ulang', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeActiveSetting($student, $spp);

    $service = app(BillGenerationService::class);
    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $september = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 9)
        ->where('period_year', 2026)
        ->first();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $september->id)
        ->set('editAmount', '950000')
        ->call('saveEditBill')
        ->assertHasNoErrors();

    $september->refresh();

    expect((float) $september->amount)->toBe(950000.0);

    $service->generateBillbook($student, Carbon::parse('2026-09-15'));

    $september->refresh();

    expect((float) $september->amount)->toBe(950000.0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $spp->id)
            ->count())->toBe(10);
});

it('tagihan belum dibayar dapat dihapus', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, 9, 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('confirmDeleteBill', $bill->id)
        ->call('deleteBill')
        ->assertHasNoErrors();

    expect(StudentBill::find($bill->id))->toBeNull();
});

it('tagihan sudah dibayar tidak dapat dihapus', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, 9, 2026);

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000123',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-10',
        'total_amount' => 400000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $spp->id,
        'period_month' => 9,
        'period_year' => 2026,
        'amount' => 400000,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('confirmDeleteBill', $bill->id)
        ->call('deleteBill')
        ->assertHasErrors(['deleteConfirm']);

    expect(StudentBill::find($bill->id))->not->toBeNull()
        ->and(Payment::find($payment->id))->not->toBeNull()
        ->and(PaymentDetail::where('payment_id', $payment->id)->count())->toBe(1);
});

it('alur pembayaran bekerja untuk banyak tagihan buku tagihan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeActiveSetting($student, $spp);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $buku);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    $sppSeptember = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 9)
        ->where('period_year', 2026)
        ->first();

    $uangBuku = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $buku->id)
        ->first();

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$sppSeptember->id, $uangBuku->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-15')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->latest('id')->first();

    expect($payment)->not->toBeNull()
        ->and((float) $payment->total_amount)->toBe(1470000.0)
        ->and($payment->receipt_number)->toMatch('/^KWT-\d{4}-\d{6}$/');

    $details = $payment->details()->get();

    expect($details)->toHaveCount(2)
        ->and($details->pluck('bill_id')->all())->toContain($sppSeptember->id, $uangBuku->id);

    $sppSeptember->refresh();
    $uangBuku->refresh();

    expect($sppSeptember->isSettled())->toBeTrue()
        ->and($uangBuku->isSettled())->toBeTrue();
});

it('daftar outstanding pembayaran memakai nominal buku tagihan', function () {
    $student = uiBillbookStudent();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeLevelDefault($spp, SchoolLevel::SMP);
    makeActiveSetting($student, $spp);

    app(BillGenerationService::class)->generateBillbook($student, Carbon::parse('2026-09-15'));

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = $component->get('outstandingBills');

    expect($outstanding)->toHaveCount(10)
        ->and($outstanding[0]['period'])->toBe('September 2026')
        ->and((int) $outstanding[0]['remaining_amount'])->toBe(970000);
});
