<?php

use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\ProspectiveStudentDetail;
use App\Livewire\ProspectiveStudentManagement;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\ProspectiveStudentPaymentCancellationService;
use App\Services\ProspectiveStudentRegistrationNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ValueError;

it('membuat calon siswa baru', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Arif Hamdani')
        ->set('nama_panggilan', 'Arif')
        ->set('jenis_kelamin', 'L')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $smpClass->id)
        ->set('nama_orang_tua', 'Bapak Hamdani')
        ->set('no_telp_orang_tua', '081234567890')
        ->call('save')
        ->assertHasNoErrors();

    $prospectiveStudent = ProspectiveStudent::query()->sole();

    expect($prospectiveStudent->nama_lengkap)->toBe('Arif Hamdani')
        ->and($prospectiveStudent->academic_year_id)->toBe($academicYear->id)
        ->and($prospectiveStudent->school_class_id)->toBe($smpClass->id)
        ->and($prospectiveStudent->registration_number)->toBe('REG-2027-000001')
        ->and($prospectiveStudent->status)->toBe(ProspectiveStudentStatus::Registered)
        ->and($prospectiveStudent->created_by)->toBeNull();
});

it('nomor pendaftaran dibuat berurutan dan unik per tahun tujuan', function () {
    $generator = app(ProspectiveStudentRegistrationNumberGenerator::class);

    expect($generator->next(2027))->toBe('REG-2027-000001')
        ->and($generator->next(2027))->toBe('REG-2027-000002')
        ->and($generator->next(2028))->toBe('REG-2028-000001');
});

it('duplikat nomor pendaftaran ditolak oleh database', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);

    ProspectiveStudent::query()->create([
        'registration_number' => 'REG-2027-000001',
        'nama_lengkap' => 'Calon A',
        'academic_year_id' => $academicYear->id,
    ]);

    expect(fn () => ProspectiveStudent::query()->create([
        'registration_number' => 'REG-2027-000001',
        'nama_lengkap' => 'Calon B',
        'academic_year_id' => $academicYear->id,
    ]))->toThrow(QueryException::class);
});

it('tidak dapat membuat calon siswa tanpa kelas tujuan', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Tanpa Kelas')
        ->set('academic_year_id', (string) $academicYear->id)
        ->call('save')
        ->assertHasErrors(['school_class_id']);

    expect(ProspectiveStudent::query()->count())->toBe(0);
});

it('tahun ajaran tujuan wajib diisi', function () {
    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Tanpa Tahun Ajaran')
        ->call('save')
        ->assertHasErrors(['academic_year_id']);
});

it('kelas tujuan yang dibuat pada form menggunakan level yang valid', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Arif Hamdani')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $smpClass->id)
        ->call('save')
        ->assertHasNoErrors();

    $record = ProspectiveStudent::query()->sole();

    expect($record->school_class_id)->toBe($smpClass->id)
        ->and($record->target_level)->toBe(SchoolLevel::SMP)
        ->and($record->target_level_label)->toBe('SMP');
});

it('kelas tujuan invalid ditolak', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Kelas Salah')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', '999999')
        ->call('save')
        ->assertHasErrors(['school_class_id']);
});

it('bisa mengedit calon siswa', function () {
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Nama Lama',
        'school_class_id' => $smpClass->id,
    ]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('edit', $prospectiveStudent->id)
        ->assertSet('isEditing', true)
        ->set('nama_lengkap', 'Nama Baru')
        ->set('school_class_id', (string) $smpClass->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($prospectiveStudent->fresh()->nama_lengkap)->toBe('Nama Baru');
});

