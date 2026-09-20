<?php

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Livewire\StudentDetail;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\StudentBillStatementService;
use Illuminate\Support\Str;
use Livewire\Livewire;

function payBillForStatement(StudentBill $bill, int $amount, string $status = Payment::STATUS_ACTIVE): void
{
    $payment = Payment::create([
        'receipt_number' => 'KWT-ST-'.uniqid(),
        'student_id' => $bill->student_id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-08-05',
        'total_amount' => $amount,
        'payment_method' => 'transfer',
        'status' => $status,
        'created_by' => User::factory()->create()->id,
    ]);

    PaymentDetail::create([
        'payment_id' => $payment->id,
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => $bill->period_month,
        'period_year' => $bill->period_year,
        'academic_year' => $bill->academic_year,
        'amount' => $amount,
    ]);
}

function renderBillStatement(Student $student, string $academicYear = ''): array
{
    $report = app(StudentBillStatementService::class)->generate($student, $academicYear);

    return [$report, view('reports.student-bills-pdf', ['report' => $report])->render()];
}

function pss_pdfPageCount(string $pdf): int
{
    preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf, $matches);

    return count($matches[0]);
}

it('protects the route and 404s invalid students', function () {
    $this->get(route('siswa.bills.pdf', ['student' => 1]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get('/siswa/999999/tagihan/cetak-pdf')
        ->assertNotFound();
});

it('menolak academic year yang tidak valid', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $this->actingAs(User::factory()->create())
        ->get(route('siswa.bills.pdf', ['student' => $student->id, 'academic_year' => str_repeat('a', 20)]))
        ->assertSessionHasErrors('academic_year');
});

it('menstream PDF F4B valid untuk siswa yang ada', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');
    $spp = makeBillType('SPP');
    makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('siswa.bills.pdf', ['student' => $student->id, 'academic_year' => '2026/2027']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=daftar-tagihan-'.Str::slug($student->nama_lengkap).'-2026-2027-'.now()->format('d-m-Y').'.pdf');

    expect($response->getContent())->toStartWith('%PDF-')
        ->and(public_path('images/annur_logo2.png'))->toBeFile();
});

it('memakai slug nama siswa dan tanggal cetak WIB pada nama file PDF', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');
    $student->update(['nama_lengkap' => 'SITI KHADIJAH ZAFRAN', 'nis' => null]);

    $spp = makeBillType('SPP');
    makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('siswa.bills.pdf', ['student' => $student->id, 'academic_year' => '2026/2027']));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=daftar-tagihan-siti-khadijah-zafran-2026-2027-'.now()->format('d-m-Y').'.pdf');
});

it('memakai penanda "semua" saat tahun ajaran kosong', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');
    $student->update(['nama_lengkap' => 'SITI KHADIJAH ZAFRAN']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('siswa.bills.pdf', ['student' => $student->id]));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=daftar-tagihan-siti-khadijah-zafran-semua-'.now()->format('d-m-Y').'.pdf');
});

it('menampilkan identitas siswa pada statement', function () {
    [$student, $schoolClass] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');
    $student->update(['nis' => '20260001']);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    expect($report['student_name'])->toBe($student->nama_lengkap)
        ->and($report['nis'])->toBe('20260001')
        ->and($report['class_name'])->toBe($schoolClass->name)
        ->and($report['academic_year_label'])->toBe('2026/2027');

    expect($html)
        ->toContain('DAFTAR TAGIHAN SISWA')
        ->toContain($student->nama_lengkap)
        ->toContain('20260001')
        ->toContain($schoolClass->name)
        ->toContain('2026/2027');
});

