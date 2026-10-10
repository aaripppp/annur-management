<?php

use App\Livewire\PaymentCreate;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

it('menampilkan tagihan outstanding saat siswa dipilih', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);

    makeMonthlyBill($student, $spp, 1750000);
    makeMonthlyBill($student, $jemputan, 550000);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $jemputan);

    $component = Livewire::test(PaymentCreate::class);

    $component->call('selectStudent', $student->id)
        ->assertSet('selected_student_id', $student->id)
        ->assertSee('Pilih Tagihan yang Akan Dibayar')
        ->assertSeeHtml('table class="w-full text-left border-collapse min-w-[640px]"')
        ->assertSeeInOrder(['No.', 'Pilih', 'Jenis Pembayaran', 'Tagihan', 'Nominal Dibayar']);

    expect($component->get('outstandingBills'))->toHaveCount(2);
});

it('mengurutkan tagihan dan ringkasan bulanan tanpa mengubah urutan state pilihan', function () {
    $student = makeBillStudent(8);
    $sppBill = makeMonthlyBill($student, makeBillType('SPP'), 970000, 10, 2026);
    $ekskulBill = makeMonthlyBill($student, makeBillType('Ekskul'), 60000, 10, 2026);
    $osisBill = makeMonthlyBill($student, makeBillType('OSIS'), 5000, 10, 2026);
    $jemputanBill = makeMonthlyBill($student, makeBillType('Jemputan'), 150000, 10, 2026);
    $digitalBill = makeMonthlyBill($student, makeBillType('Iuran Digital'), 75000, 10, 2026);

    $selectionOrder = [
        $digitalBill->id,
        $jemputanBill->id,
        $sppBill->id,
        $osisBill->id,
        $ekskulBill->id,
    ];
    $canonicalOrder = [
        $sppBill->id,
        $ekskulBill->id,
        $osisBill->id,
        $jemputanBill->id,
        $digitalBill->id,
    ];

    $component = Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', $selectionOrder)
        ->assertSet('selectedBillIds', $selectionOrder)
        ->assertSeeInOrder(['SPP', 'Ekskul', 'OSIS', 'Jemputan', 'Iuran Digital'])
        ->assertSee('Rp 1.260.000');

    preg_match_all('/data-summary-bill-id="(\d+)"/', $component->html(), $matches);

    expect(array_map('intval', $matches[1]))->toBe($canonicalOrder)
        ->and($component->get('selectedBillAmounts'))->toMatchArray([
            $sppBill->id => 970000,
            $ekskulBill->id => 60000,
            $osisBill->id => 5000,
            $jemputanBill->id => 150000,
            $digitalBill->id => 75000,
        ]);
});

it('mencetang tagihan mengisi nominal default = sisa dan membatalkan pilihan tidak menghapus bill', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $bill = makeMonthlyBill($student, $spp, 1750000);

    makeActiveSetting($student, $spp);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $component->set('selectedBillIds', [$bill->id]);

    expect($component->get('selectedBillAmounts'))->toBe([$bill->id => 1750000]);

    $component->set('selectedBillIds', []);

    expect($component->get('selectedBillAmounts'))->toBe([])
        ->and(StudentBill::find($bill->id))->not->toBeNull();
});

it('tidak menampilkan tagihan yang sudah lunas sebagai outstanding', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $bill = makeMonthlyBill($student, $spp, 1750000);

    makeActiveSetting($student, $spp);

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000010',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 1750000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $spp->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 1750000,
    ]);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    expect($component->get('outstandingBills'))->toHaveCount(0);
});

it('menyimpan payment detail dengan bill_id dan periode dari bill', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $bill = makeMonthlyBill($student, $spp, 1750000);

    makeActiveSetting($student, $spp);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $this->assertDatabaseHas('payments', ['student_id' => $student->id]);

    $detail = Payment::where('student_id', $student->id)
        ->latest('id')
        ->first()
        ->details()
        ->where('bill_id', $bill->id)
        ->first();

    expect($detail)->not->toBeNull()
        ->and($detail->bill_id)->toBe($bill->id)
        ->and($detail->period_month)->toBe(8)
        ->and($detail->period_year)->toBe(2026);

    $bill->refresh();

    expect($bill->isSettled())->toBeTrue();
});

it('menerima pembayaran tagihan siswa melalui penerimaan tunai', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create(['is_active' => true]);
    $student = makeBillStudent(8);
    $spp = makeBillType('SPP Tunai', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    $bill = makeMonthlyBill($student, $spp, 1750000);
    makeActiveSetting($student, $spp);

    Livewire::actingAs($user)
        ->test(PaymentCreate::class)
        ->assertSee('Tunai')
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $cash->id)
        ->set('payment_date', '2026-08-15')
        ->call('save')
        ->assertHasNoErrors();

    expect(Payment::query()->sole()->bank_id)->toBe($cash->id)
        ->and($bill->fresh()->isSettled())->toBeTrue();
});

it('pembayaran sebagian mengurangi sisa tagihan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);
    $bill = makeMonthlyBill($student, $jemputan, 550000);

    makeActiveSetting($student, $jemputan);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts', [$bill->id => 300_000])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $detail = Payment::where('student_id', $student->id)
        ->latest('id')
        ->first()
        ->details()
        ->first();

    $bill->refresh();

    expect($detail->bill_id)->toBe($bill->id)
        ->and($bill->paid_amount)->toBe(300_000.0)
        ->and($bill->remaining_amount)->toBe(250_000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_PARTIAL);
});

it('menolak pembayaran melebihi sisa tagihan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $student = makeBillStudent(8);
    $bill = makeMonthlyBill($student, $spp, 1750000);

    makeActiveSetting($student, $spp);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts', [$bill->id => 1_750_001])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save')
        ->assertHasErrors(['selectedBillAmounts.'.$bill->id]);

    $bill->refresh();

    expect($bill->status)->toBe(StudentBill::STATUS_UNPAID);
});

it('menolak pembayaran tanpa tagihan terpilih', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    makeActiveSetting($student, $spp);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save')
        ->assertHasErrors(['selectedBillIds']);

    expect(Payment::where('student_id', $student->id)->count())->toBe(0);
});

it('mengirimkan event payment-saved saat pembayaran disimpan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $student = makeBillStudent(8);
    $bill = makeMonthlyBill($student, $spp, 1750000);

    makeActiveSetting($student, $spp);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save')
        ->assertDispatched('payment-saved', studentId: $student->id);

    $this->assertDatabaseHas('payments', ['student_id' => $student->id]);
});
