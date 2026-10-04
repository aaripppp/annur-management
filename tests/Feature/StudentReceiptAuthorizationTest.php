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

it('menampilkan kelas tanpa NIS untuk Student aktif pada PDF', function () {
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

    expect($renderer->receipts[0]['identityContext'])
        ->toBe('Kelas '.$schoolClass->name)
        ->not->toContain('NIS')
        ->not->toContain('•');

    $card = Livewire::test(PaymentShow::class, ['id' => $payment->id]);

    $card->assertSee($renderer->receipts[0]['identityContext'])
        ->assertDontSee('NIS')
        ->assertDontSeeHtml('&bull; Kelas');
});

it('menampilkan Calon Siswa tanpa NIS dan kelas untuk Student dengan enrollment masa depan', function () {
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
        ->toBe('Calon Siswa')
        ->not->toContain('NIS')
        ->not->toContain('Kelas')
        ->not->toContain($schoolClass->name);

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee($renderer->receipts[0]['identityContext'])
        ->assertDontSee('NIS')
        ->assertDontSee('Kelas '.$schoolClass->name);
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

it('menampilkan kelas tanpa NIS untuk Student terminal dan legacy pada PDF', function (string $status) {
    $schoolClass = SchoolClass::factory()->create(['level' => 6]);
    $schoolClass->update(['name' => '6A']);
    $student = Student::factory()->create([
        'nis' => '20260099',
        'class_id' => $schoolClass->id,
        'status' => $status,
    ]);
    $payment = createAuthorizationReceipt(User::factory()->create(), ['student_id' => $student->id]);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['identityContext'])
        ->toBe('Kelas 6A')
        ->not->toContain('NIS')
        ->not->toContain('•');

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee($renderer->receipts[0]['identityContext'])
        ->assertDontSee('NIS')
        ->assertDontSeeHtml('&bull; Kelas');
})->with([
    'alumni/lulus' => 'lulus',
    'pindah' => 'pindah',
    'legacy aktif tanpa enrollment' => 'aktif',
]);

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

    expect($receipt['identityContext'])->toBe('Kelas '.$student->schoolClass->name)
        ->and($html)->toContain('<title>Kwitansi Pembayaran - YPI Nurrahim An-Nur</title>')
        ->and($html)->toContain('<div class="eyebrow">YPI Nurrahim An-Nur</div>')
        ->and($html)->toContain('<div class="info-copy">YPI An-Nur Nurrahim</div>')
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

    $cardHtml = Livewire::test(PaymentShow::class, ['id' => $payment->id])->html();

    expect($cardHtml)
        ->toContain('<div class="receipt-meta-title text-headline-sm font-bold text-on-surface mt-1">Tunai</div>')
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

    $cardHtml = Livewire::test(PaymentShow::class, ['id' => $payment->id])->html();

    expect($cardHtml)
        ->toContain('<div class="receipt-meta-title text-headline-sm font-bold text-on-surface mt-1">BSI</div>')
        ->not->toContain('BSI -')
        ->not->toContain('<div class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5"></div>');
});

it('tidak menampilkan blok otorisasi pembuat kwitansi pada detail web Student', function () {
    $creator = User::factory()->create(['name' => 'Arif Hamdani', 'position' => 'Bendahara']);
    $payment = createAuthorizationReceipt($creator);

    $component = Livewire::actingAs($creator)
        ->test(PaymentShow::class, ['id' => $payment->id])
        ->assertDontSee('Bekasi, 08 September 2026')
        ->assertDontSee('Pembuat Kwitansi')
        ->assertDontSee('Bendahara')
        ->assertDontSee('Arif Hamdani')
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

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();

    expect($html)
        ->toContain('<div class="authorization-label">Super Admin</div>')
        ->not->toContain('authorization-role');
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

it('menempatkan jabatan di atas garis dan nama di bawah garis pada PDF Student', function () {
    $creator = User::factory()->create(['name' => 'Arif Hamdani', 'position' => 'Bendahara']);
    $payment = createAuthorizationReceipt($creator);
    $renderer = fakeStudentReceiptPrintRenderer();

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.print', $payment))
        ->assertOk();

    expect($renderer->receipts[0]['creatorName'])->toBe('Arif Hamdani')
        ->and($renderer->receipts[0]['creatorPosition'])->toBe('Bendahara');

    $html = view('receipts.pdf', ['receipt' => $renderer->receipts[0]])->render();
    $positionIndex = strpos($html, '<div class="authorization-label">Bendahara</div>');
    $stampIndex = strpos($html, 'class="authorization-stamp"');
    $nameIndex = strpos($html, '<div class="authorization-name">Arif Hamdani</div>');

    expect($html)
        ->toContain('Bekasi, 08 September 2026')
        ->toContain('<div class="authorization-label">Bendahara</div>')
        ->toContain('Arif Hamdani')
        ->not->toContain('Pembuat Kwitansi')
        ->not->toContain('Admin Keuangan')
        ->not->toContain('authorization-role')
        ->toContain('.authorization-name { position: relative; z-index: 2; padding-top: 5px; border-top: 1px solid #94a3b8;')
        ->toContain('images/stample.png')
        ->toContain('authorization-stamp')
        ->toContain('authorization-name')
        ->and(substr_count($html, 'Bendahara'))->toBe(1)
        ->and($positionIndex)->not->toBeFalse()
        ->and($stampIndex)->not->toBeFalse()
        ->and($nameIndex)->not->toBeFalse()
        ->and($positionIndex)->toBeLessThan($stampIndex)
        ->and($stampIndex)->toBeLessThan($nameIndex);
});

it('tetap merender identitas kelas tanpa NIS ketika NIS siswa null', function () {
    $creator = User::factory()->create();
    $student = Student::factory()->create(['nis' => null]);
    $payment = createAuthorizationReceipt($creator, ['student_id' => $student->id]);

    $student->load('schoolClass');

    Livewire::test(PaymentShow::class, ['id' => $payment->id])
        ->assertSee('Kelas '.$student->schoolClass->name)
        ->assertDontSee('NIS')
        ->assertDontSeeHtml('&bull; Kelas');
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
