<?php

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentIndex;
use App\Livewire\ProspectivePaymentCreate;
use App\Livewire\ProspectivePaymentWorkspace;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\ProspectiveStudentConversionService;
use Livewire\Livewire;

/**
 * @return array{
 *     activeYear: AcademicYear,
 *     futureYear: AcademicYear,
 *     schoolClass: SchoolClass,
 *     schoolLevel: SchoolLevel,
 *     formulir: PaymentType,
 * }
 */
function wsRegCatalog(int $classLevel = 8, int $amount = 350000): array
{
    $activeYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $activeYear->update(['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);

    $futureYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );
    $futureYear->update(['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']);

    $schoolClass = SchoolClass::factory()->create(['level' => $classLevel]);
    $schoolLevel = SchoolLevel::fromClassLevel($classLevel);

    $formulir = PaymentType::create([
        'name' => 'Formulir Pendaftaran Workspace',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    makeLevelDefault($formulir, $schoolLevel, required: false, active: true);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $formulir->id,
        'class_level' => $classLevel,
        'amount' => $amount,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    return compact('activeYear', 'futureYear', 'schoolClass', 'schoolLevel', 'formulir');
}

/**
 * @param  array<string, mixed>  $catalog
 * @param  array<string, mixed>  $overrides
 */
function wsRegProspect(array $catalog, array $overrides = []): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create(array_merge([
        'registration_number' => 'REG-WS-0001',
        'academic_year_id' => $catalog['futureYear']->id,
        'school_class_id' => $catalog['schoolClass']->id,
        'nama_lengkap' => 'Cinta Calon Workspace',
        'nama_panggilan' => 'Cinta',
        'jenis_kelamin' => 'P',
        'status' => ProspectiveStudentStatus::Registered,
        'converted_student_id' => null,
        'converted_at' => null,
    ], $overrides));
}

function wsRegBill(ProspectiveStudent $prospect, PaymentType $type, float $amount): ProspectiveStudentBill
{
    return ProspectiveStudentBill::create([
        'prospective_student_id' => $prospect->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
        'billing_frequency' => BillFrequency::OneTime,
        'academic_year' => '2027/2028',
        'due_date' => null,
    ]);
}

function wsRegConvert(ProspectiveStudent $prospect, ?string $nis = null): Student
{
    return app(ProspectiveStudentConversionService::class)->convert($prospect, $nis);
}

/**
 * @param  array<int, float>  $allocations
 */
function wsRegPay(ProspectiveStudent $prospect, array $allocations, Bank $bank, string $paymentDate = '2026-09-15'): ProspectiveStudentPayment
{
    $component = Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $prospect])
        ->set('selectedBillIds', array_keys($allocations))
        ->set('bank_id', $bank->id)
        ->set('payment_date', $paymentDate);

    foreach ($allocations as $billId => $amount) {
        $component->set('selectedBillAmounts.'.$billId, $amount);
    }

    $component->call('save');

    return ProspectiveStudentPayment::query()
        ->where('prospective_student_id', $prospect->id)
        ->latest('id')
        ->firstOrFail();
}

/*
|--------------------------------------------------------------------------
| 1-3, 8. Visibilitas & konten pada Student Payment Workspace
|--------------------------------------------------------------------------
*/

it('menampilkan kartu pendaftaran pada workspace saat tagihan belum dibayar', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    wsRegBill($prospect, $catalog['formulir'], 350000);
    $student = wsRegConvert($prospect);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSeeHtml('data-registration-history')
        ->assertSee('Riwayat Pendaftaran')
        ->assertSee('No. Pendaftaran REG-WS-0001')
        ->assertSee('2027/2028')
        ->assertSee('Belum Bayar')
        ->assertSee('Total Tagihan Pendaftaran')
        ->assertSee('Rp 350.000')
        ->assertSee('Bayar Formulir');
});

it('menampilkan kartu pendaftaran pada workspace saat pembayaran sebagian', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    $bill = wsRegBill($prospect, $catalog['formulir'], 350000);
    $student = wsRegConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    wsRegPay($prospect->fresh(), [$bill->id => 100000], $bank);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSeeHtml('data-registration-history')
        ->assertSee('Sebagian')
        ->assertSee('Rp 100.000')
        ->assertSee('Rp 250.000');
});

it('menyembunyikan kartu pendaftaran pada workspace saat sudah lunas', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    $bill = wsRegBill($prospect, $catalog['formulir'], 350000);
    $student = wsRegConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    wsRegPay($prospect->fresh(), [$bill->id => 350000], $bank);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertDontSeeHtml('data-registration-history')
        ->assertDontSee('Riwayat Pendaftaran')
        ->assertDontSee('Lunas')
        ->assertDontSee('Bayar Formulir');
});

it('tidak menampilkan kartu pendaftaran pada workspace untuk siswa biasa', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Biasa Workspace']);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertDontSeeHtml('data-registration-history')
        ->assertDontSee('Riwayat Pendaftaran')
        ->assertDontSee('Bayar Formulir');
});

