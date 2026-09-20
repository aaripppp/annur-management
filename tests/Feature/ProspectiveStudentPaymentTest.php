<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentIndex;
use App\Livewire\ProspectivePaymentCreate;
use App\Livewire\ProspectivePaymentShow;
use App\Livewire\ProspectiveStudentDetail;
use App\Livewire\ProspectiveStudentManagement;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\ProspectiveStudentBillGenerationService;
use App\Services\ProspectiveStudentReceiptNumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Livewire\Livewire;

/**
 * Membuat calon siswa SMP level 8 dengan satu tagihan Formulir Pendaftaran.
 *
 * @return array{0: PaymentType, 1: ProspectiveStudent, 2: ProspectiveStudentBill}
 */
function makeProspectivePaymentFixture(int $amount = 350000): array
{
    $type = PaymentType::create([
        'name' => 'Formulir Pendaftaran Test',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $type->id,
        'class_level' => 8,
        'amount' => $amount,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    $class = SchoolClass::factory()->create(['level' => 8]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    $ps = ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $class->id,
        'nama_lengkap' => 'Siti Calon Berbayar',
        'no_telp_orang_tua' => '081234567890',
    ]);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);
    $bill = $ps->fresh()->bills->first();

    return [$type, $ps, $bill];
}

/**
 * Menukar fasad Pdf dengan renderer palsu untuk memeriksa data kwitansi.
 */
function fakeProspectiveReceiptPrintRenderer(): object
{
    $renderer = new class
    {
        public string $view = '';

        /** @var list<array<string, mixed>> */
        public array $receipts = [];

        /** @param array<string, mixed> $data */
        public function loadView(string $view, array $data): self
        {
            $this->view = $view;
            $this->receipts[] = $data['receipt'];

            return $this;
        }

        /** @param array<string, mixed> $options */
        public function setOption(array $options): self
        {
            return $this;
        }

        /** @param list<int|float> $paper */
        public function setPaper(array $paper): self
        {
            return $this;
        }

        public function stream(string $filename): Response
        {
            return response('PDF', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename='.$filename,
            ]);
        }

        public function download(string $filename): Response
        {
            return response('PDF', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename='.$filename,
            ]);
        }
    };

    Pdf::swap($renderer);

    return $renderer;
}

afterEach(function () {
    $this->app->forgetInstance('dompdf.wrapper');
    Pdf::clearResolvedInstances();
});

it('nomor kwitansi calon siswa berurutan dan unik per tahun', function () {
    $generator = app(ProspectiveStudentReceiptNumberGenerator::class);

    expect($generator->next(2026))->toBe('KWT-REG-2026-000001')
        ->and($generator->next(2026))->toBe('KWT-REG-2026-000002')
        ->and($generator->next(2027))->toBe('KWT-REG-2027-000001');
});

it('ProspectiveStudentPayment mencatat transaksi dengan relasi lengkap', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    $ps = ProspectiveStudent::factory()->create();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'bank_id' => $bank->id,
        'created_by' => $user->id,
    ]);

    expect($ps->payments)->toHaveCount(1)
        ->and($payment->prospectiveStudent->is($ps))->toBeTrue()
        ->and($payment->bank->is($bank))->toBeTrue()
        ->and($payment->creator->is($user))->toBeTrue()
        ->and($payment->status)->toBe(ProspectiveStudentPayment::STATUS_ACTIVE)
        ->and($payment->isActive())->toBeTrue();
});

it('ProspectiveStudentPaymentDetail merujuk bill dan payment type', function () {
    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create(['prospective_student_id' => $ps->id]);

    $detail = ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    expect($payment->details)->toHaveCount(1)
        ->and($detail->payment->is($payment))->toBeTrue()
        ->and($detail->bill->is($bill))->toBeTrue()
        ->and($detail->paymentType->is($type))->toBeTrue()
        ->and($bill->paymentDetails)->toHaveCount(1);
});

it('tagihan baru memiliki status belum bayar', function () {
    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    expect($bill->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID)
        ->and($bill->status_label)->toBe('Belum Bayar')
        ->and($bill->paid_amount)->toBe(0.0)
        ->and($bill->remaining_amount)->toBe(350000.0)
        ->and($bill->isSettled())->toBeFalse()
        ->and($bill->isUnpaid())->toBeTrue();
});

it('pembayaran lunas menandai tagihan lunas', function () {
    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    $bill->refresh();

    expect($bill->paid_amount)->toBe(350000.0)
        ->and($bill->remaining_amount)->toBe(0.0)
        ->and($bill->status)->toBe(ProspectiveStudentBill::STATUS_PAID)
        ->and($bill->status_label)->toBe('Lunas')
        ->and($bill->isSettled())->toBeTrue();
});

it('pembayaran sebagian menandai tagihan sebagian', function () {
    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 200000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 200000,
    ]);

    $bill->refresh();

    expect($bill->paid_amount)->toBe(200000.0)
        ->and($bill->remaining_amount)->toBe(150000.0)
        ->and($bill->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL)
        ->and($bill->status_label)->toBe('Sebagian')
        ->and($bill->isSettled())->toBeFalse();
});

