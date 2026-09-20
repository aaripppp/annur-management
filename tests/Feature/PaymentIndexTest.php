<?php

use App\Livewire\Dashboard;
use App\Livewire\PaymentIndex;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
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
        ->assertSeeHtml('wire:click="confirmDelete('.$activePayment->id.')"')
        ->assertSeeHtml('wire:click="confirmDelete('.$cancelledPayment->id.')"')
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