/*
|--------------------------------------------------------------------------
| 5-7. Banyak tagihan, lunas total, dan pembayaran dibatalkan
|--------------------------------------------------------------------------
*/

it('tetap menampilkan kartu saat masih ada sisa dari salah satu dari banyak tagihan', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    $formulirBill = wsRegBill($prospect, $catalog['formulir'], 350000);

    $extraType = PaymentType::create([
        'name' => 'Seragam Workspace',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);
    $extraBill = wsRegBill($prospect, $extraType, 200000);

    $student = wsRegConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    wsRegPay($prospect->fresh(), [$formulirBill->id => 350000], $bank);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSeeHtml('data-registration-history')
        ->assertSee('Rp 200.000');

    wsRegPay($prospect->fresh(), [$extraBill->id => 200000], $bank);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertDontSeeHtml('data-registration-history')
        ->assertDontSee('Riwayat Pendaftaran');
});

it('tidak menganggap lunas pembayaran yang dibatalkan', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    $bill = wsRegBill($prospect, $catalog['formulir'], 350000);
    $student = wsRegConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    $payment = wsRegPay($prospect->fresh(), [$bill->id => 350000], $bank);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertDontSeeHtml('data-registration-history');

    $payment->update(['status' => ProspectiveStudentPayment::STATUS_CANCELLED]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSeeHtml('data-registration-history')
        ->assertSee('Belum Bayar')
        ->assertSee('Rp 350.000');
});

/*
|--------------------------------------------------------------------------
| 10-11. Routing aksi
|--------------------------------------------------------------------------
*/

it('mengarahkan Bayar Formulir ke alur pembayaran calon siswa yang ada', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    wsRegBill($prospect, $catalog['formulir'], 350000);
    $student = wsRegConvert($prospect);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Bayar Formulir')
        ->assertSee(route('pembayaran.prospective.create', ['prospectiveStudent' => $prospect->id]), false)
        ->assertSee(route('pembayaran.prospective.workspace', ['prospectiveStudent' => $prospect->id]), false);
});

it('membuka workspace pembayaran calon siswa dari Riwayat Pembayaran', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    wsRegBill($prospect, $catalog['formulir'], 350000);
    wsRegConvert($prospect);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.workspace', $prospect))
        ->assertOk()
        ->assertSeeLivewire(ProspectivePaymentWorkspace::class);
});

/*
|--------------------------------------------------------------------------
| 9, 15. Parity dengan Student Detail
|--------------------------------------------------------------------------
*/

it('menampilkan total, terbayar, sisa, dan status yang sama di Student Detail dan workspace', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    $bill = wsRegBill($prospect, $catalog['formulir'], 350000);
    $student = wsRegConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    wsRegPay($prospect->fresh(), [$bill->id => 100000], $bank);

    $detail = Livewire::test(StudentDetail::class, ['student' => $student]);
    $workspace = Livewire::test(PaymentIndex::class)->call('selectStudent', $student->id);

    foreach ([$detail, $workspace] as $component) {
        $component
            ->assertSee('Total Tagihan Pendaftaran')
            ->assertSee('Rp 350.000')
            ->assertSee('Rp 100.000')
            ->assertSee('Rp 250.000')
            ->assertSee('Sebagian');
    }
});

/*
|--------------------------------------------------------------------------
| 12. Tidak membuat pembayaran/tagihan siswa biasa
|--------------------------------------------------------------------------
*/

it('tidak membuat pembayaran atau tagihan siswa biasa hanya dengan membuka kartu pendaftaran', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    wsRegBill($prospect, $catalog['formulir'], 350000);
    $student = wsRegConvert($prospect);

    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(PaymentIndex::class)->call('selectStudent', $student->id);
    Livewire::test(StudentDetail::class, ['student' => $student]);

    $this->actingAs($user)->get(route('pembayaran.index', ['student' => $student->id]))->assertOk();
    $this->actingAs($user)->get(route('pembayaran.prospective.create', $prospect))->assertOk();

    expect(Payment::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and(StudentBill::query()->where('student_id', $student->id)
            ->where('payment_type_id', $catalog['formulir']->id)->count())->toBe(0)
        ->and(ProspectiveStudentPayment::query()->where('prospective_student_id', $prospect->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 16. Route smoke
|--------------------------------------------------------------------------
*/

it('mengembalikan 200 untuk seluruh route terkait kartu pendaftaran', function () {
    $catalog = wsRegCatalog();
    $prospect = wsRegProspect($catalog);
    wsRegBill($prospect, $catalog['formulir'], 350000);
    $student = wsRegConvert($prospect);

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('siswa.show', $student))->assertOk()->assertSee('Riwayat Pendaftaran');
    $this->actingAs($user)->get(route('pembayaran.index', ['student' => $student->id]))->assertOk()->assertSee('Riwayat Pendaftaran');
    $this->actingAs($user)->get(route('pembayaran.prospective.create', $prospect))->assertOk();
    $this->actingAs($user)->get(route('pembayaran.prospective.workspace', $prospect))->assertOk();
});