it('tidak dapat mengedit calon siswa menjadi tanpa kelas tujuan', function () {
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $prospectiveStudent = ProspectiveStudent::factory()->create(['school_class_id' => $smpClass->id]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('edit', $prospectiveStudent->id)
        ->assertSet('isEditing', true)
        ->set('school_class_id', '')
        ->call('save')
        ->assertHasErrors(['school_class_id']);

    expect($prospectiveStudent->fresh()->school_class_id)->toBe($smpClass->id);
});

it('mencari calon siswa berdasarkan nama', function () {
    ProspectiveStudent::factory()->create(['nama_lengkap' => 'Arif Hamdani']);
    ProspectiveStudent::factory()->create(['nama_lengkap' => 'Budi Santoso']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('search', 'Arif')
        ->assertSee('Arif Hamdani')
        ->assertDontSee('Budi Santoso');
});

it('mencari calon siswa berdasarkan nomor pendaftaran', function () {
    ProspectiveStudent::factory()->create(['nama_lengkap' => 'Arif Hamdani', 'registration_number' => 'REG-2027-000001']);
    ProspectiveStudent::factory()->create(['nama_lengkap' => 'Budi Santoso', 'registration_number' => 'REG-2027-000002']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('search', 'REG-2027-000001')
        ->assertSee('Arif Hamdani')
        ->assertDontSee('Budi Santoso');
});

it('mencari calon siswa berdasarkan nama dan nomor HP orang tua', function () {
    ProspectiveStudent::factory()->create(['nama_lengkap' => 'Arif Hamdani', 'nama_orang_tua' => 'Bapak Hamdani', 'no_telp_orang_tua' => '081234567890']);
    ProspectiveStudent::factory()->create(['nama_lengkap' => 'Budi Santoso']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('search', 'Bapak Hamdani')
        ->assertSee('Arif Hamdani')
        ->assertDontSee('Budi Santoso');

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('search', '081234567890')
        ->assertSee('Arif Hamdani')
        ->assertDontSee('Budi Santoso');
});

it('membuat calon siswa TIDAK membuat Student, enrollment, maupun StudentBill', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Arif Hamdani')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $smpClass->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ProspectiveStudent::query()->count())->toBe(1)
        ->and(Student::query()->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(0)
        ->and(StudentBill::query()->count())->toBe(0);
});

it('kelas tujuan yang valid tersimpan saat membuat calon siswa', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $smaClass = SchoolClass::factory()->create(['level' => 10]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Calon Kelas Tujuan')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $smpClass->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ProspectiveStudent::query()->sole()->school_class_id)->toBe($smpClass->id);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('openModal')
        ->set('nama_lengkap', 'Calon SMA')
        ->set('academic_year_id', (string) $academicYear->id)
        ->set('school_class_id', (string) $smaClass->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ProspectiveStudent::query()->latest('id')->first()->school_class_id)->toBe($smaClass->id);
});

it('status hanya menampung nilai yang sah', function () {
    $values = array_map(fn (ProspectiveStudentStatus $status): string => $status->value, ProspectiveStudentStatus::cases());

    expect($values)->toBe(['registered', 'converted', 'cancelled']);
});

it('nilai status yang tidak sah tidak dapat dibaca kembali', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);

    $prospectiveStudent = ProspectiveStudent::query()->create([
        'registration_number' => 'REG-2027-000001',
        'nama_lengkap' => 'Arif Hamdani',
        'academic_year_id' => $academicYear->id,
    ]);

    expect(fn () => $prospectiveStudent->setAttribute('status', 'bukan-status-valid'))
        ->toThrow(ValueError::class);
});

it('label status sesuai konvensi domain', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);

    $record = ProspectiveStudent::query()->create([
        'registration_number' => 'REG-2027-000001',
        'nama_lengkap' => 'Arif Hamdani',
        'academic_year_id' => $academicYear->id,
        'status' => ProspectiveStudentStatus::Converted,
        'converted_at' => now(),
    ]);

    expect($record->status)->toBe(ProspectiveStudentStatus::Converted)
        ->and($record->status_label)->toBe('Dikonversi')
        ->and($record->isConverted())->toBeTrue();
});

it('relasi converted_student_id aman: siswa terkait tidak bisa dihapus paksa', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $student = Student::factory()->create();

    $prospectiveStudent = ProspectiveStudent::query()->create([
        'registration_number' => 'REG-2027-000001',
        'nama_lengkap' => 'Arif Hamdani',
        'academic_year_id' => $academicYear->id,
        'status' => ProspectiveStudentStatus::Converted,
        'converted_student_id' => $student->id,
        'converted_at' => now(),
    ]);

    expect($prospectiveStudent->convertedStudent()->first()->id)->toBe($student->id)
        ->and($prospectiveStudent->isConverted())->toBeTrue();

    expect(fn () => $student->delete())->toThrow(QueryException::class);

    expect(Student::query()->whereKey($student->id)->exists())->toBeTrue();
});

it('calon siswa yang dikonversi tidak dapat diedit', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create();

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('edit', $prospectiveStudent->id)
        ->assertSet('isModalOpen', false)
        ->assertSet('isEditing', false)
        ->assertHasNoErrors();

    expect(ProspectiveStudent::query()->find($prospectiveStudent->id)?->nama_lengkap)
        ->toBe($prospectiveStudent->nama_lengkap);
});

