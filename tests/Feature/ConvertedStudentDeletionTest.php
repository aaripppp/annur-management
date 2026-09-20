<?php

use App\Enums\BillFrequency;
use App\Enums\PaymentTypeAudience;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentIndex;
use App\Livewire\ProspectivePaymentCreate;
use App\Livewire\ProspectiveStudentManagement;
use App\Livewire\StudentManagement;
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
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\StudentPaymentSetting;
use App\Models\User;
use App\Services\DashboardOperationalMetricsService;
use App\Services\ProspectiveStudentConversionService;
use App\Services\ProspectiveStudentReceiptNumberGenerator;
use App\Services\ProspectiveStudentRegistrationNumberGenerator;
use App\Services\ReportYearOptionsService;
use App\Services\SchoolBankRecapService;
use App\Services\SchoolDailyReportService;
use App\Services\SchoolMonthlyReportService;
use App\Services\TransactionHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;

/**
 * Katalog minimal: tahun ajaran aktif 2026/2027, tahun ajaran tujuan (future)
 * 2027/2028, kelas tujuan level 8 (SMP), dan jenis pembayaran Formulir untuk
 * calon siswa.
 *
 * @return array{
 *     activeYear: AcademicYear,
 *     futureYear: AcademicYear,
 *     schoolClass: SchoolClass,
 *     schoolLevel: SchoolLevel,
 *     formulir: PaymentType,
 * }
 */
function deepDeleteCatalog(int $classLevel = 8, int $amount = 350000): array
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
        'name' => 'Formulir Pendaftaran Hapus',
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
function deepDeleteProspect(array $catalog, array $overrides = []): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create(array_merge([
        'registration_number' => 'REG-HAPUS-0001',
        'academic_year_id' => $catalog['futureYear']->id,
        'school_class_id' => $catalog['schoolClass']->id,
        'nama_lengkap' => 'Bagas Calon Hapus',
        'nama_panggilan' => 'Bagas',
        'jenis_kelamin' => 'L',
        'status' => ProspectiveStudentStatus::Registered,
        'converted_student_id' => null,
        'converted_at' => null,
    ], $overrides));
}

function deepDeleteBill(ProspectiveStudent $prospect, PaymentType $type, float $amount): ProspectiveStudentBill
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

function deepDeleteConvert(ProspectiveStudent $prospect, ?string $nis = null): Student
{
    return app(ProspectiveStudentConversionService::class)->convert($prospect, $nis);
}

/**
 * @param  array<int, float>  $allocations
 */
function deepDeletePay(ProspectiveStudent $prospect, array $allocations, Bank $bank, string $paymentDate = '2026-09-15'): ProspectiveStudentPayment
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
        ->sole();
}

/**
 * Pembayaran siswa dengan bank terkontrol agar bisa diuji pada rekap bank.
 */
function deepDeleteStudentPayment(
    Student $student,
    Bank $bank,
    User $user,
    PaymentType $type,
    int $amount,
    string $date = '2026-09-15',
    ?string $recordedAt = null,
): Payment {
    return createSchoolMonthlyReportPayment($student, $bank, $user, $date, [
        ['payment_type_id' => $type->id, 'amount' => $amount],
    ], recordedAt: $recordedAt);
}

/*
|--------------------------------------------------------------------------
| 1. Penghapusan siswa biasa tetap berjalan (items 1-3)
|--------------------------------------------------------------------------
*/

