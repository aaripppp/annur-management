<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentCreate;
use App\Livewire\StudentPaymentSettings;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use App\Services\BillGenerationService;
use Carbon\Carbon;
use Livewire\Livewire;

function makeCustomSetting(
    Student $student,
    PaymentType $type,
    ?float $customAmount = null,
    bool $active = true,
    string $startedAt = '2026-01-01',
    ?string $endedAt = null
): StudentPaymentSetting {
    return StudentPaymentSetting::updateOrCreate(
        [
            'student_id' => $student->id,
            'payment_type_id' => $type->id,
        ],
        [
            'is_active' => $active,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'custom_amount' => $customAmount,
        ]
    );
}

it('custom_amount tersimpan di setting', function () {
    $type = makeBillType('Jemputan');
    $student = makeBillStudent(8);

    $setting = makeCustomSetting($student, $type, 550000);

    expect(StudentPaymentSetting::find($setting->id)->custom_amount)->not->toBeNull()
        ->and((float) StudentPaymentSetting::find($setting->id)->custom_amount)->toBe(550000.0);
});

it('custom_amount nullable diperbolehkan', function () {
    $type = makeBillType('Jemputan');
    $student = makeBillStudent(8);

    $setting = makeCustomSetting($student, $type);

    expect(StudentPaymentSetting::find($setting->id)->custom_amount)->toBeNull();
});

it('tipe opsional dengan custom amount tidak membuat tagihan otomatis', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $type, 550000);

    $created = app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect($created)->toHaveCount(0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $type->id)
            ->exists())->toBeFalse();
});

it('tipe opsional tanpa custom amount juga tidak membuat tagihan otomatis', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $type);

    $created = app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect($created)->toHaveCount(0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $type->id)
            ->exists())->toBeFalse();
});

it('custom amount antar siswa tersimpan independen tanpa tagihan otomatis', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);

    $arip = makeBillStudent(8);
    makeCustomSetting($arip, $type, 550000);

    $budi = makeBillStudent(8);
    makeCustomSetting($budi, $type, 450000);

    $caca = makeBillStudent(8);
    makeCustomSetting($caca, $type);

    $service = app(BillGenerationService::class);

    foreach ([$arip, $budi, $caca] as $student) {
        $service->generateForStudent($student, Carbon::parse('2026-08-15'));
    }

    expect(StudentBill::where('payment_type_id', $type->id)->count())->toBe(0);

    $amount = fn (Student $student) => StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first()
        ->custom_amount;

    expect((float) $amount($arip))->toBe(550000.0)
        ->and((float) $amount($budi))->toBe(450000.0)
        ->and($amount($caca))->toBeNull();
});

it('mengubah custom amount tipe opsional tidak membuat tagihan otomatis', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    $setting = makeCustomSetting($student, $type, 550000);

    $service = app(BillGenerationService::class);
    $service->generateForStudent($student, Carbon::parse('2026-09-15'));
    $service->generateForStudent($student, Carbon::parse('2026-10-15'));

    $setting->update(['custom_amount' => 600000]);

    $service->generateForStudent($student, Carbon::parse('2026-11-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->count())->toBe(0)
        ->and((float) $setting->refresh()->custom_amount)->toBe(600000.0);
});

it('mengosongkan custom amount tipe opsional tidak membuat tagihan otomatis', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    $setting = makeCustomSetting($student, $type, 550000);

    $service = app(BillGenerationService::class);
    $service->generateForStudent($student, Carbon::parse('2026-09-15'));

    $setting->update(['custom_amount' => null]);

    $service->generateForStudent($student, Carbon::parse('2026-10-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->count())->toBe(0);
});

it('started_at pada tipe opsional dengan custom amount tidak membuat tagihan', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $type, 550000, startedAt: '2026-09-01');

    $service = app(BillGenerationService::class);
    $service->generateForStudent($student, Carbon::parse('2026-08-15'));
    $service->generateForStudent($student, Carbon::parse('2026-09-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->count())->toBe(0);
});

it('ended_at pada tipe opsional dengan custom amount tidak membuat tagihan', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $type, 550000, startedAt: '2026-01-01', endedAt: '2026-10-15');

    $service = app(BillGenerationService::class);
    $service->generateForStudent($student, Carbon::parse('2026-10-15'));
    $service->generateForStudent($student, Carbon::parse('2026-11-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->count())->toBe(0);
});

it('PaymentCreate membaca nominal tagihan opsional yang ditambahkan manual', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $type, 550000);

    makeMonthlyBill($student, $type, 550000);

    $component = Livewire::test(PaymentCreate::class);
    $component->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'));
    $jemputan = $outstanding->firstWhere('payment_type_name', 'Jemputan');

    expect((int) $jemputan['amount'])->toBe(550000)
        ->and((int) $jemputan['remaining_amount'])->toBe(550000);
});

