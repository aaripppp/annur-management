<?php

use App\Enums\BillFrequency;
use App\Enums\StudentStatus;
use App\Livewire\Dashboard;
use App\Livewire\PaymentIndex;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BillAdjustment;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\TransactionHistoryService;
use App\Support\TransactionHistoryRow;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function workspaceAcademicYear(
    string $year = '2026/2027',
    string $startDate = '2026-07-01',
    string $endDate = '2027-06-30',
    bool $active = true
): AcademicYear {
    if ($active) {
        AcademicYear::query()->update(['is_active' => false]);
    }

    return AcademicYear::query()->updateOrCreate(
        ['year' => $year],
        [
            'is_active' => $active,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]
    );
}

function enrollWorkspaceStudent(Student $student, AcademicYear $academicYear, string $status = 'active'): void
{
    StudentAcademicEnrollment::query()->updateOrCreate(
        [
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
        ],
        [
            'school_class_id' => $student->class_id,
            'status' => $status,
        ]
    );
}

function payWorkspaceBill(StudentBill $bill, int $amount): Payment
{
    $payment = Payment::create([
        'receipt_number' => 'WORKSPACE-'.$bill->id.'-'.uniqid(),
        'student_id' => $bill->student_id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-10',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'created_by' => User::factory()->create()->id,
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

    return $payment;
}

function assertWorkspaceSummary($component, int $tagihan, int $dibayar, int $tunggakan): void
{
    $component
        ->assertSeeHtml('tracking-wider">Rp '.number_format($tagihan, 0, ',', '.').'</p>')
        ->assertSeeHtml('tracking-wider">Rp '.number_format($dibayar, 0, ',', '.').'</p>')
        ->assertSeeHtml('text-error mt-1 font-numeric-data tracking-wider">Rp '.number_format($tunggakan, 0, ',', '.').'</p>');
}

function makeHistoryPayment(
    Student $student,
    Bank $bank,
    User $user,
    string $receiptNumber,
    string $paymentDate,
    string $status = Payment::STATUS_ACTIVE,
    ?string $createdAt = null
): Payment {
    $payment = Payment::create([
        'receipt_number' => $receiptNumber,
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => 150000,
        'payment_method' => 'transfer',
        'status' => $status,
        'created_by' => $user->id,
    ]);

    if ($createdAt !== null) {
        $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
    }

    return $payment->refresh();
}

function makeProspectiveHistoryPayment(
    ProspectiveStudent $prospect,
    Bank $bank,
    User $user,
    string $receiptNumber,
    string $paymentDate = '2026-08-10',
    string $status = ProspectiveStudentPayment::STATUS_ACTIVE
): ProspectiveStudentPayment {
    return ProspectiveStudentPayment::query()->create([
        'receipt_number' => $receiptNumber,
        'prospective_student_id' => $prospect->id,
        'bank_id' => $bank->id,
        'payment_date' => $paymentDate,
        'total_amount' => 250000,
        'status' => $status,
        'created_by' => $user->id,
    ]);
}

function makeProspectiveBill(
    ProspectiveStudent $prospect,
    PaymentType $type,
    int $amount,
    string $academicYear = '2026/2027'
): ProspectiveStudentBill {
    return ProspectiveStudentBill::query()->create([
        'prospective_student_id' => $prospect->id,
        'payment_type_id' => $type->id,
        'amount' => $amount,
        'billing_frequency' => BillFrequency::OneTime,
        'academic_year' => $academicYear,
    ]);
}

function payProspectiveBill(
    ProspectiveStudentPayment $payment,
    ProspectiveStudentBill $bill,
    int $amount
): ProspectiveStudentPaymentDetail {
    return ProspectiveStudentPaymentDetail::query()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'amount' => $amount,
    ]);
}

it('menampilkan pencarian siswa pada halaman pembayaran', function () {
    Livewire::test(PaymentIndex::class)
        ->assertSee('Pembayaran')
        ->assertSee('Cari Siswa / Calon Siswa')
        ->assertSee('Cari nama lengkap, nama panggilan, NIS, atau nomor pendaftaran...')
        ->assertSee('Cari siswa atau calon siswa untuk melihat data dan input pembayaran.');
});

it('membuka Pembayaran Siswa sebagai tab default dan mempertahankan siswa saat berpindah tab', function () {
    $student = makeBillStudent();

    Livewire::test(PaymentIndex::class)
        ->assertSet('activeTab', 'student')
        ->assertSee('Pembayaran Siswa')
        ->assertSee('Riwayat Transaksi')
        ->call('selectStudent', $student->id)
        ->call('setActiveTab', 'history')
        ->assertSet('selectedStudentId', $student->id)
        ->assertSee('Daftar seluruh transaksi pembayaran siswa.')
        ->call('setActiveTab', 'student')
        ->assertSet('selectedStudentId', $student->id)
        ->assertSee($student->nama_lengkap);
});

it('membuka tab Riwayat Transaksi dari query string', function () {
    Livewire::withQueryParams(['tab' => 'history'])
        ->test(PaymentIndex::class)
        ->assertSet('activeTab', 'history')
        ->assertSee('Daftar seluruh transaksi pembayaran siswa.');
});

it('memfilter riwayat berdasarkan siswa kwitansi bank dan rentang tanggal', function () {
    $user = User::factory()->create();
    $firstBank = Bank::factory()->create(['name' => 'Bank Filter Pertama']);
    $secondBank = Bank::factory()->create(['name' => 'Bank Filter Kedua']);
    $firstStudent = Student::factory()->create(['nama_lengkap' => 'Siswa Riwayat Pertama', 'nis' => 'HISTORY-001']);
    $secondStudent = Student::factory()->create(['nama_lengkap' => 'Siswa Riwayat Kedua', 'nis' => 'HISTORY-002']);
    $firstPayment = makeHistoryPayment($firstStudent, $firstBank, $user, 'KWT-HISTORY-001', '2026-08-10', createdAt: '2026-08-10 09:00:00');
    $secondPayment = makeHistoryPayment($secondStudent, $secondBank, $user, 'KWT-HISTORY-002', '2026-09-10', createdAt: '2026-09-10 09:00:00');

    $component = Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('startDate', '2026-08-01')
        ->set('endDate', '2026-09-30');

    $component->set('search', 'Siswa Riwayat Pertama')
        ->assertSee($firstPayment->receipt_number)
        ->assertDontSee($secondPayment->receipt_number)
        ->set('search', 'KWT-HISTORY-002')
        ->assertSee($secondPayment->receipt_number)
        ->assertDontSee($firstPayment->receipt_number)
        ->set('search', '')
        ->set('bankId', (string) $firstBank->id)
        ->assertSee($firstPayment->receipt_number)
        ->assertDontSee($secondPayment->receipt_number)
        ->set('bankId', '')
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->assertSee($secondPayment->receipt_number)
        ->assertDontSee($firstPayment->receipt_number);
});

it('menampilkan detail dan edit hanya untuk transaksi aktif', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $activePayment = makeHistoryPayment($student, $bank, $user, 'KWT-ACTION-ACTIVE', '2026-08-10');
    $cancelledPayment = makeHistoryPayment($student, $bank, $user, 'KWT-ACTION-CANCELLED', '2026-08-11', Payment::STATUS_CANCELLED);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee(route('pembayaran.show', $activePayment->id), false)
        ->assertSee(route('pembayaran.show', $cancelledPayment->id), false)
        ->assertSee(route('pembayaran.edit', $activePayment->id), false)
        ->assertDontSee(route('pembayaran.edit', $cancelledPayment->id), false)
        ->assertSeeHtml('wire:click="confirmDelete(\''.$activePayment->id.'\', \'student\')"', false)
        ->assertSeeHtml('wire:click="confirmDelete(\''.$cancelledPayment->id.'\', \'student\')"', false)
        ->assertSeeHtml('title="Hapus Transaksi"')
        ->assertDontSeeHtml('title="Batalkan Transaksi"');
});

it('confirmDelete membuka modal transaksi yang benar dan cancelDelete tidak mengubah data', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $payment = makeHistoryPayment($student, $bank, $user, 'KWT-CANCEL-MODAL', '2026-08-10');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->assertSet('isDeleteModalOpen', true)
        ->assertSet('deletingId', $payment->id)
        ->assertSee('KWT-CANCEL-MODAL')
        ->assertSee($student->nama_lengkap)
        ->call('cancelDelete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingId', null);

    expect($payment->fresh()->status)->toBe(Payment::STATUS_ACTIVE);
});

it('delete pada riwayat menghapus transaksi permanen dan mengembalikan saldo tanpa mengubah transaksi lain', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent();
    $paymentType = makeBillType('SPP Delete dari History');
    $bill = makeMonthlyBill($student, $paymentType, 970000, 8, 2026);
    $payment = payWorkspaceBill($bill, 970000);

    $unrelatedBill = makeMonthlyBill($student, $paymentType, 500000, 9, 2026);
    $unrelatedPayment = payWorkspaceBill($unrelatedBill, 500000);

    expect($bill->fresh()->paid_amount)->toBe(970000.0)
        ->and($unrelatedBill->fresh()->paid_amount)->toBe(500000.0);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertDontSee($payment->receipt_number);

    expect($payment->fresh())->toBeNull()
        ->and(PaymentDetail::query()->where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and($bill->fresh()->paid_amount)->toBe(0.0)
        ->and($bill->fresh()->remaining_amount)->toBe(970000.0)
        ->and($unrelatedPayment->fresh()->status)->toBe(Payment::STATUS_ACTIVE)
        ->and($unrelatedBill->fresh()->paid_amount)->toBe(500000.0);
});

it('permanent delete mengembalikan saldo partial payment', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent();
    $paymentType = makeBillType('SPP Partial Delete');
    $bill = makeMonthlyBill($student, $paymentType, 970000, 8, 2026);
    $payment = payWorkspaceBill($bill, 400000);

    expect($bill->fresh()->paid_amount)->toBe(400000.0)
        ->and($bill->fresh()->remaining_amount)->toBe(570000.0);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->call('delete');

    expect($payment->fresh())->toBeNull()
        ->and($bill->fresh()->paid_amount)->toBe(0.0)
        ->and($bill->fresh()->remaining_amount)->toBe(970000.0)
        ->and($bill->fresh()->status)->toBe(StudentBill::STATUS_UNPAID);
});

it('permanent delete mengembalikan saldo setiap bill dalam transaksi multi-bill', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent();
    $bank = Bank::factory()->create();
    $paymentType = makeBillType('Multi Bill Delete');
    $firstBill = makeMonthlyBill($student, $paymentType, 600000, 8, 2026);
    $secondBill = makeMonthlyBill($student, $paymentType, 400000, 9, 2026);
    $payment = makeHistoryPayment($student, $bank, $user, 'KWT-MULTI-DELETE', '2026-09-10');

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $firstBill->id,
        'payment_type_id' => $paymentType->id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 600000,
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $secondBill->id,
        'payment_type_id' => $paymentType->id,
        'period_month' => 9,
        'period_year' => 2026,
        'amount' => 250000,
    ]);

    expect($firstBill->fresh()->paid_amount)->toBe(600000.0)
        ->and($secondBill->fresh()->paid_amount)->toBe(250000.0);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->call('delete');

    expect($payment->fresh())->toBeNull()
        ->and($firstBill->fresh()->paid_amount)->toBe(0.0)
        ->and($firstBill->fresh()->remaining_amount)->toBe(600000.0)
        ->and($secondBill->fresh()->paid_amount)->toBe(0.0)
        ->and($secondBill->fresh()->remaining_amount)->toBe(400000.0)
        ->and(StudentBill::query()->whereKey([$firstBill->id, $secondBill->id])->count())->toBe(2);
});

it('permanent delete menghapus file receipt dan route kwitansi menjadi not found', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = makeHistoryPayment(makeBillStudent(), Bank::factory()->create(), $user, 'KWT-RECEIPT-DELETE', '2026-08-10');
    Storage::disk('public')->put('receipts/delete-proof.pdf', 'receipt');
    $payment->update(['receipt' => 'receipts/delete-proof.pdf']);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->call('delete');

    Storage::disk('public')->assertMissing('receipts/delete-proof.pdf');
    $this->actingAs($user)
        ->get(route('pembayaran.show', $payment->id))
        ->assertNotFound();
});

