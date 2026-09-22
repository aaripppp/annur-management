<?php

use App\Livewire\PaymentShow;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Support\PaymentReceiptDisplay;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Livewire\Livewire;

function makeReceiptGroupType(string $name): PaymentType
{
    return makeBillType($name);
}

function createStudentReceiptWithDetails(array $details): Payment
{
    $payment = Payment::create([
        'receipt_number' => 'KWT-GRP-'.uniqid(),
        'student_id' => Student::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-09-10',
        'total_amount' => array_sum(array_column($details, 'amount')),
        'payment_method' => 'transfer',
        'created_by' => User::factory()->create()->id,
    ]);

    foreach ($details as $detail) {
        PaymentDetail::create(array_merge($detail, ['payment_id' => $payment->id]));
    }

    return $payment->refresh();
}

function fakeStudentReceiptPdfRenderer(): object
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

it('menggabungkan SPP, Ekskul, dan OSIS periode yang sama menjadi satu baris kwitansi', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 970000],
        ['payment_type_id' => makeReceiptGroupType('Ekskul')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 50000],
        ['payment_type_id' => makeReceiptGroupType('OSIS')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 15000],
    ]);

    $component = Livewire::test(PaymentShow::class, ['id' => $payment->id]);

    $component
        ->assertSee('SPP (Juni 2026)')
        ->assertSee('1.035.000')
        ->assertDontSee('Ekskul')
        ->assertDontSee('OSIS');

    $html = $component->html();

    expect(substr_count($html, '1.035.000'))->toBe(2)
        ->and(substr_count($html, 'SPP (Juni 2026)'))->toBe(1);
});

it('menggabungkan SPP dan Ekskul tanpa OSIS menjadi satu baris kwitansi', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 970000],
        ['payment_type_id' => makeReceiptGroupType('Ekskul')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 50000],
    ]);

    $component = Livewire::test(PaymentShow::class, ['id' => $payment->id]);

    $component
        ->assertSee('SPP (Juni 2026)')
        ->assertDontSee('Ekskul');

    expect(substr_count($component->html(), '1.020.000'))->toBe(2);
});

it('tetap menampilkan SPP tunggal tanpa perubahan pada kwitansi', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 970000],
    ]);

    $component = Livewire::test(PaymentShow::class, ['id' => $payment->id]);

    $component->assertSee('SPP (Juni 2026)');

    $html = $component->html();

    expect(substr_count($html, 'SPP (Juni 2026)'))->toBe(1)
        ->and(substr_count($html, '970.000'))->toBe(2);
});

it('menggabungkan SPP Ekskul OSIS tetapi membiarkan Jemputan tetap baris sendiri', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 970000],
        ['payment_type_id' => makeReceiptGroupType('Ekskul')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 50000],
        ['payment_type_id' => makeReceiptGroupType('OSIS')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 15000],
        ['payment_type_id' => makeReceiptGroupType('Jemputan')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 500000],
    ]);

    $component = Livewire::test(PaymentShow::class, ['id' => $payment->id]);

    $component
        ->assertSee('SPP (Juni 2026)')
        ->assertSee('Jemputan')
        ->assertDontSee('Ekskul (Juni 2026)')
        ->assertDontSee('OSIS (Juni 2026)');

    $html = $component->html();

    expect(substr_count($html, '1.035.000'))->toBe(1)
        ->and(substr_count($html, '500.000'))->toBe(1)
        ->and(substr_count($html, '1.535.000'))->toBe(1);
});

it('membuat satu baris gabungan untuk setiap bulan yang berbeda', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 970000],
        ['payment_type_id' => makeReceiptGroupType('Ekskul')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 50000],
        ['payment_type_id' => makeReceiptGroupType('OSIS')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 15000],
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 7, 'period_year' => 2026, 'description' => 'Juli 2026', 'amount' => 970000],
    ]);

    $component = Livewire::test(PaymentShow::class, ['id' => $payment->id]);

    $component
        ->assertSee('SPP (Juni 2026)')
        ->assertSee('SPP (Juli 2026)');

    $html = $component->html();

    expect(substr_count($html, 'SPP (Juni 2026)'))->toBe(1)
        ->and(substr_count($html, 'SPP (Juli 2026)'))->toBe(1)
        ->and(substr_count($html, '2.005.000'))->toBe(1);
});