it('menghapus siswa biasa beserta data operasionalnya tanpa menyentuh calon siswa', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $otherProspect = deepDeleteProspect($catalog, [
        'registration_number' => 'REG-HAPUS-0002',
        'nama_lengkap' => 'Calon Lain',
    ]);

    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Biasa Hapus']);
    $other = Student::factory()->create(['nama_lengkap' => 'Siswa Lain']);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 500000, 9, 2026);
    payActiveBill($bill, 500000);
    makeActiveSetting($student, $type);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->assertSet('deletingIsConverted', false)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingId', null);

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(Student::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and(StudentBill::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and(Payment::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and(StudentPaymentSetting::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and(ProspectiveStudent::query()->whereKey($prospect->id)->exists())->toBeTrue()
        ->and(ProspectiveStudent::query()->whereKey($otherProspect->id)->exists())->toBeTrue();
});

it('menampilkan pesan sukses dan tidak menampilkan peringatan calon siswa untuk siswa biasa', function () {
    $student = Student::factory()->create(['nama_lengkap' => 'Siswa Biasa Pesan']);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->assertSet('deletingIsConverted', false)
        ->assertDontSee('Menghapus siswa ini juga akan menghapus data calon siswa asal')
        ->call('delete')
        ->assertSee('Data siswa beserta semua pembayaran terkait berhasil dihapus.');
});

it('menghapus siswa biasa tanpa melempar error foreign key (tidak ada calon siswa tertaut)', function () {
    $student = Student::factory()->create();
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 250000, 8, 2026);
    payActiveBill($bill, 250000);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(Payment::query()->where('student_id', $student->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 2. Deep delete siswa hasil konversi (items 4-13)
|--------------------------------------------------------------------------
*/

it('menghapus baris siswa dan baris calon siswa asal secara permanen', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $student = deepDeleteConvert($prospect);

    expect($prospect->fresh()->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and($prospect->fresh()->converted_student_id)->toBe($student->id);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->assertSet('deletingIsConverted', true)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingId', null);

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(ProspectiveStudent::query()->whereKey($prospect->id)->exists())->toBeFalse();
});

it('menampilkan peringatan penghapusan mendalam untuk siswa hasil konversi', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $student = deepDeleteConvert($prospect);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->assertSet('deletingIsConverted', true)
        ->assertSee('Menghapus siswa ini juga akan menghapus data calon siswa asal, tagihan pendaftaran, pembayaran formulir, dan seluruh riwayat terkait. Data tidak dapat dikembalikan.');
});

it('menghapus tagihan, pembayaran, dan detail pembayaran calon siswa asal', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    $payment = deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);

    expect(ProspectiveStudentPaymentDetail::query()->where('prospective_student_payment_id', $payment->id)->count())->toBe(1);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    expect(ProspectiveStudentBill::query()->whereKey($bill->id)->exists())->toBeFalse()
        ->and(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(ProspectiveStudentPaymentDetail::query()->where('prospective_student_payment_id', $payment->id)->count())->toBe(0);
});

it('menghapus seluruh data operasional siswa hasil konversi', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $student = deepDeleteConvert($prospect);

    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 500000, 9, 2026);
    makeActiveSetting($student, $type);
    payActiveBill($bill, 500000);

    $payment = Payment::query()->where('student_id', $student->id)->sole();
    $enrollmentCount = StudentAcademicEnrollment::query()->where('student_id', $student->id)->count();

    expect($enrollmentCount)->toBeGreaterThan(0);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(StudentBill::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and(Payment::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and($payment->details()->count())->toBe(0)
        ->and(StudentPaymentSetting::query()->where('student_id', $student->id)->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('menghapus berkas kwitansi calon siswa setelah transaksi commit', function () {
    Storage::fake('public');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    $payment = deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);

    $receiptPath = 'receipts/kwitansi-calon-hapus.pdf';
    Storage::disk('public')->put($receiptPath, 'kwitansi');
    $payment->forceFill(['receipt' => $receiptPath])->saveQuietly();

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    Storage::disk('public')->assertMissing($receiptPath);
});

it('tidak menyentuh calon siswa lain saat deep delete', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $otherProspect = deepDeleteProspect($catalog, [
        'registration_number' => 'REG-HAPUS-0009',
        'nama_lengkap' => 'Calon Aman',
    ]);
    $otherBill = deepDeleteBill($otherProspect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    expect(ProspectiveStudent::query()->whereKey($otherProspect->id)->exists())->toBeTrue()
        ->and(ProspectiveStudentBill::query()->whereKey($otherBill->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 3. Laporan keuangan (items 14-19)
|--------------------------------------------------------------------------
*/

it('menghapus pemasukan siswa dan calon siswa dari laporan harian', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);
    deepDeleteStudentPayment($student, Bank::factory()->create(['is_active' => true]), $user, makeBillType('SPP'), 1000000);

    $before = app(SchoolDailyReportService::class)->generate('2026-09-15');

    expect($before['grand_total'])->toBe(1350000.0)
        ->and($before['transaction_count'])->toBe(2);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    $after = app(SchoolDailyReportService::class)->generate('2026-09-15');

    expect($after['grand_total'])->toEqual(0.0)
        ->and($after['detail_rows'])->toHaveCount(0)
        ->and($after['transaction_count'])->toBe(0);
});

it('menghapus pemasukan siswa dan calon siswa dari laporan bulanan', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);
    deepDeleteStudentPayment($student, Bank::factory()->create(['is_active' => true]), $user, makeBillType('SPP'), 1000000);

    expect(app(SchoolMonthlyReportService::class)->generate(2026, 9)['grand_total'])->toBe(1350000.0);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    $after = app(SchoolMonthlyReportService::class)->generate(2026, 9);

    expect($after['grand_total'])->toEqual(0.0)
        ->and($after['detail_rows'])->toHaveCount(0);
});

it('menurunkan rekap bank untuk kedua sumber saat deep delete', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bsi = Bank::factory()->create(['name' => 'BSI', 'account_number' => '111111', 'is_active' => true]);
    $mandiri = Bank::factory()->create(['name' => 'Mandiri', 'account_number' => '222222', 'is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bsi);
    deepDeleteStudentPayment($student, $mandiri, $user, makeBillType('SPP'), 1000000);

    $before = app(SchoolBankRecapService::class)->generate('2026-09-15');

    expect($before['grand_total'])->toBe(1350000.0);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    $after = app(SchoolBankRecapService::class)->generate('2026-09-15');

    expect($after['grand_total'])->toEqual(0.0)
        ->and($after['detail_rows'])->toHaveCount(0);
});

it('menghapus baris riwayat transaksi siswa dan calon siswa', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    $prospectivePayment = deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);
    $studentPayment = deepDeleteStudentPayment($student, Bank::factory()->create(['is_active' => true]), $user, makeBillType('SPP'), 1000000);

    $historyBefore = app(TransactionHistoryService::class)->getHistory(search: 'Bagas Calon Hapus');

    expect($historyBefore->total())->toBe(2);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    $historyAfter = app(TransactionHistoryService::class)->getHistory(search: 'Bagas Calon Hapus');

    expect($historyAfter->total())->toBe(0)
        ->and(ProspectiveStudentPayment::query()->whereKey($prospectivePayment->id)->exists())->toBeFalse()
        ->and(Payment::query()->whereKey($studentPayment->id)->exists())->toBeFalse();
});

it('menurunkan total pemasukan dan jumlah transaksi dashboard', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);
    deepDeleteStudentPayment($student, Bank::factory()->create(['is_active' => true]), $user, makeBillType('SPP'), 1000000);

    $before = app(DashboardOperationalMetricsService::class)->generate('all', '2026-09-15', '2026-09-15');

    expect($before['total_income'])->toBe(1350000.0)
        ->and($before['transaction_count'])->toBe(2);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    $after = app(DashboardOperationalMetricsService::class)->generate('all', '2026-09-15', '2026-09-15');

    expect($after['total_income'])->toBe(0.0)
        ->and($after['transaction_count'])->toBe(0);
});

it('menghapus tahun transaksi dari pilihan tahun laporan bila sudah tidak ada transaksi', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $student = deepDeleteConvert($prospect);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    deepDeleteStudentPayment($student, Bank::factory()->create(['is_active' => true]), $user, makeBillType('SPP'), 1000000, recordedAt: '2030-05-02 09:00:00');

    expect(app(ReportYearOptionsService::class)->options())->toContain(2030);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    expect(app(ReportYearOptionsService::class)->options())->not->toContain(2030);
});

/*
|--------------------------------------------------------------------------
| 4. Atomik / rollback (items 20-24)
|--------------------------------------------------------------------------
*/

it('membatalkan seluruh deep delete saat penghapusan siswa gagal', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    $payment = deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);

    $type = makeBillType('SPP');
    $studentBill = makeMonthlyBill($student, $type, 500000, 9, 2026);
    makeActiveSetting($student, $type);
    $studentPayment = deepDeleteStudentPayment($student, Bank::factory()->create(['is_active' => true]), $user, $type, 500000);

    $dispatcher = Student::getEventDispatcher();
    $listener = function (Student $deleting) use ($student): void {
        if ($deleting->getKey() === $student->getKey()) {
            throw new RuntimeException('forced student delete failure');
        }
    };

    Student::deleting($listener);

    try {
        expect(fn () => Livewire::test(StudentManagement::class)
            ->call('confirmDelete', $student->id)
            ->call('delete'))
            ->toThrow(RuntimeException::class);
    } finally {
        $dispatcher->forget('eloquent.deleting: '.Student::class);
    }

    expect(Student::query()->whereKey($student->id)->exists())->toBeTrue()
        ->and(ProspectiveStudent::query()->whereKey($prospect->id)->exists())->toBeTrue()
        ->and($prospect->fresh()->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and($prospect->fresh()->converted_student_id)->toBe($student->id)
        ->and(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and(ProspectiveStudentBill::query()->whereKey($bill->id)->exists())->toBeTrue()
        ->and(StudentBill::query()->whereKey($studentBill->id)->exists())->toBeTrue()
        ->and(Payment::query()->whereKey($studentPayment->id)->exists())->toBeTrue()
        ->and(StudentPaymentSetting::query()->where('student_id', $student->id)->count())->toBe(1)
        ->and(ProspectiveStudentPaymentDetail::query()->where('prospective_student_payment_id', $payment->id)->count())->toBe(1);
});

it('tidak mengubah total laporan saat deep delete gagal', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);
    deepDeleteStudentPayment($student, Bank::factory()->create(['is_active' => true]), $user, makeBillType('SPP'), 1000000);

    $before = app(SchoolDailyReportService::class)->generate('2026-09-15')['grand_total'];

    $dispatcher = Student::getEventDispatcher();
    Student::deleting(function (Student $deleting) use ($student): void {
        if ($deleting->getKey() === $student->getKey()) {
            throw new RuntimeException('forced student delete failure');
        }
    });

    try {
        expect(fn () => Livewire::test(StudentManagement::class)
            ->call('confirmDelete', $student->id)
            ->call('delete'))
            ->toThrow(RuntimeException::class);
    } finally {
        $dispatcher->forget('eloquent.deleting: '.Student::class);
    }

    expect(app(SchoolDailyReportService::class)->generate('2026-09-15')['grand_total'])->toBe($before)
        ->and($before)->toBe(1350000.0);
});

