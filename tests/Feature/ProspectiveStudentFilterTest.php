<?php

use App\Enums\ProspectiveStudentStatus;
use App\Livewire\ProspectiveStudentManagement;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Livewire;

function makeFilterProspAcademicYear(string $year = '2027/2028'): AcademicYear
{
    $startYear = (int) substr($year, 0, 4);

    return AcademicYear::firstOrCreate(
        ['year' => $year],
        [
            'is_active' => false,
            'start_date' => $startYear.'-07-01',
            'end_date' => ($startYear + 1).'-06-30',
        ]
    );
}

function makeFilterProspClass(int $level = 7): SchoolClass
{
    return SchoolClass::factory()->create(['level' => $level]);
}

function makeFilterProspStudent(string $name, int $academicYearId, int $classId, string $status = ProspectiveStudentStatus::Registered->value): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create([
        'nama_lengkap' => $name,
        'academic_year_id' => $academicYearId,
        'school_class_id' => $classId,
        'status' => $status,
    ]);
}

function makeFilterProspBill(ProspectiveStudent $ps, int $amount = 400000, ?int $paymentTypeId = null): ProspectiveStudentBill
{
    return ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $ps->id,
        'amount' => $amount,
        'payment_type_id' => $paymentTypeId ?? PaymentType::factory(),
    ]);
}

function payFilterProspBill(ProspectiveStudentBill $bill, int $amount, bool $cancelled = false): void
{
    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $bill->prospective_student_id,
        'total_amount' => $amount,
        'status' => $cancelled ? ProspectiveStudentPayment::STATUS_CANCELLED : ProspectiveStudentPayment::STATUS_ACTIVE,
        'cancelled_at' => $cancelled ? now() : null,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'amount' => $amount,
    ]);
}

// -------------------------------------------------------------------
// Tahun Ajaran
// -------------------------------------------------------------------