it('menampilkan seluruh tagihan bulanan termasuk yang lunas', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');
    $osis = makeBillType('OSIS');

    payBillForStatement(
        makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026),
        1000000,
    );
    makeMonthlyBill($student, $ekskul, 200000, month: 7, year: 2026);
    makeMonthlyBill($student, $osis, 50000, month: 7, year: 2026);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    $july = $report['sections']['monthly'][0];
    expect($report['sections']['monthly'])->toHaveCount(1)
        ->and($july['period_label'])->toBe('Juli 2026')
        ->and($july['rows'])->toHaveCount(3)
        ->and(collect($july['rows'])->pluck('payment_type_name')->all())
        ->toBe(['Ekskul', 'OSIS', 'SPP'])
        ->and(collect($july['rows'])->pluck('status')->contains('Lunas'))
        ->toBeTrue();

    expect($html)
        ->toContain('TAGIHAN BULANAN')
        ->toContain('SPP')
        ->toContain('Ekskul')
        ->toContain('OSIS')
        ->toContain('Juli 2026')
        ->toContain('Lunas')
        ->toContain('Belum Bayar')
        ->not->toContain('TAGIHAN TAHUNAN')
        ->not->toContain('TAGIHAN SEKALI BAYAR');
});

it('menampilkan tagihan sebagian dengan terbayar dan sisa yang benar', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026);
    payBillForStatement($bill, 400000);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    $row = $report['sections']['monthly'][0]['rows'][0];
    expect((float) $row['target'])->toBe(1000000.0)
        ->and((float) $row['paid'])->toBe(400000.0)
        ->and((float) $row['remaining'])->toBe(600000.0)
        ->and($row['status'])->toBe('Sebagian');

    expect($html)
        ->toContain('Rp 1.000.000')
        ->toContain('Rp 400.000')
        ->toContain('Rp 600.000')
        ->toContain('Sebagian');
});

it('menampilkan tagihan tahunan per baris', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $buku = makeBillType('Uang Buku');
    $kegiatan = makeBillType('Uang Kegiatan');
    makeBillRate($buku, 8, 2500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($kegiatan, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);

    $paidBuku = makeYearlyBill($student, $buku, 2500000, '2026/2027');
    payBillForStatement($paidBuku, 2500000);
    makeYearlyBill($student, $kegiatan, 500000, '2026/2027');

    [$report, $html] = renderBillStatement($student, '2026/2027');

    expect($report['sections']['yearly'])->toHaveCount(2);

    expect($html)
        ->toContain('TAGIHAN TAHUNAN')
        ->toContain('Uang Buku')
        ->toContain('Uang Kegiatan')
        ->toContain('Tahun Ajaran 2026/2027')
        ->toContain('Lunas')
        ->toContain('Belum Bayar');
});

it('menampilkan tagihan sekali bayar per baris', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);
    $bill = makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');
    payBillForStatement($bill, 5000000);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    expect($report['sections']['one_time'])->toHaveCount(1)
        ->and($report['sections']['one_time'][0]['status'])->toBe('Lunas');

    expect($html)
        ->toContain('TAGIHAN SEKALI BAYAR')
        ->toContain('Uang Pangkal')
        ->toContain('Tahun Ajaran 2026/2027')
        ->toContain('Lunas');
});

it('tidak menampilkan jenis pembayaran yang tidak punya tagihan', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');
    makeBillRate($ekskul, 8, 200000);

    makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    expect($report['sections']['monthly'])->toHaveCount(1)
        ->and($report['sections']['monthly'][0]['rows'][0]['payment_type_name'])->toBe('SPP');

    expect($html)
        ->toContain('SPP')
        ->not->toContain('Ekskul')
        ->not->toContain('OSIS');
});

it('menempatkan tipe pembayaran dinamis pada section yang benar', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $asuransi = makeBillType('Asuransi Kesehatan');
    $aqiqah = makeBillType('Biaya Aqiqah');
    makeMonthlyBill($student, $asuransi, 75000, month: 7, year: 2026);
    makeOneTimeBill($student, $aqiqah, 250000, '2026/2027');

    [$report, $html] = renderBillStatement($student, '2026/2027');

    expect($report['sections']['monthly'][0]['rows'][0]['payment_type_name'])->toBe('Asuransi Kesehatan')
        ->and($report['sections']['one_time'][0]['payment_type_name'])->toBe('Biaya Aqiqah');

    expect($html)
        ->toContain('Asuransi Kesehatan')
        ->toContain('Biaya Aqiqah')
        ->toContain('TAGIHAN BULANAN')
        ->toContain('TAGIHAN SEKALI BAYAR');
});