it('transaksi cancelled tetap dapat dihapus permanen dari riwayat', function () {
    $user = User::factory()->create();
    $payment = makeHistoryPayment(makeBillStudent(), Bank::factory()->create(), $user, 'KWT-CANCELLED-DELETE', '2026-08-10', Payment::STATUS_CANCELLED);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('delete');

    expect($payment->fresh())->toBeNull();
});

it('Pembayaran Siswa tidak menampilkan action delete transaksi global', function () {
    $user = User::factory()->create();
    $payment = makeHistoryPayment(makeBillStudent(), Bank::factory()->create(), $user, 'KWT-STUDENT-TAB', '2026-08-10');

    Livewire::test(PaymentIndex::class)
        ->assertSet('activeTab', 'student')
        ->assertDontSee($payment->receipt_number)
        ->assertDontSee('Hapus Transaksi');
});

it('Dashboard mengarahkan Lihat Semua ke Riwayat Transaksi', function () {
    Livewire::test(Dashboard::class)
        ->assertSeeHtml('grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4')
        ->assertSee(route('pembayaran.index', ['tab' => 'history']), false);
});

it('mencari siswa berdasarkan nama lengkap', function () {
    Student::factory()->create([
        'nama_lengkap' => 'Muhammad Al Fatih',
        'nama_panggilan' => 'Fatih',
        'nis' => '20261001',
    ]);

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Muhammad Al')
        ->assertSee('Muhammad Al Fatih');
});

it('mencari siswa berdasarkan nama panggilan', function () {
    Student::factory()->create([
        'nama_lengkap' => 'Abdurrahman Hakim',
        'nama_panggilan' => 'Rahman',
        'nis' => '20261002',
    ]);

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Rahman')
        ->assertSee('Abdurrahman Hakim');
});

it('mencari siswa berdasarkan NIS', function () {
    Student::factory()->create([
        'nama_lengkap' => 'Bilal Ramadhan',
        'nama_panggilan' => 'Bilal',
        'nis' => '20261003',
    ]);

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', '20261003')
        ->assertSee('Bilal Ramadhan');
});

it('hasil pencarian membedakan record same NIS dan memilih Student ID yang benar', function () {
    $oldYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    $activeYear = workspaceAcademicYear('2028/2029', '2028-07-01', '2029-06-30');
    $sdClass = SchoolClass::factory()->create(['level' => 6]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $oldStudent = Student::factory()->create([
        'nis' => '28-0001',
        'nama_lengkap' => 'Ahmad Same NIS',
        'class_id' => $sdClass->id,
        'status' => 'lulus',
    ]);
    $newStudent = Student::factory()->create([
        'nis' => '28-0001',
        'nama_lengkap' => 'Ahmad Same NIS',
        'class_id' => $smpClass->id,
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $oldStudent->id,
        'academic_year_id' => $oldYear->id,
        'school_class_id' => $sdClass->id,
        'status' => 'lulus',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $newStudent->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $smpClass->id,
        'status' => 'active',
    ]);

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', '28-0001')
        ->assertSee('student-result-'.$oldStudent->id, false)
        ->assertSee('student-result-'.$newStudent->id, false)
        ->assertSee($sdClass->name)
        ->assertSee($smpClass->name)
        ->assertSee('SD')
        ->assertSee('SMP')
        ->assertSee($oldYear->year)
        ->assertSee($activeYear->year)
        ->assertSee('Lulus')
        ->assertSee('Aktif')
        ->call('selectStudent', $newStudent->id)
        ->assertSet('selectedStudentId', $newStudent->id)
        ->assertSee($smpClass->name);
});

it('memilih siswa menampilkan profil dan tombol aksi', function () {
    $student = Student::factory()->create([
        'nama_lengkap' => 'Muhammad Al Fatih',
        'nama_panggilan' => 'Fatih',
        'nis' => '20261004',
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Pendidikan No. 1',
        'tempat_lahir' => 'Bekasi',
        'tanggal_lahir' => '2004-03-15',
        'nama_ayah' => 'Ahmad Fauzan',
        'no_telp_ayah' => '08123456789',
        'nama_ibu' => 'Siti Aminah',
        'no_telp_ibu' => '08129876543',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSet('selectedStudentId', $student->id)
        ->assertSet('studentSearch', '')
        ->assertSee('Profil Siswa')
        ->assertSee('Muhammad Al Fatih')
        ->assertSee('Fatih')
        ->assertSee('20261004')
        ->assertSee('Laki-laki')
        ->assertSee('Bekasi, 15 Maret 2004')
        ->assertSee('Jl. Pendidikan No. 1')
        ->assertSee('Ahmad Fauzan')
        ->assertSee('08123456789')
        ->assertSee('Siti Aminah')
        ->assertSee('08129876543')
        ->assertSeeHtml('>school</span>')
        ->assertSeeHtml('>calendar_month</span>')
        ->assertSeeHtml('>cake</span>')
        ->assertSeeHtml('>location_on</span>')
        ->assertSeeHtml('>person_2</span>')
        ->assertSee('Ganti Siswa')
        ->assertSee('Edit Profil')
        ->assertSee('Input Pembayaran');
});

it('profil siswa menangani biodata nullable dengan fallback', function () {
    $student = Student::factory()->create([
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'nama_ayah' => null,
        'no_telp_ayah' => null,
        'nama_ibu' => null,
        'no_telp_ibu' => null,
    ]);

    $component = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSeeHtml('data-profile-field="birth"')
        ->assertSeeHtml('data-profile-field="father"')
        ->assertSeeHtml('data-profile-field="mother"')
        ->assertSee('Ganti Siswa')
        ->assertSee('Edit Profil')
        ->assertSee('Input Pembayaran');

    expect(substr_count($component->html(), 'font-semibold text-on-surface mt-0.5">-</p>'))
        ->toBeGreaterThanOrEqual(3);
});

it('profil menampilkan kelas status dan konteks tahun ajaran saat ini', function () {
    $academicYear = AcademicYear::query()->updateOrCreate(
        ['year' => '2026/2027'],
        [
            'is_active' => true,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]
    );
    $student = Student::factory()->create();
    $student = $student->fresh();
    $currentClassName = $student->schoolClass->name;

    StudentAcademicEnrollment::query()->updateOrCreate(
        [
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
        ],
        [
            'school_class_id' => $student->class_id,
            'status' => 'active',
        ]
    );

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee($currentClassName)
        ->assertSee('Aktif')
        ->assertSee($academicYear->year);
});

it('tidak menampilkan siswa lain untuk pencarian tertentu', function () {
    Student::factory()->create([
        'nama_lengkap' => 'Ahmad Fauzi',
        'nama_panggilan' => 'Ahmad',
        'nis' => '20261005',
    ]);
    Student::factory()->create([
        'nama_lengkap' => 'Budi Santoso',
        'nama_panggilan' => 'Budi',
        'nis' => '20261006',
    ]);

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Ahmad')
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Budi Santoso');
});

it('dapat mengganti siswa yang dipilih', function () {
    $firstStudent = Student::factory()->create(['nama_lengkap' => 'Siswa Pertama']);
    $secondStudent = Student::factory()->create(['nama_lengkap' => 'Siswa Kedua']);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $firstStudent->id)
        ->assertSee('Siswa Pertama')
        ->call('changeStudent')
        ->assertSet('selectedStudentId', null)
        ->assertSee('Cari siswa atau calon siswa untuk melihat data dan input pembayaran.')
        ->call('selectStudent', $secondStudent->id)
        ->assertSet('selectedStudentId', $secondStudent->id)
        ->assertSee('Siswa Kedua')
        ->assertDontSee('Siswa Pertama');
});

it('menampilkan Billbook Siswa setelah siswa dipilih', function () {
    $student = makeBillStudent();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Billbook Siswa')
        ->assertSee('Total Tagihan')
        ->assertSee('Total Dibayar')
        ->assertSee('Total Tunggakan');
});

it('tidak menampilkan billbook sebelum siswa dipilih', function () {
    Livewire::test(PaymentIndex::class)
        ->assertDontSee('Billbook Siswa')
        ->assertDontSee('Total Tagihan');
});

it('total tagihan workspace sama dengan Student Detail', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $type = makeBillType('SPP Workspace Tagihan');
    makeMonthlyBill($student, $type, 970000, 8, 2026);

    $workspace = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->set('summaryPeriod', '2026-08');
    $studentDetail = Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'monthly');

    assertWorkspaceSummary($workspace, 970000, 0, 970000);
    assertWorkspaceSummary($studentDetail, 970000, 0, 970000);
});

it('total dibayar workspace sama dengan Student Detail', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $type = makeBillType('SPP Workspace Dibayar');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
    payWorkspaceBill($bill, 400000);

    $workspace = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->set('summaryPeriod', '2026-08');
    $studentDetail = Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'monthly');

    assertWorkspaceSummary($workspace, 970000, 400000, 570000);
    assertWorkspaceSummary($studentDetail, 970000, 400000, 570000);
});

it('total tunggakan workspace sama dengan Student Detail', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $type = makeBillType('SPP Workspace Tunggakan');
    $bill = makeMonthlyBill($student, $type, 1200000, 8, 2026);
    payWorkspaceBill($bill, 500000);

    $workspace = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->set('summaryPeriod', '2026-08');
    $studentDetail = Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('summaryCategory', 'monthly');

    assertWorkspaceSummary($workspace, 1200000, 500000, 700000);
    assertWorkspaceSummary($studentDetail, 1200000, 500000, 700000);
});

it('menampilkan kelompok tagihan bulanan', function () {
    $student = makeBillStudent();
    $type = makeBillType('SPP Bulanan Workspace');
    makeMonthlyBill($student, $type, 970000, 8, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Tagihan Bulanan')
        ->assertSee('Tagihan Agustus 2026')
        ->assertSee('SPP Bulanan Workspace');
});

it('menampilkan kelompok tagihan tahunan', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $type = makeBillType('Uang Buku Workspace');
    makeYearlyBill($student, $type, 750000, $academicYear->year);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Tagihan Tahunan')
        ->assertSee('Uang Buku Workspace');
});

it('menampilkan kelompok tagihan sekali bayar', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $type = makeBillType('Uang Pangkal Workspace');
    makeOneTimeBill($student, $type, 5000000, $academicYear->year);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Tagihan Sekali Bayar')
        ->assertSee('Uang Pangkal Workspace');
});

it('filter tahun ajaran membatasi billbook ke tahun yang dipilih', function () {
    $oldYear = workspaceAcademicYear('2025/2026', '2025-07-01', '2026-06-30', false);
    $currentYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $currentYear);
    $oldType = makeBillType('SPP Tahun Lama Workspace');
    $currentType = makeBillType('SPP Tahun Kini Workspace');
    makeMonthlyBill($student, $oldType, 800000, 6, 2026);
    makeMonthlyBill($student, $currentType, 970000, 8, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->set('selectedAcademicYear', $oldYear->year)
        ->assertSee('SPP Tahun Lama Workspace')
        ->assertDontSee('SPP Tahun Kini Workspace');
});

it('filter periode memperbarui ringkasan billbook', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $type = makeBillType('SPP Filter Periode Workspace');
    makeMonthlyBill($student, $type, 970000, 8, 2026);
    makeMonthlyBill($student, $type, 1200000, 9, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->set('summaryPeriod', '2026-09')
        ->tap(fn ($component) => assertWorkspaceSummary($component, 1200000, 0, 1200000));
});

