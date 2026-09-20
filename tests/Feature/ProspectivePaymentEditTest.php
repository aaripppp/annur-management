<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectivePaymentEdit;
use App\Livewire\ProspectivePaymentShow;
use App\Livewire\ProspectivePaymentWorkspace;
use App\Livewire\ProspectiveStudentDetail;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ProspectiveStudentBillGenerationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Membuat calon siswa dengan satu tagihan Formulir Pendaftaran.
 *
 * @return array{0: PaymentType, 1: ProspectiveStudent, 2: ProspectiveStudentBill}
 */
function makeProspEditFixture(int $amount = 400000, string $name = 'Formulir Pendaftaran Edit'): array
{
    $type = PaymentType::create([
        'name' => $name,
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
        'nama_lengkap' => 'Siti Calon Edit',
        'no_telp_orang_tua' => '081234567890',
    ]);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);
    $bill = $ps->fresh()->bills->first();

    return [$type, $ps, $bill];
}

/**
 * Menambah tagihan kedua untuk calon siswa yang sama.
 *
 * @return array{0: PaymentType, 1: ProspectiveStudentBill}
 */
function makeProspEditExtraBill(ProspectiveStudent $ps, int $amount = 250000, string $name = 'Uang Gedung Edit'): array
{
    $type = PaymentType::create([
        'name' => $name,
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
    ]);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);
    $bill = $ps->fresh()->bills->first(fn (ProspectiveStudentBill $b) => $b->payment_type_id === $type->id);

    return [$type, $bill];
}

/**
 * Membuat pembayaran aktif dengan detail alokasi [bill_id => nominal].
 *
 * @param  array<int, int>  $allocations
 * @param  array<string, mixed>  $overrides
 */
function createProspEditPayment(ProspectiveStudent $ps, array $allocations, array $overrides = []): ProspectiveStudentPayment
{
    $bank = Bank::factory()->create(['name' => 'Bank Edit', 'is_active' => true]);
    $user = User::factory()->create();
    $total = (float) array_sum($allocations);

    $payment = ProspectiveStudentPayment::create([
        'receipt_number' => $overrides['receipt_number'] ?? 'KWT-REG-2027-001001',
        'prospective_student_id' => $ps->id,
        'bank_id' => $overrides['bank_id'] ?? $bank->id,
        'payment_date' => $overrides['payment_date'] ?? '2026-09-10',
        'total_amount' => $total,
        'description' => $overrides['description'] ?? 'Pembayaran pendaftaran',
        'created_by' => $user->id,
    ]);

    foreach ($allocations as $billId => $amount) {
        $bill = ProspectiveStudentBill::query()->find($billId);

        ProspectiveStudentPaymentDetail::create([
            'prospective_student_payment_id' => $payment->id,
            'prospective_student_bill_id' => $billId,
            'payment_type_id' => $bill->payment_type_id,
            'amount' => $amount,
            'description' => 'Tahun Ajaran 2027/2028',
        ]);
    }

    return $payment;
}

/**
 * Renderer HTML tersendiri (bukan PDF) untuk memeriksa data kwitansi calon siswa.
 */
function swapProspEditPrintRenderer(): object
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

// -------------------------------------------------------------------
// Riwayat — Aksi (mirror gaya ikon student)
// -------------------------------------------------------------------

it('riwayat workspace menampilkan ikon View ke halaman detail', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee('Riwayat Pembayaran')
        ->assertSeeHtml('title="Lihat Detail / Cetak Kwitansi"')
        ->assertSee(route('pembayaran.prospective.show', $payment->id), false);
});

it('riwayat workspace menampilkan ikon Edit untuk pembayaran aktif', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSeeHtml('title="Edit Pembayaran"')
        ->assertSee(route('pembayaran.prospective.edit', $payment->id), false);
});

it('riwayat workspace menampilkan ikon Delete untuk transaksi', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSeeHtml('title="Hapus Transaksi"')
        ->assertSeeHtml('wire:click="confirmDeletePayment('.$payment->id.')"');
});

it('riwayat menyembunyikan ikon Edit untuk pembayaran yang dibatalkan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = ProspectiveStudentPayment::factory()->cancelled()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 400000,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 400000,
    ]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertDontSeeHtml('title="Edit Pembayaran"')
        ->assertSeeHtml('title="Hapus Transaksi"');
});