it('membatasi populasi sesuai tahun ajaran terpilih', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');
    AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30'],
    );

    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');
    makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026);
    makeMonthlyBill($student, $ekskul, 200000, month: 7, year: 2027);

    [$report26, $html26] = renderBillStatement($student, '2026/2027');
    [$report27, $html27] = renderBillStatement($student, '2027/2028');

    expect($report26['sections']['monthly'])->toHaveCount(1)
        ->and($report26['sections']['monthly'][0]['rows'][0]['payment_type_name'])->toBe('SPP');
    expect($html26)->toContain('SPP')->not->toContain('Ekskul');

    expect($report27['sections']['monthly'])->toHaveCount(1)
        ->and($report27['sections']['monthly'][0]['rows'][0]['payment_type_name'])->toBe('Ekskul');
    expect($html27)->toContain('Ekskul')->not->toContain('SPP');
});

it('menggunakan snapshot tagihan yang tersimpan meski tarif berubah', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    makeBillRate($spp, 8, 500000);
    $bill = makeMonthlyBill($student, $spp, 500000, month: 7, year: 2026);
    makeBillRate($spp, 8, 900000, ['effective_from' => '2026-08-01']);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    expect((float) $report['sections']['monthly'][0]['rows'][0]['target'])->toBe(500000.0);

    expect($html)
        ->toContain('Rp 500.000')
        ->not->toContain('Rp 900.000');
});

it('tidak menghitung alokasi pembayaran yang dibatalkan', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026);
    payBillForStatement($bill, 1000000, Payment::STATUS_CANCELLED);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    $row = $report['sections']['monthly'][0]['rows'][0];
    expect((float) $row['paid'])->toBe(0.0)
        ->and((float) $row['remaining'])->toBe(1000000.0)
        ->and($row['status'])->toBe('Belum Bayar');

    expect($html)
        ->toContain('Belum Bayar')
        ->not->toContain('Lunas')
        ->and(substr_count($html, '<td class="money">-</td>'))->toBe(2);
});

it('total selalu rekonsiliasi: tagihan = terbayar + sisa', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');
    $buku = makeBillType('Uang Buku');
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($buku, 8, 2500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);

    payBillForStatement(makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026), 400000);
    makeMonthlyBill($student, $ekskul, 200000, month: 7, year: 2026);
    payBillForStatement(makeYearlyBill($student, $buku, 2500000, '2026/2027'), 2500000);
    makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    [$report, $html] = renderBillStatement($student, '2026/2027');

    expect($report['totals']['target'])->toBe(
        round($report['totals']['paid'] + $report['totals']['remaining'], 2),
    );

    expect($html)->not->toContain('RINGKASAN');
});

it('NIS yang null tetap menghasilkan PDF tanpa error', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');
    $student->update(['nis' => null]);

    $spp = makeBillType('SPP');
    makeMonthlyBill($student, $spp, 1000000, month: 7, year: 2026);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    expect($report['nis'])->toBeNull();

    expect($html)
        ->toContain('NIS')
        ->toContain($student->nama_lengkap);

    $this->actingAs(User::factory()->create())
        ->get(route('siswa.bills.pdf', ['student' => $student->id]))
        ->assertOk();
});