it('halaman detail menampilkan informasi calon siswa', function () {
    $academicYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Arif Hamdani',
        'registration_number' => 'REG-2027-000001',
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $smpClass->id,
        'nama_orang_tua' => 'Bapak Hamdani',
    ]);

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $prospectiveStudent])
        ->assertSee('Arif Hamdani')
        ->assertSee('REG-2027-000001')
        ->assertSee('2027/2028')
        ->assertSee('VII')
        ->assertSee('SMP')
        ->assertSee('Bapak Hamdani');
});

/*
|--------------------------------------------------------------------------
| Status Tagihan di Daftar & Badge Warna
|--------------------------------------------------------------------------
*/

/**
 * Membuat calon siswa dengan satu tagihan pendaftaran tanpa pembayaran.
 *
 * @return array{0: ProspectiveStudent, 1: ProspectiveStudentBill}
 */
function makeListedProspectiveBillFixture(int $amount = 350000): array
{
    $ps = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Calon Status Tagihan']);

    $bill = ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $ps->id,
        'amount' => $amount,
    ]);

    return [$ps, $bill];
}

/**
 * Membayar penuh tagihan calon siswa menggunakan factory.
 */
function payListedProspectiveBill(ProspectiveStudent $ps, ProspectiveStudentBill $bill, int $amount): ProspectiveStudentPayment
{
    $payment = ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => $amount,
    ]);

    ProspectiveStudentPaymentDetail::factory()->create([
        'prospective_student_payment_id' => $payment->id,
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'amount' => $amount,
    ]);

    return $payment;
}

it('daftar calon siswa menampilkan Belum Bayar untuk tagihan tanpa pembayaran', function () {
    [$ps] = makeListedProspectiveBillFixture();

    expect($ps->bill_status)->toBe(ProspectiveStudentBill::STATUS_UNPAID);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('Belum Bayar')
        ->assertSeeHtml('bg-error-container text-on-error-container');
});

it('daftar calon siswa mempertahankan isi tabel satu baris dengan scroll horizontal', function () {
    [$prospectiveStudent] = makeListedProspectiveBillFixture();
    $prospectiveStudent->update(['nama_orang_tua' => 'Arif Hamdani']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('Arif Hamdani')
        ->assertSee('Belum Bayar')
        ->assertSeeHtml('<div class="overflow-x-auto">')
        ->assertSeeHtml('<table class="w-full text-left border-collapse min-w-[1100px] whitespace-nowrap">');
});

it('daftar calon siswa menampilkan Sebagian untuk tagihan berbayar sebagian', function () {
    [$ps, $bill] = makeListedProspectiveBillFixture();

    ProspectiveStudentPayment::factory()->create([
        'prospective_student_id' => $ps->id,
        'total_amount' => 200000,
    ])->details()->save(ProspectiveStudentPaymentDetail::factory()->make([
        'prospective_student_bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'amount' => 200000,
    ]));

    expect($ps->fresh()->bill_status)->toBe(ProspectiveStudentBill::STATUS_PARTIAL);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('Sebagian')
        ->assertSeeHtml('bg-yellow-100 text-yellow-800');
});

it('daftar calon siswa menampilkan Lunas untuk tagihan lunas', function () {
    [$ps, $bill] = makeListedProspectiveBillFixture();
    payListedProspectiveBill($ps, $bill, 350000);

    expect($ps->fresh()->bill_status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('Lunas')
        ->assertSeeHtml('bg-tertiary-fixed text-on-tertiary-fixed');
});

it('tagihan yang pembayarannya dibatalkan kembali menjadi Belum Bayar di daftar', function () {
    [$ps, $bill] = makeListedProspectiveBillFixture();
    $payment = payListedProspectiveBill($ps, $bill, 350000);
    $user = User::factory()->create();

    expect($ps->fresh()->bill_status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    app(ProspectiveStudentPaymentCancellationService::class)->cancel($payment->id, 'Terduplikasi', $user->id);

    expect($ps->fresh()->bill_status)->toBe(ProspectiveStudentBill::STATUS_UNPAID)
        ->and($bill->fresh()->status)->toBe(ProspectiveStudentBill::STATUS_UNPAID);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('Belum Bayar');
});

it('detail dan daftar menampilkan status tagihan yang sama', function () {
    [$ps, $bill] = makeListedProspectiveBillFixture();
    payListedProspectiveBill($ps, $bill, 350000);

    expect($ps->fresh()->bill_status)->toBe(ProspectiveStudentBill::STATUS_PAID);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('Lunas');

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $ps])
        ->assertSee('Lunas');
});

it('daftar calon siswa tidak bergantung pada relasi yang basi', function () {
    [$ps, $bill] = makeListedProspectiveBillFixture();

    $component = Livewire::test(ProspectiveStudentManagement::class);
    $component->assertSee('Belum Bayar');

    $payment = payListedProspectiveBill($ps, $bill, 350000);

    $component->call('$refresh')->assertSee('Lunas');

    $user = User::factory()->create();

    app(ProspectiveStudentPaymentCancellationService::class)->cancel($payment->id, 'Terduplikasi', $user->id);

    $component->call('$refresh')->assertSee('Belum Bayar');
});

it('badge Lunas memakai warna hijau di daftar dan detail', function () {
    [$ps, $bill] = makeListedProspectiveBillFixture();
    payListedProspectiveBill($ps, $bill, 350000);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSeeHtml('bg-tertiary-fixed text-on-tertiary-fixed');

    Livewire::test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $ps])
        ->assertSeeHtml('bg-tertiary-fixed text-on-tertiary-fixed');
});

