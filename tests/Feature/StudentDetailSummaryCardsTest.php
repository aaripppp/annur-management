<?php

use App\Livewire\StudentDetail;
use App\Models\BillAdjustment;
use App\Models\User;
use Livewire\Livewire;

it('menghitung active unpaid bill ke summary card', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 970000, 0, 970000));
});

it('menghitung active partial bill ke summary card', function () {
    $student = makeBillStudent(8);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    makeActiveSetting($student, $jemputan);

    $bill = makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);
    payActiveBill($bill, 300000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 550000, 300000, 250000));
});

it('menghitung active paid bill tetap ke summary card', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    payActiveBill($bill, 970000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 970000, 970000, 0));
});

it('menghitung active paid bill dengan overpayment tidak menghasilkan sisa negatif', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    payActiveBill($bill, 1000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 970000, 1000000, 0));
});

it('menghitung inactive unpaid bill ke summary card karena bill otoritatif', function () {
    $student = makeBillStudent(8);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    makeActiveSetting($student, $jemputan, false);
    makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 550000, 0, 550000))
        ->assertSee('Rp 550.000');
});

it('menghitung inactive partial bill ke summary card karena bill otoritatif', function () {
    $student = makeBillStudent(8);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    makeActiveSetting($student, $jemputan, false);

    $bill = makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);
    payActiveBill($bill, 300000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 550000, 300000, 250000))
        ->assertSee('Rp 300.000');
});

it('menghitung inactive paid bill ke summary card dan tetap tampil di periode asalnya', function () {
    $student = makeBillStudent(8);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    makeActiveSetting($student, $jemputan, false);

    $bill = makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);
    payActiveBill($bill, 550000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 550000, 550000, 0))
        ->assertSee('Lunas');
});

it('discounted bill menggunakan effective_amount di summary', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 5000000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 5000000, month: 8, year: 2026);

    BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
        'created_by' => User::factory()->create()->id,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 4500000, 0, 4500000));
});

it('non-discounted bill menggunakan nominal yang sama di summary', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 970000, 0, 970000));
});

it('total dibayar menjumlahkan paid_amount semua active bills', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $osis = makeBillType('OSIS');
    makeBillRate($osis, 8, 50000);
    makeActiveSetting($student, $osis);

    $billSpp = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    $billOsis = makeMonthlyBill($student, $osis, 50000, month: 8, year: 2026);

    payActiveBill($billSpp, 500000);
    payActiveBill($billOsis, 50000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 1020000, 550000, 470000));
});

it('total tunggakan menjumlahkan remaining_amount semua active bills', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $osis = makeBillType('OSIS');
    makeBillRate($osis, 8, 50000);
    makeActiveSetting($student, $osis);

    $billSpp = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    makeMonthlyBill($student, $osis, 50000, month: 8, year: 2026);

    payActiveBill($billSpp, 500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 1020000, 500000, 520000));
});

it('summary sama dengan agregasi seluruh bills yang ditampilkan di Tagihan Aktif', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $uangPangkal = makeBillType('Uang Pangkal');
    makeBillRate($uangPangkal, 8, 5000000, ['is_monthly' => false]);
    makeActiveSetting($student, $uangPangkal);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    makeActiveSetting($student, $jemputan, false);

    $billSpp = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    $billUangPangkal = makeOneTimeBill($student, $uangPangkal, 5000000, '2026/2027');
    makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);

    BillAdjustment::factory()->create([
        'bill_id' => $billUangPangkal->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
        'created_by' => User::factory()->create()->id,
    ]);

    payActiveBill($billSpp, 500000);
    payActiveBill($billUangPangkal, 2000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'all')
        ->tap(fn ($component) => assertSummaryCards($component, 6020000, 2500000, 3520000))
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Tagihan Sekali Bayar');
});
