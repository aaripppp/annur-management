<?php

use App\Enums\BillFrequency;
use App\Livewire\PaymentCreate;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use Livewire\Livewire;

it('dropdown tambah tagihan manual menampilkan SPP', function () {
    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$catalog['SPP']->id.'">SPP</option>');
});

it('dropdown tambah tagihan manual menampilkan Ekskul', function () {
    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$catalog['Ekskul']->id.'">Ekskul</option>');
});

it('dropdown tambah tagihan manual menampilkan OSIS', function () {
    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$catalog['OSIS']->id.'">OSIS</option>');
});

it('dropdown tambah tagihan manual menampilkan Jemputan', function () {
    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$catalog['Jemputan']->id.'">Jemputan</option>');
});

it('dropdown tambah tagihan manual menampilkan Uang Buku', function () {
    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$catalog['Uang Buku']->id.'">Uang Buku</option>');
});

it('dropdown tambah tagihan manual menampilkan Uang Kegiatan', function () {
    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$catalog['Uang Kegiatan']->id.'">Uang Kegiatan</option>');
});

it('dropdown tambah tagihan manual menampilkan Uang Pangkal', function () {
    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$catalog['Uang Pangkal']->id.'">Uang Pangkal</option>');
});

it('dropdown tambah tagihan manual menampilkan Lain-lain', function () {
    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$catalog['Lain-lain']->id.'">Lain-lain</option>');
});

it('dropdown tambah tagihan manual tidak menampilkan nama jenis pembayaran ganda', function () {
    $student = makeBillStudent(8);

    $bukuPertama = makeBillType('Uang Buku');
    makeBillRate($bukuPertama, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $bukuKedua = makeBillType('Uang Buku');
    makeBillRate($bukuKedua, 8, 600000, ['billing_frequency' => BillFrequency::Yearly]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtml('<option value="'.$bukuPertama->id.'">Uang Buku</option>')
        ->assertDontSeeHtml('<option value="'.$bukuKedua->id.'">Uang Buku</option>');
});

it('dropdown tambah tagihan manual memakai urutan kanonik', function () {
    $student = makeBillStudent(8);

    manualAddCatalog(8);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBill')
        ->assertSeeHtmlInOrder([
            '>SPP</option>',
            '>Ekskul</option>',
            '>OSIS</option>',
            '>Jemputan</option>',
            '>Uang Buku</option>',
            '>Uang Kegiatan</option>',
            '>Uang Pangkal</option>',
            '>Lain-lain</option>',
        ]);
});

it('Jemputan dapat ditambahkan manual dari section bulanan walau tanpa pengaturan aktif', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 9, 2026);

    $jemputan = $catalog['Jemputan'];

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->exists())->toBeFalse();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('openAddBillForMonth(9, 2026)')
        ->call('openAddBillForMonth', 9, 2026)
        ->assertSet('addPeriodLocked', true)
        ->assertSet('addLockedMonth', 9)
        ->assertSet('addLockedYear', 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->period_month)->toBe(9)
        ->and($bill->period_year)->toBe(2026)
        ->and((float) $bill->amount)->toBe(550000.0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $jemputan->id)
            ->count())->toBe(1);
});

it('Jemputan dapat ditambahkan manual tanpa pengaturan aktif dan memakai bulan dari kartu bulanan', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 10, 2026);

    $jemputan = $catalog['Jemputan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('openAddBillForMonth(10, 2026)')
        ->call('openAddBillForMonth', 10, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '450000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->period_month)->toBe(10)
        ->and($bill->period_year)->toBe(2026)
        ->and((float) $bill->amount)->toBe(450000.0);
});

it('Lain-lain dapat ditambahkan manual', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $lain = $catalog['Lain-lain'];

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $lain->id)
        ->exists())->toBeFalse();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->assertSet('addPeriodLocked', false)
        ->set('addPaymentTypeId', (string) $lain->id)
        ->set('addAmount', '125000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $lain->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull()
        ->and($bill->academic_year)->toBeNull()
        ->and((float) $bill->amount)->toBe(125000.0);
});

it('kartu bulanan otomatis mengunci bulan dan tahun untuk tagihan manual', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 9, 2026);

    $jemputan = $catalog['Jemputan'];

    $component = Livewire::test(StudentDetail::class, ['student' => $student]);

    $component
        ->assertSeeHtml('openAddBillForMonth(9, 2026)')
        ->call('openAddBillForMonth', 9, 2026)
        ->assertSet('addPeriodLocked', true)
        ->assertSet('addLockedMonth', 9)
        ->assertSet('addLockedYear', 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id);

    $component->assertSet('addFrequency', BillFrequency::Monthly->value)
        ->assertSee('Periode diambil otomatis dari bagian tagihan yang dipilih.');

    $component
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->period_month)->toBe(9)
        ->and($bill->period_year)->toBe(2026);
});