it('mengganti siswa mengganti data billbook dan mereset filter', function () {
    $this->travelTo('2026-08-15');

    $academicYear = workspaceAcademicYear();
    $firstStudent = makeBillStudent();
    $secondStudent = makeBillStudent();
    enrollWorkspaceStudent($firstStudent, $academicYear);
    enrollWorkspaceStudent($secondStudent, $academicYear);
    $firstType = makeBillType('Tagihan Siswa Pertama Workspace');
    $secondType = makeBillType('Tagihan Siswa Kedua Workspace');
    makeMonthlyBill($firstStudent, $firstType, 100000, 8, 2026);
    makeMonthlyBill($secondStudent, $secondType, 200000, 9, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $firstStudent->id)
        ->set('summaryPeriod', '2026-08')
        ->assertSee('Tagihan Siswa Pertama Workspace')
        ->call('selectStudent', $secondStudent->id)
        ->assertSet('summaryPeriod', '2026-08')
        ->assertSee('Tagihan Siswa Kedua Workspace')
        ->assertDontSee('Tagihan Siswa Pertama Workspace');
});

it('billbook calon siswa mengikuti scope tahun ajaran rencana masuk', function () {
    workspaceAcademicYear();
    $futureYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $futureYear);
    $monthlyType = makeBillType('SPP Calon Siswa Workspace');
    $yearlyType = makeBillType('Uang Buku Calon Siswa Workspace');
    makeMonthlyBill($student, $monthlyType, 970000, 7, 2027);
    makeYearlyBill($student, $yearlyType, 750000, $futureYear->year);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Calon Siswa')
        ->assertSee('SPP Calon Siswa Workspace')
        ->assertSee('Uang Buku Calon Siswa Workspace');
});

it('billbook siswa lulus mengikuti scope enrollment terakhir', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear, 'lulus');
    $type = makeBillType('SPP Alumni Workspace');
    makeMonthlyBill($student, $type, 970000, 8, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Lulus')
        ->assertSee('SPP Alumni Workspace');
});

it('melihat billbook tidak mengubah data bill maupun pembayaran', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $type = makeBillType('SPP Read Only Workspace');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
    $payment = payWorkspaceBill($bill, 400000);

    $billSnapshot = $bill->only(['amount', 'period_month', 'period_year', 'academic_year', 'billing_frequency']);
    $paymentSnapshot = $payment->fresh()->only(['total_amount', 'status', 'student_id']);
    $billCount = StudentBill::query()->count();
    $paymentCount = Payment::query()->count();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->set('summaryPeriod', '2026-08')
        ->assertSee('SPP Read Only Workspace');

    expect($bill->fresh()->only(array_keys($billSnapshot)))->toBe($billSnapshot)
        ->and($payment->fresh()->only(array_keys($paymentSnapshot)))->toBe($paymentSnapshot)
        ->and(StudentBill::query()->count())->toBe($billCount)
        ->and(Payment::query()->count())->toBe($paymentCount);
});

it('membuka modal Edit Profil untuk siswa terpilih', function () {
    $student = makeBillStudent();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('isEditProfileOpen', true)
        ->assertSee('Edit Profil Siswa');
});

it('modal Edit Profil Pembayaran hanya menandai Nama Lengkap dan Kelas sebagai wajib', function () {
    $student = Student::factory()->create();

    $html = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('isEditProfileOpen', true)
        ->html();

    expect(str_contains($html, 'Nama Lengkap <span class="text-error">*</span>'))->toBeTrue()
        ->and(str_contains($html, 'Kelas <span class="text-error">*</span>'))->toBeTrue()
        ->and(str_contains($html, '>NIS</label>'))->toBeTrue()
        ->and(str_contains($html, '>Nama Panggilan</label>'))->toBeTrue()
        ->and(str_contains($html, '>Jenis Kelamin</label>'))->toBeTrue()
        ->and(str_contains($html, '>Alamat Lengkap</label>'))->toBeTrue()
        ->and(str_contains($html, 'id="edit-nis" wire:model="nis" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">'))->toBeTrue()
        ->and(str_contains($html, 'id="edit-nama-panggilan" wire:model="nama_panggilan" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">'))->toBeTrue()
        ->and(str_contains($html, 'id="edit-alamat" wire:model="alamat" rows="3" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">'))->toBeTrue()
        ->and(str_contains($html, 'id="edit-class-id" wire:model="class_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" required'))->toBeTrue()
        ->and(str_contains($html, 'id="edit-nama-lengkap" wire:model="nama_lengkap" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" required'))->toBeTrue();
});