it('halaman detail calon siswa menampilkan aksi ikon yang sama', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $ps])
        ->assertSeeHtml('title="Lihat Detail / Cetak Kwitansi"')
        ->assertSeeHtml('title="Edit Pembayaran"')
        ->assertSeeHtml('title="Hapus Transaksi"')
        ->assertSeeHtml('wire:click="confirmDeletePayment('.$payment->id.')"');
});

// -------------------------------------------------------------------
// Detail — Batalkan dihapus, Edit ditambahkan
// -------------------------------------------------------------------

it('halaman detail calon siswa tidak lagi menampilkan Batalkan Pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertDontSee('Batalkan Pembayaran')
        ->assertDontSeeHtml('wire:click="openCancelModal"');
});

it('halaman detail menampilkan Edit Pembayaran, Cetak Kwitansi, dan Download PDF', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertSee('Edit Pembayaran')
        ->assertSee(route('pembayaran.prospective.edit', $payment->id), false)
        ->assertSee('Cetak Kwitansi')
        ->assertSee('Download PDF');
});

it('halaman detail tidak menampilkan Edit Pembayaran untuk pembayaran dibatalkan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = ProspectiveStudentPayment::factory()->cancelled()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 400000,
    ]);

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertDontSee('Edit Pembayaran')
        ->assertDontSee('Batalkan Pembayaran');
});

// -------------------------------------------------------------------
// Edit — Pemuatan data
// -------------------------------------------------------------------

it('halaman edit mengisi data pembayaran dan alokasi tagihan yang ada', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->assertStatus(200)
        ->assertSee('Edit Pembayaran')
        ->assertSet('bank_id', $payment->bank_id)
        ->assertSet('payment_date', '2026-09-10')
        ->assertSet('selectedBillIds', [$bill->id])
        ->assertSet('selectedBillAmounts.'.$bill->id, 400000);
});

it('halaman edit menampilkan identitas calon siswa tanpa NIS', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->assertSee('Informasi Calon Siswa')
        ->assertSee($ps->nama_lengkap)
        ->assertSee('No. Pendaftaran')
        ->assertSee('Kelas Tujuan')
        ->assertSee('Tahun Ajaran Tujuan')
        ->assertDontSee('NIS');
});

it('halaman edit menolak pembayaran yang dibatalkan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = ProspectiveStudentPayment::factory()->cancelled()->create([
        'prospective_student_id' => $ps->id,
    ]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->assertStatus(403);
});

it('rute edit dapat dimuat lewat HTTP dengan model binding', function () {
    $user = User::factory()->create();
    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.edit', ['payment' => $payment->id]))
        ->assertOk()
        ->assertSeeLivewire(ProspectivePaymentEdit::class);
});

// -------------------------------------------------------------------
// Edit — Perilaku
// -------------------------------------------------------------------

it('dapat mengubah bank pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);
    $newBank = Bank::factory()->cash()->create();

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('bank_id', $newBank->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($payment->fresh()->bank_id)->toBe($newBank->id);
});

it('dapat mengubah tanggal pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture();
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('payment_date', '2026-11-15')
        ->call('save')
        ->assertHasNoErrors();

    expect($payment->fresh()->payment_date->format('Y-m-d'))->toBe('2026-11-15');
});

it('dapat mengubah nominal dan total_amount dihitung ulang', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('selectedBillAmounts.'.$bill->id, 300000)
        ->call('save');

    expect($payment->fresh()->total_amount)->toBe('300000.00')
        ->and((float) $payment->fresh()->details()->sum('amount'))->toBe(300000.0)
        ->and($bill->fresh()->paid_amount)->toBe(300000.0);
});

it('dapat menghapus satu tagihan ketika tagihan lain masih dipilih', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    [$type2, $bill2] = makeProspEditExtraBill($ps);
    $payment = createProspEditPayment($ps, [$bill->id => 400000, $bill2->id => 250000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->call('removeBill', $bill2->id)
        ->call('save')
        ->assertHasNoErrors();

    $payment->refresh();

    expect($payment->details()->count())->toBe(1)
        ->and($payment->details()->first()->prospective_student_bill_id)->toBe($bill->id)
        ->and($payment->total_amount)->toBe('400000.00')
        ->and($bill2->fresh()->remaining_amount)->toBe(250000.0);
});

it('menolak nominal melebihi sisa tagihan ditambah alokasi pembayaran saat ini', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->assertSet('selectedBillAmounts.'.$bill->id, 400000)
        ->set('selectedBillAmounts.'.$bill->id, 500000)
        ->call('save')
        ->assertHasErrors();

    expect($payment->fresh()->total_amount)->toBe('400000.00')
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);
});

