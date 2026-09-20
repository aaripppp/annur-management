<?php

use App\Livewire\DaycarePaymentCreate;
use App\Livewire\DaycarePaymentShow;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function makeDaycareReceiptPayment(array $paymentAttributes = []): DaycarePayment
{
    $child = DaycareChild::factory()->create([
        'nama_lengkap' => 'Ahmad Fauzan',
        'kelas' => 'C',
    ]);
    $bank = Bank::factory()->create([
        'name' => 'BSI',
        'account_number' => '7123456789',
        'account_name' => 'Yayasan Annur',
    ]);
    $creator = User::factory()->create(['name' => 'Admin Daycare']);
    $payment = DaycarePayment::factory()->create(array_merge([
        'receipt_number' => 'KWT-DC-2026-000001',
        'daycare_child_id' => $child->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-25',
        'total_amount' => 875000,
        'notes' => 'Pembayaran diterima',
        'created_by' => $creator->id,
    ], $paymentAttributes));

    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $payment->id,
        'description' => 'Penitipan Agustus',
        'amount' => 750000,
    ]);
    DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $payment->id,
        'description' => 'Kegiatan Daycare',
        'amount' => 125000,
    ]);

    return $payment;
}

it('menyimpan pembayaran lalu mengarahkan ke detail Daycare dengan nomor kwitansi', function () {
    $user = User::factory()->create();
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(DaycarePaymentCreate::class, ['child' => $child])
        ->set('items', [
            ['description' => 'Penitipan Agustus', 'amount' => 750000],
            ['description' => 'Kegiatan Daycare', 'amount' => 125000],
        ])
        ->set('bank_id', (string) $bank->id)
        ->set('payment_date', '2026-08-25')
        ->call('save')
        ->assertHasNoErrors();

    $payment = DaycarePayment::query()->sole();

    expect($payment->receipt_number)->toBe('KWT-DC-2026-000001');
    $component->assertRedirect(route('daycare.payment.show', $payment));
});

it('menghasilkan nomor kwitansi Daycare yang unik dan berurutan per tahun', function () {
    $user = User::factory()->create();
    $child = DaycareChild::factory()->create();
    $bank = Bank::factory()->create();

    foreach (['Pembayaran Pertama', 'Pembayaran Kedua'] as $description) {
        Livewire::actingAs($user)
            ->test(DaycarePaymentCreate::class, ['child' => $child])
            ->set('items.0.description', $description)
            ->set('items.0.amount', 100000)
            ->set('bank_id', (string) $bank->id)
            ->set('payment_date', '2026-08-25')
            ->call('save')
            ->assertHasNoErrors();
    }

    expect(DaycarePayment::query()->orderBy('id')->pluck('receipt_number')->all())->toBe([
        'KWT-DC-2026-000001',
        'KWT-DC-2026-000002',
    ]);
});

it('detail dan kwitansi menampilkan konteks Daycare lengkap', function () {
    $payment = makeDaycareReceiptPayment();

    Livewire::test(DaycarePaymentShow::class, ['payment' => $payment])
        ->assertSee('KWT-DC-2026-000001')
        ->assertSee('Ahmad Fauzan')
        ->assertSee('Kelas C')
        ->assertSee('Daycare')
        ->assertSee('25 Agustus 2026')
        ->assertSee('BSI')
        ->assertSee('7123456789')
        ->assertSee('Pembayaran diterima')
        ->assertSee(asset('images/annur_logo2.png'), false)
        ->assertSee(route('daycare.payment.print', $payment), false)
        ->assertSee(route('daycare.payment.pdf', $payment), false)
        ->assertSeeHtml('class="receipt-sheet w-[95%] max-w-none')
        ->assertSeeHtml('receipt-payment-meta')
        ->assertSeeHtml('grid-template-columns: repeat(2, minmax(0, 1fr))')
        ->assertSeeHtml('id="receipt-print-styles"')
        ->assertDontSeeHtml('min-height: 99mm;')
        ->assertDontSee('Petugas')
        ->assertDontSee('Penerima')
        ->assertDontSee('Mengetahui')
        ->assertDontSeeHtml('receipt-signature')
        ->assertSeeInOrder([
            'Penitipan Agustus',
            'Rp 750.000',
            'Kegiatan Daycare',
            'Rp 125.000',
            'Rp 875.000',
        ])
        ->assertSee('Cetak Kwitansi')
        ->assertSee('Kembali ke Detail Anak');
});

