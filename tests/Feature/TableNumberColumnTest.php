<?php

use App\Livewire\BankManagement;
use App\Livewire\PaymentCreate;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentRateManagement;
use App\Livewire\PaymentShow;
use App\Livewire\PaymentTypeManagement;
use App\Livewire\SchoolClassManagement;
use App\Livewire\StudentDetail;
use App\Livewire\StudentManagement;
use App\Livewire\StudentPaymentSettings;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\User;
use Livewire\Livewire;

const CRUD_TABLE_NO_HEADER = '<th class="py-3 px-3 w-14 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">No.</th>';

const CRUD_TABLE_NO_CELL = 'py-4 px-3 text-body-md text-on-surface-variant text-center font-numeric-data';

const STUDENT_TABLE_NO_HEADER = '<th class="p-4 w-14 text-center font-semibold">No.</th>';

const STUDENT_TABLE_NO_CELL = 'p-4 text-center text-on-surface-variant font-numeric-data';

const PAYMENT_TABLE_NO_HEADER = '<th class="py-3.5 px-3 w-14 whitespace-nowrap text-center">No.</th>';

const PAYMENT_TABLE_NO_CELL = 'py-4 px-3 text-body-md text-on-surface-variant text-center whitespace-nowrap font-numeric-data';

const BILL_TABLE_NO_HEADER = '<th class="py-2.5 px-3 w-12 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center whitespace-nowrap">No.</th>';

const BILL_TABLE_NO_CELL = 'py-3.5 px-3 align-middle text-center text-body-md text-on-surface-variant font-numeric-data whitespace-nowrap';

function rowNumberCells(string $html, string $cellClass): array
{
    preg_match_all('/<td class="'.preg_quote($cellClass, '/').'">(\d+)<\/td>/', $html, $matches);

    return array_map('intval', $matches[1]);
}

it('daftar tarif pembayaran menampilkan kolom No. dengan penomoran per halaman', function () {
    $type = PaymentType::factory()->create(['name' => 'SPP']);

    foreach (range(1, 26) as $level) {
        PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $type->id,
            'class_level' => $level,
            'amount' => 100000 + $level,
        ]);
    }

    $component = Livewire::test(PaymentRateManagement::class);

    $component->assertSeeHtml(CRUD_TABLE_NO_HEADER);
    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toBe(range(1, 25));

    $component->call('gotoPage', 2);
    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toBe([26]);
});

it('hasil filter tarif menomori ulang dari 1 dan berlanjut antar halaman', function () {
    $typeA = PaymentType::factory()->create(['name' => 'SPP']);
    $typeB = PaymentType::factory()->create(['name' => 'Uang Buku']);

    foreach (range(1, 26) as $level) {
        PaymentRate::factory()->monthly()->create([
            'payment_type_id' => $typeA->id,
            'class_level' => $level,
            'amount' => 100000 + $level,
        ]);
    }

    PaymentRate::factory()->monthly()->create([
        'payment_type_id' => $typeB->id,
        'class_level' => 8,
        'amount' => 500000,
    ]);

    $component = Livewire::test(PaymentRateManagement::class)
        ->set('filterPaymentTypeId', $typeB->id);

    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toBe([1]);

    $component->set('filterPaymentTypeId', $typeA->id);
    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toBe(range(1, 25));

    $component->call('gotoPage', 2);
    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toBe([26]);
});

it('daftar jenis pembayaran menampilkan kolom No. dengan baris pertama bernomor 1', function () {
    makeBillType('SPP');

    $component = Livewire::test(PaymentTypeManagement::class);

    $component->assertSeeHtml(CRUD_TABLE_NO_HEADER);
    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toContain(1);
});

it('daftar bank menampilkan kolom No. dengan baris pertama bernomor 1', function () {
    Bank::factory()->create();

    $component = Livewire::test(BankManagement::class);

    $component->assertSeeHtml(CRUD_TABLE_NO_HEADER);
    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toBe([1]);
});

it('daftar kelas menampilkan kolom No. dengan baris pertama bernomor 1', function () {
    SchoolClass::factory()->create(['level' => 8]);

    $component = Livewire::test(SchoolClassManagement::class);

    $component->assertSeeHtml(CRUD_TABLE_NO_HEADER);
    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toBe([1]);
});

