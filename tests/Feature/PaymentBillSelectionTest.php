<?php

use App\Enums\BillFrequency;
use App\Livewire\PaymentCreate;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentShow;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

function makeDirectPayment(User $user, Bank $bank, Student $student, array $details): Payment
{
    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => array_sum(array_column($details, 'amount')),
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    foreach ($details as $detail) {
        $payment->details()->create($detail);
    }

    return $payment;
}

it('tagihan bulanan dikelompokkan per periode pada halaman tambah pembayaran', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);

    $ekskul = makeBillType('Ekskul');
    makeBillRate($ekskul, 8, 60000);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);

    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    makeMonthlyBill($student, $ekskul, 60000, month: 8, year: 2026);
    makeMonthlyBill($student, $spp, 970000, month: 9, year: 2026);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    expect($component->html())->toContain('Tagihan Agustus 2026')
        ->and($component->html())->toContain('Tagihan September 2026');
});

it('tagihan tahunan dan sekali bayar muncul di kelompok masing-masing', function () {
    $student = makeBillStudent(8);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);

    makeActiveSetting($student, $buku);
    makeActiveSetting($student, $pangkal);

    $tahunBill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $buku->id,
        'amount' => 500000,
        'academic_year' => '2026/2027',
    ]);

    $pangkalBill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $pangkal->id,
        'amount' => 5000000,
    ]);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'));

    expect($outstanding->pluck('id')->all())->toContain($tahunBill->id, $pangkalBill->id);

    expect($component->html())->toContain('Tahun Ajaran 2026/2027')
        ->and($component->html())->toContain('Tagihan Sekali Bayar');
});

it('satu transaksi membayar beberapa tagihan bulanan sekaligus', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $aug = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    $sep = makeMonthlyBill($student, $spp, 970000, month: 9, year: 2026);
    $okt = makeMonthlyBill($student, $spp, 970000, month: 10, year: 2026);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$aug->id, $sep->id, $okt->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $payments = Payment::where('student_id', $student->id)->get();

    expect($payments)->toHaveCount(1);

    $payment = $payments->first();
    $details = $payment->details()->orderBy('period_month')->get();

    expect($details)->toHaveCount(3)
        ->and($details->pluck('bill_id')->all())->toBe([$aug->id, $sep->id, $okt->id])
        ->and((float) $payment->total_amount)->toBe(2910000.0);

    $aug->refresh();
    $sep->refresh();
    $okt->refresh();

    expect($aug->status)->toBe(StudentBill::STATUS_PAID)
        ->and($sep->status)->toBe(StudentBill::STATUS_PAID)
        ->and($okt->status)->toBe(StudentBill::STATUS_PAID);
});

it('satu transaksi mencampur tagihan bulanan dan tahunan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $buku);

    $aug = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    $sep = makeMonthlyBill($student, $spp, 970000, month: 9, year: 2026);

    $tahunBill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $buku->id,
        'amount' => 500000,
        'academic_year' => '2026/2027',
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$aug->id, $sep->id, $tahunBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->first();
    $details = $payment->details()->get();

    expect($details)->toHaveCount(3)
        ->and($details->pluck('bill_id')->all())->toContain($aug->id, $sep->id, $tahunBill->id)
        ->and((float) $payment->total_amount)->toBe(2440000.0);

    $ayDetail = $details->firstWhere('bill_id', $tahunBill->id);

    expect($ayDetail->academic_year)->toBe('2026/2027')
        ->and($ayDetail->period_month)->toBeNull()
        ->and($ayDetail->period_year)->toBeNull();
});

it('satu transaksi mencampur tagihan bulanan dan sekali bayar', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $pangkal);

    $aug = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);

    $pangkalBill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $pangkal->id,
        'amount' => 5000000,
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$aug->id, $pangkalBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->first();
    $details = $payment->details()->get();

    expect($details)->toHaveCount(2)
        ->and($details->pluck('bill_id')->all())->toContain($aug->id, $pangkalBill->id)
        ->and((float) $payment->total_amount)->toBe(5970000.0);

    $oneTimeDetail = $details->firstWhere('bill_id', $pangkalBill->id);

    expect($oneTimeDetail->academic_year)->toBeNull()
        ->and($oneTimeDetail->period_month)->toBeNull()
        ->and($oneTimeDetail->period_year)->toBeNull();
});

it('total pembayaran bereaksi terhadap pilihan dan nominal tagihan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $aug = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    $sep = makeMonthlyBill($student, $spp, 970000, month: 9, year: 2026);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $component->set('selectedBillIds', [$aug->id])
        ->assertSee('Rp 970.000');

    $component->set('selectedBillIds', [$aug->id, $sep->id])
        ->assertSee('Rp 1.940.000');

    $component->set('selectedBillAmounts', [$aug->id => 500000, $sep->id => 970000])
        ->assertSee('Rp 1.470.000');
});