it('menjaga total kwitansi tetap sama dengan jumlah semua baris yang ditampilkan', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 970000],
        ['payment_type_id' => makeReceiptGroupType('Ekskul')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 50000],
        ['payment_type_id' => makeReceiptGroupType('OSIS')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 15000],
        ['payment_type_id' => makeReceiptGroupType('Jemputan')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 500000],
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 7, 'period_year' => 2026, 'description' => 'Juli 2026', 'amount' => 970000],
    ]);

    $rows = PaymentReceiptDisplay::rows($payment);

    expect(count($rows))->toBe(3)
        ->and(array_sum($rows->pluck('amount')->all()))->toBe((float) $payment->total_amount);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee('2.505.000');
});

it('tidak menampilkan baris terpisah untuk Ekskul dan OSIS pada tampilan web', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 970000],
        ['payment_type_id' => makeReceiptGroupType('Ekskul')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 50000],
        ['payment_type_id' => makeReceiptGroupType('OSIS')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 15000],
    ]);

    $html = Livewire::test(PaymentShow::class, ['id' => $payment->id])->html();

    $body = Str::after($html, '<tbody');

    expect($body)->not->toContain('Ekskul')
        ->and($body)->not->toContain('OSIS')
        ->and(substr_count($body, '<tr>'))->toBe(1);
});

it('tidak menampilkan baris terpisah untuk Ekskul dan OSIS pada PDF', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('SPP')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 970000],
        ['payment_type_id' => makeReceiptGroupType('Ekskul')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 50000],
        ['payment_type_id' => makeReceiptGroupType('OSIS')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 15000],
        ['payment_type_id' => makeReceiptGroupType('Jemputan')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 500000],
    ]);

    $renderer = fakeStudentReceiptPdfRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.pdf', $payment))
        ->assertOk();

    $details = $renderer->receipts[0]['details'];

    expect($details)->toHaveCount(2)
        ->and($details[0]['name'])->toBe('SPP (Juni 2026)')
        ->and($details[0]['description'])->toBeNull()
        ->and($details[0]['amount'])->toBe(1035000.0)
        ->and($details[1]['name'])->toBe('Jemputan')
        ->and($details[1]['description'])->toBe('Juni 2026')
        ->and($details[1]['amount'])->toBe(500000.0)
        ->and(array_sum(array_column($details, 'amount')))->toBe((float) $renderer->receipts[0]['total']);

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)
        ->toContain('<strong>SPP (Juni 2026)</strong>')
        ->toContain('<strong>Jemputan</strong>')
        ->toContain('(Juni 2026)</span>')
        ->not->toContain('<strong>Ekskul')
        ->not->toContain('<strong>OSIS')
        ->not->toContain('OSIS (Juni 2026)');
});

it('memberi label baris gabungan sesuai jenis tersedia saat SPP tidak ikut dibayar', function () {
    $payment = createStudentReceiptWithDetails([
        ['payment_type_id' => makeReceiptGroupType('Ekskul')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 50000],
        ['payment_type_id' => makeReceiptGroupType('OSIS')->id, 'period_month' => 6, 'period_year' => 2026, 'description' => 'Juni 2026', 'amount' => 15000],
    ]);

    $component = Livewire::test(PaymentShow::class, ['id' => $payment->id]);

    $component
        ->assertSee('Ekskul (Juni 2026)')
        ->assertDontSee('OSIS (Juni 2026)');

    expect(substr_count($component->html(), '65.000'))->toBe(2);
});