it('filter tahun ajaran menampilkan hanya calon siswa di tahun tersebut', function () {
    $ay2027 = makeFilterProspAcademicYear('2027/2028');
    $ay2028 = makeFilterProspAcademicYear('2028/2029');
    $class = makeFilterProspClass();

    makeFilterProspStudent('Tahun Satu', $ay2027->id, $class->id);
    makeFilterProspStudent('Tahun Dua', $ay2028->id, $class->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterAcademicYearId', (string) $ay2027->id)
        ->assertSee('Tahun Satu')
        ->assertDontSee('Tahun Dua');
});

it('tanpa filter tahun ajaran menampilkan semua tahun', function () {
    $ay2027 = makeFilterProspAcademicYear('2027/2028');
    $ay2028 = makeFilterProspAcademicYear('2028/2029');
    $class = makeFilterProspClass();

    makeFilterProspStudent('Tahun Satu', $ay2027->id, $class->id);
    makeFilterProspStudent('Tahun Dua', $ay2028->id, $class->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('Tahun Satu')
        ->assertSee('Tahun Dua');
});

// -------------------------------------------------------------------
// Jenjang
// -------------------------------------------------------------------

it('filter jenjang TK menampilkan hanya calon siswa berjenjang TK', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    makeFilterProspStudent('Siapa TK', $ay->id, makeFilterProspClass(-1)->id);
    makeFilterProspStudent('Siapa SD', $ay->id, makeFilterProspClass(1)->id);
    makeFilterProspStudent('Siapa SMP', $ay->id, makeFilterProspClass(7)->id);
    makeFilterProspStudent('Siapa SMA', $ay->id, makeFilterProspClass(10)->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterLevel', 'TK')
        ->assertSee('Siapa TK')
        ->assertDontSee('Siapa SD')
        ->assertDontSee('Siapa SMP')
        ->assertDontSee('Siapa SMA');
});

it('filter jenjang SD menampilkan hanya calon siswa berjenjang SD', function () {
    $ay = makeFilterProspAcademicYear();

    makeFilterProspStudent('Siapa TK', $ay->id, makeFilterProspClass(-1)->id);
    makeFilterProspStudent('Siapa SD', $ay->id, makeFilterProspClass(1)->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterLevel', 'SD')
        ->assertSee('Siapa SD')
        ->assertDontSee('Siapa TK');
});

it('filter jenjang SMP menampilkan hanya calon siswa berjenjang SMP', function () {
    $ay = makeFilterProspAcademicYear();

    makeFilterProspStudent('Siapa SD', $ay->id, makeFilterProspClass(1)->id);
    makeFilterProspStudent('Siapa SMP', $ay->id, makeFilterProspClass(7)->id);
    makeFilterProspStudent('Siapa SMA', $ay->id, makeFilterProspClass(10)->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterLevel', 'SMP')
        ->assertSee('Siapa SMP')
        ->assertDontSee('Siapa SD')
        ->assertDontSee('Siapa SMA');
});

it('filter jenjang SMA menampilkan hanya calon siswa berjenjang SMA', function () {
    $ay = makeFilterProspAcademicYear();

    makeFilterProspStudent('Siapa SMP', $ay->id, makeFilterProspClass(7)->id);
    makeFilterProspStudent('Siapa SMA', $ay->id, makeFilterProspClass(10)->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterLevel', 'SMA')
        ->assertSee('Siapa SMA')
        ->assertDontSee('Siapa SMP');
});

// -------------------------------------------------------------------
// Kelas Tujuan
// -------------------------------------------------------------------

it('filter kelas menampilkan hanya calon siswa di kelas tersebut', function () {
    $ay = makeFilterProspAcademicYear();
    $classA = makeFilterProspClass(7);
    $classB = makeFilterProspClass(7);

    makeFilterProspStudent('Kelas Satu', $ay->id, $classA->id);
    makeFilterProspStudent('Kelas Dua', $ay->id, $classB->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterClassId', (string) $classA->id)
        ->assertSee('Kelas Satu')
        ->assertDontSee('Kelas Dua');
});

it('opsi kelas mengikuti jenjang yang dipilih', function () {
    $sdClass = makeFilterProspClass(1);
    $smpClass = makeFilterProspClass(7);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterLevel', 'SMP')
        ->assertSee($smpClass->name.' (Tingkat 7)')
        ->assertDontSee($sdClass->name.' (Tingkat 1)');
});

it('mengubah jenjang mereset kelas yang tidak kompatibel', function () {
    $ay = makeFilterProspAcademicYear();
    $smpClass = makeFilterProspClass(7);

    makeFilterProspStudent('Siswa SMP', $ay->id, $smpClass->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterClassId', (string) $smpClass->id)
        ->set('filterLevel', 'SD')
        ->assertSet('filterClassId', '')
        ->assertDontSee('Siswa SMP');
});

// -------------------------------------------------------------------
// Status Pendaftaran
// -------------------------------------------------------------------

it('filter status Terdaftar menampilkan hanya calon siswa terdaftar', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    makeFilterProspStudent('Saya Terdaftar', $ay->id, $class->id, ProspectiveStudentStatus::Registered->value);
    makeFilterProspStudent('Saya Dikonversi', $ay->id, $class->id, ProspectiveStudentStatus::Converted->value);
    makeFilterProspStudent('Saya Dibatalkan', $ay->id, $class->id, ProspectiveStudentStatus::Cancelled->value);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterStatus', ProspectiveStudentStatus::Registered->value)
        ->assertSee('Saya Terdaftar')
        ->assertDontSee('Saya Dikonversi')
        ->assertDontSee('Saya Dibatalkan');
});

it('filter status Dikonversi menampilkan hanya calon siswa yang dikonversi', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    makeFilterProspStudent('Saya Terdaftar', $ay->id, $class->id, ProspectiveStudentStatus::Registered->value);
    makeFilterProspStudent('Saya Dikonversi', $ay->id, $class->id, ProspectiveStudentStatus::Converted->value);
    makeFilterProspStudent('Saya Dibatalkan', $ay->id, $class->id, ProspectiveStudentStatus::Cancelled->value);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterStatus', ProspectiveStudentStatus::Converted->value)
        ->assertSee('Saya Dikonversi')
        ->assertDontSee('Saya Terdaftar')
        ->assertDontSee('Saya Dibatalkan');
});

it('filter status Dibatalkan menampilkan hanya calon siswa yang dibatalkan', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    makeFilterProspStudent('Saya Terdaftar', $ay->id, $class->id, ProspectiveStudentStatus::Registered->value);
    makeFilterProspStudent('Saya Dibatalkan', $ay->id, $class->id, ProspectiveStudentStatus::Cancelled->value);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterStatus', ProspectiveStudentStatus::Cancelled->value)
        ->assertSee('Saya Dibatalkan')
        ->assertDontSee('Saya Terdaftar');
});

// -------------------------------------------------------------------
// Status Tagihan
// -------------------------------------------------------------------

it('filter tagihan Belum Ada Tagihan menampilkan calon siswa tanpa tagihan', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    $noBill = makeFilterProspStudent('Tanpa Bill', $ay->id, $class->id);
    $hasBill = makeFilterProspStudent('Punya Bill', $ay->id, $class->id);

    makeFilterProspBill($hasBill);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'none')
        ->assertSee('Tanpa Bill')
        ->assertDontSee('Punya Bill');
});

it('filter tagihan Belum Bayar menampilkan calon siswa dengan tagihan belum dibayar', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    $unpaid = makeFilterProspStudent('Belum Dibayar', $ay->id, $class->id);
    $partial = makeFilterProspStudent('Dibayar Sebagian', $ay->id, $class->id);

    $unpaidBill = makeFilterProspBill($unpaid);
    $partialBill = makeFilterProspBill($partial);
    payFilterProspBill($partialBill, 150000);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'unpaid')
        ->assertSee('Belum Dibayar')
        ->assertDontSee('Dibayar Sebagian');
});