it('tetap dapat membuka kwitansi calon siswa saat deep delete gagal', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    $payment = deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);

    $dispatcher = Student::getEventDispatcher();
    Student::deleting(function (Student $deleting) use ($student): void {
        if ($deleting->getKey() === $student->getKey()) {
            throw new RuntimeException('forced student delete failure');
        }
    });

    try {
        expect(fn () => Livewire::test(StudentManagement::class)
            ->call('confirmDelete', $student->id)
            ->call('delete'))
            ->toThrow(RuntimeException::class);
    } finally {
        $dispatcher->forget('eloquent.deleting: '.Student::class);
    }

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.show', $payment->id))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| 5. Sequence nomor pendaftaran & kwitansi (items 25-27)
|--------------------------------------------------------------------------
*/

it('tidak menurunkan sequence nomor pendaftaran dan tidak memakai ulang nomor', function () {
    $catalog = deepDeleteCatalog();
    $registrationNumber = app(ProspectiveStudentRegistrationNumberGenerator::class)->next(2027);
    $prospect = deepDeleteProspect($catalog, ['registration_number' => $registrationNumber]);
    $student = deepDeleteConvert($prospect);

    expect($registrationNumber)->toBe('REG-2027-000001');

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    $sequence = DB::table('prospective_student_sequences')->where('year', 2027)->first();

    expect($sequence)->not->toBeNull()
        ->and((int) $sequence->last_number)->toBe(1)
        ->and(app(ProspectiveStudentRegistrationNumberGenerator::class)->next(2027))->toBe('REG-2027-000002');
});

