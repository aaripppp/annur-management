<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function tuBillingClass(SchoolLevel $level): SchoolClass
{
    return SchoolClass::factory()->create(['level' => match ($level) {
        SchoolLevel::TK => -1,
        SchoolLevel::SD => 4,
        SchoolLevel::SMP => 8,
        SchoolLevel::SMA => 11,
    }]);
}

function tuBillingStudent(SchoolClass $schoolClass, AcademicYear $academicYear, string $name): Student
{
    $student = Student::factory()->create([
        'class_id' => $schoolClass->id,
        'nama_lengkap' => $name,
    ]);

    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);

    return $student;
}

function tuBillingBill(
    Student $student,
    PaymentType $paymentType,
    float $amount,
    ?int $month = null,
    ?int $year = null,
    ?string $academicYear = null,
    string $frequency = BillFrequency::Monthly->value,
): StudentBill {
    return StudentBill::factory()->create([
        'student_id' => $student->id,
        'payment_type_id' => $paymentType->id,
        'amount' => $amount,
        'period_month' => $month,
        'period_year' => $year,
        'academic_year' => $academicYear,
        'billing_frequency' => $frequency,
    ]);
}

function tuBillingPay(StudentBill $bill, int $amount): Payment
{
    $payment = Payment::query()->create([
        'receipt_number' => 'TU-BILL-'.uniqid(),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $bill->student_id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-05',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => User::factory()->create()->id,
    ]);

    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);

    return $payment;
}

function tuBillingFinancialSnapshot(): array
{
    return collect([
        'student_bills',
        'bill_adjustments',
        'payments',
        'payment_details',
        'student_payment_settings',
    ])->mapWithKeys(fn (string $table): array => [
        $table => DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all(),
    ])->all();
}