it('renders a Daycare cash receipt without empty account rows', function () {
    $user = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $payment = makeDaycareReceiptPayment(['bank_id' => $cash->id]);

    Livewire::test(DaycarePaymentShow::class, ['payment' => $payment])
        ->assertSee('Metode Pembayaran')
        ->assertSee('Tunai')
        ->assertDontSeeHtml('<p class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5"></p>');

    $response = $this->actingAs($user)->get(route('daycare.payment.print', $payment));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

it('route detail Daycare terikat ke DaycarePayment bukan Student Payment', function () {
    $user = User::factory()->create();
    $payment = makeDaycareReceiptPayment();

    $this->actingAs($user)
        ->get(route('daycare.payment.show', $payment))
        ->assertOk()
        ->assertSee('Ahmad Fauzan')
        ->assertSee('Kategori: Daycare')
        ->assertDontSee('Informasi Siswa');

    $inlineResponse = $this->get(route('daycare.payment.print', $payment));

    $inlineResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($inlineResponse->headers->get('content-disposition'))->toContain('inline')->toContain('KWT-DC-2026-000001.pdf')
        ->and($inlineResponse->getContent())->toStartWith('%PDF')
        ->and($inlineResponse->getContent())->toContain(mb_convert_encoding('Kwitansi Pembayaran - Annur Management', 'UTF-16BE'))
        ->and($inlineResponse->getContent())->toContain('/Subtype /Image');
});

it('mengunduh PDF Daycare lengkap tanpa mengubah transaksi', function () {
    $user = User::factory()->create();
    $payment = makeDaycareReceiptPayment();
    $paymentCount = DaycarePayment::query()->count();
    $detailCount = DaycarePaymentDetail::query()->count();
    $receiptNumber = $payment->receipt_number;
    $total = $payment->total_amount;
    $details = $payment->details()->orderBy('id')->get(['description', 'amount'])->toArray();

    $inlineResponse = $this->actingAs($user)->get(route('daycare.payment.print', $payment));
    $firstResponse = $this->actingAs($user)->get(route('daycare.payment.pdf', $payment));
    $secondResponse = $this->get(route('daycare.payment.pdf', $payment));

    $inlineResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $firstResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $secondResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($inlineResponse->headers->get('content-disposition'))->toContain('inline')->toContain($receiptNumber.'.pdf')
        ->and($inlineResponse->getContent())->toStartWith('%PDF')
        ->and($firstResponse->headers->get('content-disposition'))->toContain('attachment')->toContain($receiptNumber.'.pdf')
        ->and($firstResponse->getContent())->toStartWith('%PDF')
        ->and($secondResponse->getContent())->toStartWith('%PDF')
        ->and($payment->refresh()->receipt_number)->toBe($receiptNumber)
        ->and($payment->total_amount)->toBe($total)
        ->and($payment->details()->orderBy('id')->get(['description', 'amount'])->toArray())->toBe($details)
        ->and(DaycarePayment::query()->count())->toBe($paymentCount)
        ->and(DaycarePaymentDetail::query()->count())->toBe($detailCount);
});

it('route PDF Daycare tidak menerima id Student Payment', function () {
    $user = User::factory()->create();
    $payment = Payment::create([
        'receipt_number' => 'KWT-STUDENT-ONLY',
        'student_id' => Student::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-25',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('daycare.payment.pdf', ['payment' => $payment->id]))
        ->assertNotFound();
    $this->get(route('daycare.payment.print', ['payment' => $payment->id]))->assertNotFound();
});

it('mencetak ulang tidak mengubah nomor atau membuat transaksi dan detail baru', function () {
    $user = User::factory()->create();
    $payment = makeDaycareReceiptPayment();
    $receiptNumber = $payment->receipt_number;
    $paymentCount = DaycarePayment::query()->count();
    $detailCount = DaycarePaymentDetail::query()->count();

    $this->actingAs($user)->get(route('daycare.payment.show', $payment))->assertOk();
    $this->actingAs($user)->get(route('daycare.payment.show', $payment))->assertOk();

    expect($payment->refresh()->receipt_number)->toBe($receiptNumber)
        ->and(DaycarePayment::query()->count())->toBe($paymentCount)
        ->and(DaycarePaymentDetail::query()->count())->toBe($detailCount);
});

it('route detail dan kwitansi Daycare memerlukan autentikasi', function () {
    $payment = makeDaycareReceiptPayment();

    $this->get(route('daycare.payment.show', $payment))->assertRedirect(route('login'));
    $this->get(route('daycare.payment.print', $payment))->assertRedirect(route('login'));
    $this->get(route('daycare.payment.pdf', $payment))->assertRedirect(route('login'));
});

it('migration memberi nomor kwitansi stabil pada transaksi Daycare lama', function () {
    $payment = makeDaycareReceiptPayment(['payment_date' => '2025-12-20']);
    $migration = require database_path('migrations/2026_08_25_085955_add_receipt_number_to_daycare_payments_table.php');

    $migration->down();

    expect(Schema::hasColumn('daycare_payments', 'receipt_number'))->toBeFalse();

    $migration->up();

    expect(DB::table('daycare_payments')->where('id', $payment->id)->value('receipt_number'))->toBe('KWT-DC-2025-000001')
        ->and(DB::table('daycare_receipt_sequences')->where('year', 2025)->value('last_number'))->toBe(1);
});
