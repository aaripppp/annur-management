<?php

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentIndex;
use App\Livewire\ProspectivePaymentCreate;
use App\Livewire\ProspectivePaymentShow;
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
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\ProspectiveStudentConversionService;
use App\Services\SchoolBankRecapService;
use App\Services\SchoolDailyReportService;
use App\Services\SchoolMonthlyAllUnitsReportService;
use App\Services\SchoolMonthlyReportService;
use Livewire\Livewire;

/**
 * Katalog minimal untuk skenario pembayaran pendaftaran setelah konversi:
 * tahun ajaran aktif 2026/2027, tahun ajaran tujuan (future) 2027/2028,
 * kelas tujuan level 8 (SMP), dan jenis pembayaran Formulir Pendaftaran
 * untuk calon siswa.
 *
 * @return array{
 *     activeYear: AcademicYear,
 *     futureYear: AcademicYear,
 *     schoolClass: SchoolClass,
 *     schoolLevel: SchoolLevel,
 *     formulir: PaymentType,
 * }
 */
function convertedPaymentCatalog(int $classLevel = 8, int $amount = 350000): array
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
        'name' => 'Formulir Pendaftaran Konversi',
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
function convertablePaymentProspect(array $catalog, array $overrides = []): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create(array_merge([
        'registration_number' => 'REG-CONV-0001',
        'academic_year_id' => $catalog['futureYear']->id,
        'school_class_id' => $catalog['schoolClass']->id,
        'nama_lengkap' => 'Rina Calon Konversi',
        'nama_panggilan' => 'Rina',
        'jenis_kelamin' => 'P',
        'status' => ProspectiveStudentStatus::Registered,
        'converted_student_id' => null,
        'converted_at' => null,
    ], $overrides));
}

function convertedPaymentBill(ProspectiveStudent $prospect, PaymentType $type, float $amount): ProspectiveStudentBill
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

function convertPaymentProspect(ProspectiveStudent $prospect, ?string $nis = null): Student
{
    return app(ProspectiveStudentConversionService::class)->convert($prospect, $nis);
}

/**
 * @param  array<string, mixed>  $allocations
 */
function submitConvertedPayment(ProspectiveStudent $prospect, array $allocations, Bank $bank, string $paymentDate = '2026-09-10'): void
{
    $component = Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $prospect])
        ->set('selectedBillIds', array_keys($allocations))
        ->set('bank_id', $bank->id)
        ->set('payment_date', $paymentDate);

    foreach ($allocations as $billId => $amount) {
        $component->set('selectedBillAmounts.'.$billId, $amount);
    }

    $component->call('save');
}

/*
|--------------------------------------------------------------------------
| 1. Eligibility pembayaran: converted tetap boleh, cancelled ditolak
|--------------------------------------------------------------------------
*/

it('allows a converted prospect to pay an outstanding registration bill', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);

    $student = convertPaymentProspect($prospect);
    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());

    expect($prospect->fresh()->isConverted())->toBeTrue()
        ->and($student->id)->toBe($prospect->fresh()->converted_student_id);

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $prospect->fresh()])
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts.'.$bill->id, 350000)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-10')
        ->call('save')
        ->assertHasNoErrors();

    expect(ProspectiveStudentPayment::where('prospective_student_id', $prospect->id)->count())->toBe(1)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID)
        ->and((float) $bill->fresh()->remaining_amount)->toBe(0.0);
});

it('marks the bill as partial when a converted prospect pays part of it', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $prospect->fresh()])
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts.'.$bill->id, 100000)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-10')
        ->call('save')
        ->assertHasNoErrors();

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL)
        ->and((float) $bill->fresh()->paid_amount)->toBe(100000.0)
        ->and((float) $bill->fresh()->remaining_amount)->toBe(250000.0);
});

it('allows a converted prospect to settle the remaining balance of a partial bill', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());

    submitConvertedPayment($prospect->fresh(), [$bill->id => 100000], $bank);
    submitConvertedPayment($prospect->fresh(), [$bill->id => 250000], $bank, '2026-09-12');

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID)
        ->and(ProspectiveStudentPayment::where('prospective_student_id', $prospect->id)->count())->toBe(2);
});

it('rejects a registration payment for a cancelled prospect', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog, ['status' => ProspectiveStudentStatus::Cancelled]);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $prospect])
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts.'.$bill->id, 50000)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-10')
        ->call('save')
        ->assertHasErrors('selectedBillIds');

    expect(ProspectiveStudentPayment::count())->toBe(0);
});

