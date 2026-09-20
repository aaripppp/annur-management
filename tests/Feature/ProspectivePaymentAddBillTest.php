<?php

use App\Enums\PaymentTypeAudience;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectivePaymentWorkspace;
use App\Models\AcademicYear;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\ProspectiveStudentBillGenerationService;
use Livewire\Livewire;

/**
 * @return array{0: PaymentType, 1: ProspectiveStudent, 2: ProspectiveStudentBill}
 */
function makeProspAddBase(int $amount = 400000, string $name = 'Formulir Pendaftaran Add'): array
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
        'nama_lengkap' => 'Dewi Add Tagihan',
        'no_telp_orang_tua' => '081234567999',
    ]);

    app(ProspectiveStudentBillGenerationService::class)->generateFor($ps);
    $bill = $ps->fresh()->bills->first();

    return [$type, $ps, $bill];
}

function makeProspAddExtraType(int $amount = 250000, string $name = 'Wawancara Add', SchoolLevel $level = SchoolLevel::SMP): PaymentType
{
    $type = PaymentType::create([
        'name' => $name,
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    PaymentTypeSchoolLevel::create([
        'payment_type_id' => $type->id,
        'school_level' => $level,
        'is_active' => true,
        'is_required' => false,
    ]);

    PaymentRate::factory()->oneTime()->create([
        'payment_type_id' => $type->id,
        'class_level' => $level === SchoolLevel::SMP ? 8 : 1,
        'amount' => $amount,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
    ]);

    return $type;
}

// -------------------------------------------------------------------
// Tambah Tagihan
// -------------------------------------------------------------------

it('menampilkan tombol Tambah Tagihan pada bagian Tagihan Pendaftaran', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertSee('Tambah Tagihan');
});

it('modal tambah tagihan terbuka dengan tahun ajaran tujuan terisi', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->assertSet('isAddOpen', true)
        ->assertSet('addAcademicYear', '2027/2028')
        ->assertSee('Tambah Tagihan Manual')
        ->assertSee('Pilih tahun ajaran');
});

it('hanya menampilkan jenis pembayaran calon siswa yang aktif di modal tambah tagihan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();

    PaymentType::create([
        'name' => 'Uang Pangkal',
        'audience' => PaymentTypeAudience::Student,
        'is_active' => true,
    ]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->assertSee($type->name)
        ->assertDontSee('Uang Pangkal');
});

it('menyaring jenis pembayaran sesuai jenjang tujuan calon siswa', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();

    $sdType = makeProspAddExtraType(100000, 'Iuran SD Add', SchoolLevel::SD);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->assertSee($type->name)
        ->assertDontSee('Iuran SD Add');
});

it('memilih jenis pembayaran mengisi nominal dari rate default', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();
    $extraType = makeProspAddExtraType();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->set('addPaymentTypeId', $extraType->id)
        ->assertSet('addAmount', '250000');
});

it('nominal kustom disimpan sebagai snapshot tanpa mengubah PaymentRate', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();
    $extraType = makeProspAddExtraType();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->set('addPaymentTypeId', $extraType->id)
        ->set('addAmount', 300000)
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $newBill = $ps->fresh()->bills()->where('payment_type_id', $extraType->id)->first();

    expect($newBill)->not->toBeNull()
        ->and((float) $newBill->amount)->toBe(300000.0)
        ->and($newBill->is_manual_override)->toBeTrue()
        ->and((float) $extraType->fresh()->rates()->first()->amount)->toBe(250000.0);
});

it('nominal sama dengan rate default tidak menandai manual override', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();
    $extraType = makeProspAddExtraType();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->set('addPaymentTypeId', $extraType->id)
        ->assertSet('addAmount', '250000')
        ->call('saveAddBill')
        ->assertHasNoErrors();

    $newBill = $ps->fresh()->bills()->where('payment_type_id', $extraType->id)->first();

    expect($newBill->is_manual_override)->toBeFalse();
});

it('menolak tagihan duplikat dengan pesan yang ramah', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();

    expect($bill)->not->toBeNull();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->set('addPaymentTypeId', $type->id)
        ->set('addAmount', 400000)
        ->call('saveAddBill')
        ->assertHasErrors(['addPeriod']);

    expect($ps->fresh()->bills()->count())->toBe(1);
});

it('menyimpan tagihan baru hanya membuat ProspectiveStudentBill', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();
    $extraType = makeProspAddExtraType();

    $studentCount = Student::count();
    $studentBillCount = StudentBill::count();
    $paymentCount = ProspectiveStudentPayment::count();
    $prospectiveBillCount = ProspectiveStudentBill::count();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->set('addPaymentTypeId', $extraType->id)
        ->set('addAmount', 250000)
        ->call('saveAddBill')
        ->assertHasNoErrors();

    expect(ProspectiveStudentBill::count())->toBe($prospectiveBillCount + 1)
        ->and(Student::count())->toBe($studentCount)
        ->and(StudentBill::count())->toBe($studentBillCount)
        ->and(ProspectiveStudentPayment::count())->toBe($paymentCount);
});

it('menolak menambah tagihan saat kelas tujuan belum ditentukan', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2028/2029'],
        ['is_active' => false, 'start_date' => '2028-07-01', 'end_date' => '2029-06-30']
    );

    $type = PaymentType::create([
        'name' => 'Wawancara Tanpa Kelas',
        'audience' => PaymentTypeAudience::ProspectiveStudent,
        'is_active' => true,
    ]);

    $ps = ProspectiveStudent::factory()->create([
        'academic_year_id' => $academicYear->id,
        'school_class_id' => null,
        'nama_lengkap' => 'Tanpa Kelas Add',
    ]);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->set('addPaymentTypeId', $type->id)
        ->set('addAmount', 250000)
        ->call('saveAddBill')
        ->assertHasErrors(['addPaymentTypeId']);

    expect($ps->fresh()->bills()->count())->toBe(0);
});

it('tagihan yang baru ditambahkan langsung tampil di tabel workspace', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();
    $extraType = makeProspAddExtraType();

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->call('openAddBill')
        ->set('addPaymentTypeId', $extraType->id)
        ->set('addAmount', 250000)
        ->call('saveAddBill')
        ->assertHasNoErrors()
        ->assertSee('Wawancara Add')
        ->assertSee('Rp 250.000');
});

it('tombol Tambah Tagihan disembunyikan untuk calon siswa yang sudah dikonversi', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    [$type, $ps, $bill] = makeProspAddBase();

    $ps->update(['status' => 'converted']);

    Livewire::test(ProspectivePaymentWorkspace::class, ['prospectiveStudent' => $ps])
        ->assertDontSee('Tambah Tagihan');
});