it('tidak menurunkan sequence kwitansi calon siswa dan tidak memakai ulang nomor', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    Livewire::actingAs(User::factory()->create());
    $payment = deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);

    expect($payment->receipt_number)->toBe('KWT-REG-2026-000001');

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    $sequence = DB::table('prospective_student_payment_sequences')->where('year', 2026)->first();

    expect($sequence)->not->toBeNull()
        ->and((int) $sequence->last_number)->toBe(1)
        ->and(app(ProspectiveStudentReceiptNumberGenerator::class)->next(2026))->toBe('KWT-REG-2026-000002');
});

/*
|--------------------------------------------------------------------------
| 6. Route & UX setelah deep delete (items 28-34)
|--------------------------------------------------------------------------
*/

it('mengembalikan 404 untuk halaman siswa dan kwitansi calon siswa yang sudah dihapus', function () {
    $this->travelTo('2026-09-15');

    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $bill = deepDeleteBill($prospect, $catalog['formulir'], 350000);
    $student = deepDeleteConvert($prospect);

    $bank = Bank::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    Livewire::actingAs($user);
    $payment = deepDeletePay($prospect->fresh(), [$bill->id => 350000], $bank);

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    $this->actingAs($user)->get(route('siswa.show', ['student' => $student->id]))->assertNotFound();
    $this->actingAs($user)->get(route('pembayaran.prospective.show', $payment->id))->assertNotFound();
});

