<?php

use App\Livewire\DaycarePaymentShow;
use App\Livewire\PaymentIndex;
use App\Livewire\PaymentShow;
use App\Livewire\ProspectivePaymentShow;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentCancellationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Livewire\Livewire;

function badgeFreeBank(): Bank
{
    return Bank::factory()->create(['is_active' => true]);
}

function badgeFreeStudentPayment(Bank $bank, User $user, string $receiptNumber): Payment
{
    $payment = Payment::query()->create([
        'receipt_number' => $receiptNumber,
        'student_id' => Student::factory()->create()->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-08',
        'total_amount' => 970000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $payment->forceFill([
        'created_at' => '2026-09-08 09:30:00',
        'updated_at' => '2026-09-08 09:30:00',
    ])->saveQuietly();

    return $payment;
}

function badgeFreeProspectivePayment(Bank $bank, User $user, string $receiptNumber): ProspectiveStudentPayment
{
    $payment = ProspectiveStudentPayment::query()->create([
        'receipt_number' => $receiptNumber,
        'prospective_student_id' => ProspectiveStudent::factory()->create()->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-08',
        'total_amount' => 350000,
        'created_by' => $user->id,
    ]);

    $payment->forceFill([
        'created_at' => '2026-09-08 09:30:00',
        'updated_at' => '2026-09-08 09:30:00',
    ])->saveQuietly();

    return $payment;
}

function badgeFreeDaycarePayment(Bank $bank, User $user, string $receiptNumber): DaycarePayment
{
    $payment = DaycarePayment::factory()->create([
        'receipt_number' => $receiptNumber,
        'daycare_child_id' => DaycareChild::factory()->create()->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-09-08',
        'total_amount' => 1500000,
        'created_by' => $user->id,
    ]);

    $payment->forceFill([
        'created_at' => '2026-09-08 09:30:00',
        'updated_at' => '2026-09-08 09:30:00',
    ])->saveQuietly();

    return $payment;
}

/**
 * Tangkap payload kwitansi yang dikirim controller ke view PDF tanpa merender PDF sungguhan.
 */
function captureReceiptPdfPayload(): object
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
            return response('PDF', 200, ['Content-Type' => 'application/pdf']);
        }

        public function download(string $filename): Response
        {
            return response('PDF', 200, ['Content-Type' => 'application/pdf']);
        }
    };

    Pdf::swap($renderer);

    return $renderer;
}

afterEach(function () {
    $this->app->forgetInstance('dompdf.wrapper');
    Pdf::clearResolvedInstances();
});

// ---------------------------------------------------------------------------
// WEB KWT — SISWA
// ---------------------------------------------------------------------------

it('kwitansi web siswa tidak lagi menampilkan badge status pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = badgeFreeStudentPayment(badgeFreeBank(), $user, 'KWT-BADGE-SISWA-1');

    expect($payment->status_label)->toBe('Lunas');

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertOk()
        ->assertSee('KWITANSI PEMBAYARAN')
        ->assertSee($payment->receipt_number)
        ->assertDontSeeHtml('<span class="receipt-badge')
        ->assertDontSee('Lunas');
});

it('kwitansi web siswa tetap menampilkan alasan dan detail pembatalan tanpa badge', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = badgeFreeStudentPayment(badgeFreeBank(), $user, 'KWT-BADGE-BATAL-SISWA');
    app(PaymentCancellationService::class)->cancel($payment->id, 'Transfer ditolak bank', $user->id);
    $payment->refresh();

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertOk()
        ->assertDontSeeHtml('<span class="receipt-badge')
        ->assertSee('Pembayaran dibatalkan')
        ->assertSee('Transfer ditolak bank')
        ->assertSee($payment->cancelled_at?->translatedFormat('d F Y, H:i'))
        ->assertSee($user->name);
});

// ---------------------------------------------------------------------------
// WEB KWT — CALON SISWA
// ---------------------------------------------------------------------------

it('kwitansi web calon siswa tidak lagi menampilkan badge status pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = badgeFreeProspectivePayment(badgeFreeBank(), $user, 'KWT-BADGE-PROSP-1');

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertOk()
        ->assertSee('Kategori: Calon Siswa')
        ->assertSee($payment->receipt_number)
        ->assertDontSeeHtml('<span class="receipt-badge')
        ->assertDontSee($payment->status_label);
});