it('pembayaran yang dibatalkan tidak lagi dihitung sebagai pembayaran tagihan', function () {
    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->cancelled()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    $bill->refresh();

    expect($payment->isCancelled())->toBeTrue()
        ->and($payment->status_label)->toBe('Dibatalkan')
        ->and($bill->paid_amount)->toBe(0.0)
        ->and($bill->remaining_amount)->toBe(350000.0)
        ->and($bill->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID);
});

it('pencarian di halaman Pembayaran menemukan calon siswa terdaftar', function () {
    $class = SchoolClass::factory()->create(['level' => 8]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Arif Calon Bayar',
        'registration_number' => 'REG-2027-000321',
        'school_class_id' => $class->id,
        'academic_year_id' => $academicYear->id,
    ]);

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Arif')
        ->assertSee('Arif Calon Bayar')
        ->assertSee('Calon Siswa')
        ->assertSee('REG-2027-000321');
});

it('pencarian pembayaran hanya menampilkan calon siswa yang masih terdaftar', function () {
    ProspectiveStudent::factory()->converted()->create(['nama_lengkap' => 'Beta Konversi']);
    ProspectiveStudent::factory()->cancelled()->create(['nama_lengkap' => 'Gamma Batal']);

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Beta')
        ->assertDontSee('Beta Konversi');

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Gamma')
        ->assertDontSee('Gamma Batal');
});

it('pencarian pembayaran menemukan siswa dan calon siswa secara bersamaan', function () {
    $class = SchoolClass::factory()->create(['level' => 8]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    Student::factory()->create(['nama_lengkap' => 'Dewi Siswa Bayar', 'nis' => '12345', 'class_id' => $class->id]);
    ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Dewi Calon Bayar',
        'school_class_id' => $class->id,
        'academic_year_id' => $academicYear->id,
    ]);

    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Dewi')
        ->assertSee('Dewi Siswa Bayar')
        ->assertSee('Dewi Calon Bayar')
        ->assertSee('Calon Siswa');
});

it('menampilkan tagihan pendaftaran yang belum lunas di halaman pembayaran calon siswa', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $component = Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps]);

    $outstanding = $component->get('outstandingBills');

    expect($outstanding)->toHaveCount(1)
        ->and($outstanding[0]['id'])->toBe($bill->id)
        ->and($outstanding[0]['remaining_amount'])->toBe(350000.0);
});

it('mencatat pembayaran calon siswa lengkap dengan detail dan nomor kwitansi', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'Bank Test', 'is_active' => true]);
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-10')
        ->call('save')
        ->assertHasNoErrors();

    $payment = ProspectiveStudentPayment::query()->sole();

    expect($payment->receipt_number)->toStartWith('KWT-REG-')
        ->and($payment->bank_id)->toBe($bank->id)
        ->and($payment->payment_date->format('Y-m-d'))->toBe('2026-09-10')
        ->and($payment->total_amount)->toBe('350000.00')
        ->and($payment->created_by)->toBe($user->id)
        ->and($payment->status)->toBe(ProspectiveStudentPayment::STATUS_ACTIVE)
        ->and($payment->details)->toHaveCount(1)
        ->and($payment->details->first()->prospective_student_bill_id)->toBe($bill->id)
        ->and($payment->details->first()->payment_type_id)->toBe($type->id)
        ->and($payment->details->first()->amount)->toBe('350000.00')
        ->and($bill->fresh()->isSettled())->toBeTrue();
});

it('mendukung pembayaran sebagian pada tagihan pendaftaran', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts', [$bill->id => 200000])
        ->set('bank_id', $bank->id)
        ->call('save')
        ->assertHasNoErrors();

    $payment = ProspectiveStudentPayment::query()->sole();

    expect($payment->total_amount)->toBe('200000.00')
        ->and($payment->details->first()->amount)->toBe('200000.00')
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL)
        ->and($bill->fresh()->remaining_amount)->toBe(150000.0);
});

it('menolak pembayaran melebihi sisa tagihan', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts', [$bill->id => 400000])
        ->set('bank_id', $bank->id)
        ->call('save')
        ->assertHasErrors(['selectedBillAmounts.'.$bill->id]);

    expect(ProspectiveStudentPayment::query()->count())->toBe(0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID);
});

it('menolak nominal pembayaran nol atau negatif', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$bill->id])
        ->set('selectedBillAmounts', [$bill->id => 0])
        ->set('bank_id', $bank->id)
        ->call('save')
        ->assertHasErrors(['selectedBillAmounts.'.$bill->id]);

    expect(ProspectiveStudentPayment::query()->count())->toBe(0);
});

it('menolak tagihan milik calon siswa lain', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();
    [$otherType, $otherPs, $otherBill] = makeProspectivePaymentFixture();

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$otherBill->id])
        ->set('bank_id', $bank->id)
        ->call('save')
        ->assertHasErrors(['selectedBillIds']);

    expect(ProspectiveStudentPayment::query()->count())->toBe(0);
});

