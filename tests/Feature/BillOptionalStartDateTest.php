<?php

use App\Enums\SchoolLevel;
use App\Livewire\PaymentCreate;
use App\Livewire\StudentDetail;
use App\Livewire\StudentPaymentSettings;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use App\Services\BillGenerationService;
use Carbon\Carbon;
use Livewire\Livewire;

it('aktivasi opsional hanya mencatat keikutsertaan, tidak membuat tagihan', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('confirmActivate', $jemputan->id)
        ->set('activateStartMonth', '2026-09')
        ->call('activate');

    $setting = StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->first();

    expect($setting)->not->toBeNull()
        ->and($setting->is_active)->toBeTrue()
        ->and($setting->started_at->toDateString())->toBe('2026-09-01')
        ->and($setting->ended_at)->toBeNull()
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $jemputan->id)
            ->count())->toBe(0);
});

it('generate until tidak membuat tagihan untuk tipe opsional', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $jemputan->id,
        'is_active' => true,
        'started_at' => '2026-09-01',
    ]);

    app(BillGenerationService::class)->generateUntil($student, Carbon::parse('2026-12-15'));

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0);
});

it('generateForStudent tidak membuat tagihan untuk tipe opsional aktif', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $jemputan->id,
        'is_active' => true,
        'started_at' => '2026-09-01',
    ]);

    $service = app(BillGenerationService::class);

    $augustCreated = $service->generateForStudent($student, Carbon::parse('2026-08-15'));
    $septemberCreated = $service->generateForStudent($student, Carbon::parse('2026-09-15'));

    expect($augustCreated)->toHaveCount(0)
        ->and($septemberCreated)->toHaveCount(0)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $jemputan->id)
            ->count())->toBe(0);
});

it('generateUntilBills tidak membuat tagihan untuk tipe opsional', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $jemputan->id,
        'is_active' => true,
        'started_at' => '2026-09-01',
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('generateUntilMonth', '2026-12')
        ->call('generateUntilBills');

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0);
});

it('ended_at tidak membuat tagihan untuk tipe opsional', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $jemputan->id,
        'is_active' => true,
        'started_at' => '2026-09-01',
        'ended_at' => '2026-11-30',
    ]);

    $service = app(BillGenerationService::class);

    expect($service->generateForStudent($student, Carbon::parse('2026-08-15')))->toHaveCount(0)
        ->and($service->generateForStudent($student, Carbon::parse('2026-09-15')))->toHaveCount(0)
        ->and($service->generateForStudent($student, Carbon::parse('2026-11-15')))->toHaveCount(0)
        ->and($service->generateForStudent($student, Carbon::parse('2026-12-15')))->toHaveCount(0);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0);
});

it('SPP/Ekskul auto-enrollment tetap berjalan dengan started_at otomatis', function () {
    $this->travelTo('2026-08-15');

    $spp = makeBillType('SPP', auto: true, required: true);
    makeBillRate($spp, 8, 1750000);
    makeLevelDefault($spp, SchoolLevel::SMP);

    $student = makeBillStudent(8);

    $created = app(BillGenerationService::class)->generateForStudent($student, Carbon::parse('2026-08-15'));

    expect($created)->toHaveCount(1);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $spp->id)
        ->where('period_month', 8)
        ->where('period_year', 2026)
        ->exists())->toBeTrue();
});

it('menonaktifkan tipe opsional tidak menghapus tagihan yang sudah ada', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    makeMonthlyBill($student, $jemputan, 550000, 9, 2026);

    Livewire::test(StudentPaymentSettings::class, ['student' => $student])
        ->call('confirmActivate', $jemputan->id)
        ->set('activateStartMonth', '2026-09')
        ->call('activate')
        ->call('toggle', $jemputan->id);

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(1)
        ->and(StudentBill::where('student_id', $student->id)
            ->where('payment_type_id', $jemputan->id)
            ->where('period_month', 9)
            ->exists())->toBeTrue();
});

it('mengaktifkan ulang tipe opsional tidak membuat tagihan otomatis', function () {
    $this->travelTo('2026-08-15');

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    $component = Livewire::test(StudentPaymentSettings::class, ['student' => $student]);

    $component->call('confirmActivate', $jemputan->id)
        ->set('activateStartMonth', '2026-09')
        ->call('activate')
        ->call('toggle', $jemputan->id)
        ->call('confirmActivate', $jemputan->id)
        ->set('activateStartMonth', '2026-09')
        ->call('activate');

    expect(StudentBill::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->count())->toBe(0);

    expect(StudentPaymentSetting::where('student_id', $student->id)
        ->where('payment_type_id', $jemputan->id)
        ->where('is_active', true)
        ->whereNull('ended_at')
        ->exists())->toBeTrue();
});

it('alur pembayaran tetap berjalan untuk tagihan opsional yang ditambahkan manual', function () {
    $this->travelTo('2026-08-15');

    $user = User::factory()->create();
    $bank = Bank::factory()->create();

    $jemputan = makeBillType('Jemputan', auto: false, required: false);
    makeBillRate($jemputan, 8, 550000);

    $student = makeBillStudent(8);

    $bill = makeMonthlyBill($student, $jemputan, 550000, 9, 2026);

    StudentPaymentSetting::create([
        'student_id' => $student->id,
        'payment_type_id' => $jemputan->id,
        'is_active' => true,
        'started_at' => '2026-09-01',
    ]);

    Livewire::actingAs($user);

    $component = Livewire::test(PaymentCreate::class)
        ->call('selectStudent', $student->id);

    $outstanding = collect($component->get('outstandingBills'));

    expect($outstanding->contains('id', $bill->id))->toBeTrue();

    $component->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-05')
        ->call('save');

    $this->assertDatabaseHas('payments', ['student_id' => $student->id]);

    $detail = Payment::where('student_id', $student->id)
        ->latest('id')
        ->first()
        ->details()
        ->first();

    expect($detail->bill_id)->toBe($bill->id)
        ->and((int) $detail->amount)->toBe(550000)
        ->and($detail->period_month)->toBe(9)
        ->and($detail->period_year)->toBe(2026);
});