it('menampilkan badge kategori pendaftaran dengan aman untuk data lama', function () {
    $annur = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Badge Keluarga Annur',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    ]);
    $nonAnnur = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Badge Non Keluarga Annur',
        'applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
    ]);
    $nullCategory = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Badge Kategori Null']);
    $emptyCategory = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Badge Kategori Kosong']);
    $unknownCategory = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Badge Kategori Lama']);

    DB::table('prospective_students')->where('id', $emptyCategory->id)->update(['applicant_category' => '']);
    DB::table('prospective_students')->where('id', $unknownCategory->id)->update(['applicant_category' => 'legacy_family']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSeeInOrder([$annur->nama_lengkap, 'Keluarga Annur'])
        ->assertSeeInOrder([$nonAnnur->nama_lengkap, 'Non-Keluarga Annur'])
        ->assertSeeInOrder([$nullCategory->nama_lengkap, 'Belum Dikategorikan'])
        ->assertSeeInOrder([$emptyCategory->nama_lengkap, 'Belum Dikategorikan'])
        ->assertSeeInOrder([$unknownCategory->nama_lengkap, 'Belum Dikategorikan']);
});

it('menyesuaikan colspan keadaan kosong untuk kolom kategori', function () {
    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSeeHtml('colspan="11"');
});

it('memfilter kategori pendaftaran termasuk nilai lama yang belum dikategorikan', function () {
    $annur = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Filter Keluarga Annur',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    ]);
    $nonAnnur = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Filter Non Keluarga Annur',
        'applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
    ]);
    $nullCategory = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Filter Kategori Null']);
    $emptyCategory = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Filter Kategori Kosong']);
    $unknownCategory = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Filter Kategori Lama']);

    DB::table('prospective_students')->where('id', $emptyCategory->id)->update(['applicant_category' => '']);
    DB::table('prospective_students')->where('id', $unknownCategory->id)->update(['applicant_category' => 'legacy_family']);

    $component = Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee([$annur->nama_lengkap, $nonAnnur->nama_lengkap, $nullCategory->nama_lengkap]);

    $component->set('filterCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->assertSee($annur->nama_lengkap)
        ->assertDontSee([$nonAnnur->nama_lengkap, $nullCategory->nama_lengkap, $emptyCategory->nama_lengkap, $unknownCategory->nama_lengkap]);

    $component->set('filterCategory', PpdbApplicantCategory::NON_ANNUR_FAMILY->value)
        ->assertSee($nonAnnur->nama_lengkap)
        ->assertDontSee([$annur->nama_lengkap, $nullCategory->nama_lengkap, $emptyCategory->nama_lengkap, $unknownCategory->nama_lengkap]);

    $component->set('filterCategory', 'uncategorized')
        ->assertSee([$nullCategory->nama_lengkap, $emptyCategory->nama_lengkap, $unknownCategory->nama_lengkap])
        ->assertDontSee([$annur->nama_lengkap, $nonAnnur->nama_lengkap]);

    $component->set('filterCategory', 'invalid-filter')
        ->assertDontSee([$annur->nama_lengkap, $nonAnnur->nama_lengkap, $nullCategory->nama_lengkap]);
});

