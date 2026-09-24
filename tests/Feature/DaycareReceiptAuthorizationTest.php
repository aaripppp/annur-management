<?php

use App\Livewire\DaycarePaymentShow;
use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Livewire\Livewire;

function daycareAuthorizationPayment(User $creator, array $overrides = []): DaycarePayment
{
    $payment = DaycarePayment::factory()->create(array_merge([
        'daycare_child_id' => DaycareChild::factory(),
        'bank_id' => Bank::factory(),
        'payment_date' => '2026-09-08',
        'total_amount' => 250000,
        'created_by' => $creator->id,
    ], $overrides));

    $payment->forceFill([
        'created_at' => '2026-09-08 09:30:00',
        'updated_at' => '2026-09-08 09:30:00',
    ])->saveQuietly();

    return $payment;
}

function daycareAuthorizationDetail(DaycarePayment $payment, int $index): DaycarePaymentDetail
{
    return DaycarePaymentDetail::factory()->create([
        'daycare_payment_id' => $payment->id,
        'description' => 'Item Daycare '.$index,
        'amount' => 100000,
    ]);
}

function studentAuthorizationPayment(User $creator, int $detailCount): Payment
{
    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-AUTH-ST-'.uniqid(),
        'student_id' => Student::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-09-08',
        'total_amount' => $detailCount * 100000,
        'payment_method' => 'transfer',
        'created_by' => $creator->id,
    ]);

    $payment->forceFill([
        'created_at' => '2026-09-08 09:30:00',
        'updated_at' => '2026-09-08 09:30:00',
    ])->saveQuietly();

    $paymentType = PaymentType::factory()->create(['name' => 'Pembayaran Multi Item']);

    foreach (range(1, $detailCount) as $index) {
        PaymentDetail::create([
            'payment_id' => $payment->id,
            'payment_type_id' => $paymentType->id,
            'amount' => 100000,
            'description' => 'Item Student '.$index,
        ]);
    }

    return $payment;
}

function fakeReceiptPrintRenderer(): object
{
    $renderer = new class
    {
        public string $view = '';

        /** @var list<array<string, mixed>> */
        public array $receipts = [];

        /** @var list<array{0: int, 1: int, 2: float, 3: float}> */
        public array $papers = [];

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
            $this->papers[] = $paper;

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

it('menampilkan blok otorisasi pembuat kwitansi yang sama dengan Student pada PDF Daycare', function () {
    $creator = User::factory()->create(['name' => 'Arif Hamdani']);
    $payment = daycareAuthorizationPayment($creator);
    $renderer = fakeReceiptPrintRenderer();

    $this->actingAs(User::factory()->create(['name' => 'Admin Lain']))
        ->get(route('daycare.payment.print', $payment))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $receipt = $renderer->receipts[0];

    expect($receipt)->toMatchArray([
        'creatorName' => 'Arif Hamdani',
        'authorizationDate' => '08 September 2026',
    ]);

    $html = view('receipts.pdf', ['receipt' => $receipt])->render();

    expect($html)
        ->toContain('Pembuat Kwitansi')
        ->toContain('Admin Keuangan')
        ->toContain('Arif Hamdani')
        ->toContain('Bekasi, 08 September 2026')
        ->toContain('images/stample.png')
        ->toContain('authorization-stamp')
        ->toContain('authorization-name');
});

it('mempertahankan pembuat historis ketika kwitansi Daycare dibuka admin lain', function () {
    $creator = User::factory()->create(['name' => 'Arif Hamdani']);
    $viewer = User::factory()->create(['name' => 'Super Admin Lain']);
    $payment = daycareAuthorizationPayment($creator);
    $renderer = fakeReceiptPrintRenderer();

    $this->actingAs($viewer)
        ->get(route('daycare.payment.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['creatorName'])->toBe('Arif Hamdani')
        ->and($renderer->receipts[0]['creatorName'])->not->toBe('Super Admin Lain');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)->toContain('Arif Hamdani')
        ->and($html)->not->toContain('Super Admin Lain');
});

it('memakai created_at WIB sebagai tanggal otorisasi Daycare, bukan payment_date', function () {
    $payment = daycareAuthorizationPayment(User::factory()->create(), ['payment_date' => '2026-09-05']);
    $renderer = fakeReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('daycare.payment.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['authorizationDate'])->toBe('08 September 2026');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)
        ->toContain('05 September 2026')
        ->toContain('Bekasi, 08 September 2026');
});

it('tidak menampilkan stempel pada preview web Daycare', function () {
    $payment = daycareAuthorizationPayment(User::factory()->create());

    Livewire::test(DaycarePaymentShow::class, ['payment' => $payment])
        ->assertDontSee('stample', false);
});

it('memberi ruang otorisasi dan kertas F4B portrait yang sama dengan kwitansi Student', function () {
    $creator = User::factory()->create();
    $daycarePayment = daycareAuthorizationPayment($creator);

    foreach (range(1, 3) as $index) {
        daycareAuthorizationDetail($daycarePayment, $index);
    }

    $studentPayment = studentAuthorizationPayment($creator, 3);

    $renderer = fakeReceiptPrintRenderer();

    $this->actingAs($creator)
        ->get(route('daycare.payment.print', $daycarePayment))
        ->assertOk();

    $this->actingAs($creator)
        ->get(route('pembayaran.print', $studentPayment))
        ->assertOk();

    $daycarePaper = $renderer->papers[0];
    $studentPaper = $renderer->papers[1];
    $expectedHeightMillimeters = 330;

    expect($daycarePaper[2])->toBe($studentPaper[2])
        ->and($daycarePaper[3])->toBe($studentPaper[3])
        ->and(abs($daycarePaper[3] - ($expectedHeightMillimeters * 72 / 25.4)))->toBeLessThan(0.01);
});