it('filter tagihan Sebagian menampilkan calon siswa dengan pembayaran sebagian', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    $partial = makeFilterProspStudent('Dibayar Sebagian', $ay->id, $class->id);
    $paid = makeFilterProspStudent('Dibayar Lunas', $ay->id, $class->id);

    $partialBill = makeFilterProspBill($partial);
    payFilterProspBill($partialBill, 150000);

    $paidBill = makeFilterProspBill($paid);
    payFilterProspBill($paidBill, 400000);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'partial')
        ->assertSee('Dibayar Sebagian')
        ->assertDontSee('Dibayar Lunas');
});

it('filter tagihan Lunas menampilkan calon siswa dengan tagihan lunas', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    $paid = makeFilterProspStudent('Dibayar Lunas', $ay->id, $class->id);
    $partial = makeFilterProspStudent('Dibayar Sebagian', $ay->id, $class->id);

    $paidBill = makeFilterProspBill($paid);
    payFilterProspBill($paidBill, 400000);

    $partialBill = makeFilterProspBill($partial);
    payFilterProspBill($partialBill, 150000);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'paid')
        ->assertSee('Dibayar Lunas')
        ->assertDontSee('Dibayar Sebagian');
});

it('pembayaran yang dibatalkan tidak dihitung sebagai tagihan lunas', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    $fullyPaid = makeFilterProspStudent('Lunas Aktif', $ay->id, $class->id);
    $cancelled = makeFilterProspStudent('Lunas Dibatalkan', $ay->id, $class->id);

    $paidBill = makeFilterProspBill($fullyPaid);
    payFilterProspBill($paidBill, 400000);

    $cancelledBill = makeFilterProspBill($cancelled);
    payFilterProspBill($cancelledBill, 400000, cancelled: true);

    expect($cancelled->fresh()->bill_status)->toBe('unpaid');

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'paid')
        ->assertSee('Lunas Aktif')
        ->assertDontSee('Lunas Dibatalkan');

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'unpaid')
        ->assertSee('Lunas Dibatalkan')
        ->assertDontSee('Lunas Aktif');
});