it('memakai kelas enrollment historis untuk tahun ajaran terpilih', function () {
    $level = SchoolLevel::SMP;

    $ay1 = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $ay2 = AcademicYear::firstOrCreate(
        ['year' => '2027/2028'],
        ['is_active' => false, 'start_date' => '2027-07-01', 'end_date' => '2028-06-30'],
    );

    $class1 = SchoolClass::factory()->create(['level' => 8]);
    $class2 = SchoolClass::factory()->create(['level' => 8]);
    $student = Student::factory()->create(['class_id' => $class2->id]);

    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $ay1->id,
        'school_class_id' => $class1->id,
        'status' => 'active',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $student->id,
        'academic_year_id' => $ay2->id,
        'school_class_id' => $class2->id,
        'status' => 'active',
    ]);

    [$report26, $html26] = renderBillStatement($student, '2026/2027');
    [$report27, $html27] = renderBillStatement($student, '2027/2028');

    expect($report26['class_name'])->toBe($class1->name)
        ->and($report27['class_name'])->toBe($class2->name);

    expect($html26)->toContain($class1->name)
        ->and($html27)->toContain($class2->name);
});

it('menampilkan tombol Cetak Tagihan dengan tahun ajaran yang dipilih', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->set('selectedAcademicYear', '2026/2027')
        ->assertSee('Cetak Tagihan')
        ->assertSeeHtml(route('siswa.bills.pdf', ['student' => $student->id, 'academic_year' => '2026/2027']));
});

it('mengelompokkan tagihan per bulan dengan subtotal masing-masing', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');

    payBillForStatement(makeMonthlyBill($student, $spp, 800000, month: 7, year: 2026), 800000);
    makeMonthlyBill($student, $ekskul, 135000, month: 7, year: 2026);
    makeMonthlyBill($student, $spp, 800000, month: 8, year: 2026);
    makeMonthlyBill($student, $ekskul, 135000, month: 8, year: 2026);

    [$report, $html] = renderBillStatement($student, '2026/2027');

    $july = $report['sections']['monthly'][0];
    $august = $report['sections']['monthly'][1];

    expect(collect($report['sections']['monthly'])->pluck('period_label')->all())
        ->toBe(['Juli 2026', 'Agustus 2026']);

    expect($july['rows'])->toHaveCount(2)
        ->and(collect($july['rows'])->pluck('payment_type_name')->all())->toBe(['Ekskul', 'SPP'])
        ->and(collect($july['rows'])->pluck('status')->contains('Lunas'))->toBeTrue()
        ->and((float) $july['totals']['target'])->toBe(935000.0)
        ->and((float) $july['totals']['paid'])->toBe(800000.0)
        ->and((float) $july['totals']['remaining'])->toBe(135000.0)
        ->and($july['totals']['target'])->toBe(round($july['totals']['paid'] + $july['totals']['remaining'], 2));

    expect($august['rows'])->toHaveCount(2)
        ->and(collect($august['rows'])->pluck('status')->contains('Belum Bayar'))->toBeTrue()
        ->and((float) $august['totals']['paid'])->toBe(0.0)
        ->and((float) $august['totals']['remaining'])->toBe(935000.0)
        ->and($august['totals']['target'])->toBe(round($august['totals']['paid'] + $august['totals']['remaining'], 2));

    expect($html)
        ->toContain('Juli 2026')
        ->toContain('Agustus 2026')
        ->toContain('TOTAL');
});