it('mengizinkan nominal hingga batas sisa plus alokasi saat ini pada pembayaran sebagian', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 200000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('selectedBillAmounts.'.$bill->id, 400000)
        ->call('save')
        ->assertHasNoErrors();

    expect($payment->fresh()->total_amount)->toBe('400000.00')
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);
});

it('menolak tagihan milik calon siswa lain', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    [$otherType, $otherPs, $otherBill] = makeProspEditFixture(400000, 'Formulir Pendaftaran Lain');
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('selectedBillIds', [$bill->id, $otherBill->id])
        ->set('selectedBillAmounts', [$bill->id => 400000, $otherBill->id => 100000])
        ->call('save')
        ->assertHasErrors(['selectedBillIds']);

    expect($payment->fresh()->total_amount)->toBe('400000.00')
        ->and($payment->fresh()->details()->count())->toBe(1);
});

it('addBill menolak tagihan milik calon siswa lain', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    [$otherType, $otherPs, $otherBill] = makeProspEditFixture(400000, 'Formulir Pendaftaran Lain');
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->call('addBill', $otherBill->id)
        ->assertSet('selectedBillIds', [$bill->id]);
});

it('menjaga nomor kwitansi dan pembuat transaksi tidak berubah saat edit', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000], ['receipt_number' => 'KWT-REG-2027-000777']);
    $originalCreator = $payment->created_by;
    $originalReceipt = $payment->receipt_number;

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('selectedBillAmounts.'.$bill->id, 350000)
        ->set('description', 'Revisi nominal')
        ->call('save')
        ->assertHasNoErrors();

    $payment->refresh();

    expect($payment->receipt_number)->toBe($originalReceipt)
        ->and($payment->created_by)->toBe($originalCreator)
        ->and($payment->description)->toBe('Revisi nominal');
});

it('mengalihkan ke halaman detail setelah edit berhasil', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('pembayaran.prospective.show', $payment->id));
});

// -------------------------------------------------------------------
// Settlement — penerjemahan status tagihan
// -------------------------------------------------------------------

it('edit dari lunas menjadi sebagian menandai tagihan Sebagian', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('selectedBillAmounts.'.$bill->id, 250000)
        ->call('save')
        ->assertHasNoErrors();

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL)
        ->and($bill->fresh()->status_label)->toBe('Sebagian')
        ->and($bill->fresh()->remaining_amount)->toBe(150000.0);
});

it('edit dari sebagian menjadi lunas menandai tagihan Lunas', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 250000]);

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('selectedBillAmounts.'.$bill->id, 400000)
        ->call('save')
        ->assertHasNoErrors();

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID)
        ->and($bill->fresh()->status_label)->toBe('Lunas')
        ->and($bill->fresh()->remaining_amount)->toBe(0.0);
});

// -------------------------------------------------------------------
// Delete — Permanen
// -------------------------------------------------------------------

it('menghapus pembayaran calon siswa beserta detail dan mengembalikan saldo tagihan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeletePayment', $payment->id)
        ->assertSet('isDeletePaymentModalOpen', true)
        ->assertSet('deletingPaymentId', $payment->id)
        ->assertSee($payment->receipt_number)
        ->call('deletePayment')
        ->assertSet('isDeletePaymentModalOpen', false);

    expect(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(ProspectiveStudentPaymentDetail::query()->where('prospective_student_payment_id', $payment->id)->exists())->toBeFalse()
        ->and($bill->fresh()->paid_amount)->toBe(0.0)
        ->and($bill->fresh()->remaining_amount)->toBe(400000.0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID)
        ->and($ps->fresh())->not->toBeNull()
        ->and($bill->fresh())->not->toBeNull()
        ->and(PaymentType::query()->whereKey($type->id)->exists())->toBeTrue()
        ->and(Bank::query()->whereKey($payment->bank_id)->exists())->toBeTrue();
});