it('kwitansi menampilkan semua detail pembayaran multi tagihan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $buku);

    $aug = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);

    $tahunBill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $buku->id,
        'amount' => 500000,
        'academic_year' => '2026/2027',
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$aug->id, $tahunBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->first();

    expect($payment->details()->count())->toBe(2);
    expect($payment->payment_kind)->toBe(Payment::KIND_BILL);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee($payment->receipt_number)
        ->assertSee($student->nama_lengkap)
        ->assertSee($student->nis)
        ->assertSee($student->schoolClass->name)
        ->assertSee($bank->name)
        ->assertSee($bank->account_number)
        ->assertSee('15 Agustus 2026')
        ->assertSee(asset('images/annur_logo2.png'), false)
        ->assertSee(route('pembayaran.print', $payment), false)
        ->assertSee(route('pembayaran.pdf', $payment), false)
        ->assertSee('SPP')
        ->assertSee('Uang Buku')
        ->assertSee('2026/2027')
        ->assertSee('Rp '.number_format((float) $payment->total_amount, 0, ',', '.'))
        ->assertSeeHtml('class="receipt-sheet w-[95%] max-w-none')
        ->assertSeeHtml('receipt-payment-meta')
        ->assertSeeHtml('grid-template-columns: repeat(2, minmax(0, 1fr))')
        ->assertSeeHtml('id="receipt-print-styles"')
        ->assertDontSeeHtml('min-height: 99mm;')
        ->assertDontSee('Petugas')
        ->assertDontSee('Penerima')
        ->assertDontSee('Mengetahui')
        ->assertDontSeeHtml('receipt-signature');

    $paymentCount = Payment::query()->count();
    $detailCount = PaymentDetail::query()->count();
    $receiptNumber = $payment->receipt_number;
    $total = $payment->total_amount;
    $details = $payment->details()->orderBy('id')->get(['payment_type_id', 'description', 'amount'])->toArray();
    $inlinePdf = $this->actingAs($user)->get(route('pembayaran.print', $payment));
    $firstPdf = $this->get(route('pembayaran.pdf', $payment));
    $secondPdf = $this->get(route('pembayaran.pdf', $payment));

    $inlinePdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $firstPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $secondPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($inlinePdf->headers->get('content-disposition'))->toContain('inline')->toContain($receiptNumber.'.pdf')
        ->and($inlinePdf->getContent())->toStartWith('%PDF')
        ->and($inlinePdf->getContent())->toContain(mb_convert_encoding('Kwitansi Pembayaran - Annur Management', 'UTF-16BE'))
        ->and($inlinePdf->getContent())->toContain('/Subtype /Image')
        ->and($firstPdf->headers->get('content-disposition'))->toContain('attachment')->toContain($receiptNumber.'.pdf')
        ->and($firstPdf->getContent())->toStartWith('%PDF')
        ->and($secondPdf->getContent())->toStartWith('%PDF')
        ->and($payment->refresh()->receipt_number)->toBe($receiptNumber)
        ->and($payment->total_amount)->toBe($total)
        ->and($payment->details()->orderBy('id')->get(['payment_type_id', 'description', 'amount'])->toArray())->toBe($details)
        ->and(Payment::query()->count())->toBe($paymentCount)
        ->and(PaymentDetail::query()->count())->toBe($detailCount);

    $this->get(route('daycare.payment.pdf', ['payment' => $payment->id]))->assertNotFound();
    $this->get(route('daycare.payment.print', ['payment' => $payment->id]))->assertNotFound();

    auth()->logout();
    $this->get(route('pembayaran.pdf', $payment))->assertRedirect(route('login'));
});

it('renders a Student cash receipt without empty account rows', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $student = makeBillStudent(8);
    $type = makeBillType('SPP Receipt Tunai');
    $bill = makeMonthlyBill($student, $type, 250000);
    $payment = makeDirectPayment($user, $cash, $student, [[
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 250000,
    ]]);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee('Metode Pembayaran')
        ->assertSee('Tunai')
        ->assertDontSeeHtml('<div class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5"></div>');

    $response = $this->actingAs($user)->get(route('pembayaran.print', $payment));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

it('halaman index pembayaran menampilkan description pembayaran', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $aug = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    $sep = makeMonthlyBill($student, $spp, 970000, month: 9, year: 2026);

    $tahunBill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $buku->id,
        'amount' => 500000,
        'academic_year' => '2026/2027',
    ]);

    $payment = makeDirectPayment($user, $bank, $student, [
        ['bill_id' => $aug->id, 'payment_type_id' => $spp->id, 'period_month' => 8, 'period_year' => 2026, 'amount' => 970000],
        ['bill_id' => $sep->id, 'payment_type_id' => $spp->id, 'period_month' => 9, 'period_year' => 2026, 'amount' => 970000],
        ['bill_id' => $tahunBill->id, 'payment_type_id' => $buku->id, 'period_month' => null, 'period_year' => null, 'academic_year' => '2026/2027', 'amount' => 500000],
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($payment->receipt_number);

    // Tanpa description, tampilan Detail Pembayaran adalah "-"
    expect($payment->detail_display)->toBe('-');

    // Dengan description, tampilan menampilkan description persis
    $payment->update(['description' => 'SPP Agustus + SPP September + Uang Buku 2026/2027']);
    expect($payment->fresh()->detail_display)->toBe('SPP Agustus + SPP September + Uang Buku 2026/2027');
});

it('pagination halaman index pembayaran tetap berfungsi', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);

    $aug = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);

    $receipts = [];
    foreach (range(1, 11) as $i) {
        $payment = makeDirectPayment($user, $bank, $student, [
            ['bill_id' => $aug->id, 'payment_type_id' => $spp->id, 'period_month' => 8, 'period_year' => 2026, 'amount' => 970000],
        ]);
        $receipts[] = $payment->receipt_number;
    }

    $component = Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history');

    // Halaman 1 = 10 transaksi terbaru (id tertinggi), transaksi pertama di halaman 2.
    $component->assertSee($receipts[10])
        ->assertDontSee($receipts[0]);

    $component->call('gotoPage', 2);

    $component->assertSee($receipts[0])
        ->assertDontSee($receipts[10]);
});
