<?php

use App\Livewire\ProspectiveStudentManagement;
use App\Models\AcademicYear;
use App\Models\PaymentType;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\SchoolClass;
use App\Models\Student;
use Livewire\Livewire;

it('dapat menghapus calon siswa tanpa tagihan', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->assertSet('isDeleteModalOpen', true)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false)
        ->assertSet('deletingId', null);

    expect(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeFalse();
});

it('dapat menghapus calon siswa dengan tagihan pendaftaran yang belum dibayar', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'payment_type_id' => PaymentType::factory()->create()->id,
        'amount' => 350000,
        'academic_year' => '2027/2028',
    ]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false);

    expect(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeFalse();
});

it('menghapus tagihan terkait saat calon siswa dihapus', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $bills = ProspectiveStudentBill::factory()->count(2)->create(['prospective_student_id' => $prospectiveStudent->id]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->call('delete');

    expect(ProspectiveStudentBill::query()->whereIn('id', $bills->pluck('id'))->exists())->toBeFalse()
        ->and(ProspectiveStudentBill::query()->count())->toBe(0);
});

it('calon siswa yang dikonversi tidak dapat dihapus', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create();

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->assertSet('isDeleteModalOpen', false)
        ->set('deletingId', $prospectiveStudent->id)
        ->call('delete')
        ->assertSet('isDeleteModalOpen', false);

    expect(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeTrue();
});

it('penghapusan tidak menghapus Student', function () {
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->create();

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->call('delete');

    expect(Student::query()->whereKey($student->id)->exists())->toBeTrue();
});

it('penghapusan tidak menghapus PaymentType', function () {
    $paymentType = PaymentType::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    ProspectiveStudentBill::factory()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'payment_type_id' => $paymentType->id,
    ]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->call('delete');

    expect(PaymentType::query()->whereKey($paymentType->id)->exists())->toBeTrue();
});

it('penghapusan tidak menghapus AcademicYear', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $academicYearId = $prospectiveStudent->academic_year_id;

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->call('delete');

    expect(AcademicYear::query()->whereKey($academicYearId)->exists())->toBeTrue();
});

it('penghapusan tidak menghapus SchoolClass', function () {
    $schoolClass = SchoolClass::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->create(['school_class_id' => $schoolClass->id]);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->call('delete');

    expect(SchoolClass::query()->whereKey($schoolClass->id)->exists())->toBeTrue();
});

it('modal konfirmasi menghapus terbuka dengan detail calon siswa', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Calon Dihapus']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->assertSet('isDeleteModalOpen', true)
        ->assertSet('deletingId', $prospectiveStudent->id)
        ->assertSet('deletingName', 'Calon Dihapus')
        ->assertSet('deletingRegistrationNumber', $prospectiveStudent->registration_number)
        ->assertSee('Calon Dihapus')
        ->assertSee($prospectiveStudent->registration_number)
        ->assertSee('Batal')
        ->assertSee('Hapus');
});

it('record menghilang dari daftar setelah dihapus', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create(['nama_lengkap' => 'Akan Hilang']);

    Livewire::test(ProspectiveStudentManagement::class)
        ->assertSee('Akan Hilang')
        ->call('confirmDelete', $prospectiveStudent->id)
        ->call('delete')
        ->assertSee('Tidak ada calon siswa yang ditemukan.');

    expect(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeFalse();
});