beforeEach(function () {
    AcademicYear::query()->update(['is_active' => false]);
    $this->academicYear = AcademicYear::query()->updateOrCreate(['year' => '2026/2027'], [
        'is_active' => true,
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->otherAcademicYear = AcademicYear::query()->updateOrCreate(['year' => '2025/2026'], [
        'is_active' => false,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);
    $this->sdClass = tuBillingClass(SchoolLevel::SD);
    $this->smpClass = tuBillingClass(SchoolLevel::SMP);
    $this->student = tuBillingStudent($this->sdClass, $this->academicYear, 'TU Billing Student');
    $this->foreignStudent = tuBillingStudent($this->smpClass, $this->academicYear, 'Foreign Billing Student');
    $this->tu = User::factory()->create([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => SchoolLevel::SD,
        'is_active' => true,
    ]);
    $this->paymentType = PaymentType::factory()->create(['name' => 'SPP']);
});

it('shows complete own-unit billing as read-only with July through June ordering and statuses', function () {
    $monthlyBills = collect([
        [7, 2026], [8, 2026], [9, 2026], [10, 2026], [11, 2026], [12, 2026],
        [1, 2027], [2, 2027], [3, 2027], [4, 2027], [5, 2027], [6, 2027],
    ])->map(fn (array $period): StudentBill => tuBillingBill(
        $this->student,
        $this->paymentType,
        1_000_000,
        $period[0],
        $period[1],
    ));
    tuBillingPay($monthlyBills[0], 400_000);
    tuBillingPay($monthlyBills[1], 1_000_000);
    tuBillingBill($this->student, PaymentType::factory()->create(['name' => 'Uang Buku']), 2_500_000, academicYear: '2026/2027', frequency: BillFrequency::Yearly->value);
    tuBillingBill($this->student, PaymentType::factory()->create(['name' => 'Uang Pangkal']), 5_000_000, academicYear: '2026/2027', frequency: BillFrequency::OneTime->value);

    $response = $this->actingAs($this->tu)->get(route('siswa.show', $this->student));

    $response->assertOk()
        ->assertSee('Tagihan &amp; Pembayaran', false)
        ->assertSee('Cetak Tagihan')
        ->assertSee('Juli 2026')
        ->assertSee('Juni 2027')
        ->assertSeeInOrder(['Juli 2026', 'Agustus 2026', 'September 2026', 'Juni 2027'])
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Sebagian')
        ->assertSee('Lunas')
        ->assertSee('Belum Bayar')
        ->assertSee('Rp 400.000')
        ->assertSee('Rp 600.000')
        ->assertDontSee('wire:click="openAddBill', false)
        ->assertDontSee('wire:click="openBillbook', false)
        ->assertDontSee('wire:click="editBill', false)
        ->assertDontSee('wire:click="confirmDeleteBill', false)
        ->assertDontSee(route('pembayaran.show', $monthlyBills[0]->paymentDetails()->first()->payment_id), false)
        ->assertDontSee('Tanggal TF Terakhir')
        ->assertDontSee('Tanggal Input Terakhir')
        ->assertDontSee('>Riwayat</th>', false);

    expect(substr_count($response->getContent(), 'min-w-[850px]'))->toBe(14)
        ->and(substr_count($response->getContent(), 'min-w-[1450px]'))->toBe(0);
});

it('allows valid academic-year selection but rejects invalid state and ownership changes', function () {
    tuBillingBill($this->student, $this->paymentType, 500_000, academicYear: '2025/2026', frequency: BillFrequency::Yearly->value);

    $component = Livewire::actingAs($this->tu)
        ->test(StudentDetail::class, ['student' => $this->student])
        ->set('selectedAcademicYear', '2025/2026')
        ->assertHasNoErrors()
        ->assertSee('2025/2026');

    Livewire::actingAs($this->tu)
        ->test(StudentDetail::class, ['student' => $this->student])
        ->set('selectedAcademicYear', '2099/2100')
        ->assertHasErrors('selectedAcademicYear');

    $this->student->enrollments()->where('academic_year_id', $this->academicYear->id)->update([
        'school_class_id' => $this->smpClass->id,
    ]);

    $component->set('summaryCategory', 'monthly')->assertForbidden();
});

it('rejects tampered Student identity and finance capability state', function (string $property, mixed $value) {
    $tamperedValue = $value === 'foreign-student-id' ? $this->foreignStudent->id : $value;

    expect(fn () => Livewire::actingAs($this->tu)
        ->test(StudentDetail::class, ['student' => $this->student])
        ->set($property, $tamperedValue))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'foreign Student ID' => ['student', 'foreign-student-id'],
    'finance-write capability' => ['canManageFinance', true],
]);

it('denies foreign detail and billing PDF while allowing own-unit PDF without writes', function () {
    $bill = tuBillingBill($this->student, $this->paymentType, 1_000_000, 7, 2026);
    tuBillingPay($bill, 400_000);
    $before = tuBillingFinancialSnapshot();

    $this->actingAs($this->tu)
        ->get(route('siswa.bills.pdf', ['student' => $this->student, 'academic_year' => '2099/2100']))
        ->assertSessionHasErrors('academic_year');
    $this->actingAs($this->tu)
        ->get(route('siswa.show', $this->foreignStudent))
        ->assertForbidden();
    $this->get(route('siswa.bills.pdf', ['student' => $this->foreignStudent, 'academic_year' => '2026/2027']))
        ->assertForbidden();
    $this->get(route('siswa.bills.pdf', ['student' => $this->student, 'academic_year' => '2026/2027']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(tuBillingFinancialSnapshot())->toBe($before);
});

it('does not mutate financial records when TU views billing detail', function () {
    $bill = tuBillingBill($this->student, $this->paymentType, 1_000_000, 7, 2026);
    tuBillingPay($bill, 400_000);
    $before = tuBillingFinancialSnapshot();

    $this->actingAs($this->tu)->get(route('siswa.show', $this->student))->assertOk();

    expect(tuBillingFinancialSnapshot())->toBe($before);
});

it('denies every hidden StudentDetail financial mutation for TU', function (string $method, array $parameters) {
    $bill = tuBillingBill($this->student, $this->paymentType, 1_000_000, 7, 2026);
    $before = tuBillingFinancialSnapshot();

    Livewire::actingAs($this->tu)
        ->test(StudentDetail::class, ['student' => $this->student])
        ->call($method, ...array_map(fn (mixed $value): mixed => $value === 'bill' ? $bill->id : $value, $parameters))
        ->assertForbidden();

    expect(tuBillingFinancialSnapshot())->toBe($before);
})->with([
    'generate bills' => ['generateBills', []],
    'open generate until' => ['openGenerateUntil', []],
    'generate until' => ['generateUntilBills', []],
    'open billbook' => ['openBillbook', []],
    'generate billbook' => ['generateBillbook', []],
    'edit adjustment' => ['editAdjustment', ['bill']],
    'save adjustment' => ['saveAdjustment', []],
    'remove adjustment' => ['removeAdjustment', []],
    'open manual bill' => ['openAddBill', []],
    'open monthly bill' => ['openAddBillForMonth', [7, 2026]],
    'open yearly bill' => ['openAddBillForAcademicYear', ['2026/2027']],
    'open one-time bill' => ['openAddBillForOneTime', []],
    'save manual bill' => ['saveAddBill', []],
    'edit bill' => ['editBill', ['bill']],
    'save edited bill' => ['saveEditBill', []],
    'confirm bill deletion' => ['confirmDeleteBill', ['bill']],
    'delete bill' => ['deleteBill', []],
    'refresh finance event' => ['refreshBills', []],
]);

it('keeps payment and billing-settings workspaces denied', function () {
    $bill = tuBillingBill($this->student, $this->paymentType, 1_000_000, 7, 2026);
    $payment = tuBillingPay($bill, 400_000);

    $this->actingAs($this->tu);

    foreach ([
        route('pembayaran.create'),
        route('pembayaran.edit', $payment->id),
        route('pembayaran.koreksi', $payment->id),
        route('siswa.payment-settings', $this->student),
    ] as $url) {
        $this->get($url)->assertRedirect(route('tu.pending'));
    }
});

it('keeps billing detail SELECT queries bounded as bill rows grow', function () {
    $this->actingAs($this->tu);

    $measure = function (int $billCount): int {
        PaymentDetail::query()->delete();
        Payment::query()->delete();
        StudentBill::query()->delete();

        foreach (range(1, $billCount) as $index) {
            tuBillingBill($this->student, $this->paymentType, 1_000_000, (($index - 1) % 12) + 1, 2026 + intdiv($index - 1, 12));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(StudentDetail::class, ['student' => $this->student])->html();
        $count = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->count();
        DB::disableQueryLog();

        return $count;
    };

    $measurements = [1 => $measure(1), 12 => $measure(12), 36 => $measure(36)];

    expect(array_unique($measurements))->toHaveCount(1)
        ->and($measurements[1])->toBe(22);
});

it('preserves administrator billing UI and finance permissions', function (string $role) {
    tuBillingBill($this->student, $this->paymentType, 1_000_000, 7, 2026);
    tuBillingBill($this->student, PaymentType::factory()->create(['name' => 'Uang Buku']), 2_500_000, academicYear: '2026/2027', frequency: BillFrequency::Yearly->value);
    tuBillingBill($this->student, PaymentType::factory()->create(['name' => 'Uang Pangkal']), 5_000_000, academicYear: '2026/2027', frequency: BillFrequency::OneTime->value);
    $user = User::factory()->create(['role' => $role]);

    $response = $this->actingAs($user)->get(route('siswa.show', $this->student));

    $response
        ->assertOk()
        ->assertSee('Cetak Tagihan')
        ->assertSee('Lengkapi Tagihan')
        ->assertSee('wire:click="editBill', false)
        ->assertSee('wire:click="confirmDeleteBill', false)
        ->assertSee('Tanggal TF Terakhir')
        ->assertSee('Tanggal Input Terakhir')
        ->assertSee('>Riwayat</th>', false);

    expect(substr_count($response->getContent(), 'min-w-[1450px]'))->toBe(3)
        ->and(substr_count($response->getContent(), 'min-w-[850px]'))->toBe(0);
})->with([
    'admin' => User::ROLE_ADMIN,
    'superadmin' => User::ROLE_SUPER_ADMIN,
]);