it('modal Edit Profil memuat data siswa yang ada', function () {
    $student = Student::factory()->create([
        'nis' => 'EDIT-001',
        'nama_lengkap' => 'Nama Lengkap Awal',
        'nama_panggilan' => 'Panggilan Awal',
        'jenis_kelamin' => 'P',
        'alamat' => 'Alamat Awal',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('nis', 'EDIT-001')
        ->assertSet('nama_lengkap', 'Nama Lengkap Awal')
        ->assertSet('nama_panggilan', 'Panggilan Awal')
        ->assertSet('class_id', $student->class_id)
        ->assertSet('jenis_kelamin', 'P')
        ->assertSet('alamat', 'Alamat Awal');
});

it('calon siswa tanpa NIS dapat membuka Edit Profil', function () {
    workspaceAcademicYear();
    $futureYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    $student = Student::factory()->create(['nama_lengkap' => 'Ahmad Calon Siswa', 'nis' => null]);
    enrollWorkspaceStudent($student, $futureYear);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('isEditProfileOpen', true)
        ->assertSet('nis', null)
        ->assertSee('Edit Profil Siswa');
});

it('siswa tanpa nama panggilan dapat membuka Edit Profil', function () {
    $student = Student::factory()->create(['nama_panggilan' => null]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('nama_panggilan', null)
        ->assertSet('isEditProfileOpen', true);
});

it('siswa tanpa jenis kelamin dapat membuka Edit Profil', function () {
    $student = Student::factory()->create(['jenis_kelamin' => null]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('jenis_kelamin', null)
        ->assertSet('isEditProfileOpen', true);
});

it('siswa tanpa alamat dapat membuka Edit Profil', function () {
    $student = Student::factory()->create(['alamat' => null]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('alamat', null)
        ->assertSet('isEditProfileOpen', true);
});

it('seluruh biodata opsional null dapat dimuat tanpa TypeError', function () {
    $student = Student::factory()->create([
        'nis' => null,
        'nama_panggilan' => null,
        'jenis_kelamin' => null,
        'alamat' => null,
        'nama_ayah' => null,
        'no_telp_ayah' => null,
        'nama_ibu' => null,
        'no_telp_ibu' => null,
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'entry_date' => null,
        'foto' => null,
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('nis', null)
        ->assertSet('nama_panggilan', null)
        ->assertSet('jenis_kelamin', null)
        ->assertSet('alamat', null)
        ->assertSet('nama_ayah', '')
        ->assertSet('tanggal_lahir', '')
        ->assertSet('existing_foto', null)
        ->assertSet('isEditProfileOpen', true);
});

it('siswa dengan biodata lengkap tetap dimuat normal', function () {
    $student = Student::factory()->create([
        'nis' => 'COMPLETE-001',
        'nama_panggilan' => 'Lengkap',
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Lengkap',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('nis', 'COMPLETE-001')
        ->assertSet('nama_panggilan', 'Lengkap')
        ->assertSet('jenis_kelamin', 'L')
        ->assertSet('alamat', 'Jl. Lengkap');
});

it('menyimpan biodata opsional kosong mempertahankan nilai canonical null', function () {
    $student = Student::factory()->create([
        'nis' => 'OPTIONAL-001',
        'nama_panggilan' => 'Opsional',
        'jenis_kelamin' => 'P',
        'alamat' => 'Alamat Opsional',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('nis', '')
        ->set('nama_panggilan', '')
        ->set('jenis_kelamin', '')
        ->set('alamat', '')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $student->refresh();

    expect($student->nis)->toBeNull()
        ->and($student->nama_panggilan)->toBeNull()
        ->and($student->jenis_kelamin)->toBeNull()
        ->and($student->alamat)->toBeNull();
});

it('NIS tetap opsional saat profil disimpan', function () {
    $student = Student::factory()->create(['nis' => null]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($student->fresh()->nis)->toBeNull();
});

it('tidak membuat placeholder NIS untuk siswa yang tidak memilikinya', function () {
    $student = Student::factory()->create(['nis' => null]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('nama_lengkap', 'Nama Diperbarui')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($student->fresh()->nis)->toBeNull();
});

it('gender Edit Profil memakai satu radio group dan dibersihkan saat ditutup', function () {
    $student = Student::factory()->create(['jenis_kelamin' => 'P']);
    $component = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->assertSet('jenis_kelamin', 'P');

    expect(substr_count($component->html(), 'name="jenis_kelamin"'))->toBe(2)
        ->and(substr_count($component->html(), 'wire:model="jenis_kelamin"'))->toBe(2);

    $component
        ->set('jenis_kelamin', 'L')
        ->assertSet('jenis_kelamin', 'L')
        ->set('jenis_kelamin', 'P')
        ->assertSet('jenis_kelamin', 'P')
        ->call('closeEditProfile')
        ->assertSet('isEditProfileOpen', false)
        ->assertSet('jenis_kelamin', null);
});

it('menampilkan tahun ajaran enrollment aktif pada kartu dan modal Edit Profil', function () {
    $activeYear = workspaceAcademicYear();
    $futureYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $activeYear);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $futureYear->id,
        'school_class_id' => $student->class_id,
        'status' => 'planned',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee($activeYear->year)
        ->call('openEditProfile')
        ->assertSee('Hanya dibaca')
        ->assertSee($activeYear->year);
});

it('mengedit nama lengkap memperbarui Student', function () {
    $student = makeBillStudent();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('nama_lengkap', 'Nama Lengkap Baru')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSee('Nama Lengkap Baru');

    expect($student->fresh()->nama_lengkap)->toBe('Nama Lengkap Baru');
});

it('mengedit nama panggilan memperbarui Student', function () {
    $student = makeBillStudent();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('nama_panggilan', 'Panggilan Baru')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSee('Panggilan Baru');

    expect($student->fresh()->nama_panggilan)->toBe('Panggilan Baru');
});

it('edit gender dan alamat memakai validasi StudentManagement', function () {
    $student = makeBillStudent();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('jenis_kelamin', 'X')
        ->set('alamat', '')
        ->call('saveProfile')
        ->assertHasErrors(['jenis_kelamin']);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('jenis_kelamin', 'L')
        ->set('alamat', '')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($student->fresh()->jenis_kelamin)->toBe('L')
        ->and($student->fresh()->alamat)->toBeNull();
});

it('mengedit kelas memperbarui Student class_id', function () {
    $student = makeBillStudent();
    $newClass = SchoolClass::factory()->create(['name' => 'VIII B', 'level' => 8]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('class_id', $newClass->id)
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($student->fresh()->class_id)->toBe($newClass->id);
});

it('mengedit kelas memperbarui enrollment aktif tahun ajaran aktif', function () {
    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $newClass = SchoolClass::factory()->create(['name' => 'VIII C', 'level' => 8]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('class_id', $newClass->id)
        ->call('saveProfile')
        ->assertHasNoErrors();

    $enrollment = StudentAcademicEnrollment::query()
        ->where('student_id', $student->id)
        ->where('academic_year_id', $academicYear->id)
        ->firstOrFail();

    expect($enrollment->school_class_id)->toBe($newClass->id);
});

it('mengedit kelas tidak mengubah enrollment historis', function () {
    $historicalYear = workspaceAcademicYear('2025/2026', '2025-07-01', '2026-06-30', false);
    $activeYear = workspaceAcademicYear();
    $historicalClass = SchoolClass::factory()->create(['name' => 'VII A', 'level' => 7]);
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $activeYear);
    $historicalEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $historicalYear->id,
        'school_class_id' => $historicalClass->id,
        'status' => 'promoted',
    ]);
    $newClass = SchoolClass::factory()->create(['name' => 'VIII D', 'level' => 8]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('class_id', $newClass->id)
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($historicalEnrollment->fresh()->school_class_id)->toBe($historicalClass->id);
});

it('mengedit kelas tidak mengubah StudentBill yang sudah ada', function () {
    $student = makeBillStudent();
    $type = makeBillType('SPP Snapshot Edit Profil');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
    $billSnapshot = $bill->only(['amount', 'academic_year', 'period_month', 'period_year', 'billing_frequency']);
    $newClass = SchoolClass::factory()->create();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('class_id', $newClass->id)
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($bill->fresh()->only(array_keys($billSnapshot)))->toBe($billSnapshot);
});

it('mengedit profil tidak mengubah PaymentDetail yang sudah ada', function () {
    $student = makeBillStudent();
    $type = makeBillType('SPP Payment Detail Edit Profil');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
    $payment = payWorkspaceBill($bill, 400000);
    $detail = $payment->details()->firstOrFail();
    $detailSnapshot = $detail->only(['payment_id', 'bill_id', 'payment_type_id', 'period_month', 'period_year', 'academic_year', 'amount']);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('nama_lengkap', 'Nama Setelah Pembayaran')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($detail->fresh()->only(array_keys($detailSnapshot)))->toBe($detailSnapshot);
});

it('siswa tetap terpilih setelah profil disimpan', function () {
    $student = makeBillStudent();

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('alamat', 'Alamat Sesudah Edit')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSet('selectedStudentId', $student->id)
        ->assertSet('isEditProfileOpen', false)
        ->assertSee('Alamat Sesudah Edit');
});

it('billbook tetap tampil setelah profil disimpan', function () {
    $student = makeBillStudent();
    $type = makeBillType('SPP Billbook Setelah Edit');
    makeMonthlyBill($student, $type, 970000, 8, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('nama_panggilan', 'Sesudah Edit')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSee('Billbook Siswa')
        ->assertSee('SPP Billbook Setelah Edit');
});

it('Billbook Payment Workspace menampilkan Tambah Tagihan Edit dan Hapus', function () {
    $student = makeBillStudent();
    $paymentType = makeBillType('SPP Action Workspace');
    $bill = makeMonthlyBill($student, $paymentType, 970000, 8, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee('Tambah Tagihan')
        ->assertSeeHtml('wire:click="editBill('.$bill->id.')"')
        ->assertSeeHtml('wire:click="confirmDeleteBill('.$bill->id.')"');
});

it('menambah tagihan dari Payment Workspace memakai siswa terpilih dan memperbarui total', function () {
    $this->travelTo('2026-08-15');

    $academicYear = workspaceAcademicYear();
    $student = makeBillStudent();
    enrollWorkspaceStudent($student, $academicYear);
    $unrelatedStudent = makeBillStudent();
    $existingType = makeBillType('SPP Existing Workspace');
    $newType = makeBillType('Jemputan Add Workspace');
    makeBillRate($newType, 8, 250000);
    makeMonthlyBill($student, $existingType, 100000, 8, 2026);

    $component = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openAddBillForMonth', 8, 2026)
        ->set('addPaymentTypeId', (string) $newType->id)
        ->set('addAmount', '250000')
        ->call('saveAddBill')
        ->assertHasNoErrors()
        ->assertSet('selectedStudentId', $student->id)
        ->assertSee('Jemputan Add Workspace');

    $addedBill = StudentBill::query()
        ->where('student_id', $student->id)
        ->where('payment_type_id', $newType->id)
        ->where('period_month', 8)
        ->where('period_year', 2026)
        ->firstOrFail();

    expect((float) $addedBill->amount)->toBe(250000.0)
        ->and(StudentBill::query()->where('student_id', $unrelatedStudent->id)->where('payment_type_id', $newType->id)->exists())->toBeFalse();

    assertWorkspaceSummary($component, 350000, 0, 350000);
});

it('mengedit dan menghapus tagihan dari Payment Workspace memperbarui bill dan total yang benar', function () {
    $this->travelTo('2026-08-15');

    $student = makeBillStudent();
    $unrelatedStudent = makeBillStudent();
    $paymentType = makeBillType('Edit Delete Workspace');
    $bill = makeMonthlyBill($student, $paymentType, 300000, 8, 2026);
    $unrelatedBill = makeMonthlyBill($unrelatedStudent, $paymentType, 400000, 8, 2026);

    $component = Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('editBill', $bill->id)
        ->set('editAmount', '450000')
        ->call('saveEditBill')
        ->assertHasNoErrors()
        ->assertSee('Rp 450.000');

    expect((float) $bill->fresh()->amount)->toBe(450000.0)
        ->and((float) $unrelatedBill->fresh()->amount)->toBe(400000.0);

    assertWorkspaceSummary($component, 450000, 0, 450000);

    $component->call('confirmDeleteBill', $bill->id)
        ->call('deleteBill')
        ->assertHasNoErrors()
        ->assertSet('selectedStudentId', $student->id);

    expect($bill->fresh())->toBeNull()
        ->and($unrelatedBill->fresh())->not->toBeNull();

    assertWorkspaceSummary($component, 0, 0, 0);
});

it('Payment Workspace mempertahankan proteksi delete untuk tagihan yang sudah dibayar', function () {
    $student = makeBillStudent();
    $paymentType = makeBillType('Paid Delete Protection Workspace');
    $bill = makeMonthlyBill($student, $paymentType, 500000, 8, 2026);
    $payment = payWorkspaceBill($bill, 200000);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('confirmDeleteBill', $bill->id)
        ->assertSet('deletePaidAmount', 200000.0)
        ->call('deleteBill')
        ->assertHasErrors(['deleteConfirm']);

    expect($bill->fresh())->not->toBeNull()
        ->and($payment->details()->where('bill_id', $bill->id)->exists())->toBeTrue();
});

it('Payment Workspace tidak dapat mengelola bill milik siswa lain', function () {
    $selectedStudent = makeBillStudent();
    $otherStudent = makeBillStudent();
    $bill = makeMonthlyBill($otherStudent, makeBillType('Other Student Bill'), 300000, 8, 2026);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $selectedStudent->id)
        ->call('editBill', $bill->id)
        ->assertSet('isEditOpen', false)
        ->call('confirmDeleteBill', $bill->id)
        ->assertSet('isDeleteOpen', false);

    expect($bill->fresh())->not->toBeNull();
});

it('shared bill table tetap menyembunyikan action dalam read-only mode', function () {
    $student = makeBillStudent();
    $bill = makeMonthlyBill($student, makeBillType('Read Only Shared Bill'), 300000, 8, 2026);
    $html = view('livewire.student.bill-table', [
        'bills' => collect([$bill->load('paymentType')]),
        'readOnly' => true,
    ])->render();

    expect($html)
        ->not->toContain('editBill('.$bill->id.')')
        ->not->toContain('confirmDeleteBill('.$bill->id.')');
});

it('edit kelas calon siswa memperbarui enrollment rencana masuk saja', function () {
    $historicalYear = workspaceAcademicYear('2097/2098', '2097-07-01', '2098-06-30', false);
    $activeYear = workspaceAcademicYear('2098/2099', '2098-07-01', '2099-06-30');
    $futureYear = workspaceAcademicYear('2099/2100', '2099-07-01', '2100-06-30', false);
    $historicalClass = SchoolClass::factory()->create(['name' => 'Kelas Historis', 'level' => 7]);
    $unrelatedCurrentClass = SchoolClass::factory()->create(['name' => 'Kelas Current Tidak Aktif', 'level' => 8]);
    $futureClass = SchoolClass::factory()->create(['name' => 'Kelas Rencana Awal', 'level' => 8]);
    $newFutureClass = SchoolClass::factory()->create(['name' => 'Kelas Rencana Baru', 'level' => 8]);
    $student = Student::factory()->create(['class_id' => $futureClass->id]);
    $student->update(['class_id' => $futureClass->id]);

    $historicalEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $historicalYear->id,
        'school_class_id' => $historicalClass->id,
        'status' => 'promoted',
    ]);
    $unrelatedCurrentEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $unrelatedCurrentClass->id,
        'status' => 'planned',
    ]);
    $futureEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $futureYear->id,
        'school_class_id' => $futureClass->id,
        'status' => 'active',
    ]);

    expect($student->fresh()->academicStatus())->toBe('calon_siswa')
        ->and($student->fresh()->class_id)->toBe($futureClass->id)
        ->and($futureEnrollment->academic_year_id)->toBeGreaterThan($activeYear->id);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->assertSee($futureYear->year)
        ->call('openEditProfile')
        ->assertSet('class_id', $futureClass->id)
        ->assertSee('Hanya dibaca')
        ->assertSee($futureYear->year)
        ->set('class_id', $newFutureClass->id)
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($student->fresh()->class_id)->toBe($newFutureClass->id)
        ->and($futureEnrollment->fresh()->school_class_id)->toBe($newFutureClass->id)
        ->and($historicalEnrollment->fresh()->school_class_id)->toBe($historicalClass->id)
        ->and($unrelatedCurrentEnrollment->fresh()->school_class_id)->toBe($unrelatedCurrentClass->id);
});

it('edit profil siswa lulus tidak mengubah enrollment historis', function () {
    $historicalYear = workspaceAcademicYear('2025/2026', '2025-07-01', '2026-06-30', false);
    $graduationYear = workspaceAcademicYear();
    $historicalClass = SchoolClass::factory()->create(['name' => 'VII Alumni', 'level' => 7]);
    $graduationClass = SchoolClass::factory()->create(['name' => 'VIII Alumni', 'level' => 8]);
    $newProfileClass = SchoolClass::factory()->create(['name' => 'Kelas Profil Alumni', 'level' => 8]);
    $student = Student::factory()->create(['class_id' => $graduationClass->id]);
    $student->update(['class_id' => $graduationClass->id]);
    $historicalEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $historicalYear->id,
        'school_class_id' => $historicalClass->id,
        'status' => 'promoted',
    ]);
    $graduatedEnrollment = StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $graduationYear->id,
        'school_class_id' => $graduationClass->id,
        'status' => 'lulus',
    ]);

    Livewire::test(PaymentIndex::class)
        ->call('selectStudent', $student->id)
        ->call('openEditProfile')
        ->set('class_id', $newProfileClass->id)
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($student->fresh()->class_id)->toBe($newProfileClass->id)
        ->and($historicalEnrollment->fresh()->school_class_id)->toBe($historicalClass->id)
        ->and($graduatedEnrollment->fresh()->school_class_id)->toBe($graduationClass->id);
});

it('baris riwayat calon siswa menampilkan tombol hapus transaksi', function () {
    $user = User::factory()->create();
    $prospect = ProspectiveStudent::factory()->create();
    $payment = makeProspectiveHistoryPayment($prospect, Bank::factory()->create(), $user, 'KWT-REG-BTN');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($payment->receipt_number)
        ->assertSeeHtml('wire:click="confirmDelete(\'prospect-'.$payment->id.'\', \'prospective\')"', false)
        ->assertSeeHtml('title="Hapus Transaksi"', false);
});

it('baris riwayat siswa tetap menampilkan tombol hapus transaksi seperti sebelumnya', function () {
    $user = User::factory()->create();
    $payment = makeHistoryPayment(makeBillStudent(), Bank::factory()->create(), $user, 'KWT-STUDENT-BTN', '2026-08-10');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($payment->receipt_number)
        ->assertSeeHtml('wire:click="confirmDelete(\''.$payment->id.'\', \'student\')"', false)
        ->assertSeeHtml('title="Hapus Transaksi"', false);
});

it('delete transaksi calon siswa menghapus payment beserta detailnya', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $prospect = ProspectiveStudent::factory()->create();
    $bank = Bank::factory()->create();
    $type = makeBillType('Formulir Delete');
    $bill = makeProspectiveBill($prospect, $type, 350000);
    $payment = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-REG-DELETE');
    payProspectiveBill($payment, $bill, 350000);

    expect($bill->fresh()->paid_amount)->toBe(350000.0);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', 'prospect-'.$payment->id, 'prospective')
        ->assertSet('isDeleteModalOpen', true)
        ->assertSet('deletingId', $payment->id)
        ->assertSet('deletingSource', 'prospective')
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingId', null)
        ->assertSet('deletingSource', '');

    expect(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(ProspectiveStudentPaymentDetail::query()
            ->where('prospective_student_payment_id', $payment->id)
            ->exists())->toBeFalse()
        ->and(ProspectiveStudentBill::query()->whereKey($bill->id)->exists())->toBeTrue();
});

it('permanent delete calon siswa mengembalikan tagihan lunas menjadi belum bayar', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $prospect = ProspectiveStudent::factory()->create();
    $type = makeBillType('Formulir Lunas');
    $bill = makeProspectiveBill($prospect, $type, 350000);
    $payment = makeProspectiveHistoryPayment($prospect, Bank::factory()->create(), $user, 'KWT-REG-LUNAS');
    payProspectiveBill($payment, $bill, 350000);

    expect($bill->fresh()->remaining_amount)->toBe(0.0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', 'prospect-'.$payment->id, 'prospective')
        ->call('delete');

    expect($bill->fresh()->paid_amount)->toBe(0.0)
        ->and($bill->fresh()->remaining_amount)->toBe(350000.0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID);
});

it('permanent delete calon siswa mengembalikan saldo parsial tanpa merusak pembayaran lain', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $prospect = ProspectiveStudent::factory()->create();
    $type = makeBillType('Formulir Parsial');
    $bill = makeProspectiveBill($prospect, $type, 350000);
    $firstPayment = makeProspectiveHistoryPayment($prospect, Bank::factory()->create(), $user, 'KWT-REG-PARTIAL-A');
    $secondPayment = makeProspectiveHistoryPayment($prospect, Bank::factory()->create(), $user, 'KWT-REG-PARTIAL-B');
    payProspectiveBill($firstPayment, $bill, 200000);
    payProspectiveBill($secondPayment, $bill, 150000);

    expect($bill->fresh()->paid_amount)->toBe(350000.0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', 'prospect-'.$firstPayment->id, 'prospective')
        ->call('delete');

    expect(ProspectiveStudentPayment::query()->whereKey($firstPayment->id)->exists())->toBeFalse()
        ->and($secondPayment->fresh())->not->toBeNull()
        ->and($bill->fresh()->paid_amount)->toBe(150000.0)
        ->and($bill->fresh()->remaining_amount)->toBe(200000.0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL);
});

it('permanent delete calon siswa menghapus file receipt dari storage', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = makeProspectiveHistoryPayment(ProspectiveStudent::factory()->create(), Bank::factory()->create(), $user, 'KWT-REG-RECEIPT');
    Storage::disk('public')->put('receipts/prospect-delete-proof.pdf', 'receipt');
    $payment->update(['receipt' => 'receipts/prospect-delete-proof.pdf']);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', 'prospect-'.$payment->id, 'prospective')
        ->call('delete');

    Storage::disk('public')->assertMissing('receipts/prospect-delete-proof.pdf');
});

it('delete transaksi calon siswa menampilkan flash sukses', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = makeProspectiveHistoryPayment(ProspectiveStudent::factory()->create(), Bank::factory()->create(), $user, 'KWT-REG-FLASH');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', 'prospect-'.$payment->id, 'prospective')
        ->call('delete')
        ->assertHasNoErrors();

    expect(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(session()->all()['_flash']['new'] ?? [])->toContain('success');
});

it('transaksi calon siswa hilang dari riwayat setelah dihapus', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $prospect = ProspectiveStudent::factory()->create();
    $type = makeBillType('Formulir Riwayat');
    $bill = makeProspectiveBill($prospect, $type, 350000);
    $payment = makeProspectiveHistoryPayment($prospect, Bank::factory()->create(), $user, 'KWT-REG-RIWAYAT');
    payProspectiveBill($payment, $bill, 350000);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-REG-RIWAYAT')
        ->call('confirmDelete', 'prospect-'.$payment->id, 'prospective')
        ->call('delete')
        ->assertDontSee('KWT-REG-RIWAYAT');
});

it('id baris calon siswa yang di-namespace di-resolve ke id payment yang benar', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = makeProspectiveHistoryPayment(ProspectiveStudent::factory()->create(), Bank::factory()->create(), $user, 'KWT-REG-ID');

    $component = Livewire::test(PaymentIndex::class)->call('setActiveTab', 'history');

    $component->call('confirmDelete', 'prospect-'.$payment->id, 'prospective')
        ->assertSet('deletingId', $payment->id)
        ->assertSet('isDeleteModalOpen', true);

    $component->call('cancelDelete')
        ->assertSet('deletingId', null)
        ->assertSet('isDeleteModalOpen', false);

    $component->call('confirmDelete', 'prospect-'.$payment->id, 'prospective')
        ->assertSet('deletingId', $payment->id)
        ->assertSet('deletingSource', 'prospective');
});

