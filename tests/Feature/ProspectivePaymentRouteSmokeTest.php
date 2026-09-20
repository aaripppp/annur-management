<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Livewire\PaymentIndex;
use App\Livewire\ProspectivePaymentCreate;
use App\Livewire\ProspectivePaymentEdit;
use App\Livewire\ProspectivePaymentShow;
use App\Livewire\ProspectivePaymentWorkspace;
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
use App\Services\ProspectiveStudentPaymentDeletionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Livewire\Livewire;

/**
 * @return array{0: PaymentType, 1: ProspectiveStudent, 2: ProspectiveStudentBill}
 */
function makeProspSmokeBase(int $amount = 400000, string $name = 'Formulir Pendaftaran Smoke'): array
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
        'nama_lengkap' => 'Dewi Smoke',
        'no_telp_orang_tua' => '081234567998',
    ]);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);
    $bill = $ps->fresh()->bills->first();

    return [$type, $ps, $bill];
}

function createProspSmokePayment(ProspectiveStudent $ps, array $allocations, array $overrides = []): ProspectiveStudentPayment
{
    $bank = Bank::factory()->create(['name' => 'Bank Smoke', 'is_active' => true]);
    $user = User::factory()->create();
    $total = (float) array_sum($allocations);

    $payment = ProspectiveStudentPayment::create([
        'receipt_number' => $overrides['receipt_number'] ?? 'KWT-REG-2027-005001',
        'prospective_student_id' => $ps->id,
        'bank_id' => $overrides['bank_id'] ?? $bank->id,
        'payment_date' => $overrides['payment_date'] ?? '2026-09-10',
        'total_amount' => $total,
        'description' => 'Pembayaran pendaftaran',
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

function swapSmokePdfRenderer(): object
{
    $renderer = new class
    {
        /** @var list<array<string, mixed>> */
        public array $receipts = [];

        /** @param array<string, mixed> $data */
        public function loadView(string $view, array $data): self
        {
            $this->receipts[] = $data['receipt'];

            return $this;
        }

        public function setOption(array $options): self
        {
            return $this;
        }

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
// Route smoke — setiap halaman prospective payment harus 200
// -------------------------------------------------------------------

it('rute workspace calon siswa mengembalikan 200', function () {
    $user = User::factory()->create();
    [$type, $ps, $bill] = makeProspSmokeBase();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.workspace', $ps))
        ->assertOk()
        ->assertSee('Tagihan Pendaftaran');
});

it('rute create pembayaran calon siswa mengembalikan 200', function () {
    $user = User::factory()->create();
    [$type, $ps, $bill] = makeProspSmokeBase();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.create', $ps))
        ->assertOk()
        ->assertSeeLivewire(ProspectivePaymentCreate::class);
});

it('rute show pembayaran calon siswa mengembalikan 200', function () {
    $user = User::factory()->create();
    [$type, $ps, $bill] = makeProspSmokeBase();
    $payment = createProspSmokePayment($ps, [$bill->id => 400000]);

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.show', $payment->id))
        ->assertOk()
        ->assertSeeLivewire(ProspectivePaymentShow::class);
});

it('rute edit pembayaran calon siswa mengembalikan 200', function () {
    $user = User::factory()->create();
    [$type, $ps, $bill] = makeProspSmokeBase();
    $payment = createProspSmokePayment($ps, [$bill->id => 400000]);

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.edit', ['payment' => $payment->id]))
        ->assertOk()
        ->assertSeeLivewire(ProspectivePaymentEdit::class);
});

it('rute cetak kwitansi mengembalikan 200', function () {
    $user = User::factory()->create();
    [$type, $ps, $bill] = makeProspSmokeBase();
    $payment = createProspSmokePayment($ps, [$bill->id => 400000]);

    $renderer = swapSmokePdfRenderer();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.print', $payment->id))
        ->assertOk();

    expect($renderer->receipts)->toHaveCount(1);
});

it('rute PDF kwitansi mengembalikan 200 dengan header pdf', function () {
    $user = User::factory()->create();
    [$type, $ps, $bill] = makeProspSmokeBase();
    $payment = createProspSmokePayment($ps, [$bill->id => 400000]);

    swapSmokePdfRenderer();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.pdf', $payment->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

// -------------------------------------------------------------------
// Route smoke — id asing/nonexistent gagal dengan aman
// -------------------------------------------------------------------

it('rute workspace mengembalikan 404 untuk calon siswa yang tidak ada', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.workspace', 999_999))
        ->assertNotFound();
});

it('rute create mengembalikan 404 untuk calon siswa yang tidak ada', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.create', 999_999))
        ->assertNotFound();
});

it('rute show mengembalikan 404 untuk pembayaran yang tidak ada', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.show', 999_999))
        ->assertNotFound();
});

it('rute edit mengembalikan 404 untuk pembayaran yang tidak ada', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.edit', ['payment' => 999_999]))
        ->assertNotFound();
});

it('rute cetak kwitansi mengembalikan 404 setelah pembayaran dihapus', function () {
    $user = User::factory()->create();
    [$type, $ps, $bill] = makeProspSmokeBase();
    $payment = createProspSmokePayment($ps, [$bill->id => 400000]);

    app(ProspectiveStudentPaymentDeletionService::class)->delete($payment->id);

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.print', $payment->id))
        ->assertNotFound();
});

// -------------------------------------------------------------------
// Back navigation dari detail pembayaran
// -------------------------------------------------------------------

it('tombol Kembali pada detail menuju workspace pembayaran, bukan ke manajemen calon siswa', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspSmokeBase();
    $payment = createProspSmokePayment($ps, [$bill->id => 400000]);

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertSee('Kembali ke Pembayaran Calon Siswa')
        ->assertSee(route('pembayaran.prospective.workspace', ['prospectiveStudent' => $ps->id]), false)
        ->assertDontSee('Kembali ke Calon Siswa')
        ->assertDontSee(route('calon-siswa.show', $ps), false);
});

// -------------------------------------------------------------------
// Alur end-to-end pembayaran calon siswa
// -------------------------------------------------------------------

it('alur lengkap pembayaran calon siswa dari pencarian sampai kwitansi', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$formType, $ps, $regBill] = makeProspSmokeBase();

    // 3. Tagihan pendaftaran otomatis terbuat dan belum bayar
    expect($regBill)->not->toBeNull()
        ->and((float) $regBill->amount)->toBe(400000.0)
        ->and($regBill->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID);

    // 4-6. Pencarian pembayaran menemukan calon siswa dan menautkan ke workspace
    Livewire::test(PaymentIndex::class)
        ->set('studentSearch', 'Dewi Smoke')
        ->assertSee(route('pembayaran.prospective.workspace', $ps), false)
        ->assertSee('Dewi Smoke');

    // 7. Buka workspace lewat rute sungguhan
    $this->actingAs($user)
        ->get(route('pembayaran.prospective.workspace', $ps))
        ->assertOk()
        ->assertSee('Tagihan Pendaftaran')
        ->assertSee('Riwayat Pembayaran');

    // 8. Tambah tagihan lain (Wawancara 250.000)
    $extraType = PaymentType::create([
        'name' => 'Wawancara E2E',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $extraType->id,
        'school_level' => SchoolLevel::SMP,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $extraType->id,
        'class_level' => 8,
        'amount' => 250000,
        'effective_from' => '2026-01-01',
    ]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->set('addPaymentTypeId', $extraType->id)
        ->assertSet('addAmount', '250000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    expect($ps->fresh()->bills()->count())->toBe(2);

    // 9. Edit nominal tagihan pendaftaran menjadi 350.000
    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('editBill', $regBill->id)
        ->set('editAmount', 350000)
        ->call('saveEditBill')
        ->assertHasNoErrors();

    expect($regBill->fresh()->amount)->toBe('350000.00')
        ->and((float) $formType->fresh()->rates()->first()->amount)->toBe(400000.0);

    // 10-11. Input Pembayaran sebagian 150.000
    $bank = Bank::factory()->create(['name' => 'Bank E2E', 'is_active' => true]);

    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$regBill->id])
        ->set('selectedBillAmounts.'.$regBill->id, 150000)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-01')
        ->call('save')
        ->assertHasNoErrors();

    $partialPayment = $ps->fresh()->payments()->orderBy('id')->first();

    expect($partialPayment)->not->toBeNull();

    // 12-13. Kembali ke workspace: tagihan menjadi Sebagian
    expect($regBill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL)
        ->and((float) $regBill->fresh()->paid_amount)->toBe(150000.0)
        ->and((float) $regBill->fresh()->remaining_amount)->toBe(200000.0);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee($partialPayment->receipt_number)
        ->assertSee('Rp 150.000');

    // 14. Pembayaran sisa 200.000
    Livewire::test(ProspectivePaymentCreate::class, ['prospectiveStudent' => $ps])
        ->set('selectedBillIds', [$regBill->id])
        ->set('selectedBillAmounts.'.$regBill->id, 200000)
        ->set('bank_id', $bank->id)
        ->set('payment_date', '2026-09-10')
        ->call('save')
        ->assertHasNoErrors();

    $payments = $ps->fresh()->payments()->orderBy('id')->get();
    $fullPayment = $payments->get(1);

    // 15. Tagihan Lunas
    expect($regBill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PAID)
        ->and((float) $regBill->fresh()->remaining_amount)->toBe(0.0);

    // 16. Buka detail pembayaran
    Livewire::test(ProspectivePaymentShow::class, ['payment' => $partialPayment->id])
        ->assertSee('Detail Pembayaran Pendaftaran');

    // 17. Tombol kembali mengarah ke workspace pembayaran
    Livewire::test(ProspectivePaymentShow::class, ['payment' => $partialPayment->id])
        ->assertSee('Kembali ke Pembayaran Calon Siswa')
        ->assertSee(route('pembayaran.prospective.workspace', ['prospectiveStudent' => $ps->id]), false)
        ->assertDontSee(route('calon-siswa.show', $ps), false);

    // 18. Edit pembayaran sebagian menjadi 100.000
    Livewire::test(ProspectivePaymentEdit::class, ['payment' => $partialPayment])
        ->assertSet('selectedBillAmounts.'.$regBill->id, 150000)
        ->set('selectedBillAmounts.'.$regBill->id, 100000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('pembayaran.prospective.show', $partialPayment->id));

    // 19. Workspace mencerminkan alokasi yang diedit
    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee($partialPayment->receipt_number)
        ->assertSee('Rp 100.000');

    expect((float) $regBill->fresh()->paid_amount)->toBe(300000.0);

    // 20-23. Aksi riwayat: View, Edit, Delete tersedia
    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSeeHtml('title="Lihat Detail / Cetak Kwitansi"')
        ->assertSeeHtml('title="Edit Pembayaran"')
        ->assertSeeHtml('title="Hapus Transaksi"');

    // 24. Hapus pembayaran lunas (200.000)
    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('confirmDeletePayment', $fullPayment->id)
        ->assertSet('isDeletePaymentModalOpen', true)
        ->call('deletePayment')
        ->assertSet('isDeletePaymentModalOpen', false);

    expect(ProspectiveStudentPayment::query()->whereKey($fullPayment->id)->exists())->toBeFalse();

    // 25. Settlement tagihan dihitung ulang
    expect((float) $regBill->fresh()->paid_amount)->toBe(100000.0)
        ->and((float) $regBill->fresh()->remaining_amount)->toBe(250000.0)
        ->and($regBill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL);

    // 26. Cetak kwitansi tetap berfungsi setelah edit
    $renderer = swapSmokePdfRenderer();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.print', $partialPayment->id))
        ->assertOk();

    $receipt = $renderer->receipts[0];

    expect((float) $receipt['total'])->toBe(100000.0)
        ->and($receipt['identityName'])->toBe($ps->nama_lengkap)
        ->and($receipt['receiptNumber'])->toBe($partialPayment->receipt_number);

    // 27. Rute PDF tetap berfungsi
    $this->actingAs($user)
        ->get(route('pembayaran.prospective.pdf', $partialPayment->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