it('menggunakan rekening bank yang sudah ada tanpa membuat bank baru', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create(['name' => 'Bank Rekening Sama', 'is_active' => true]);
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $banksBefore = Bank::count();

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Bank::count())->toBe($banksBefore)
        ->and(ProspectiveStudentPayment::query()->sole()->bank_id)->toBe($bank->id);
});

it('menyimpan pembayaran calon siswa tanpa membuat data siswa', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $studentsBefore = Student::count();
    $studentBillsBefore = StudentBill::count();
    $studentPaymentsBefore = Payment::count();

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$bill->id])
        ->set('bank_id', $bank->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Student::count())->toBe($studentsBefore)
        ->and(StudentBill::count())->toBe($studentBillsBefore)
        ->and(Payment::count())->toBe($studentPaymentsBefore);
});

it('halaman detail pembayaran menampilkan identitas pendaftaran calon siswa', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertSee($payment->receipt_number)
        ->assertSee('No. Pendaftaran')
        ->assertSee($ps->registration_number)
        ->assertSee('Kelas Tujuan '.$ps->schoolClass->name)
        ->assertSee('Tahun Ajaran Tujuan '.$ps->academicYear->year)
        ->assertDontSee('NIS');
});

it('kwitansi PDF calon siswa memakai nomor pendaftaran bukan NIS', function () {
    $creator = User::factory()->create(['name' => 'Admin Kwitansi']);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'bank_id' => Bank::factory()->create(['name' => 'Bank PDF', 'is_active' => true])->id,
        'payment_date' => '2026-09-15',
        'total_amount' => 350000,
        'created_by' => $creator->id,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    $renderer = fakeProspectiveReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.prospective.print', $payment))
        ->assertOk();

    $receipt = $renderer->receipts[0];

    expect($receipt['category'])->toBe('Calon Siswa')
        ->and($receipt['identityName'])->toBe($ps->nama_lengkap)
        ->and($receipt['identityContext'])->toContain('No. Pendaftaran '.$ps->registration_number)
        ->and($receipt['identityContext'])->toContain('Kelas Tujuan '.$ps->schoolClass->name)
        ->and($receipt['identityContext'])->toContain('TA '.$ps->academicYear->year)
        ->and($receipt['identityContext'])->not->toContain('NIS');

    $html = view('receipts.pdf', ['receipt' => $receipt])->render();

    expect($html)->toContain('Calon Siswa')
        ->and($html)->not->toContain('NIS');
});

it('halaman detail calon siswa menampilkan riwayat pembayaran', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $ps])
        ->assertSee('Riwayat Pembayaran')
        ->assertSee($payment->receipt_number)
        ->assertSee('Kelas Tujuan')
        ->assertSee($ps->schoolClass->name);
});

it('halaman detail calon siswa menampilkan pesan kosong saat belum ada pembayaran', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $ps])
        ->assertSee('Riwayat Pembayaran')
        ->assertSee('Belum ada riwayat pembayaran');
});

it('calon siswa dengan riwayat pembayaran tidak dapat dihapus permanen', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $ps->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false);

    expect(ProspectiveStudent::query()->whereKey($ps->id)->exists())->toBeTrue()
        ->and($bill->fresh())->not->toBeNull()
        ->and($ps->fresh()->status->value)->toBe(ProspectiveStudent::STATUS_REGISTERED)
        ->and(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeTrue();
});

it('pembatalan pembayaran mencatat metadata dan mengembalikan saldo tagihan', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->call('openCancelModal')
        ->set('cancelReason', 'Terduplikasi, sudah dibayar lewat jalur lain')
        ->call('cancelPayment')
        ->assertHasNoErrors()
        ->assertSet('showCancelModal', false);

    $payment->refresh();

    expect($payment->status)->toBe(ProspectiveStudentPayment::STATUS_CANCELLED)
        ->and($payment->cancelled_by)->toBe($user->id)
        ->and($payment->cancellation_reason)->toBe('Terduplikasi, sudah dibayar lewat jalur lain')
        ->and($payment->cancelled_at)->not->toBeNull()
        ->and($payment->status_label)->toBe('Dibatalkan')
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID)
        ->and($bill->fresh()->remaining_amount)->toBe(350000.0);
});

it('pembatalan menolak alasan kosong atau terlalu pendek', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->call('openCancelModal')
        ->set('cancelReason', '')
        ->call('cancelPayment')
        ->assertHasErrors(['cancelReason']);

    expect($payment->fresh()->status)->toBe(ProspectiveStudentPayment::STATUS_ACTIVE);
});

it('pembayaran yang sudah dibatalkan menampilkan alasan di halaman detail', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspectivePaymentFixture();

    $payment = ProspectiveStudentPayment::factory()->cancelled()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 350000,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Pembayaran ditolak sekolah',
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 350000,
    ]);

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertSee('Dibatalkan')
        ->assertSee('Pembayaran ditolak sekolah')
        ->assertSee($user->name);
});