it('id baris rusak tidak pernah di-resolve ke payment', function (mixed $rowId, string $source) {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = makeProspectiveHistoryPayment(ProspectiveStudent::factory()->create(), Bank::factory()->create(), $user, 'KWT-REG-MALFORMED');

    $component = Livewire::test(PaymentIndex::class)->call('setActiveTab', 'history');

    $component->call('confirmDelete', $rowId, $source)
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingId', null);

    $component->call('delete')
        ->assertSet('isDeleteModalOpen', false);

    expect(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeTrue();
})->with([
    'prefix kosong' => ['prospect-', 'prospective'],
    'prefix terpotong' => ['prospe', 'prospective'],
    'bagian angka bukan digit' => ['prospect-abc', 'prospective'],
    'bagian angka negatif' => ['prospect--5', 'prospective'],
    'bagian angka nol' => ['prospect-0', 'prospective'],
    'id siswa negatif' => ['-5', 'student'],
    'id siswa nol' => ['0', 'student'],
    'sumber tidak dikenal' => ['1', 'unknown'],
    'sumber kosong' => ['1', ''],
    'id siswa tanpa prefix' => ['5', 'prospective'],
    'id prospek tanpa prefix' => ['prospect-5', 'student'],
]);

it('delete transaksi siswa tetap memakai flow dan service siswa', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $student = makeBillStudent();
    $type = makeBillType('SPP Delete Siswa');
    $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
    $payment = payWorkspaceBill($bill, 400000);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('confirmDelete', $payment->id)
        ->assertSet('deletingSource', 'student')
        ->call('delete')
        ->assertHasNoErrors();

    expect($payment->fresh())->toBeNull()
        ->and(PaymentDetail::query()->where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and($bill->fresh()->paid_amount)->toBe(0.0)
        ->and($bill->fresh()->remaining_amount)->toBe(970000.0);
});

it('delete menerima kedua argumen dan sumber tidak dikenal tidak menghapus apa pun', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = makeHistoryPayment(makeBillStudent(), Bank::factory()->create(), $user, 'KWT-GUARD', '2026-08-10');

    $component = Livewire::test(PaymentIndex::class)->call('setActiveTab', 'history');

    $component->set('deletingId', $payment->id)
        ->set('deletingSource', 'unknown')
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false);

    expect($payment->fresh())->not->toBeNull();

    $component->set('deletingSource', 'student')
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false);

    expect($payment->fresh())->toBeNull();
});

it('lookup payment untuk hapus hanya terjadi sekali saat modal dibuka bukan per baris', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $prospect = ProspectiveStudent::factory()->create();
    $bank = Bank::factory()->create();
    $bill = makeProspectiveBill($prospect, makeBillType('Formulir Lookup'), 350000);

    for ($index = 1; $index <= 12; $index++) {
        $payment = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-REG-LOOKUP-'.$index);
        payProspectiveBill($payment, $bill, 100000);
    }

    $targetId = ProspectiveStudentPayment::query()->orderByDesc('id')->value('id');

    $component = Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('KWT-REG-LOOKUP-1')
        ->assertSeeHtml('wire:click="confirmDelete(\'prospect-'.$targetId.'\', \'prospective\')"', false);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $component->call('confirmDelete', 'prospect-'.$targetId, 'prospective')
        ->assertSet('isDeleteModalOpen', true)
        ->assertSet('deletingId', $targetId);

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();
    DB::flushQueryLog();

    $singleIdLookups = $queries
        ->filter(fn (string $query): bool => str_contains($query, 'from "prospective_student_payments"')
            && str_contains($query, '"prospective_student_payments"."id" = ?'))
        ->count();

    $bulkLookups = $queries
        ->filter(fn (string $query): bool => str_contains($query, 'from "prospective_student_payments"')
            && str_contains($query, 'in (?'))
        ->count();

    expect($singleIdLookups)->toBeLessThanOrEqual(2)
        ->and($bulkLookups)->toBeLessThanOrEqual(2);
});

function historyPaginator(array $filters = []): LengthAwarePaginator
{
    return app(TransactionHistoryService::class)->getHistory(...$filters);
}

/** @return Collection<int, TransactionHistoryRow> */
function historyRows(array $filters = []): Collection
{
    return collect(historyPaginator($filters)->items());
}

function historyRow(string $receiptNumber, array $filters = []): TransactionHistoryRow
{
    $row = historyRows($filters)->firstWhere('receiptNumber', $receiptNumber);

    expect($row)->toBeInstanceOf(TransactionHistoryRow::class);

    return $row;
}

function setPaymentCreatedAt(Payment|ProspectiveStudentPayment $payment, string $createdAt): void
{
    $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
}

/**
 * Kumpulkan SQL yang dijalankan di dalam closure, lalu kembalikan daftar string SQL.
 * Memakai query log (bukan DB::listen) supaya aman dipanggil berulang dalam satu test.
 *
 * @return list<string>
 */
function captureHistoryQueries(Closure $run): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $run();

    $queries = collect(DB::getQueryLog())->pluck('query')->all();
    DB::disableQueryLog();
    DB::flushQueryLog();

    return $queries;
}

function countQueriesContaining(array $queries, string $needle): int
{
    return collect($queries)->filter(fn (string $sql): bool => str_contains($sql, $needle))->count();
}

const PROSPECT_LAZY_DETAIL_QUERY = '"prospective_student_payment_details"."prospective_student_bill_id" = ?';

const PROSPECT_LAZY_PAYMENT_QUERY = '"prospective_student_payments"."id" = ?';

const STUDENT_LAZY_DETAIL_QUERY = '"payment_details"."bill_id" = ?';

const STUDENT_LAZY_ADJUSTMENT_QUERY = '"bill_adjustments"."bill_id" = ?';

/**
 * @param  Closure(): mixed  $run
 * @return array{total: int, lazy: int}
 */
function measureHistoryQueries(Closure $run): array
{
    $queries = captureHistoryQueries($run);

    return [
        'total' => count($queries),
        'lazy' => collect([
            PROSPECT_LAZY_DETAIL_QUERY,
            PROSPECT_LAZY_PAYMENT_QUERY,
            STUDENT_LAZY_DETAIL_QUERY,
            STUDENT_LAZY_ADJUSTMENT_QUERY,
        ])->sum(fn (string $needle): int => countQueriesContaining($queries, $needle)),
    ];
}

function makeProspectiveSettlementPayment(
    ProspectiveStudent $prospect,
    Bank $bank,
    User $user,
    string $receiptNumber,
    string $typeName,
    int $billAmount,
    int $paidAmount
): ProspectiveStudentPayment {
    $bill = makeProspectiveBill($prospect, makeBillType($typeName), $billAmount);
    $payment = makeProspectiveHistoryPayment($prospect, $bank, $user, $receiptNumber);
    payProspectiveBill($payment, $bill, $paidAmount);

    return $payment;
}

it('status label riwayat calon siswa tetap Lunas saat tagihan lunas', function () {
    $user = User::factory()->create();
    $prospect = ProspectiveStudent::factory()->create();

    makeProspectiveSettlementPayment(
        $prospect,
        Bank::factory()->create(),
        $user,
        'KWT-REG-SET-LUNAS',
        'Formulir Settlement Lunas',
        350000,
        350000
    );

    $row = historyRow('KWT-REG-SET-LUNAS');

    expect($row->statusLabel)->toBe('Lunas')
        ->and($row->source)->toBe('prospective')
        ->and($row->isActive)->toBeTrue()
        ->and($row->badgeLabel)->toBe('Pendaftaran');
});

it('status label riwayat calon siswa tetap Sebagian saat tagihan hanya dibayar sebagian', function () {
    $user = User::factory()->create();
    $prospect = ProspectiveStudent::factory()->create();

    makeProspectiveSettlementPayment(
        $prospect,
        Bank::factory()->create(),
        $user,
        'KWT-REG-SET-SEBAGIAN',
        'Formulir Settlement Sebagian',
        350000,
        150000
    );

    expect(historyRow('KWT-REG-SET-SEBAGIAN')->statusLabel)->toBe('Sebagian');
});