it('daftar siswa menampilkan kolom No. dengan penomoran per halaman', function () {
    foreach (range(1, 11) as $i) {
        makeBillStudent(8);
    }

    $component = Livewire::test(StudentManagement::class);

    $component->assertSeeHtml(STUDENT_TABLE_NO_HEADER);
    expect(rowNumberCells($component->html(), STUDENT_TABLE_NO_CELL))->toBe(range(1, 10));

    $component->call('gotoPage', 2);
    expect(rowNumberCells($component->html(), STUDENT_TABLE_NO_CELL))->toBe([11]);
});

it('daftar pembayaran menampilkan kolom No. dengan baris pertama bernomor 1', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 100000, 8, 2026);

    $payment = Payment::create([
        'receipt_number' => 'REC-1000',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 100000,
    ]);

    $component = Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history');

    $component->assertSeeHtml(PAYMENT_TABLE_NO_HEADER);
    expect(rowNumberCells($component->html(), PAYMENT_TABLE_NO_CELL))->toBe([1]);
});

it('pengaturan pembayaran siswa menampilkan kolom No. dengan penomoran berurutan', function () {
    makeBillType('Jemputan');
    makeBillType('SPP');

    $student = makeBillStudent(8);

    $component = Livewire::test(StudentPaymentSettings::class, ['student' => $student]);

    $component->assertSeeHtml(CRUD_TABLE_NO_HEADER);
    expect(rowNumberCells($component->html(), CRUD_TABLE_NO_CELL))->toBe([1, 2]);
});

it('setiap section tagihan siswa menomori ulang dari 1', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1500000);

    $ekskul = makeBillType('Ekskul');
    makeBillRate($ekskul, 8, 200000);

    $osis = makeBillType('OSIS');
    makeBillRate($osis, 8, 100000);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);
    makeActiveSetting($student, $osis);

    makeMonthlyBill($student, $spp, 1500000, month: 8, year: 2026);
    makeMonthlyBill($student, $ekskul, 200000, month: 8, year: 2026);
    makeMonthlyBill($student, $osis, 100000, month: 8, year: 2026);

    makeMonthlyBill($student, $spp, 1500000, month: 9, year: 2026);
    makeMonthlyBill($student, $ekskul, 200000, month: 9, year: 2026);
    makeMonthlyBill($student, $osis, 100000, month: 9, year: 2026);

    $component = Livewire::test(StudentDetail::class, ['student' => $student]);

    $component->assertSeeHtml(BILL_TABLE_NO_HEADER);

    expect(rowNumberCells($component->html(), BILL_TABLE_NO_CELL))->toBe([1, 2, 3, 1, 2, 3]);
});

it('halaman tambah pembayaran mengelompokkan tagihan per periode tanpa kolom No.', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);

    $ekskul = makeBillType('Ekskul');
    makeBillRate($ekskul, 8, 200000);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $ekskul);

    makeMonthlyBill($student, $spp, 1750000, month: 8, year: 2026);
    makeMonthlyBill($student, $ekskul, 200000, month: 8, year: 2026);
    makeMonthlyBill($student, $spp, 1750000, month: 9, year: 2026);

    $component = Livewire::test(PaymentCreate::class);

    $component->call('selectStudent', $student->id);

    // Pemilihan tagihan tidak lagi memakai tabel dengan kolom No.
    expect($component->html())->not->toContain(BILL_TABLE_NO_HEADER);

    // Kelompok "Tagihan Agustus 2026" = 2 baris, "Tagihan September 2026" = 1 baris.
    $component->assertSee('Tagihan Agustus 2026')
        ->assertSee('Tagihan September 2026')
        ->assertSee('2 tagihan')
        ->assertSee('1 tagihan')
        ->assertSee('SPP')
        ->assertSee('Ekskul');
});

it('halaman detail pembayaran menampilkan kolom No. pada rincian item', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 100000, 8, 2026);

    $payment = Payment::create([
        'receipt_number' => 'REC-2000',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-10',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 100000,
    ]);

    $component = Livewire::test(PaymentShow::class, ['id' => $payment->id]);

    $component->assertSeeHtml('<th class="py-3 px-2 w-14 text-center">No.</th>');
    expect(rowNumberCells($component->html(), 'py-3.5 px-2 text-center text-body-md text-on-surface-variant font-numeric-data'))
        ->toBe([1]);
});