it('rejects overpayment on an already settled registration bill', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());

    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank);

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $prospect->fresh()])
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts.'.$bill->id, 50000)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-11')
        ->call('save')
        ->assertHasErrors('selectedBillAmounts.'.$bill->id);

    expect(ProspectiveStudentPayment::where('prospective_student_id', $prospect->id)->count())->toBe(1);
});

it('rejects a payment for a bill owned by another prospect', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertPaymentProspect($prospect);

    $other = convertablePaymentProspect($catalog, ['registration_number' => 'REG-CONV-9999']);
    $foreignBill = convertedPaymentBill($other, $catalog['formulir'], 350000);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $prospect->fresh()])
        ->set('selectedBillIds', [$foreignBill->id])
        ->set('selectedBillAmounts.'.$foreignBill->id, 50000)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-10')
        ->call('save')
        ->assertHasErrors('selectedBillIds');

    expect(ProspectiveStudentPayment::count())->toBe(0);
});

it('still allows a registered prospect to pay before conversion', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $prospect])
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts.'.$bill->id, 350000)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-10')
        ->call('save')
        ->assertHasNoErrors();

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);
});

/*
|--------------------------------------------------------------------------
| 2. Section Riwayat Pendaftaran pada workspace siswa
|--------------------------------------------------------------------------
*/

it('renders the registration history card for a student converted from a prospect', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Riwayat Pendaftaran')
        ->assertSee('REG-CONV-0001')
        ->assertSee('2027/2028');
});

it('shows the total registration bill and remaining amount', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Total Tagihan Pendaftaran')
        ->assertSee('Rp 350.000');
});

it('shows the paid amount after a partial registration payment', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 100000], $bank);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Rp 100.000')
        ->assertSee('Rp 250.000')
        ->assertSee('Sebagian');
});

it('labels an unpaid registration bill as Belum Bayar', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Belum Bayar');
});

it('labels a partially paid registration bill as Sebagian', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 120000], $bank);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Sebagian');
});

it('hides the whole registration card when the bill is settled', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSeeHtml('data-registration-history')
        ->assertDontSee('Riwayat Pendaftaran')
        ->assertDontSee('Lunas')
        ->assertDontSee('Bayar Formulir')
        ->assertDontSee('Riwayat Pembayaran');
});

it('shows the Bayar Formulir action linking to the prospective payment create page', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Bayar Formulir')
        ->assertSee(route('pembayaran.prospective.create', ['prospectiveStudent' => $prospect->id]), false);
});

it('does not render the registration history card for a regular student', function () {
    $student = Student::factory()->create();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSee('Riwayat Pendaftaran')
        ->assertDontSee('Bayar Formulir');
});

it('summarizes multiple prospective bills instead of hardcoding a single bill', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);

    $wawancara = PaymentType::create([
        'name' => 'Wawancara Konversi',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);
    convertedPaymentBill($prospect, $wawancara, 150000);

    $student = convertPaymentProspect($prospect);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Rp 500.000');
});

it('links the registration card to the prospective payment workspace for history', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee(route('pembayaran.prospective.workspace', ['prospectiveStudent' => $prospect->id]), false);
});

/*
|--------------------------------------------------------------------------
| 3. Penyimpanan pembayaran: hanya domain calon siswa, tanpa duplikasi
|--------------------------------------------------------------------------
*/

it('stores a converted registration payment only in prospective tables', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank);

    expect(ProspectiveStudentPayment::count())->toBe(1)
        ->and(Payment::count())->toBe(0)
        ->and(StudentBill::count())->toBe(0);
});

it('does not create a student-side bill for the registration payment', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank);

    expect($student->fresh()->bills()->count())->toBe(0)
        ->and($student->fresh()->payments()->count())->toBe(0);
});

it('links the payment detail to the prospective bill and records the amount', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 200000], $bank);

    $detail = ProspectiveStudentPaymentDetail::firstOrFail();

    expect($detail->prospective_student_bill_id)->toBe($bill->id)
        ->and((float) $detail->amount)->toBe(200000.0);
});

it('reuses a single prospective bill across sequential payments', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 150000], $bank);
    submitConvertedPayment($prospect->fresh(), [$bill->id => 200000], $bank, '2026-09-15');

    expect($prospect->fresh()->bills()->count())->toBe(1)
        ->and((float) $bill->fresh()->paid_amount)->toBe(350000.0);
});