it('status label riwayat calon siswa tetap Dibatalkan untuk transaksi batal', function () {
    $user = User::factory()->create();
    $prospect = ProspectiveStudent::factory()->create();

    $payment = makeProspectiveSettlementPayment(
        $prospect,
        Bank::factory()->create(),
        $user,
        'KWT-REG-SET-BATAL',
        'Formulir Settlement Batal',
        350000,
        350000
    );
    $payment->update(['status' => ProspectiveStudentPayment::STATUS_CANCELLED]);

    $row = historyRow('KWT-REG-SET-BATAL');

    expect($row->statusLabel)->toBe('Dibatalkan')
        ->and($row->isActive)->toBeFalse()
        ->and($row->editUrl)->toBeNull();
});

it('label detail riwayat calon siswa tetap memakai tanda plus tanpa deduplikasi', function () {
    $user = User::factory()->create();
    $prospect = ProspectiveStudent::factory()->create();
    $bank = Bank::factory()->create();

    $twoLabels = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-REG-LABEL-2');
    payProspectiveBill($twoLabels, makeProspectiveBill($prospect, makeBillType('Formulir A'), 350000), 100000);
    payProspectiveBill($twoLabels, makeProspectiveBill($prospect, makeBillType('Formulir B'), 350000), 100000);

    $threeLabels = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-REG-LABEL-3');
    payProspectiveBill($threeLabels, makeProspectiveBill($prospect, makeBillType('Formulir C'), 350000), 100000);
    payProspectiveBill($threeLabels, makeProspectiveBill($prospect, makeBillType('Formulir D'), 350000), 100000);
    payProspectiveBill($threeLabels, makeProspectiveBill($prospect, makeBillType('Formulir E'), 350000), 100000);

    $duplicates = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-REG-LABEL-DUP');
    payProspectiveBill($duplicates, makeProspectiveBill($prospect, makeBillType('Formulir X'), 350000), 100000);
    payProspectiveBill($duplicates, makeProspectiveBill($prospect, makeBillType('Formulir X'), 350000), 100000);

    $empty = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-REG-LABEL-KOSONG');

    expect(historyRow('KWT-REG-LABEL-2')->detailDisplay)->toBe('Formulir A + Formulir B')
        ->and(historyRow('KWT-REG-LABEL-3')->detailDisplay)->toBe('Formulir C + 2 lainnya')
        ->and(historyRow('KWT-REG-LABEL-DUP')->detailDisplay)->toBe('Formulir X + Formulir X')
        ->and(historyRow('KWT-REG-LABEL-KOSONG')->detailDisplay)->toBe('-');
});

it('jumlah query riwayat calon siswa tidak bertambah linear terhadap jumlah baris', function () {
    $user = User::factory()->create();
    $prospect = ProspectiveStudent::factory()->create();
    $bank = Bank::factory()->create();
    $bill = makeProspectiveBill($prospect, makeBillType('Formulir Query Guard'), 350000);

    $addRows = function (int $from, int $to) use ($prospect, $bank, $user, $bill): void {
        for ($index = $from; $index <= $to; $index++) {
            $payment = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-REG-GUARD-'.$index);
            payProspectiveBill($payment, $bill, 100000);
        }
    };

    $addRows(1, 1);
    $oneRow = measureHistoryQueries(fn () => historyRows(['perPage' => 50]));

    $addRows(2, 20);
    $manyRows = measureHistoryQueries(fn () => historyRows(['perPage' => 50]));

    expect(historyRows(['perPage' => 50]))->toHaveCount(20)
        ->and($oneRow['lazy'])->toBe(0)
        ->and($manyRows['lazy'])->toBe(0)
        ->and($manyRows['total'] - $oneRow['total'])->toBeLessThanOrEqual(1);
});

it('status label riwayat siswa mengikuti settlement tagihan yang sudah dibayar', function () {
    $user = User::factory()->create();
    $student = makeBillStudent();

    $lunasBill = makeMonthlyBill($student, makeBillType('SPP Settlement Lunas'), 970000, 8, 2026);
    $lunasPayment = payWorkspaceBill($lunasBill, 970000);

    $sebagianBill = makeMonthlyBill($student, makeBillType('SPP Settlement Sebagian'), 970000, 9, 2026);
    $sebagianPayment = payWorkspaceBill($sebagianBill, 300000);

    expect(historyRow($lunasPayment->receipt_number)->statusLabel)->toBe('Lunas')
        ->and(historyRow($sebagianPayment->receipt_number)->statusLabel)->toBe('Tunggakan');
});

it('effective amount dan status tagihan siswa tetap memperhitungkan penyesuaian', function () {
    $user = User::factory()->create();
    $student = makeBillStudent();

    $discountedBill = makeMonthlyBill($student, makeBillType('SPP Diskon Penuh'), 970000, 8, 2026);
    BillAdjustment::query()->create([
        'bill_id' => $discountedBill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -700000,
        'reason' => 'Diskon promo',
    ]);
    $lunasPayment = payWorkspaceBill($discountedBill, 970000);

    $partialBill = makeMonthlyBill($student, makeBillType('SPP Diskon Sebagian'), 970000, 9, 2026);
    BillAdjustment::query()->create([
        'bill_id' => $partialBill->id,
        'type' => BillAdjustment::TYPE_DISCOUNT,
        'amount' => -700000,
        'reason' => 'Diskon promo',
    ]);
    $partialPayment = payWorkspaceBill($partialBill, 150000);

    expect($discountedBill->fresh()->effective_amount)->toBe(270000.0)
        ->and($partialBill->fresh()->effective_amount)->toBe(270000.0)
        ->and($partialBill->fresh()->remaining_amount)->toBe(120000.0)
        ->and($partialBill->fresh()->status)->toBe(StudentBill::STATUS_PARTIAL)
        ->and(historyRow($lunasPayment->receipt_number)->statusLabel)->toBe('Lunas')
        ->and(historyRow($partialPayment->receipt_number)->statusLabel)->toBe('Tunggakan');
});

it('jumlah query riwayat siswa tidak bertambah linear terhadap jumlah baris', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $type = makeBillType('SPP Query Guard');

    $addRows = function (int $from, int $to) use ($student, $bank, $user, $type): void {
        for ($index = $from; $index <= $to; $index++) {
            $bill = makeMonthlyBill($student, $type, 970000, 8, 2026);
            PaymentDetail::query()->create([
                'payment_id' => makeHistoryPayment($student, $bank, $user, 'KWT-SISWA-GUARD-'.$index, '2026-08-10')->id,
                'bill_id' => $bill->id,
                'payment_type_id' => $type->id,
                'period_month' => 8,
                'period_year' => 2026,
                'amount' => 970000,
            ]);
        }
    };

    $addRows(1, 1);
    $oneRow = measureHistoryQueries(fn () => historyRows(['perPage' => 50]));

    $addRows(2, 20);
    $manyRows = measureHistoryQueries(fn () => historyRows(['perPage' => 50]));

    expect(historyRows(['perPage' => 50]))->toHaveCount(20)
        ->and($oneRow['lazy'])->toBe(0)
        ->and($manyRows['lazy'])->toBe(0)
        ->and($manyRows['total'] - $oneRow['total'])->toBeLessThanOrEqual(1);
});

it('halaman campuran siswa dan calon siswa tetap urut dan tidak memicu query per baris', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $prospect = ProspectiveStudent::factory()->create();

    $studentBill = makeMonthlyBill($student, makeBillType('SPP Campuran'), 970000, 8, 2026);
    $studentPayment = makeHistoryPayment($student, $bank, $user, 'KWT-CAMPURAN-SISWA', '2026-08-10');
    PaymentDetail::query()->create([
        'payment_id' => $studentPayment->id,
        'bill_id' => $studentBill->id,
        'payment_type_id' => $studentBill->payment_type_id,
        'period_month' => 8,
        'period_year' => 2026,
        'amount' => 970000,
    ]);
    setPaymentCreatedAt($studentPayment, '2026-08-10 08:00:00');

    $prospectBill = makeProspectiveBill($prospect, makeBillType('Formulir Campuran'), 350000);
    $prospectPayment = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-CAMPURAN-PROSPEK');
    payProspectiveBill($prospectPayment, $prospectBill, 350000);
    setPaymentCreatedAt($prospectPayment, '2026-08-11 08:00:00');

    $measurement = measureHistoryQueries(fn () => historyRows(['perPage' => 50]));
    $rows = historyRows(['perPage' => 50]);

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('receiptNumber')->all())->toBe(['KWT-CAMPURAN-PROSPEK', 'KWT-CAMPURAN-SISWA'])
        ->and($rows->pluck('source')->all())->toBe(['prospective', 'student'])
        ->and($rows->pluck('statusLabel')->all())->toBe(['Lunas', 'Lunas'])
        ->and($measurement['lazy'])->toBe(0);
});

it('paginasi riwayat transaksi tetap 10 per halaman dengan total dan isi yang benar', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeBillStudent();
    $prospect = ProspectiveStudent::factory()->create();
    $bill = makeProspectiveBill($prospect, makeBillType('Formulir Paginasi'), 350000);
    $studentBill = makeMonthlyBill($student, makeBillType('SPP Paginasi'), 970000, 8, 2026);

    for ($index = 1; $index <= 6; $index++) {
        $prospectPayment = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-PAGINASI-P'.$index);
        payProspectiveBill($prospectPayment, $bill, 100000);
        setPaymentCreatedAt($prospectPayment, '2026-08-20 08:00:00');

        $studentPayment = makeHistoryPayment($student, $bank, $user, 'KWT-PAGINASI-S'.$index, '2026-08-20');
        PaymentDetail::query()->create([
            'payment_id' => $studentPayment->id,
            'bill_id' => $studentBill->id,
            'payment_type_id' => $studentBill->payment_type_id,
            'period_month' => 8,
            'period_year' => 2026,
            'amount' => 970000,
        ]);
        setPaymentCreatedAt($studentPayment, '2026-08-20 08:00:00');
    }

    Paginator::currentPageResolver(fn (): int => 1);
    $firstPage = historyPaginator();
    Paginator::currentPageResolver(fn (): int => 2);
    $secondPage = historyPaginator();

    expect($firstPage->total())->toBe(12)
        ->and($firstPage->items())->toHaveCount(10)
        ->and($secondPage->total())->toBe(12)
        ->and($secondPage->items())->toHaveCount(2)
        ->and(array_intersect(
            collect($firstPage->items())->pluck('id')->all(),
            collect($secondPage->items())->pluck('id')->all(),
        ))->toBe([]);
});

// ---------------------------------------------------------------------------
// FILTER KATEGORI (Semua / Siswa Aktif / Calon Siswa / Lulus / Pindah)
// ---------------------------------------------------------------------------

function makeCategoryStudent(string $status, string $name, string $nis): Student
{
    return Student::factory()->create([
        'nama_lengkap' => $name,
        'nis' => $nis,
        'status' => $status,
    ]);
}

function seedCategoryHistory(User $user, Bank $bank): array
{
    return [
        'aktif' => makeHistoryPayment(
            makeCategoryStudent(StudentStatus::Active->value, 'Siswa Kat Aktif', 'NIS-KAT-AKTIF'),
            $bank,
            $user,
            'KWT-KAT-AKTIF',
            '2026-08-10',
            createdAt: '2026-08-10 09:00:00'
        ),
        'lulus' => makeHistoryPayment(
            makeCategoryStudent(StudentStatus::Graduated->value, 'Siswa Kat Lulus', 'NIS-KAT-LULUS'),
            $bank,
            $user,
            'KWT-KAT-LULUS',
            '2026-08-11',
            createdAt: '2026-08-11 09:00:00'
        ),
        'pindah' => makeHistoryPayment(
            makeCategoryStudent(StudentStatus::Transferred->value, 'Siswa Kat Pindah', 'NIS-KAT-PINDAH'),
            $bank,
            $user,
            'KWT-KAT-PINDAH',
            '2026-08-12',
            createdAt: '2026-08-12 09:00:00'
        ),
        'prospective' => makeProspectiveHistoryPayment(
            ProspectiveStudent::factory()->create(['nama_lengkap' => 'Calon Kat Pross']),
            $bank,
            $user,
            'KWT-KAT-PROSP',
            '2026-08-13'
        ),
    ];
}