it('menghasilkan statement billbook normal sebagai satu halaman F4B portrait tanpa gaya pemotongan', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');
    $osis = makeBillType('OSIS');
    $buku = makeBillType('Uang Buku');
    $kegiatan = makeBillType('Uang Kegiatan');
    $pangkal = makeBillType('Uang Pangkal');
    makeBillRate($buku, 8, 2500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($kegiatan, 8, 500000, ['billing_frequency' => BillFrequency::Yearly]);
    makeBillRate($pangkal, 8, 5000000, ['billing_frequency' => BillFrequency::OneTime]);

    foreach ([7, 8, 9, 10, 11, 12] as $month) {
        makeMonthlyBill($student, $spp, 800000, month: $month, year: 2026);
        makeMonthlyBill($student, $ekskul, 135000, month: $month, year: 2026);
        makeMonthlyBill($student, $osis, 100000, month: $month, year: 2026);
    }

    payBillForStatement(makeMonthlyBill($student, $spp, 800000, month: 1, year: 2027), 400000);
    makeMonthlyBill($student, $ekskul, 135000, month: 1, year: 2027);
    makeMonthlyBill($student, $osis, 100000, month: 1, year: 2027);
    makeMonthlyBill($student, $spp, 800000, month: 2, year: 2027);
    makeMonthlyBill($student, $ekskul, 135000, month: 2, year: 2027);
    makeMonthlyBill($student, $osis, 100000, month: 2, year: 2027);
    makeMonthlyBill($student, $spp, 800000, month: 3, year: 2027);
    makeMonthlyBill($student, $ekskul, 135000, month: 3, year: 2027);
    makeMonthlyBill($student, $osis, 100000, month: 3, year: 2027);
    makeMonthlyBill($student, $spp, 800000, month: 4, year: 2027);
    makeMonthlyBill($student, $ekskul, 135000, month: 4, year: 2027);
    makeMonthlyBill($student, $osis, 100000, month: 4, year: 2027);
    makeMonthlyBill($student, $spp, 800000, month: 5, year: 2027);
    makeMonthlyBill($student, $ekskul, 135000, month: 5, year: 2027);
    makeMonthlyBill($student, $osis, 100000, month: 5, year: 2027);
    makeMonthlyBill($student, $spp, 800000, month: 6, year: 2027);
    makeMonthlyBill($student, $ekskul, 135000, month: 6, year: 2027);
    makeMonthlyBill($student, $osis, 100000, month: 6, year: 2027);

    payBillForStatement(makeYearlyBill($student, $buku, 2500000, '2026/2027'), 2500000);
    makeYearlyBill($student, $kegiatan, 500000, '2026/2027');
    makeOneTimeBill($student, $pangkal, 5000000, '2026/2027');

    $response = $this->actingAs(User::factory()->create())
        ->get(route('siswa.bills.pdf', ['student' => $student->id, 'academic_year' => '2026/2027']));

    $response->assertOk();
    expect(pss_pdfPageCount($response->getContent()))->toBe(1);

    $report = app(StudentBillStatementService::class)->generate($student, '2026/2027');
    $html = view('reports.student-bills-pdf', ['report' => $report])->render();

    expect($html)
        ->toContain('@page { size: 216mm 330mm')
        ->toContain('DAFTAR TAGIHAN SISWA')
        ->toContain('TAGIHAN BULANAN')
        ->toContain('TAGIHAN TAHUNAN')
        ->toContain('TAGIHAN SEKALI BAYAR')
        ->not->toContain('RINGKASAN')
        ->not->toContain('overflow')
        ->not->toContain('overflow: hidden');

    expect($report['sections']['monthly'])->toHaveCount(12)
        ->and(collect($report['sections']['monthly'])->pluck('period_label')->all())
        ->toBe([
            'Juli 2026', 'Agustus 2026', 'September 2026', 'Oktober 2026',
            'November 2026', 'Desember 2026', 'Januari 2027', 'Februari 2027',
            'Maret 2027', 'April 2027', 'Mei 2027', 'Juni 2027',
        ]);
});

it('mengalirkan PDF billbook satu tahun penuh tanpa error', function () {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP, '2026/2027');

    $spp = makeBillType('SPP');
    $ekskul = makeBillType('Ekskul');
    $osis = makeBillType('OSIS');

    foreach (range(2026, 2027) as $year) {
        foreach (range(1, 12) as $month) {
            makeMonthlyBill($student, $spp, 800000, month: $month, year: $year);
            makeMonthlyBill($student, $ekskul, 135000, month: $month, year: $year);
            makeMonthlyBill($student, $osis, 100000, month: $month, year: $year);
        }
    }

    $response = $this->actingAs(User::factory()->create())
        ->get(route('siswa.bills.pdf', ['student' => $student->id, 'academic_year' => '2026/2027']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});
