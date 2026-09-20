<?php

use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\User;
use Livewire\Livewire;

it('menampilkan tagihan Agustus di bawah judul "Tagihan Agustus 2026"', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('SPP')
        ->assertSee('Rp 1.500.000');
});

it('menampilkan tagihan September di bawah judul "Tagihan September 2026"', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    makeMonthlyBill($student, $spp, 1500000, month: 9, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan September 2026')
        ->assertSee('SPP');
});

it('memisahkan tagihan dari bulan yang berbeda ke section masing-masing', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);

    $ekskul = makeBillType('Ekskul');
    makeBillRate($ekskul, 8, 200000);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);

    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    makeMonthlyBill($student, $ekskul, 200000, month: 9, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeInOrder([
            'Tagihan Agustus 2026',
            'SPP',
            'Tagihan September 2026',
            'Ekskul',
        ]);
});

it('mengurutkan section bulan secara kronologis dari periode terlama', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    makeMonthlyBill($student, $spp, 1500000, month: 9, year: 2026);
    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    makeMonthlyBill($student, $spp, 1500000, month: 10, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeInOrder([
            'Tagihan Agustus 2026',
            'Tagihan September 2026',
            'Tagihan Oktober 2026',
        ]);
});

it('tidak menampilkan section bulan yang tidak memiliki tagihan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    makeMonthlyBill($student, $spp, 1500000, month: 10, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Tagihan Oktober 2026')
        ->assertDontSee('Tagihan September 2026');
});

it('menampilkan tagihan non-bulanan seperti Uang Pangkal di section "Tagihan Sekali Bayar"', function () {
    $student = makeBillStudent(8);

    $uangPangkal = makeBillType('Uang Pangkal');
    makeBillRate($uangPangkal, 8, 5000000, ['is_monthly' => false]);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);

    makeActiveSetting($student, $uangPangkal);
    makeActiveSetting($student, $spp);

    makeOneTimeBill($student, $uangPangkal, 5000000, '2026/2027');
    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeInOrder([
            'Tagihan Agustus 2026',
            'Tagihan Sekali Bayar',
        ])
        ->assertSee('Uang Pangkal')
        ->assertSee('Rp 5.000.000');
});

it('menampilkan nominal efektif setelah diskon di kolom Tagihan tabel bulanan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 5000000, month: 8, year: 2026);

    BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -500000,
        'created_by' => User::factory()->create()->id,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Rp 4.500.000')
        ->assertSee('Belum Bayar')
        ->assertDontSee('-Rp 500.000');
});

it('aksi baris Edit dan Hapus tetap berfungsi di tabel bulanan', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);
    makeActiveSetting($student, $spp);

    $bill = makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Edit')
        ->assertSee('Hapus')
        ->call('editBill', $bill->id)
        ->assertSet('isEditOpen', true)
        ->assertSet('editTypeName', 'SPP')
        ->assertSee('Simpan')
        ->call('closeEdit')
        ->call('confirmDeleteBill', $bill->id)
        ->assertSet('isDeleteOpen', true);
});

it('menampilkan status sebagian untuk tagihan bulanan yang sudah dibayar sebagian', function () {
    $student = makeBillStudent(8);

    $jemputan = makeBillType('Jemputan');
    makeBillRate($jemputan, 8, 550000);
    makeActiveSetting($student, $jemputan);

    $bill = makeMonthlyBill($student, $jemputan, 550000, month: 8, year: 2026);

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000010',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 300000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $jemputan->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 300000,
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Sebagian')
        ->assertSee('Rp 250.000');
});