it('leaves the prospect bill count unchanged after conversion and payment', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);

    $billsBefore = $prospect->bills()->count();
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank);

    expect($prospect->fresh()->bills()->count())->toBe($billsBefore);
});

/*
|--------------------------------------------------------------------------
| 4. Pencarian: converted tidak lagi muncul sebagai calon siswa
|--------------------------------------------------------------------------
*/

it('excludes a converted prospect from prospective search results', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Rina Calon')
        ->assertSeeHtml('wire:key="student-result-'.$student->id.'"')
        ->assertDontSeeHtml('wire:key="prospective-result-'.$prospect->id.'"');
});

it('does not return a converted prospect even when searching by registration number', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'REG-CONV-0001')
        ->assertDontSeeHtml('wire:key="prospective-result-'.$prospect->id.'"')
        ->assertDontSee(route('pembayaran.prospective.workspace', ['prospectiveStudent' => $prospect->id]), false);
});

it('still lists a registered prospect in prospective search results', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Rina Calon')
        ->assertSeeHtml('wire:key="prospective-result-'.$prospect->id.'"');
});

/*
|--------------------------------------------------------------------------
| 5. Regresi laporan: pembayaran converted tetap dihitung sekali
|--------------------------------------------------------------------------
*/

it('includes a converted prospect payment once in the daily report', function () {
    $this->travelTo('2026-09-15');

    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank, '2026-09-15');

    $report = app(SchoolDailyReportService::class)->generate('2026-09-15');
    $prospectRows = collect($report['detail_rows'])->where('source', 'prospective');

    expect($prospectRows)->toHaveCount(1)
        ->and($report['grand_total'])->toBe(350000.0)
        ->and($report['transaction_count'])->toBe(1);
});

it('includes a converted prospect payment once in the monthly report', function () {
    $this->travelTo('2026-09-15');

    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank, '2026-09-15');

    $report = app(SchoolMonthlyReportService::class)->generate(2026, 9);

    expect(collect($report['detail_rows'])->where('source', 'prospective'))->toHaveCount(1)
        ->and($report['grand_total'])->toBe(350000.0);
});

it('includes a converted prospect payment once in the all-units monthly report', function () {
    $this->travelTo('2026-09-15');

    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank, '2026-09-15');

    $report = app(SchoolMonthlyAllUnitsReportService::class)->generate(2026, 9, SchoolLevel::SMP);

    expect($report['grand_total'])->toBe(350000.0);
});

it('includes a converted prospect payment once in the bank recap', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank, '2026-09-15');

    $recap = app(SchoolBankRecapService::class)->generate('2026-09-15');
    $prospectRow = collect($recap['detail_rows'])->firstWhere('source', SchoolBankRecapService::SOURCE_PROSPECTIVE);

    expect($recap['grand_total'])->toBe(350000.0)
        ->and($recap['transaction_count'])->toBe(1)
        ->and($prospectRow['name'])->toBe($prospect->nama_lengkap);
});

it('keeps student-side financial totals free of the registration payment', function () {
    $this->travelTo('2026-09-15');

    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank, '2026-09-15');

    expect(Payment::count())->toBe(0)
        ->and(StudentBill::count())->toBe(0)
        ->and($student->fresh()->bills()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 6. Smoke rute + Livewire end-to-end
|--------------------------------------------------------------------------
*/

it('runs the full converted-prospect payment journey end to end', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);
    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);

    // 1. Kartu Riwayat Pendaftaran tampil di workspace siswa
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee('Riwayat Pendaftaran')
        ->assertSee('Bayar Formulir');

    // 2. Rute workspace pembayaran calon siswa tetap 200 setelah konversi
    $this->actingAs($user)
        ->get(route('pembayaran.prospective.workspace', $prospect->fresh()))
        ->assertOk()
        ->assertSee('Tagihan Pendaftaran')
        ->assertSee('Riwayat Pembayaran');

    // 3. Pembayaran sebagian
    submitConvertedPayment($prospect->fresh(), [$bill->id => 150000], $bank);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $prospect->fresh()])
        ->assertSee('Rp 150.000')
        ->assertSee('Sebagian');

    // 4. Pelunasan sisa
    submitConvertedPayment($prospect->fresh(), [$bill->id => 200000], $bank, '2026-09-15');

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    // 5. Setelah lunas, seluruh kartu riwayat pendaftaran hilang
    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSeeHtml('data-registration-history')
        ->assertDontSee('Riwayat Pendaftaran')
        ->assertDontSee('Lunas')
        ->assertDontSee('Bayar Formulir');

    // 6. Rute kwitansi/detail pembayaran 200
    $payment = ProspectiveStudentPayment::where('prospective_student_id', $prospect->id)->latest('id')->firstOrFail();

    $this->get(route('pembayaran.prospective.show', $payment->id))
        ->assertOk()
        ->assertSeeLivewire(ProspectivePaymentShow::class);

    // 7. Pencarian menampilkan siswa (bukan calon siswa) setelah konversi
    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Rina Calon')
        ->assertSeeHtml('wire:key="student-result-'.$student->id.'"')
        ->assertDontSeeHtml('wire:key="prospective-result-'.$prospect->id.'"');
});

