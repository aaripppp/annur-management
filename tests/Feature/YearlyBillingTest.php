<?php

use App\Enums\BillFrequency;
use App\Livewire\PaymentCreate;
use App\Livewire\StudentDetail;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use App\Services\BillGenerationService;
use Carbon\Carbon;
use Database\Seeders\SchoolDataSeeder;
use Livewire\Livewire;

function assertYearlySummaryCards($component, int $tagihan, int $dibayar, int $tunggakan): void
{
    $component
        ->assertSeeHtml('tracking-wider">Rp '.number_format($tagihan, 0, ',', '.').'</p>')
        ->assertSeeHtml('tracking-wider">Rp '.number_format($dibayar, 0, ',', '.').'</p>')
        ->assertSeeHtml('class="text-headline-md font-headline-md text-error mt-1 font-numeric-data tracking-wider">Rp '.number_format($tunggakan, 0, ',', '.').'</p>');
}

function payYearlyBill(StudentBill $bill, int $amount): void
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

it('seeder mengatur Uang Buku dan Uang Kegiatan sebagai tahunan', function () {
    $this->seed(SchoolDataSeeder::class);

    foreach (['SPP', 'Ekskul', 'OSIS', 'Jemputan'] as $name) {
        $type = PaymentType::where('name', $name)->firstOrFail();
        expect(PaymentRate::where('payment_type_id', $type->id)->first()->billing_frequency)
            ->toBe(BillFrequency::Monthly);
    }

    foreach (['Uang Buku', 'Uang Kegiatan'] as $name) {
        $type = PaymentType::where('name', $name)->firstOrFail();
        expect(PaymentRate::where('payment_type_id', $type->id)->first()->billing_frequency)
            ->toBe(BillFrequency::Yearly);
    }

    $pangkal = PaymentType::where('name', 'Uang Pangkal')->firstOrFail();
    expect(PaymentRate::where('payment_type_id', $pangkal->id)->first()->billing_frequency)
        ->toBe(BillFrequency::OneTime);
});

it('membuat satu tagihan Uang Buku tahunan 2026/2027 dengan periode NULL', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    $created = app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    expect($created)->toHaveCount(1)
        ->and($bill)->not->toBeNull()
        ->and((int) $bill->amount)->toBe(500000)
        ->and($bill->academic_year)->toBe('2026/2027')
        ->and($bill->period_month)->toBeNull()
        ->and($bill->period_year)->toBeNull();
});

it('membuat satu tagihan Uang Kegiatan tahunan untuk tahun ajaran yang sama', function () {
    $type = makeBillType('Uang Kegiatan');
    makeBillRate($type, 8, 100000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    expect($bill)->not->toBeNull()
        ->and($bill->academic_year)->toBe('2026/2027')
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $type->id)
            ->count())->toBe(1);
});

it('generate ulang tahun ajaran yang sama tidak membuat duplikat', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    $service = app(BillGenerationService::class);
    $service->generateForStudent($student, Carbon::parse('2026-08-15'));
    $service->generateForStudent($student, Carbon::parse('2026-10-15'));
    $service->generateForStudent($student, Carbon::parse('2027-05-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->count())->toBe(1);
});

it('tidak membuat tagihan bulanan untuk tipe tahunan', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->whereNotNull('period_month')
        ->count())->toBe(0);
});

it('tahun ajaran baru dibuat setelah melewati bulan Juli', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    $service = app(BillGenerationService::class);
    $service->generateForStudent($student, Carbon::parse('2026-08-15'));
    $service->generateForStudent($student, Carbon::parse('2027-08-15'));

    $bills = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->get();

    expect($bills)->toHaveCount(2)
        ->and($bills->pluck('academic_year'))->toContain('2026/2027', '2027/2028');
});

it('generate until Juli 2027 tetap hanya menghasilkan tahun ajaran 2026/2027', function () {
    $this->travelTo('2026-08-15');

    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2027-07-15'));

    $bills = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->get();

    expect($bills)->toHaveCount(1)
        ->and($bills->first()->academic_year)->toBe('2026/2027');
});

it('generate until Agustus 2027 menambahkan tahun ajaran 2027/2028', function () {
    $this->travelTo('2026-08-15');

    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2027-08-15'));

    $bills = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->get();

    expect($bills)->toHaveCount(2)
        ->and($bills->pluck('academic_year'))->toContain('2026/2027', '2027/2028');
});

