<?php

use App\Enums\BillFrequency;
use App\Livewire\StudentDetail;
use App\Models\BillAdjustment;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use ReflectionProperty;

it('default kategori Semua Tagihan menghitung seluruh tagihan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $buku);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 2000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);

    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    makeYearlyBill($student, $buku, 500000, '2026/2027');
    makeOneTimeBill($student, $pangkal, 2000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSet('summaryCategory', 'all')
        ->assertSee('Tagihan Bulanan')
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Tagihan Sekali Bayar')
        ->tap(fn ($component) => assertSummaryCards($component, 3470000, 0, 3470000));
});

it('Semua Tagihan menjumlahkan tagihan bulanan dari semua bulan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    makeMonthlyBill($student, $spp, 1200000, month: 9, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 2170000, 0, 2170000));
});

it('kategori Bulanan hanya menghitung tagihan bulanan dan tidak menyembunyikan buku tagihan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $buku);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 2000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);

    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    makeMonthlyBill($student, $spp, 970000, month: 9, year: 2026);
    makeYearlyBill($student, $buku, 500000, '2026/2027');
    makeOneTimeBill($student, $pangkal, 2000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'monthly')
        ->tap(fn ($component) => assertSummaryCards($component, 1940000, 0, 1940000))
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Tahun Ajaran 2026/2027')
        ->assertSee('Tagihan Sekali Bayar');
});

it('kategori Tahunan hanya menghitung tagihan tahunan di tahun ajaran terpilih', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $buku);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 2000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);

    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    makeYearlyBill($student, $buku, 500000, '2026/2027');
    makeOneTimeBill($student, $pangkal, 2000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'yearly')
        ->tap(fn ($component) => assertSummaryCards($component, 500000, 0, 500000));
});

it('kategori Sekali Bayar hanya menghitung tagihan sekali bayar', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 2000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);

    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    makeOneTimeBill($student, $pangkal, 2000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'one_time')
        ->tap(fn ($component) => assertSummaryCards($component, 2000000, 0, 2000000));
});

it('Semua Tagihan menghitung seluruh tagihan termasuk bill tanpa periode', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $lainnya = makeBillType('Lain-lain');
    makeBillRate($lainnya, 8, 5000000, ['is_monthly' => false]);
    makeActiveSetting($student, $lainnya);

    makeMonthlyBill($student, $lainnya, 5000000, month: null, year: null);
    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '')
        ->tap(fn ($component) => assertSummaryCards($component, 5970000, 0, 5970000))
        ->set('summaryCategory', 'monthly')
        ->tap(fn ($component) => assertSummaryCards($component, 970000, 0, 970000));
});

it('active paid bill tetap dihitung pada kategori terpilih', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    payActiveBill($bill, 970000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'monthly')
        ->tap(fn ($component) => assertSummaryCards($component, 970000, 970000, 0));
});

it('active partial bill dihitung pada kategori terpilih', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    payActiveBill($bill, 500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'monthly')
        ->tap(fn ($component) => assertSummaryCards($component, 970000, 500000, 470000));
});

it('discounted bill menggunakan effective_amount pada kategori terpilih', function () {
    $student = makeBillStudent(8);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);

    $bill = makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
        'created_by' => User::factory()->create()->id,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'one_time')
        ->tap(fn ($component) => assertSummaryCards($component, 4500000, 0, 4500000));
});

it('total dibayar menjumlahkan paid_amount pada kategori terpilih', function () {
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
        ->set('summaryCategory', 'monthly')
        ->tap(fn ($component) => assertSummaryCards($component, 1020000, 550000, 470000));
});

it('total tunggakan menjumlahkan remaining_amount pada kategori terpilih', function () {
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
        ->set('summaryCategory', 'monthly')
        ->tap(fn ($component) => assertSummaryCards($component, 1020000, 500000, 520000));
});

it('mengubah kategori lewat Livewire langsung memperbarui summary cards', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);
    makeActiveSetting($student, $spp);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeActiveSetting($student, $buku);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 2000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $pangkal);

    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    makeYearlyBill($student, $buku, 500000, '2026/2027');
    makeOneTimeBill($student, $pangkal, 2000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->tap(fn ($component) => assertSummaryCards($component, 3470000, 0, 3470000))
        ->set('summaryCategory', 'monthly')
        ->tap(fn ($component) => assertSummaryCards($component, 970000, 0, 970000))
        ->set('summaryCategory', 'yearly')
        ->tap(fn ($component) => assertSummaryCards($component, 500000, 0, 500000))
        ->set('summaryCategory', 'one_time')
        ->tap(fn ($component) => assertSummaryCards($component, 2000000, 0, 2000000))
        ->set('summaryCategory', 'all')
        ->tap(fn ($component) => assertSummaryCards($component, 3470000, 0, 3470000));
});

it('summaryCategory tidak dipersistensikan ke URL', function () {
    $reflection = new ReflectionProperty(StudentDetail::class, 'summaryCategory');

    expect($reflection->getAttributes(Url::class))->toBe([]);
});