it('status tagihan menangani beberapa tagihan dengan benar', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    $mixed = makeFilterProspStudent('Campuran Satu Lunas', $ay->id, $class->id);
    $allPaid = makeFilterProspStudent('Semua Lunas', $ay->id, $class->id);

    $billA = makeFilterProspBill($mixed);
    payFilterProspBill($billA, 400000);
    $billB = makeFilterProspBill($mixed);
    // billB sengaja tidak dibayar

    $billC = makeFilterProspBill($allPaid);
    payFilterProspBill($billC, 400000);
    $billD = makeFilterProspBill($allPaid);
    payFilterProspBill($billD, 400000);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'partial')
        ->assertSee('Campuran Satu Lunas')
        ->assertDontSee('Semua Lunas');

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'paid')
        ->assertSee('Semua Lunas')
        ->assertDontSee('Campuran Satu Lunas');

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterBillStatus', 'unpaid')
        ->assertDontSee('Campuran Satu Lunas')
        ->assertDontSee('Semua Lunas');
});

// -------------------------------------------------------------------
// Kombinasi
// -------------------------------------------------------------------

it('kombinasi tahun ajaran dan jenjang', function () {
    $ay2027 = makeFilterProspAcademicYear('2027/2028');
    $ay2028 = makeFilterProspAcademicYear('2028/2029');
    $smpClass = makeFilterProspClass(7);
    $sdClass = makeFilterProspClass(1);

    makeFilterProspStudent('Kombinasi SMP 2027', $ay2027->id, $smpClass->id);
    makeFilterProspStudent('Kombinasi SD 2027', $ay2027->id, $sdClass->id);
    makeFilterProspStudent('Kombinasi SMP 2028', $ay2028->id, $smpClass->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterAcademicYearId', (string) $ay2027->id)
        ->set('filterLevel', 'SMP')
        ->assertSee('Kombinasi SMP 2027')
        ->assertDontSee('Kombinasi SD 2027')
        ->assertDontSee('Kombinasi SMP 2028');
});

it('kombinasi jenjang dan kelas', function () {
    $ay = makeFilterProspAcademicYear();
    $classA = makeFilterProspClass(7);
    $classB = makeFilterProspClass(7);

    makeFilterProspStudent('Kombinasi Kelas A', $ay->id, $classA->id);
    makeFilterProspStudent('Kombinasi Kelas B', $ay->id, $classB->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterLevel', 'SMP')
        ->set('filterClassId', (string) $classA->id)
        ->assertSee('Kombinasi Kelas A')
        ->assertDontSee('Kombinasi Kelas B');
});

it('kombinasi status pendaftaran dan status tagihan', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);

    $convertedPaid = makeFilterProspStudent('Konversi Lunas', $ay->id, $class->id, ProspectiveStudentStatus::Converted->value);
    $registeredPaid = makeFilterProspStudent('Registrasi Lunas', $ay->id, $class->id, ProspectiveStudentStatus::Registered->value);

    $bill = makeFilterProspBill($convertedPaid);
    payFilterProspBill($bill, 400000);

    $bill2 = makeFilterProspBill($registeredPaid);
    payFilterProspBill($bill2, 400000);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterStatus', ProspectiveStudentStatus::Converted->value)
        ->set('filterBillStatus', 'paid')
        ->assertSee('Konversi Lunas')
        ->assertDontSee('Registrasi Lunas');
});

it('pencarian bekerja bersama semua filter', function () {
    $ay2027 = makeFilterProspAcademicYear('2027/2028');
    $smpClass = makeFilterProspClass(7);
    $sdClass = makeFilterProspClass(1);

    $dikaSmp = makeFilterProspStudent('Dika Pratama', $ay2027->id, $smpClass->id);
    makeFilterProspStudent('Dika Santoso', $ay2027->id, $sdClass->id);
    makeFilterProspStudent('Budi SMP', $ay2027->id, $smpClass->id);

    makeFilterProspBill($dikaSmp);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('search', 'dika')
        ->set('filterAcademicYearId', (string) $ay2027->id)
        ->set('filterLevel', 'SMP')
        ->set('filterBillStatus', 'unpaid')
        ->assertSee('Dika Pratama')
        ->assertDontSee('Dika Santoso')
        ->assertDontSee('Budi SMP');
});