/*
|--------------------------------------------------------------------------
| 7. Visibilitas kartu pendaftaran: hanya selama ada sisa tagihan
|--------------------------------------------------------------------------
*/

it('shows the registration card for an outstanding registration bill', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('data-registration-history')
        ->assertSee('Riwayat Pendaftaran')
        ->assertSee('Belum Bayar')
        ->assertSee('Bayar Formulir');
});

it('shows the registration card while a payment is only partial', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 100000], $bank);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('data-registration-history')
        ->assertSee('Sebagian')
        ->assertSee('Rp 250.000');
});

it('hides the registration card entirely when the only bill is fully paid', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSeeHtml('data-registration-history')
        ->assertDontSee('Riwayat Pendaftaran')
        ->assertDontSee('Lunas')
        ->assertDontSee('Total Tagihan Pendaftaran')
        ->assertDontSee('Sisa Tagihan')
        ->assertDontSee('Bayar Formulir')
        ->assertDontSee('Riwayat Pembayaran');
});

it('keeps the registration card when one of several bills is still outstanding', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $formulirBill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);

    $otherType = PaymentType::create([
        'name' => 'Wawancara Visibilitas',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);
    convertedPaymentBill($prospect, $otherType, 100000);

    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$formulirBill->id => 350000], $bank);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('data-registration-history')
        ->assertSee('Belum Bayar')
        ->assertSee('Rp 100.000');
});

it('hides the registration card only after every bill is fully paid', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $formulirBill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);

    $otherType = PaymentType::create([
        'name' => 'Wawancara Lunas',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);
    $otherBill = convertedPaymentBill($prospect, $otherType, 100000);

    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    submitConvertedPayment($prospect->fresh(), [$formulirBill->id => 350000], $bank);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('data-registration-history');

    submitConvertedPayment($prospect->fresh(), [$otherBill->id => 100000], $bank, '2026-09-12');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSeeHtml('data-registration-history')
        ->assertDontSee('Riwayat Pendaftaran');
});

it('keeps the registration card visible when a settled payment is cancelled', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSeeHtml('data-registration-history');

    $payment = ProspectiveStudentPayment::query()->where('prospective_student_id', $prospect->id)->sole();
    $payment->update([
        'status' => ProspectiveStudentPayment::STATUS_CANCELLED,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Terduplikasi',
    ]);

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSeeHtml('data-registration-history')
        ->assertSee('Riwayat Pendaftaran')
        ->assertSee('Rp 350.000')
        ->assertSee('Bayar Formulir');
});

it('keeps prospective history in the database when the card is hidden', function () {
    $catalog = convertedPaymentCatalog();
    $prospect = convertablePaymentProspect($catalog);
    $bill = convertedPaymentBill($prospect, $catalog['formulir'], 350000);
    $student = convertPaymentProspect($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    submitConvertedPayment($prospect->fresh(), [$bill->id => 350000], $bank);

    $payment = ProspectiveStudentPayment::query()->where('prospective_student_id', $prospect->id)->sole();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertDontSeeHtml('data-registration-history');

    expect(ProspectiveStudent::query()->whereKey($prospect->id)->exists())->toBeTrue()
        ->and($prospect->fresh()->converted_student_id)->toBe($student->id)
        ->and($prospect->fresh()->bills()->count())->toBe(1)
        ->and(ProspectiveStudentPayment::query()->where('prospective_student_id', $prospect->id)->count())->toBe(1)
        ->and(ProspectiveStudentPaymentDetail::query()->where('prospective_student_payment_id', $payment->id)->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.show', $payment->id))
        ->assertOk()
        ->assertSeeLivewire(ProspectivePaymentShow::class);
});
