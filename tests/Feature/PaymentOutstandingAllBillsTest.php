<?php

use App\Livewire\PaymentCreate;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentShow;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use Livewire\Livewire;

function payOutstandingBill(StudentBill $bill, int $amount): void
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-'.uniqid(),
        'student_id' => $bill->student_id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);
}

it('menampilkan tagihan bulanan default yang outstanding', function () {
    $student = makeBillStudent(8);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'));

    expect($outstanding->pluck('id')->all())->toBe([$bill->id])
        ->and($component->html())->toContain('Tagihan Oktober 2026');
});

it('menampilkan tagihan Jemputan manual tanpa pengaturan aktif', function () {
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $jemputan = $catalog['Jemputan'];

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->exists())->toBeFalse();

    $bill = makeMonthlyBill($student, $jemputan, 500000, 10, 2026);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'))
        ->firstWhere('id', $bill->id);

    expect($outstanding)->not->toBeNull()
        ->and($outstanding['period'])->toBe('Oktober 2026')
        ->and((int) $outstanding['amount'])->toBe(500000)
        ->and((int) $outstanding['remaining_amount'])->toBe(500000);

    $component->assertSee('Jemputan')
        ->assertSee('Rp 500.000')
        ->assertSee('Tagihan Oktober 2026');
});

it('menampilkan tagihan Lain-lain manual tanpa pengaturan aktif', function () {
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $lain = $catalog['Lain-lain'];

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $lain->id)
        ->exists())->toBeFalse();

    $bill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $lain->id,
        'amount' => 100000,
    ]);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'))
        ->firstWhere('id', $bill->id);

    expect($outstanding)->not->toBeNull()
        ->and((int) $outstanding['remaining_amount'])->toBe(100000);

    $component->assertSee('Lain-lain')
        ->assertSee('Rp 100.000')
        ->assertSee('Tagihan Sekali Bayar');
});

it('menampilkan tagihan tahunan Uang Buku tanpa pengaturan aktif', function () {
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $uangBuku = $catalog['Uang Buku'];

    $bill = makeYearlyBill($student, $uangBuku, 500000, '2026/2027');

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'))
        ->firstWhere('id', $bill->id);

    expect($outstanding)->not->toBeNull()
        ->and($outstanding['period'])->toBe('Tahun Ajaran 2026/2027')
        ->and((int) $outstanding['remaining_amount'])->toBe(500000);

    $component->assertSee('Uang Buku')
        ->assertSee('Tahun Ajaran 2026/2027');
});

it('menampilkan tagihan tahunan Uang Kegiatan tanpa pengaturan aktif', function () {
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $uangKegiatan = $catalog['Uang Kegiatan'];

    $bill = makeYearlyBill($student, $uangKegiatan, 100000, '2026/2027');

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'));

    expect($outstanding->pluck('id')->all())->toBe([$bill->id]);

    $component->assertSee('Uang Kegiatan')
        ->assertSee('Tahun Ajaran 2026/2027');
});

it('menampilkan tagihan sekali bayar Uang Pangkal tanpa pengaturan aktif', function () {
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $pangkal = $catalog['Uang Pangkal'];

    $bill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $pangkal->id,
        'amount' => 5000000,
    ]);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'));

    expect($outstanding->pluck('id')->all())->toBe([$bill->id]);

    $component->assertSee('Uang Pangkal')
        ->assertSee('Tagihan Sekali Bayar');
});

it('tidak menampilkan tagihan yang sudah lunas penuh', function () {
    $student = makeBillStudent(8);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, 10, 2026);
    payOutstandingBill($bill, 970000);

    expect($bill->refresh()->remaining_amount)->toBe(0.0);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    expect($component->get('outstandingBills'))->toHaveCount(0);
});

it('menampilkan tagihan sebagian dengan sisa yang benar', function () {
    $student = makeBillStudent(8);
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, 10, 2026);
    payOutstandingBill($bill, 200000);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'))
        ->firstWhere('id', $bill->id);

    expect($outstanding)->not->toBeNull()
        ->and((int) $outstanding['amount'])->toBe(970000)
        ->and((int) $outstanding['paid_amount'])->toBe(200000)
        ->and((int) $outstanding['remaining_amount'])->toBe(770000);
});