it('filter kategori riwayat default Semua menampilkan siswa dan calon siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $payments = seedCategoryHistory($user, $bank);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSet('studentCategory', '')
        ->assertSee('Semua')
        ->assertSee($payments['aktif']->receipt_number)
        ->assertSee($payments['lulus']->receipt_number)
        ->assertSee($payments['pindah']->receipt_number)
        ->assertSee($payments['prospective']->receipt_number);
});

it('kategori Siswa Aktif hanya menampilkan pembayaran siswa berstatus aktif', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $payments = seedCategoryHistory($user, $bank);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', StudentStatus::Active->value)
        ->assertSet('studentCategory', StudentStatus::Active->value)
        ->assertSee($payments['aktif']->receipt_number)
        ->assertDontSee($payments['lulus']->receipt_number)
        ->assertDontSee($payments['pindah']->receipt_number)
        ->assertDontSee($payments['prospective']->receipt_number);
});

it('kategori Calon Siswa hanya menampilkan pembayaran calon siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $payments = seedCategoryHistory($user, $bank);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', 'calon_siswa')
        ->assertSee($payments['prospective']->receipt_number)
        ->assertDontSee($payments['aktif']->receipt_number)
        ->assertDontSee($payments['lulus']->receipt_number)
        ->assertDontSee($payments['pindah']->receipt_number);
});

it('kategori Lulus hanya menampilkan pembayaran siswa berstatus lulus', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $payments = seedCategoryHistory($user, $bank);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', StudentStatus::Graduated->value)
        ->assertSee($payments['lulus']->receipt_number)
        ->assertDontSee($payments['aktif']->receipt_number)
        ->assertDontSee($payments['pindah']->receipt_number)
        ->assertDontSee($payments['prospective']->receipt_number);
});

it('kategori Pindah hanya menampilkan pembayaran siswa berstatus pindah', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $payments = seedCategoryHistory($user, $bank);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', StudentStatus::Transferred->value)
        ->assertSee($payments['pindah']->receipt_number)
        ->assertDontSee($payments['aktif']->receipt_number)
        ->assertDontSee($payments['lulus']->receipt_number)
        ->assertDontSee($payments['prospective']->receipt_number);
});

it('kategori tidak dikenal disamakan ke Semua', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $payments = seedCategoryHistory($user, $bank);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', 'kategori-ngawur')
        ->assertSet('studentCategory', '')
        ->assertSee($payments['aktif']->receipt_number)
        ->assertSee($payments['lulus']->receipt_number)
        ->assertSee($payments['pindah']->receipt_number)
        ->assertSee($payments['prospective']->receipt_number);

    expect(historyRows(['studentCategory' => 'kategori-ngawur'])->pluck('receiptNumber')->all())
        ->toEqualCanonicalizing([
            'KWT-KAT-AKTIF',
            'KWT-KAT-LULUS',
            'KWT-KAT-PINDAH',
            'KWT-KAT-PROSP',
        ]);
});

it('kategori tidak dikenal pada service dinormalkan tanpa membocorkan seluruh data', function () {
    $user = User::factory()->create();
    seedCategoryHistory($user, Bank::factory()->create());

    expect(historyRows(['studentCategory' => 'pindah'])->pluck('receiptNumber')->all())
        ->toBe(['KWT-KAT-PINDAH'])
        ->and(historyRows(['studentCategory' => 'tidak-ada'])->pluck('receiptNumber')->all())
        ->toHaveCount(4);
});

it('pembayaran calon siswa yang sudah dikonversi tetap berada pada kategori Calon Siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $prospect = ProspectiveStudent::factory()->create(['converted_student_id' => Student::factory()->create()->id]);
    $payment = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-KAT-PROSP-KONVERSI');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', 'calon_siswa')
        ->assertSee($payment->receipt_number);

    expect(historyRow('KWT-KAT-PROSP-KONVERSI', ['studentCategory' => 'calon_siswa'])->source)
        ->toBe('prospective');
});

it('mengubah filter kategori mengembalikan riwayat ke halaman pertama', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $student = makeCategoryStudent(StudentStatus::Active->value, 'Siswa Kat Paginasi', 'NIS-KAT-PAGINASI');

    for ($index = 1; $index <= 12; $index++) {
        makeHistoryPayment($student, $bank, $user, 'KWT-KAT-PAGE-'.$index, '2026-08-10', createdAt: sprintf('2026-08-10 09:%02d:00', $index));
    }

    $component = Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->call('setPage', 2);

    expect($component->get('paginators')['page'])->toBe(2);

    $component->set('studentCategory', StudentStatus::Active->value);

    expect($component->get('paginators')['page'])->toBe(1);
});

it('filter kategori tetap bisa digabung dengan filter bank, pencarian, dan tanggal', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'Bank Kategori']);
    $otherBank = Bank::factory()->create(['name' => 'Bank Lainnya']);
    $activeStudent = makeCategoryStudent(StudentStatus::Active->value, 'Siswa Gabung Aktif', 'NIS-GABUNG-AKTIF');
    $graduatedStudent = makeCategoryStudent(StudentStatus::Graduated->value, 'Siswa Gabung Lulus', 'NIS-GABUNG-LULUS');

    $wanted = makeHistoryPayment($activeStudent, $bank, $user, 'KWT-GABUNG-ADA', '2026-08-20', createdAt: '2026-08-20 09:00:00');
    makeHistoryPayment($activeStudent, $otherBank, $user, 'KWT-GABUNG-BANK-LAIN', '2026-08-20', createdAt: '2026-08-20 09:00:00');
    makeHistoryPayment($graduatedStudent, $bank, $user, 'KWT-GABUNG-LULUS', '2026-08-20', createdAt: '2026-08-20 09:00:00');
    makeHistoryPayment($activeStudent, $bank, $user, 'KWT-GABUNG-TANGGAL-LAMA', '2026-07-01', createdAt: '2026-07-01 09:00:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', StudentStatus::Active->value)
        ->set('bankId', (string) $bank->id)
        ->set('startDate', '2026-08-01')
        ->assertSee($wanted->receipt_number)
        ->assertDontSee('KWT-GABUNG-BANK-LAIN')
        ->assertDontSee('KWT-GABUNG-LULUS')
        ->assertDontSee('KWT-GABUNG-TANGGAL-LAMA');

    expect(historyRows([
        'studentCategory' => StudentStatus::Active->value,
        'bankId' => (string) $bank->id,
        'search' => 'Siswa Gabung',
        'startDate' => '2026-08-01',
    ])->pluck('receiptNumber')->all())->toBe(['KWT-GABUNG-ADA']);
});

// ---------------------------------------------------------------------------
// KOLOM TANGGAL INPUT (created_at, bukan payment_date)
// ---------------------------------------------------------------------------

it('kolom Tanggal Input memakai created_at dan bukan payment_date', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $payment = makeHistoryPayment(
        makeCategoryStudent(StudentStatus::Active->value, 'Siswa Tanggal Input', 'NIS-TANGGAL-INPUT'),
        $bank,
        $user,
        'KWT-TANGGAL-INPUT-1',
        '2026-08-26',
        createdAt: '2026-09-25 13:36:00'
    );

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Tanggal TF')
        ->assertSee('Tanggal Input')
        ->assertSee('25 Sep 2026')
        ->assertSee('13:36')
        ->assertSee(Carbon::parse('2026-08-26')->translatedFormat('d M Y'));

    expect(historyRow('KWT-TANGGAL-INPUT-1')->createdAt?->format('Y-m-d H:i'))->toBe('2026-09-25 13:36')
        ->and(historyRow('KWT-TANGGAL-INPUT-1')->paymentDate?->format('Y-m-d'))->toBe('2026-08-26');
});

it('Tanggal Input menumpuk tanggal di atas jam dan Tanggal TF tetap terpisah', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    makeHistoryPayment(
        makeCategoryStudent(StudentStatus::Active->value, 'Siswa Tumpukan Tanggal', 'NIS-TUMPUK-TANGGAL'),
        $bank,
        $user,
        'KWT-TANGGAL-TUMPUK',
        '2026-08-26',
        createdAt: '2026-09-25 13:36:00'
    );

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('<th class="py-3 px-4 whitespace-nowrap">Tanggal Input</th>')
        ->assertSeeHtml('<div>25 Sep 2026</div>')
        ->assertSeeHtml('<div class="text-body-sm">13:36</div>')
        ->assertSeeInOrder(['Tanggal TF', 'Tanggal Input', 'Nama & Kelas']);
});

it('Tanggal Input pembayaran calon siswa juga memakai created_at', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $payment = makeProspectiveHistoryPayment(
        ProspectiveStudent::factory()->create(['nama_lengkap' => 'Calon Tanggal Input']),
        $bank,
        $user,
        'KWT-TANGGAL-INPUT-PROSP'
    );
    setPaymentCreatedAt($payment, '2026-09-25 08:05:00');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', 'calon_siswa')
        ->assertSee('25 Sep 2026')
        ->assertSee('08:05');

    expect(historyRow('KWT-TANGGAL-INPUT-PROSP')->createdAt?->format('Y-m-d H:i'))->toBe('2026-09-25 08:05');
});

it('empty state riwayat memakai colspan sebelas kolom', function () {
    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee('Belum ada transaksi pembayaran.')
        ->assertSeeHtml('colspan="11"')
        ->assertDontSeeHtml('colspan="10"');

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', StudentStatus::Active->value)
        ->assertSee('Tidak ada transaksi yang sesuai filter.')
        ->assertSeeHtml('colspan="11"');
});

it('baris riwayat tanpa data pembayaran memakai tanda hubung pada Tanggal Input', function () {
    expect(historyPaginator()->total())->toBe(0);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSeeHtml('colspan="11"');
});

// ---------------------------------------------------------------------------
// KLASIFIKASI KATEGORI DARI STATUS AKADEMIK (konversi ke tahun ajaran mendatang)
// ---------------------------------------------------------------------------

/**
 * Siswa hasil konversi yang enrollment aktifnya hanya ada pada tahun ajaran yang
 * akan datang, pembayaran SPP-nya sudah tercatat pada tabel payments.
 *
 * @return array{student: Student, prospect: ProspectiveStudent, payment: Payment}
 */
function seedConvertedFutureStudent(
    User $user,
    Bank $bank,
    AcademicYear $targetYear,
    string $receiptNumber,
    string $nis = 'NIS-KONV-FUTURE'
): array {
    $schoolClass = SchoolClass::factory()->create();

    $prospect = ProspectiveStudent::factory()->converted()->create([
        'nama_lengkap' => 'Calon Konversi Mendatang '.$nis,
        'academic_year_id' => $targetYear->id,
        'school_class_id' => $schoolClass->id,
        'converted_at' => Carbon::parse('2026-08-05'),
    ]);

    $student = makeCategoryStudent(StudentStatus::Active->value, 'Siswa Konversi '.$nis, $nis);
    $student->forceFill(['class_id' => $schoolClass->id])->saveQuietly();
    $student->refresh();

    $prospect->forceFill(['converted_student_id' => $student->id])->saveQuietly();

    enrollWorkspaceStudent($student, $targetYear);

    return [
        'student' => $student,
        'prospect' => $prospect->refresh(),
        'payment' => makeHistoryPayment($student, $bank, $user, $receiptNumber, '2026-08-10', createdAt: '2026-08-10 09:00:00'),
    ];
}

/**
 * @return list<string>
 */
function historyCategoriesFor(string $receiptNumber): array
{
    return collect(['aktif', 'calon_siswa', 'lulus', 'pindah'])
        ->filter(fn (string $category): bool => historyRows(['studentCategory' => $category])
            ->contains('receiptNumber', $receiptNumber))
        ->values()
        ->all();
}