it('section tahunan otomatis mengunci tahun ajaran untuk tagihan manual', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);
    makeMonthlyBill($student, $spp, 970000, 9, 2026);

    $uangBuku = $catalog['Uang Buku'];
    makeActiveSetting($student, $uangBuku);
    StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $uangBuku->id,
        'amount' => 500000,
        'period_month' => null,
        'period_year' => null,
        'academic_year' => '2026/2027',
        'billing_frequency' => BillFrequency::Yearly->value,
        'due_date' => null,
    ]);

    $uangKegiatan = $catalog['Uang Kegiatan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml("openAddBillForAcademicYear('2026/2027')")
        ->call('openAddBillForAcademicYear', '2026/2027')
        ->assertSet('addPeriodLocked', true)
        ->assertSet('addLockedAcademicYear', '2026/2027')
        ->set('addPaymentTypeId', (string) $uangKegiatan->id)
        ->assertSet('addFrequency', BillFrequency::Yearly->value)
        ->assertSee('Tahun ajaran diambil otomatis dari bagian tagihan yang dipilih.')
        ->set('addAmount', '100000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $uangKegiatan->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->academic_year)->toBe('2026/2027')
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull();
});

it('section sekali bayar tidak mengisi bulan maupun tahun untuk tagihan manual', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $pangkal = $catalog['Uang Pangkal'];
    makeActiveSetting($student, $pangkal);
    StudentBill::create([
        'student_id' => $student->id,
        'payment_type_id' => $pangkal->id,
        'amount' => 5000000,
        'period_month' => null,
        'period_year' => null,
        'academic_year' => null,
        'billing_frequency' => BillFrequency::OneTime->value,
        'due_date' => null,
    ]);

    $lain = $catalog['Lain-lain'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('openAddBillForOneTime')
        ->call('openAddBillForOneTime')
        ->assertSet('addPeriodLocked', false)
        ->assertSet('addLockedMonth', null)
        ->set('addPaymentTypeId', (string) $lain->id)
        ->set('addAmount', '125000')
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

it('pencegahan duplikat bulanan tetap berfungsi untuk tagihan manual', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $jemputan = $catalog['Jemputan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 9, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 9, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(1);
});

it('pencegahan duplikat tahunan tetap berfungsi untuk tagihan manual', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $uangKegiatan = $catalog['Uang Kegiatan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForAcademicYear', '2026/2027')
        ->set('addPaymentTypeId', (string) $uangKegiatan->id)
        ->set('addAmount', '100000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForAcademicYear', '2026/2027')
        ->set('addPaymentTypeId', (string) $uangKegiatan->id)
        ->set('addAmount', '100000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $uangKegiatan->id)
        ->count())->toBe(1);
});

it('pencegahan duplikat sekali bayar tetap berfungsi untuk tagihan manual', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $lain = $catalog['Lain-lain'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $lain->id)
        ->set('addAmount', '125000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForOneTime')
        ->set('addPaymentTypeId', (string) $lain->id)
        ->set('addAmount', '125000')
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $lain->id)
        ->count())->toBe(1);
});

it('edit dan hapus tagihan manual tetap berfungsi', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $jemputan = $catalog['Jemputan'];

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 9, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('editBill', $bill->id)
        ->assertSet('isEditOpen', true)
        ->set('editAmount', '600000')
        ->call('saveEditBill')
        ->assertHasNoErrors();

    expect((float) $bill->refresh()->amount)->toBe(600000.0);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('confirmDeleteBill', $bill->id)
        ->call('deleteBill')
        ->assertHasNoErrors();

    expect(StudentBill::find($bill->id))->toBeNull();
});

it('alur pembayaran tetap berfungsi untuk tagihan manual', function () {
    $this->travelTo('2026-08-15');

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $student = makeBillStudent(8);

    $catalog = manualAddCatalog(8);

    $spp = $catalog['SPP'];
    makeActiveSetting($student, $spp);

    $jemputan = $catalog['Jemputan'];

    makeActiveSetting($student, $jemputan);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('openAddBillForMonth', 9, 2026)
        ->set('addPaymentTypeId', (string) $jemputan->id)
        ->set('addAmount', '550000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-15')
        ->call('save');

    $payment = Payment::where('student_id', $student->id)->latest('id')->first();

    expect($payment)->not->toBeNull()
        ->and((float) $payment->total_amount)->toBe(550000.0)
        ->and($bill->refresh()->isSettled())->toBeTrue();
});
