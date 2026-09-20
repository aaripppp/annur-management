<?php

use App\Livewire\PaymentShow;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Livewire\Livewire;

function createAuthorizationReceipt(User $creator, array $overrides = []): Payment
{
    $payment = Payment::query()->create(array_merge([
        'receipt_number' => 'KWT-AUTH-'.uniqid(),
        'student_id' => Student::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-09-08',
        'total_amount' => 250000,
        'payment_method' => 'transfer',
        'created_by' => $creator->id,
    ], $overrides));

    $payment->forceFill([
        'created_at' => '2026-09-08 09:30:00',
        'updated_at' => '2026-09-08 09:30:00',
    ])->saveQuietly();

    return $payment;
}

function fakeStudentReceiptPrintRenderer(): object
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

it('tidak menampilkan blok otorisasi pembuat kwitansi pada detail web Student', function () {
    $creator = User::factory()->create(['name' => 'Arif Hamdani']);
    $payment = createAuthorizationReceipt($creator);

    $component = Livewire::actingAs($creator)
        ->test(PaymentShow::class, ['id' => $payment->id])
        ->assertDontSee('Bekasi, 08 September 2026')
        ->assertDontSee('Pembuat Kwitansi')
        ->assertDontSee('Admin Keuangan')
        ->assertDontSee('Terima kasih atas kepercayaannya.')
        ->assertDontSee('Semoga Allah memberikan keberkahan.')
        ->assertDontSeeHtml('receipt-footer')
        ->assertDontSeeHtml('receipt-thank-you')
        ->assertDontSeeHtml('receipt-authorization')
        ->assertSeeHtml('id="kwitansi-print-area"');

    $document = new DOMDocument;
    $previousUseInternalErrors = libxml_use_internal_errors(true);
    $document->loadHTML($component->html());
    libxml_clear_errors();
    libxml_use_internal_errors($previousUseInternalErrors);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " receipt-footer ")]')->length)->toBe(0)
        ->and($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " receipt-authorization ") or contains(concat(" ", normalize-space(@class), " "), " receipt-authorization-block ") or contains(concat(" ", normalize-space(@class), " "), " receipt-thank-you ")]')->length)->toBe(0);
});

it('mempertahankan pembuat historis pada PDF Student ketika kwitansi dibuka admin lain', function () {
    $creator = User::factory()->create(['name' => 'Arif Hamdani']);
    $viewer = User::factory()->create(['name' => 'Super Admin Lain']);
    $payment = createAuthorizationReceipt($creator);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs($viewer)
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['creatorName'])->toBe('Arif Hamdani')
        ->and($renderer->receipts[0]['creatorName'])->not->toBe('Super Admin Lain');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)->toContain('Arif Hamdani')
        ->and($html)->not->toContain('Super Admin Lain');
});

it('menampilkan tanggal otorisasi dari created_at dalam WIB pada PDF Student', function () {
    $payment = createAuthorizationReceipt(User::factory()->create());
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['authorizationDate'])->toBe('08 September 2026');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)->toContain('Bekasi, 08 September 2026');
});

it('tidak memakai payment_date sebagai tanggal otorisasi pada PDF Student', function () {
    $payment = createAuthorizationReceipt(User::factory()->create(), [
        'payment_date' => '2026-09-05',
    ]);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['authorizationDate'])->toBe('08 September 2026');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)
        ->toContain('05 September 2026')
        ->toContain('Bekasi, 08 September 2026');
});

it('mempertahankan blok pembuat kwitansi dan stempel pada PDF Student', function () {
    $creator = User::factory()->create(['name' => 'Arif Hamdani']);
    $payment = createAuthorizationReceipt($creator);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['creatorName'])->toBe('Arif Hamdani');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)
        ->toContain('Bekasi, 08 September 2026')
        ->toContain('Pembuat Kwitansi')
        ->toContain('Arif Hamdani')
        ->toContain('Admin Keuangan')
        ->toContain('images/stample.png')
        ->toContain('authorization-stamp')
        ->toContain('authorization-name');
});

it('tetap merender kwitansi siswa dengan NIS null', function () {
    $creator = User::factory()->create();
    $student = Student::factory()->create(['nis' => null]);
    $payment = createAuthorizationReceipt($creator, ['student_id' => $student->id]);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee('NIS —')
        ->assertDontSee('NIS null');
});

it('menghapus stempel dari preview web tetapi mempertahankannya di PDF', function () {
    $viewer = User::factory()->create();
    $payment = createAuthorizationReceipt(User::factory()->create());

    expect(public_path('images/stample.png'))->toBeFile();

    Livewire::actingAs($viewer)
        ->test(PaymentShow::class, ['id' => $payment->id])
        ->assertDontSee('stample', false);

    $response = $this->actingAs($viewer)->get(route('pembayaran.print', $payment));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr_count($response->getContent(), '/Subtype /Image'))->toBeGreaterThanOrEqual(2);
});