it('menghilangkan siswa dan calon siswa dari hasil pencarian pembayaran', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $student = deepDeleteConvert($prospect);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Bagas Calon')
        ->assertSeeHtml('wire:key="student-result-'.$student->id.'"')
        ->assertDontSeeHtml('wire:key="prospective-result-'.$prospect->id.'"');

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Bagas Calon')
        ->assertDontSeeHtml('wire:key="student-result-'.$student->id.'"')
        ->assertDontSeeHtml('wire:key="prospective-result-'.$prospect->id.'"');
});

it('menghilangkan siswa dari daftar siswa dan calon siswa dari daftar calon siswa', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    $student = deepDeleteConvert($prospect);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Bagas Calon Hapus')
        ->assertSeeHtml('wire:key="student-'.$student->id.'"');

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('search', 'Bagas Calon Hapus')
        ->assertSeeHtml('wire:key="prospective-student-'.$prospect->id.'"');

    Livewire::test(StudentManagement::class)
        ->call('confirmDelete', $student->id)
        ->call('delete');

    Livewire::test(StudentManagement::class)
        ->set('search', 'Bagas Calon Hapus')
        ->assertDontSeeHtml('wire:key="student-'.$student->id.'"');

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('search', 'Bagas Calon Hapus')
        ->assertDontSeeHtml('wire:key="prospective-student-'.$prospect->id.'"');
});

it('tetap menghalangi penghapusan calon siswa hasil konversi dari halaman calon siswa', function () {
    $catalog = deepDeleteCatalog();
    $prospect = deepDeleteProspect($catalog);
    deepDeleteConvert($prospect);

    Livewire::actingAs(User::factory()->create());

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospect->id)
        ->assertSet('isDeleteModalOpen', false)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false);

    expect(ProspectiveStudent::query()->whereKey($prospect->id)->exists())->toBeTrue();
});
