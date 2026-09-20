<?php

use App\Enums\BillFrequency;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\User;
use Livewire\Livewire;

function payBillForPeriodStatus(StudentBill $bill, int $amount): void
{
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-PS-'.uniqid(),
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

it('menampilkan status Belum Lunas pada kartu bulan yang belum dibayar', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    $ekskul = makeBillType('Ekskul');
    makeBillRate($ekskul, 8, 200000);
    $osis = makeBillType('OSIS');
    makeBillRate($osis, 8, 50000);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);
    makeActiveSetting($student, $osis);

    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    makeMonthlyBill($student, $ekskul, 200000, month: 8, year: 2026);
    makeMonthlyBill($student, $osis, 50000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Belum Lunas');
});

it('menampilkan status Sebagian pada kartu bulan yang dibayar sebagian', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    payBillForPeriodStatus($bill, 500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Sebagian');
});

it('menampilkan status Lunas pada kartu bulan yang sudah lunas', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    payBillForPeriodStatus($bill, 1500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Lunas');
});

it('menempatkan tagihan lunas di dalam kartu bulan asalnya', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    payBillForPeriodStatus($bill, 1500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeInOrder(['Tagihan Agustus 2026', 'SPP'])
        ->assertDontSee('Riwayat Tagihan');
});

it('tetap menampilkan kartu bulan yang sudah lunas', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    payBillForPeriodStatus($bill, 1500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Lunas')
        ->assertSee('Total sisa Rp 0');
});

it('tidak mengubah urutan kartu bulan yang sudah lunas', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $paid = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    payBillForPeriodStatus($paid, 1500000);

    makeMonthlyBill($student, $spp, 1500000, month: 9, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeInOrder([
            'Tagihan Agustus 2026',
            'Tagihan September 2026',
        ]);
});

it('menghitung status September secara independen dari Agustus', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $august = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    payBillForPeriodStatus($august, 1500000);

    makeMonthlyBill($student, $spp, 1500000, month: 9, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Lunas')
        ->assertSee('Tagihan September 2026')
        ->assertSee('Belum Lunas');
});

it('tidak merender section Riwayat Tagihan terpisah', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    payBillForPeriodStatus($bill, 1500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSee('Riwayat Tagihan')
        ->assertDontSee('Tagihan yang sudah lunas');
});

it('menampilkan status agregat pada section tagihan tahunan', function () {
    $student = makeBillStudent(8);

    $buku = makeBillType('Uang Buku');
    makeBillRate($buku, 8, 2500000, ['billing_frequency' => BillFrequency::Yearly]);
    $kegiatan = makeBillType('Uang Kegiatan');
    makeBillRate($kegiatan, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    makeActiveSetting($student, $buku);
    makeActiveSetting($student, $kegiatan);

    $paid = makeYearlyBill($student, $buku, 2500000, '2026/2027');
    payBillForPeriodStatus($paid, 2500000);

    makeYearlyBill($student, $kegiatan, 500000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Sebagian');
});

it('menampilkan status agregat pada section tagihan sekali bayar', function () {
    $student = makeBillStudent(8);

    $uangPangkal = makeBillType('Uang Pangkal');
    makeBillRate($uangPangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $uangPangkal);

    makeOneTimeBill($student, $uangPangkal, 5000000, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Belum Lunas');
});

it('menampilkan status Lunas pada section tagihan sekali bayar yang sudah lunas', function () {
    $student = makeBillStudent(8);

    $uangPangkal = makeBillType('Uang Pangkal');
    makeBillRate($uangPangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    makeActiveSetting($student, $uangPangkal);

    $bill = makeOneTimeBill($student, $uangPangkal, 5000000, '2026/2027');
    payBillForPeriodStatus($bill, 5000000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Lunas');
});

it('menampilkan Lunas saat seluruh tagihan bulan lunas (regresi Ekskul/OSIS/SPP)', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 870000);
    $ekskul = makeBillType('Ekskul');
    makeBillRate($ekskul, 8, 60000);
    $osis = makeBillType('OSIS');
    makeBillRate($osis, 8, 5000);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);
    makeActiveSetting($student, $osis);

    payBillForPeriodStatus(makeMonthlyBill($student, $spp, 870000, month: 8, year: 2026), 870000);
    payBillForPeriodStatus(makeMonthlyBill($student, $ekskul, 60000, month: 8, year: 2026), 60000);
    payBillForPeriodStatus(makeMonthlyBill($student, $osis, 5000, month: 8, year: 2026), 5000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('3 tagihan · Total sisa Rp 0')
        ->assertSee('Lunas')
        ->assertDontSee('Sebagian')
        ->assertSeeHtml('tracking-wider">Rp 935.000</p>')
        ->assertSeeHtml('class="text-headline-md font-headline-md text-error mt-1 font-numeric-data tracking-wider">Rp 0</p>');
});

it('merender badge status tepat setelah judul periode dan tombol tambah di kanan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    payBillForPeriodStatus($bill, 1500000);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeInOrder([
            'Tagihan Agustus 2026',
            'Lunas',
            'Total sisa',
            'Tambah Tagihan',
        ])
        ->assertSeeHtml('wire:click="openAddBillForMonth(8, 2026)"');
});