it('generate until dua kali tidak membuat duplikat tagihan tahunan', function () {
    $this->travelTo('2026-08-15');

    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    $service = app(BillGenerationService::class);
    $service->generateUntil($student, Carbon::parse('2027-08-15'));
    $service->generateUntil($student, Carbon::parse('2027-08-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->count())->toBe(2);
});

it('tidak membuat tagihan tahunan sebelum setting dimulai', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->update(['started_at' => '2028-01-01']);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->count())->toBe(0);
});

it('pembayaran sebagian pada tagihan tahunan mengurangi sisa dan status sebagian', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    payYearlyBill($bill, 200000);

    $bill->refresh();

    expect((float) $bill->paid_amount)->toBe(200000.0)
        ->and((float) $bill->remaining_amount)->toBe(300000.0)
        ->and($bill->status)->toBe(StudentBill::STATUS_PARTIAL);
});

it('status tagihan tahunan berubah menjadi lunas setelah dibayar penuh', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    expect($bill->status)->toBe(StudentBill::STATUS_UNPAID);

    payYearlyBill($bill, 500000);

    $bill->refresh();

    expect($bill->status)->toBe(StudentBill::STATUS_PAID)
        ->and((float) $bill->remaining_amount)->toBe(0.0);
});

it('diskon tetap bekerja pada tagihan tahunan', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -50000,
        'created_by' => User::factory()->create()->id,
    ]);

    $bill->refresh();

    expect((float) $bill->effective_amount)->toBe(450000.0)
        ->and((float) $bill->remaining_amount)->toBe(450000.0);
});

it('menampilkan tagihan tahunan pada daftar outstanding pembayaran', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id)
        ->assertSee('Pilih Tagihan yang Akan Dibayar')
        ->assertSee('Tahun Ajaran 2026/2027');

    $outstanding = $component->get('outstandingBills');

    expect($outstanding)->toHaveCount(1)
        ->and($outstanding[0]['period'])->toBe('Tahun Ajaran 2026/2027')
        ->and((int) $outstanding[0]['remaining_amount'])->toBe(500000);
});

it('memilih tagihan tahunan mengisi nominal default dengan periode tahun ajaran', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->assertSee('Tahun Ajaran 2026/2027');

    expect($component->get('selectedBillAmounts'))->toBe([$bill->id => 500000]);
});

it('menyimpan payment detail tagihan tahunan dengan academic_year', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $detail = Payment::where('student_id', $student->id)
        ->latest('id')
        ->first()
        ->details()
        ->first();

    expect($detail->bill_id)->toBe($bill->id)
        ->and($detail->academic_year)->toBe('2026/2027')
        ->and($detail->period_month)->toBeNull()
        ->and($detail->period_year)->toBeNull();
});

it('pembayaran sebagian tagihan tahunan mencocokkan bill dan menyimpan academic_year', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts', [$bill->id => 200000])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $detail = Payment::where('student_id', $student->id)
        ->latest('id')
        ->first()
        ->details()
        ->first();

    expect($detail->bill_id)->toBe($bill->id)
        ->and($detail->academic_year)->toBe('2026/2027');

    $bill->refresh();

    expect($bill->status)->toBe(StudentBill::STATUS_PARTIAL);
});

it('student detail menampilkan tagihan tahunan dalam section Tahun Ajaran', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);

    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $type);

    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('Tahun Ajaran 2026/2027')
        ->assertSee('Uang Buku');
});

it('summary kategori tahunan hanya menghitung bill tahunan aktif', function () {
    $student = makeBillStudent(8);

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 970000);

    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    makeActiveSetting($student, $spp);
    makeActiveSetting($student, $type);

    makeMonthlyBill($student, $spp, 970000, month: 8, year: 2026);
    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'yearly')
        ->tap(fn ($component) => assertYearlySummaryCards($component, 500000, 0, 500000));
});

it('kategori Sekali Bayar tidak menyertakan tagihan tahunan', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 5000000, ['is_monthly' => false]);

    $student = makeBillStudent(8);
    makeActiveSetting($student, $type);
    makeActiveSetting($student, $pangkal);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'one_time')
        ->tap(fn ($component) => assertYearlySummaryCards($component, 5000000, 0, 5000000));
});