it('menggabungkan filter kategori dengan semua filter daftar yang sudah ada', function () {
    $academicYear = AcademicYear::create([
        'year' => '2031/2032',
        'is_active' => false,
        'start_date' => '2031-07-01',
        'end_date' => '2032-06-30',
    ]);
    $otherAcademicYear = AcademicYear::create([
        'year' => '2032/2033',
        'is_active' => false,
        'start_date' => '2032-07-01',
        'end_date' => '2033-06-30',
    ]);
    $targetClass = SchoolClass::factory()->create(['level' => 7]);
    $otherClass = SchoolClass::factory()->create(['level' => 8]);

    $matching = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Dika Kategori Cocok',
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $targetClass->id,
        'status' => ProspectiveStudentStatus::Registered,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    ]);
    ProspectiveStudentBill::factory()->create(['prospective_student_id' => $matching->id]);

    ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Dika Kategori Berbeda',
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $targetClass->id,
        'applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
    ]);
    ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Dika Tahun Berbeda',
        'academic_year_id' => $otherAcademicYear->id,
        'school_class_id' => $targetClass->id,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    ]);
    ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Dika Kelas Berbeda',
        'academic_year_id' => $academicYear->id,
        'school_class_id' => $otherClass->id,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    ]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('search', 'Dika')
        ->set('filterAcademicYearId', (string) $academicYear->id)
        ->set('filterLevel', SchoolLevel::SMP->value)
        ->set('filterClassId', (string) $targetClass->id)
        ->set('filterStatus', ProspectiveStudentStatus::Registered->value)
        ->set('filterBillStatus', ProspectiveStudentBill::STATUS_UNPAID)
        ->set('filterCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->assertSee($matching->nama_lengkap)
        ->assertDontSee(['Dika Kategori Berbeda', 'Dika Tahun Berbeda', 'Dika Kelas Berbeda']);
});

it('menerapkan filter kategori pada statistik gender dan kategori', function () {
    ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Stat Annur L',
        'jenis_kelamin' => 'L',
        'status' => ProspectiveStudentStatus::Registered,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    ]);
    ProspectiveStudent::factory()->converted()->create([
        'nama_lengkap' => 'Stat Annur P',
        'jenis_kelamin' => 'P',
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    ]);
    ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Stat Non Annur',
        'jenis_kelamin' => 'L',
        'applicant_category' => PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
    ]);
    $uncategorized = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Stat Belum Kategori',
        'jenis_kelamin' => null,
    ]);
    DB::table('prospective_students')->where('id', $uncategorized->id)->update(['applicant_category' => 'legacy_family']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('filterCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->assertSeeInOrder(['Total Calon Siswa', '2', 'Laki-laki', '1', 'Perempuan', '1'])
        ->assertSeeInOrder(['Terdaftar', '1', 'Laki-laki', '1', 'Perempuan', '0'])
        ->assertSeeInOrder(['Dikonversi', '1', 'Laki-laki', '0', 'Perempuan', '1'])
        ->assertSeeInOrder(['Keluarga Annur', '2', 'Laki-laki', '1', 'Perempuan', '1'])
        ->assertSeeInOrder(['Non-Keluarga Annur', '0', 'Laki-laki', '0', 'Perempuan', '0'])
        ->assertDontSee('Belum Dikategorikan:')
        ->set('filterCategory', 'uncategorized')
        ->assertSeeInOrder(['Total Calon Siswa', '1', 'Laki-laki', '0', 'Perempuan', '0'])
        ->assertSeeInOrder(['Keluarga Annur', '0', 'Non-Keluarga Annur', '0'])
        ->assertSee('Belum Dikategorikan: 1');
});

it('mengubah filter kategori mereset pagination dan mempertahankan aksi baris', function () {
    $student = ProspectiveStudent::factory()->create([
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
    ]);
    ProspectiveStudent::factory()->count(20)->create();

    Livewire::test(ProspectiveStudentManagement::class)
        ->set('paginators.page', 2)
        ->assertSet('paginators.page', 2)
        ->set('filterCategory', PpdbApplicantCategory::ANNUR_FAMILY->value)
        ->assertSet('paginators.page', 1)
        ->assertSee(route('calon-siswa.show', $student), false)
        ->assertSee('Lihat Detail');
});