it('Oktober menampilkan SPP, Ekskul, OSIS, Jemputan, dan Lain-lain sekaligus', function () {
    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);

    $bills = [
        makeMonthlyBill($student, $spp, 970000, 10, 2026),
        makeMonthlyBill($student, $catalog['Ekskul'], 52000, 10, 2026),
        makeMonthlyBill($student, $catalog['OSIS'], 5000, 10, 2026),
        makeMonthlyBill($student, $catalog['Jemputan'], 500000, 10, 2026),
        makeMonthlyBill($student, $catalog['Lain-lain'], 100000, 10, 2026),
    ];

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $ids = collect($component->get('outstandingBills'))->pluck('id')->all();

    expect($ids)->toHaveCount(5)
        ->and($ids)->toEqualCanonicalizing(collect($bills)->pluck('id')->all());

    $component->assertSee('Tagihan Oktober 2026')
        ->assertSee('5 tagihan')
        ->assertSee('SPP')
        ->assertSee('Ekskul')
        ->assertSee('OSIS')
        ->assertSee('Jemputan')
        ->assertSee('Lain-lain');
});

it('pembayaran multi tagihan termasuk Jemputan menyimpan bill_id yang benar', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);

    $sppBill = makeMonthlyBill($student, $spp, 970000, 10, 2026);
    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 500000, 10, 2026);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$sppBill->id, $jemputanBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-10-05')
        ->call('save');

    $payments = Payment::where('student_id', $student->id)->get();

    expect($payments)->toHaveCount(1);

    $payment = $payments->first();
    $details = $payment->details()->get();

    expect($details)->toHaveCount(2)
        ->and($details->pluck('bill_id')->all())->toEqualCanonicalizing([$sppBill->id, $jemputanBill->id])
        ->and((float) $payment->total_amount)->toBe(1470000.0);

    $jemputanDetail = $details->firstWhere('bill_id', $jemputanBill->id);

    expect($jemputanDetail->period_month)->toBe(10)
        ->and($jemputanDetail->period_year)->toBe(2026)
        ->and($jemputanDetail->academic_year)->toBeNull();

    $sppBill->refresh();
    $jemputanBill->refresh();

    expect($sppBill->isSettled())->toBeTrue()
        ->and($jemputanBill->isSettled())->toBeTrue();
});

it('pembayaran multi tagihan termasuk Lain-lain sekali bayar menyimpan bill_id yang benar', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);

    $sppBill = makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $lainBill = StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $catalog['Lain-lain']->id,
        'amount' => 100000,
    ]);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$sppBill->id, $lainBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-10-05')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->first();
    $details = $payment->details()->get();

    expect($details)->toHaveCount(2)
        ->and($details->pluck('bill_id')->all())->toEqualCanonicalizing([$sppBill->id, $lainBill->id])
        ->and((float) $payment->total_amount)->toBe(1070000.0);

    $lainDetail = $details->firstWhere('bill_id', $lainBill->id);

    expect($lainDetail->period_month)->toBeNull()
        ->and($lainDetail->period_year)->toBeNull()
        ->and($lainDetail->academic_year)->toBeNull();
});

it('kwitansi tetap menampilkan detail tagihan manual yang dibayar', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);

    $sppBill = makeMonthlyBill($student, $spp, 970000, 10, 2026);
    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 500000, 10, 2026);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$sppBill->id, $jemputanBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-10-05')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->first();

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee($payment->receipt_number)
        ->assertSee('SPP')
        ->assertSee('Jemputan')
        ->assertSee('Oktober 2026')
        ->assertSee('Rp '.number_format((float) $payment->total_amount, 0, ',', '.'));
});

it('index pembayaran tetap menampilkan transaksi yang mencakup tagihan manual', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);

    $sppBill = makeMonthlyBill($student, $spp, 970000, 10, 2026);
    $jemputanBill = makeMonthlyBill($student, $catalog['Jemputan'], 500000, 10, 2026);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$sppBill->id, $jemputanBill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-10-05')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->first();

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($payment->receipt_number)
        ->assertSee($payment->detail_display);
});