// -------------------------------------------------------------------
// Pagination & UX state
// -------------------------------------------------------------------

it('mengubah filter mereset pagination ke halaman 1', function () {
    $class = makeFilterProspClass(7);

    ProspectiveStudent::factory()->count(25)->create(['school_class_id' => $class->id]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('paginators.page', 2)
        ->assertSet('paginators.page', 2)
        ->set('filterStatus', ProspectiveStudentStatus::Registered->value)
        ->assertSet('paginators.page', 1);
});

it('tidak merender tombol Reset', function () {
    Livewire::test(ProspectiveStudentManagement::class)
        ->assertDontSee('Reset');
});

it('filter tidak dipersistenkan lewat URL', function () {
    $reflection = new ReflectionClass(ProspectiveStudentManagement::class);

    foreach (['filterAcademicYearId', 'filterLevel', 'filterClassId', 'filterStatus', 'filterBillStatus'] as $prop) {
        expect(count($reflection->getProperty($prop)->getAttributes(Url::class)))->toBe(0);
    }
});

it('list dengan filter tagihan tetap pada jumlah kueri konstan (no N+1)', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);
    $paymentType = PaymentType::factory()->create();

    ProspectiveStudent::factory()
        ->count(25)
        ->create(['academic_year_id' => $ay->id, 'school_class_id' => $class->id])
        ->each(fn ($ps) => makeFilterProspBill($ps, paymentTypeId: $paymentType->id));

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ProspectiveStudentManagement::class, [
        'filterLevel' => 'SMP',
        'filterBillStatus' => 'unpaid',
    ])
        ->assertSet('filterLevel', 'SMP')
        ->assertSet('filterBillStatus', 'unpaid');

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(15);
});

it('filter tagihan memakai ekspresi agregat MAX pada HAVING dan parameter terikat', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);
    $ps = makeFilterProspStudent('Siswa Sebagian', $ay->id, $class->id);
    $bill = makeFilterProspBill($ps);
    payFilterProspBill($bill, 150000);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(ProspectiveStudentManagement::class, ['filterBillStatus' => 'partial']);

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $sql = $queries->pluck('query')->implode(' ');

    expect($sql)->toContain('MAX(b.amount)')
        ->and($sql)->not()->toContain('< b.amount')
        ->and($sql)->not()->toContain("= 'active'");

    $bindings = $queries->flatMap(fn ($query) => $query['bindings'])->unique()->values();

    expect($bindings)->toContain(ProspectiveStudentPayment::STATUS_ACTIVE);
});

it('halaman daftar calon siswa merender tanpa SQL exception untuk semua filter status tagihan', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);
    $ps = makeFilterProspStudent('Siswa Filter', $ay->id, $class->id);
    $bill = makeFilterProspBill($ps);
    payFilterProspBill($bill, 150000);

    foreach (['none', 'unpaid', 'partial', 'paid'] as $billStatus) {
        Livewire::test(ProspectiveStudentManagement::class, ['filterBillStatus' => $billStatus])
            ->assertSee('Data Calon Siswa');
    }
});

it('route halaman daftar calon siswa merender tanpa SQL exception', function () {
    $ay = makeFilterProspAcademicYear();
    $class = makeFilterProspClass(7);
    $ps = makeFilterProspStudent('Siswa Route', $ay->id, $class->id);
    $bill = makeFilterProspBill($ps);
    payFilterProspBill($bill, 400000);

    $this->actingAs(User::factory()->create())
        ->get(route('calon-siswa.index'))
        ->assertOk()
        ->assertSee('Data Calon Siswa')
        ->assertSee('Siswa Route');
});
