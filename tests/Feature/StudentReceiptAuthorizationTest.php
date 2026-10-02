<?php

use App\Livewire\PaymentShow;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
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

/** @return array{0: AcademicYear, 1: AcademicYear} */
function createReceiptAcademicYears(): array
{
    $activeYear = AcademicYear::query()->updateOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );
    $futureYear = AcademicYear::query()->updateOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30']
    );

    return [$activeYear, $futureYear];
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

it('mempertahankan NIS dan kelas untuk Student aktif pada PDF', function () {
    [$activeYear] = createReceiptAcademicYears();
    $schoolClass = SchoolClass::factory()->create(['name' => '7C']);
    $student = Student::factory()->create([
        'nis' => '20260001',
        'class_id' => $schoolClass->id,
    ]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);
    $payment = createAuthorizationReceipt(User::factory()->create(), ['student_id' => $student->id]);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['identityContext'])->toBe('NIS 20260001 • Kelas '.$schoolClass->name);
});

it('menampilkan NIS dan Calon Siswa tanpa kelas untuk Student dengan enrollment masa depan', function () {
    [, $futureYear] = createReceiptAcademicYears();
    $schoolClass = SchoolClass::factory()->create(['name' => '7C']);
    $student = Student::factory()->create([
        'nis' => '20270027',
        'class_id' => $schoolClass->id,
    ]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $futureYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);
    $payment = createAuthorizationReceipt(User::factory()->create(), ['student_id' => $student->id]);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['identityContext'])
        ->toBe('NIS 20270027 • Calon Siswa')
        ->not->toContain('Kelas')
        ->not->toContain($schoolClass->name);
});

it('menampilkan Calon Siswa saja ketika Student enrollment masa depan tidak memiliki NIS', function () {
    [, $futureYear] = createReceiptAcademicYears();
    $schoolClass = SchoolClass::factory()->create(['name' => '7C']);
    $student = Student::factory()->create([
        'nis' => null,
        'class_id' => $schoolClass->id,
    ]);
    StudentAcademicEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => $futureYear->id,
        'school_class_id' => $schoolClass->id,
        'status' => 'active',
    ]);
    $payment = createAuthorizationReceipt(User::factory()->create(), ['student_id' => $student->id]);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['identityContext'])->toBe('Calon Siswa');
});

it('merender brand metode pembayaran dan tanggal pada bagian informasi PDF Student', function () {
    $creator = User::factory()->create(['name' => 'Admin Kwitansi']);
    $bank = Bank::factory()->create([
        'name' => 'BSI',
        'type' => Bank::TYPE_BANK,
        'account_number' => '7023358276',
        'account_name' => 'YPI An-Nur Nurrahim',
    ]);
    $payment = createAuthorizationReceipt($creator, [
        'bank_id' => $bank->id,
        'payment_date' => '2026-10-01',
    ]);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    $receipt = $renderer->receipts[0];
    $student = $payment->student()->with('schoolClass')->firstOrFail();
    $html = view('receipts.pdf', ['receipt' => $receipt])->render();

    expect($receipt['identityContext'])->toBe('NIS '.$student->nis.' • Kelas '.$student->schoolClass->name)
        ->and($html)->toContain('YPI An-Nur Nurrahim')
        ->and($html)->not->toContain('ANNUR MANAGEMENT')
        ->and($html)->toMatch('/<div class="info-title">BSI - 7023358276<\/div>\s*<div class="info-copy payment-date">01 Oktober 2026<\/div>\s*<div class="info-copy">YPI An-Nur Nurrahim<\/div>/')
        ->and($html)->toContain('.authorization-block { position: relative; right: 40px; height: 140px; }')
        ->and($html)->toContain('.authorization-stamp { position: absolute; top: 16px; left: 68px; width: 118px; height: auto; }');
});

it('merender metode tunai tanpa separator rekening pada PDF Student', function () {
    $creator = User::factory()->create();
    $cash = Bank::factory()->cash()->create();
    $payment = createAuthorizationReceipt($creator, [
        'bank_id' => $cash->id,
        'payment_date' => '2026-10-01',
    ]);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)
        ->toMatch('/<div class="info-title">Tunai<\/div>\s*<div class="info-copy payment-date">01 Oktober 2026<\/div>/')
        ->not->toContain('Tunai -');
});

it('merender nama bank tanpa separator ketika nomor rekening kosong', function () {
    $creator = User::factory()->create();
    $bank = Bank::factory()->create([
        'name' => 'BSI',
        'type' => Bank::TYPE_BANK,
        'account_number' => '   ',
        'account_name' => null,
    ]);
    $payment = createAuthorizationReceipt($creator, ['bank_id' => $bank->id]);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)
        ->toContain('<div class="info-title">BSI</div>')
        ->not->toContain('BSI -');
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
    $creator = User::factory()->create(['name' => 'Arif Hamdani', 'position' => 'Bendahara']);
    $viewer = User::factory()->create(['name' => 'Super Admin Lain', 'position' => 'Direktur Keuangan']);
    $payment = createAuthorizationReceipt($creator);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs($viewer)
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['creatorName'])->toBe('Arif Hamdani')
        ->and($renderer->receipts[0]['creatorName'])->not->toBe('Super Admin Lain')
        ->and($renderer->receipts[0]['creatorPosition'])->toBe('Bendahara')
        ->and($renderer->receipts[0]['creatorPosition'])->not->toBe('Direktur Keuangan');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)->toContain('Arif Hamdani')
        ->and($html)->toContain('Bendahara')
        ->and($html)->not->toContain('Super Admin Lain');
});

it('memakai label role ketika jabatan pembuat kwitansi Student kosong', function () {
    $creator = User::factory()->create([
        'position' => null,
        'role' => User::ROLE_SUPER_ADMIN,
    ]);
    $payment = createAuthorizationReceipt($creator);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['creatorPosition'])->toBe('Super Admin');
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
    $creator = User::factory()->create(['name' => 'Arif Hamdani', 'position' => 'Bendahara']);
    $payment = createAuthorizationReceipt($creator);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['creatorName'])->toBe('Arif Hamdani')
        ->and($renderer->receipts[0]['creatorPosition'])->toBe('Bendahara');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)
        ->toContain('Bekasi, 08 September 2026')
        ->toContain('Pembuat Kwitansi')
        ->toContain('Arif Hamdani')
        ->toContain('Bendahara')
        ->not->toContain('Admin Keuangan')
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
