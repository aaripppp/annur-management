<?php

use App\Enums\BillFrequency;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use Livewire\Livewire;

it('tagihan Jemputan manual Oktober dibuat, modal tertutup, dan langsung tampil di kartu Oktober', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $jemputan = $catalog['Jemputan'];

    $component = Livewire::test(StudentDetail::class, ['student' => $student]);

    $component->call('openAddBillForMonth', 10, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '500000')
        ->call('saveAddBill')
        ->assertHasNoErrors()
        ->assertSet('isAddOpen', false)
        ->assertSet('addPaymentTypeId', '')
        ->assertSet('addAmount', '');

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->where('period_month', 10)
        ->where('period_year', 2026)
        ->first();

    expect($bill)->not->toBeNull()
        ->and((float) $bill->amount)->toBe(500000.0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $jemputan->id)
            ->count())->toBe(1);

    $component->assertSee('Tagihan Oktober 2026')
        ->assertSee('Jemputan')
        ->assertSee('Rp 500.000');
});

it('tagihan manual tampil walau tanpa pengaturan pembayaran aktif', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $jemputan = $catalog['Jemputan'];

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->exists())->toBeFalse();

    makeMonthlyBill($student, $jemputan, 500000, 10, 2026);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Oktober 2026')
        ->assertSee('Jemputan')
        ->assertSee('Rp 500.000');
});

it('duplikat Jemputan Oktober ditolak dengan pesan yang menyebut jenis dan periode', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $jemputan = $catalog['Jemputan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 10, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '500000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 10, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '500000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod'])
        ->assertSee('Tagihan Jemputan untuk Oktober 2026 sudah ada. Edit tagihan yang sudah ada jika ingin mengubah nominal.');

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(1);
});

it('Jemputan September tetap bisa dibuat setelah Jemputan Oktober ada', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $jemputan = $catalog['Jemputan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 10, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '500000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 9, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->where('period_month', 10)
        ->where('period_year', 2026)
        ->count())->toBe(1)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $jemputan->id)
            ->where('period_month', 9)
            ->where('period_year', 2026)
            ->count())->toBe(1);
});

it('Jemputan Oktober dan SPP Oktober bisa berdampingan', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $jemputan = $catalog['Jemputan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 10, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '500000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $component = Livewire::test(StudentDetail::class, ['student' => $student]);

    expect(StudentBill::where('student_id', $student->id)
        ->where('period_month', 10)
        ->where('period_year', 2026)
        ->count())->toBe(2);

    $component->assertSee('2 tagihan · Total sisa Rp 1.470.000');
});

it('summary card memperbarui total setelah tagihan manual ditambahkan', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $jemputan = $catalog['Jemputan'];

    $component = Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'monthly');

    $component->assertSeeHtml('tracking-wider">Rp 970.000</p>');

    $component->call('openAddBillForMonth', 10, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '500000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $component->assertSee('Rp 1.470.000');
});

it('status kartu periode memperbarui setelah tagihan manual ditambahkan', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $sppBill = makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $payment = Payment::create([
        'receipt_number' => 'KWT-2026-000777',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $sppBill->id,
        'payment_type_id' => $spp->id,
        'period_month' => 10,
        'period_year' => 2026,
        'amount' => 970000,
    ]);

    $jemputan = $catalog['Jemputan'];

    $component = Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Lunas');

    $component->call('openAddBillForMonth', 10, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '500000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $component->assertSee('Sebagian');
});

it('modal menampilkan periode bulan terkunci sebagai nilai read-only', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);
    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $jemputan = $catalog['Jemputan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 10, 2026)
        ->assertSet('addPeriodLocked', true)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->assertSet('addFrequency', BillFrequency::Monthly->value)
        ->assertSee('Periode diambil otomatis dari bagian tagihan yang dipilih.')
        ->assertDontSeeHtml('id="add_month"')
        ->assertDontSeeHtml('id="add_year"');
});