it('kwitansi web calon siswa tetap menampilkan alasan pembatalan tanpa badge', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = badgeFreeProspectivePayment(badgeFreeBank(), $user, 'KWT-BADGE-BATAL-PROSP');
    $payment->update([
        'status' => ProspectiveStudentPayment::STATUS_CANCELLED,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Formulir tidak lengkap',
    ]);
    $payment->refresh();

    Livewire::test(ProspectivePaymentShow::class, ['payment' => $payment->id])
        ->assertOk()
        ->assertDontSeeHtml('<span class="receipt-badge')
        ->assertSee('Pembayaran dibatalkan')
        ->assertSee('Formulir tidak lengkap')
        ->assertSee($user->name);
});

// ---------------------------------------------------------------------------
// RIWAYAT TRANSAKSI — BADGE TETAP ADA
// ---------------------------------------------------------------------------

it('tabel riwayat transaksi tetap menampilkan badge status pembayaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $bank = badgeFreeBank();
    $activePayment = badgeFreeStudentPayment($bank, $user, 'KWT-BADGE-HISTORI-AKTIF');
    $cancelledPayment = badgeFreeStudentPayment($bank, $user, 'KWT-BADGE-HISTORI-BATAL');
    app(PaymentCancellationService::class)->cancel($cancelledPayment->id, 'Alasan pembatalan riwayat', $user->id);

    Livewire::test(PaymentIndex::class)
        ->call('setActiveTab', 'history')
        ->assertSee($activePayment->receipt_number)
        ->assertSee('Lunas')
        ->assertSee($cancelledPayment->receipt_number)
        ->assertSee('Dibatalkan')
        ->assertSeeHtml('bg-error-container text-on-error-container')
        ->assertSeeHtml('bg-primary-fixed text-on-primary-fixed');
});

// ---------------------------------------------------------------------------
// DAYCARE — LABEL SUMBER TETAP ADA
// ---------------------------------------------------------------------------

it('kwitansi web Daycare tetap menampilkan label sumber Pembayaran Daycare', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $payment = badgeFreeDaycarePayment(badgeFreeBank(), $user, 'KWT-BADGE-DAYCARE-1');

    Livewire::test(DaycarePaymentShow::class, ['payment' => $payment])
        ->assertOk()
        ->assertSeeHtml('receipt-badge')
        ->assertSeeHtml('Pembayaran Daycare</span>');
});

// ---------------------------------------------------------------------------
// PDF KWT
// ---------------------------------------------------------------------------

it('PDF kwitansi siswa tidak lagi mengirim maupun merender badge status', function () {
    $user = User::factory()->create();
    $payment = badgeFreeStudentPayment(badgeFreeBank(), $user, 'KWT-BADGE-PDF-SISWA');
    $renderer = captureReceiptPdfPayload();

    $this->actingAs($user)
        ->get(route('pembayaran.print', $payment))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $receipt = $renderer->receipts[0];

    expect($receipt)->not->toHaveKey('badge')
        ->and($receipt['receiptNumber'])->toBe($payment->receipt_number)
        ->and($receipt['total'])->toBe(970000.0);

    $html = view('receipts.pdf', ['receipt' => $receipt])->render();

    expect($html)
        ->toContain($payment->receipt_number)
        ->not->toContain('<div class="badge">');
});

it('PDF kwitansi calon siswa tidak lagi mengirim maupun merender badge status', function () {
    $user = User::factory()->create();
    $payment = badgeFreeProspectivePayment(badgeFreeBank(), $user, 'KWT-BADGE-PDF-PROSP');
    $renderer = captureReceiptPdfPayload();

    $this->actingAs($user)
        ->get(route('pembayaran.prospective.print', $payment))
        ->assertOk();

    $receipt = $renderer->receipts[0];

    expect($receipt)->not->toHaveKey('badge')
        ->and($receipt['category'])->toBe('Calon Siswa');

    $html = view('receipts.pdf', ['receipt' => $receipt])->render();

    expect($html)->not->toContain('<div class="badge">');
});

it('PDF kwitansi Daycare mempertahankan label sumber pembayaran', function () {
    $user = User::factory()->create();
    $payment = badgeFreeDaycarePayment(badgeFreeBank(), $user, 'KWT-BADGE-PDF-DAYCARE');
    $renderer = captureReceiptPdfPayload();

    $this->actingAs($user)
        ->get(route('daycare.payment.print', $payment))
        ->assertOk();

    $receipt = $renderer->receipts[0];

    expect($receipt['badge'])->toBe('Pembayaran Daycare');

    $html = view('receipts.pdf', ['receipt' => $receipt])->render();

    expect($html)->toContain('<div class="badge">Pembayaran Daycare</div>');
});