it('menghapus satu pembayaran dan menurunkan status Lunas menjadi Sebagian', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $fullPayment = createProspEditPayment($ps, [$bill->id => 400000], ['receipt_number' => 'KWT-REG-2027-9001']);

    $partialBank = Bank::factory()->create(['name' => 'Bank Sebagian', 'is_active' => true]);
    $partialUser = User::factory()->create();
    $partialPayment = ProspectiveStudentPayment::create([
        'receipt_number' => 'KWT-REG-2027-9002',
        'prospective_student_id' => $ps->id,
        'bank_id' => $partialBank->id,
        'payment_date' => '2026-09-20',
        'total_amount' => 100000,
        'created_by' => $partialUser->id,
    ]);
    ProspectiveStudentPaymentDetail::create([
        'prospective_student_payment_id' => $partialPayment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 100000,
    ]);

    expect($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeletePayment', $fullPayment->id)
        ->call('deletePayment');

    expect(ProspectiveStudentPayment::query()->whereKey($fullPayment->id)->exists())->toBeFalse()
        ->and(ProspectiveStudentPayment::query()->whereKey($partialPayment->id)->exists())->toBeTrue()
        ->and($bill->fresh()->paid_amount)->toBe(100000.0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL)
        ->and($bill->fresh()->remaining_amount)->toBe(300000.0);
});

it('menghapus pembayaran dari halaman detail calon siswa', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeletePayment', $payment->id)
        ->assertSet('isDeletePaymentModalOpen', true)
        ->call('deletePayment')
        ->assertSet('isDeletePaymentModalOpen', false);

    expect(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID);
});

it('menghapus file kwitansi dan halaman detail menjadi 404 setelah delete', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);
    Storage::disk('public')->put('receipts/delete-proof-prosp.pdf', 'receipt');
    $payment->update(['receipt' => 'receipts/delete-proof-prosp.pdf']);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeletePayment', $payment->id)
        ->call('deletePayment');

    Storage::disk('public')->assertMissing('receipts/delete-proof-prosp.pdf');

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.show', $payment->id))
        ->assertNotFound();
});

it('pembayaran yang dibatalkan tetap dapat dihapus permanen dari riwayat', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = ProspectiveStudentPayment::factory()->cancelled()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 400000,
    ]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeletePayment', $payment->id)
        ->call('deletePayment');

    expect(ProspectiveStudentPayment::query()->whereKey($payment->id)->exists())->toBeFalse();
});

// -------------------------------------------------------------------
// Regression
// -------------------------------------------------------------------

it('pembayaran calon siswa yang dibatalkan tidak dihitung dalam settlement tagihan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);

    $activePayment = createProspEditPayment($ps, [$bill->id => 400000], ['receipt_number' => 'KWT-REG-2027-8111']);

    $cancelled = ProspectiveStudentPayment::factory()->cancelled()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 400000,
    ]);
    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $cancelled->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 400000,
    ]);

    expect($bill->fresh()->paid_amount)->toBe(400000.0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeletePayment', $activePayment->id)
        ->call('deletePayment');

    expect($bill->fresh()->paid_amount)->toBe(0.0)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID)
        ->and(ProspectiveStudentPayment::query()->whereKey($cancelled->id)->exists())->toBeTrue();
});

it('kwitansi cetak serta PDF masih berfungsi setelah nominal diedit', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('selectedBillAmounts.'.$bill->id, 250000)
        ->call('save')
        ->assertHasNoErrors();

    $renderer = swapProspEditPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.prospective.print', $payment->fresh()))
        ->assertOk();

    $receipt = $renderer->receipts[0];

    expect($receipt['identityName'])->toBe($ps->nama_lengkap)
        ->and((float) $receipt['total'])->toBe(250000.0)
        ->and($receipt['receiptNumber'])->toBe($payment->receipt_number);
});

it('halaman detail menampilkan total terbaru setelah edit', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $payment])
        ->set('selectedBillAmounts.'.$bill->id, 250000)
        ->call('save');

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertSee('Rp '.number_format(250000, 0, ',', '.'))
        ->assertSee('Edit Pembayaran');
});

it('rute edit calon siswa hanya tersedia untuk pengguna terautentikasi', function () {
    [$type, $ps, $bill] = makeProspEditFixture(400000);
    $payment = createProspEditPayment($ps, [$bill->id => 400000]);

    $this->get(route('pembayaran.prospective.edit', $payment->id))
        ->assertRedirect(route('login'));
});