it('diskon tetap bekerja pada tagihan opsional yang ditambahkan manual', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $type, 550000);

    $bill = makeMonthlyBill($student, $type, 550000);

    BillAdjustment::factory()->create([
        'bill_id' => $bill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -50000,
        'created_by' => User::factory()->create()->id,
    ]);

    $bill->refresh();

    expect((float) $bill->effective_amount)->toBe(500000.0)
        ->and((float) $bill->remaining_amount)->toBe(500000.0);
});

it('alur pembayaran tetap berjalan pada tagihan opsional manual', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $type, 550000);

    $bill = makeMonthlyBill($student, $type, 550000);

    Livewire::actingAs($user);

    Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id)
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-08-15')
        ->call('save');

    $bill->refresh();

    expect($bill->status)->toBe(StudentBill::STATUS_PAID);

    $payment = Payment::where('student_id', $student->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->details()->first()->bill_id)->toBe($bill->id);
});

it('custom amount juga berlaku untuk tipe tahunan', function () {
    $type = makeBillType('Uang Buku');
    makeBillRate($type, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $type, 550000);

    app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    $bill = StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $type->id)
        ->first();

    expect((float) $bill->amount)->toBe(550000.0)
        ->and($bill->academic_year)->toBe('2026/2027');
});

it('resolvedAmount mengembalikan custom amount atau rate default', function () {
    $type = makeBillType('Jemputan');
    makeBillRate($type, 8, 500000);

    $studentA = makeBillStudent(8);
    $settingA = makeCustomSetting($studentA, $type, 550000);

    $studentB = makeBillStudent(8);
    $settingB = makeCustomSetting($studentB, $type);

    expect($settingA->resolvedAmount(8))->toBe(550000.0)
        ->and($settingB->resolvedAmount(8))->toBe(500000.0);
});

it('settings UI menampilkan nominal default dan nominal khusus untuk tipe opsional aktif', function () {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $jemputan, 550000);

    $component = Livewire::test(StudentPaymentSettings::class, ['student' => $student]);

    $component
        ->assertSee('Bulanan')
        ->assertSee('Rp 500.000')
        ->assertSee('Kosongkan untuk menggunakan tarif default.')
        ->assertSee('Simpan');

    expect($component->get('customAmountInputs')[$jemputan->id])->toBe('550000');
});

it('tipe wajib tidak menampilkan field nominal khusus', function () {
    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->assertSee('LOCKED')
        ->assertSee('Rp 1.750.000')
        ->assertDontSee('Kosongkan untuk menggunakan tarif default.')
        ->assertDontSee('saveCustomAmount');
});

it('menyimpan custom amount lewat UI', function () {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $jemputan);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->set("customAmountInputs.{$jemputan->id}", '600000')
        ->call('saveCustomAmount', $jemputan->id)
        ->assertDispatched('student-payment-settings-updated', studentId: $student->id);

    $setting = StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    expect((float) $setting->custom_amount)->toBe(600000.0);
});

it('mengosongkan custom amount lewat UI menghapusnya', function () {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $jemputan, 550000);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->set("customAmountInputs.{$jemputan->id}", '')
        ->call('saveCustomAmount', $jemputan->id);

    $setting = StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    expect($setting->custom_amount)->toBeNull();
});

it('menolak custom amount yang tidak valid', function (string $value) {
    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 500000);
    $student = makeBillStudent(8);
    makeCustomSetting($student, $jemputan);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->set("customAmountInputs.{$jemputan->id}", $value)
        ->call('saveCustomAmount', $jemputan->id)
        ->assertHasErrors("customAmountInputs.{$jemputan->id}");

    $setting = StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    expect($setting->custom_amount)->toBeNull();
})->with([
    'nol' => '0',
    'negatif' => '-50000',
    'bukan angka' => 'abc',
]);