it('pembayaran siswa hasil konversi ke tahun ajaran mendatang muncul pada kategori Calon Siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $seed = seedConvertedFutureStudent($user, $bank, $targetYear, 'KWT-KONV-FUTURE-AKTIF');

    expect($seed['student']->fresh()->status->value)->toBe(StudentStatus::Active->value)
        ->and($seed['prospect']->converted_student_id)->toBe($seed['student']->id)
        ->and(historyRow($seed['payment']->receipt_number, ['studentCategory' => 'calon_siswa'])->source)->toBe('student')
        ->and(historyCategoriesFor($seed['payment']->receipt_number))->toBe(['calon_siswa']);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', 'calon_siswa')
        ->assertSee($seed['payment']->receipt_number);
});

it('pembayaran siswa konversi tahun mendatang tidak muncul pada kategori Siswa Aktif', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $seed = seedConvertedFutureStudent($user, $bank, $targetYear, 'KWT-KONV-FUTURE-BUKAN-AKTIF');

    expect(historyRows(['studentCategory' => 'aktif'])->pluck('receiptNumber')->all())->not->toContain($seed['payment']->receipt_number)
        ->and(historyRows(['studentCategory' => 'calon_siswa'])->pluck('receiptNumber')->all())->toContain($seed['payment']->receipt_number);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->set('studentCategory', 'aktif')
        ->assertDontSee($seed['payment']->receipt_number);
});

it('pembayaran konversi berpindah ke Siswa Aktif setelah tahun target menjadi tahun ajaran aktif', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $seed = seedConvertedFutureStudent($user, $bank, $targetYear, 'KWT-KONV-FUTURE-TRANSISI');

    expect(historyCategoriesFor($seed['payment']->receipt_number))->toBe(['calon_siswa']);

    workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', true);

    expect(AcademicYear::active()?->id)->toBe($targetYear->id)
        ->and(historyCategoriesFor($seed['payment']->receipt_number))->toBe(['aktif'])
        ->and(historyRow($seed['payment']->receipt_number, ['studentCategory' => 'aktif'])->source)->toBe('student')
        ->and(historyRows(['studentCategory' => 'calon_siswa'])->pluck('receiptNumber')->all())->not->toContain($seed['payment']->receipt_number);
});

it('link converted_student_id yang masih ada tidak menahan siswa pada kategori Calon Siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $currentYear = workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $seed = seedConvertedFutureStudent($user, $bank, $currentYear, 'KWT-KONV-LINK-TAHAN');
    $student = $seed['student'];

    expect($student->enrollments()->where('academic_year_id', $currentYear->id)->update(['status' => 'active']))->toBe(1);

    expect($seed['prospect']->converted_student_id)->toBe($student->id)
        ->and(historyCategoriesFor($seed['payment']->receipt_number))->toBe(['aktif']);
});

it('kategori Lulus tetap memakai status stored walau ada enrollment masa depan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $student = makeCategoryStudent(StudentStatus::Graduated->value, 'Siswa Lulus Masa Depan', 'NIS-LULUS-DEPAN');
    enrollWorkspaceStudent($student, $targetYear);
    $payment = makeHistoryPayment($student, $bank, $user, 'KWT-KONV-LULUS', '2026-08-10');

    expect(historyCategoriesFor($payment->receipt_number))->toBe(['lulus']);
});

it('kategori Pindah tetap memakai status stored walau ada enrollment masa depan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $student = makeCategoryStudent(StudentStatus::Transferred->value, 'Siswa Pindah Masa Depan', 'NIS-PINDAH-DEPAN');
    enrollWorkspaceStudent($student, $targetYear);
    $payment = makeHistoryPayment($student, $bank, $user, 'KWT-KONV-PINDAH', '2026-08-10');

    expect(historyCategoriesFor($payment->receipt_number))->toBe(['pindah']);
});

it('pembayaran calon siswa yang belum dikonversi tetap Calon Siswa tanpa enrollment', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $prospect = ProspectiveStudent::factory()->create(['converted_student_id' => null]);
    $payment = makeProspectiveHistoryPayment($prospect, $bank, $user, 'KWT-KONV-BELUM-DIKONVERSI');

    expect(historyCategoriesFor($payment->receipt_number))->toBe(['calon_siswa'])
        ->and(historyRow($payment->receipt_number, ['studentCategory' => 'calon_siswa'])->source)->toBe('prospective');
});

it('kategori Semua tetap menggabungkan pembayaran siswa dan calon siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    $currentYear = workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $future = seedConvertedFutureStudent($user, $bank, $targetYear, 'KWT-SEMUA-FUTURE');
    $currentStudent = makeCategoryStudent(StudentStatus::Active->value, 'Siswa Semua Aktif', 'NIS-SEMUA-AKTIF');
    enrollWorkspaceStudent($currentStudent, $currentYear);
    $currentPayment = makeHistoryPayment($currentStudent, $bank, $user, 'KWT-SEMUA-AKTIF', '2026-08-10');
    $prospectPayment = makeProspectiveHistoryPayment(
        ProspectiveStudent::factory()->create(),
        $bank,
        $user,
        'KWT-SEMUA-PROSP'
    );

    $rows = historyRows(['studentCategory' => '']);

    expect($rows->pluck('receiptNumber')->all())->toContain($future['payment']->receipt_number)
        ->and($rows->pluck('receiptNumber')->all())->toContain($currentPayment->receipt_number)
        ->and($rows->pluck('receiptNumber')->all())->toContain($prospectPayment->receipt_number)
        ->and($rows->pluck('source')->all())->toContain('student')
        ->and($rows->pluck('source')->all())->toContain('prospective');
});

it('siswa aktif lama tanpa enrollment tetap masuk kategori Siswa Aktif', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $student = makeCategoryStudent(StudentStatus::Active->value, 'Siswa Legacy Tanpa Enrollment', 'NIS-LEGACY');
    $payment = makeHistoryPayment($student, $bank, $user, 'KWT-LEGACY-TANPA-ENROLLMENT', '2026-08-10');

    expect($student->enrollments()->count())->toBe(0)
        ->and($student->academicStatus())->toBe(StudentStatus::Active->value)
        ->and(historyCategoriesFor($payment->receipt_number))->toBe(['aktif']);
});

it('enrollment tahun berjalan non-aktif dengan enrollment masa depan aktif masuk Calon Siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    $currentYear = workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $student = makeCategoryStudent(StudentStatus::Active->value, 'Siswa Planned Tahun Berjalan', 'NIS-PLANNED');
    enrollWorkspaceStudent($student, $currentYear, 'planned');
    enrollWorkspaceStudent($student, $targetYear);
    $payment = makeHistoryPayment($student, $bank, $user, 'KWT-KONV-PLANNED', '2026-08-10');

    expect($student->academicStatus())->toBe('calon_siswa')
        ->and(historyCategoriesFor($payment->receipt_number))->toBe(['calon_siswa']);
});

it('tanpa tahun ajaran aktif tidak ada error dan kategori memakai status stored', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $inactiveYear = workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', false);

    expect(AcademicYear::active())->toBeNull();

    $activeStudent = makeCategoryStudent(StudentStatus::Active->value, 'Siswa Tanpa Tahun Aktif', 'NIS-NO-AY-AKTIF');
    $activePayment = makeHistoryPayment($activeStudent, $bank, $user, 'KWT-NO-AY-AKTIF', '2026-08-10');
    $graduatedPayment = makeHistoryPayment(
        makeCategoryStudent(StudentStatus::Graduated->value, 'Siswa Tanpa Tahun Aktif Lulus', 'NIS-NO-AY-LULUS'),
        $bank,
        $user,
        'KWT-NO-AY-LULUS',
        '2026-08-11'
    );
    $prospectPayment = makeProspectiveHistoryPayment(
        ProspectiveStudent::factory()->create(['academic_year_id' => $inactiveYear->id]),
        $bank,
        $user,
        'KWT-NO-AY-PROSP'
    );

    $futureSeed = seedConvertedFutureStudent($user, $bank, $inactiveYear, 'KWT-NO-AY-FUTURE');

    expect(historyCategoriesFor($activePayment->receipt_number))->toBe(['aktif'])
        ->and(historyCategoriesFor($graduatedPayment->receipt_number))->toBe(['lulus'])
        ->and(historyCategoriesFor($prospectPayment->receipt_number))->toBe(['calon_siswa'])
        ->and(historyCategoriesFor($futureSeed['payment']->receipt_number))->toBe(['aktif'])
        ->and(historyRows(['studentCategory' => '']))->not->toBeEmpty();
});

it('klasifikasi SQL kategori riwayat sama dengan Student::academicStatus()', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    $currentYear = workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $activeStudent = makeCategoryStudent(StudentStatus::Active->value, 'Parity Aktif', 'NIS-PARITY-AKTIF');
    enrollWorkspaceStudent($activeStudent, $currentYear);
    $activePayment = makeHistoryPayment($activeStudent, $bank, $user, 'KWT-PARITY-AKTIF', '2026-08-10');

    $futureStudent = makeCategoryStudent(StudentStatus::Active->value, 'Parity Calon', 'NIS-PARITY-CALON');
    enrollWorkspaceStudent($futureStudent, $targetYear);
    $futurePayment = makeHistoryPayment($futureStudent, $bank, $user, 'KWT-PARITY-CALON', '2026-08-11');

    $graduatedStudent = makeCategoryStudent(StudentStatus::Graduated->value, 'Parity Lulus', 'NIS-PARITY-LULUS');
    $graduatedPayment = makeHistoryPayment($graduatedStudent, $bank, $user, 'KWT-PARITY-LULUS', '2026-08-12');

    $transferredStudent = makeCategoryStudent(StudentStatus::Transferred->value, 'Parity Pindah', 'NIS-PARITY-PINDAH');
    $transferredPayment = makeHistoryPayment($transferredStudent, $bank, $user, 'KWT-PARITY-PINDAH', '2026-08-13');

    expect($activeStudent->academicStatus())->toBe('aktif')
        ->and($futureStudent->academicStatus())->toBe('calon_siswa')
        ->and($graduatedStudent->academicStatus())->toBe('lulus')
        ->and($transferredStudent->academicStatus())->toBe('pindah')
        ->and(historyCategoriesFor($activePayment->receipt_number))->toBe([$activeStudent->academicStatus()])
        ->and(historyCategoriesFor($futurePayment->receipt_number))->toBe([$futureStudent->academicStatus()])
        ->and(historyCategoriesFor($graduatedPayment->receipt_number))->toBe([$graduatedStudent->academicStatus()])
        ->and(historyCategoriesFor($transferredPayment->receipt_number))->toBe([$transferredStudent->academicStatus()]);
});

it('klasifikasi kategori riwayat tidak memicu query tambahan per baris', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $targetYear = workspaceAcademicYear('2027/2028', '2027-07-01', '2028-06-30', false);
    workspaceAcademicYear('2026/2027', '2026-07-01', '2027-06-30', true);

    $seed = seedConvertedFutureStudent($user, $bank, $targetYear, 'KWT-N1-1');
    $prospectPayment = makeProspectiveHistoryPayment(
        ProspectiveStudent::factory()->create(),
        $bank,
        $user,
        'KWT-N1-PROSP'
    );

    $oneRow = measureHistoryQueries(fn () => historyRows(['perPage' => 50, 'studentCategory' => 'calon_siswa']));

    $addRows = function (int $from, int $to) use ($seed, $bank, $user): void {
        for ($index = $from; $index <= $to; $index++) {
            makeHistoryPayment(
                $seed['student'],
                $bank,
                $user,
                'KWT-N1-'.$index,
                '2026-08-10',
                createdAt: sprintf('2026-08-10 10:%02d:00', $index)
            );
        }
    };

    $addRows(2, 20);
    $manyRows = measureHistoryQueries(fn () => historyRows(['perPage' => 50, 'studentCategory' => 'calon_siswa']));

    expect(historyRows(['perPage' => 50, 'studentCategory' => 'calon_siswa']))->toHaveCount(21)
        ->and($oneRow['lazy'])->toBe(0)
        ->and($manyRows['lazy'])->toBe(0)
        ->and($manyRows['total'] - $oneRow['total'])->toBeLessThanOrEqual(1)
        ->and($prospectPayment->receipt_number)->toBeString();
});
