<?php

use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Livewire\Livewire;

it('search by nama lengkap', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260001']);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Budi Santoso', 'nis' => '20260002']);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Budi Santoso');
});

it('search by nama panggilan', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Ahmad Fauzi', 'nama_panggilan' => 'Ahmad', 'nis' => '20260001']);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Budi Santoso', 'nama_panggilan' => 'Budi', 'nis' => '20260002']);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Budi')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Ahmad Fauzi');
});

it('search by NIS', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260081']);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Budi Santoso', 'nis' => '20260082']);

    Livewire::test(StudentManagement::class)
        ->set('search', '20260081')
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Budi Santoso');
});

it('membedakan dua record siswa dengan NIS sama berdasarkan konteks akademik', function () {
    $oldYear = AcademicYear::create([
        'year' => '2027/2028',
        'is_active' => false,
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $activeYear = AcademicYear::create([
        'year' => '2028/2029',
        'is_active' => true,
        'start_date' => '2028-07-01',
        'end_date' => '2029-06-30',
    ]);
    $sdClass = SchoolClass::factory()->create(['level' => 6]);
    $smpClass = SchoolClass::factory()->create(['level' => 7]);
    $oldStudent = Student::factory()->create([
        'nis' => '28-0001',
        'nama_lengkap' => 'Ahmad Same NIS',
        'class_id' => $sdClass->id,
        'status' => 'lulus',
    ]);
    $newStudent = Student::factory()->create([
        'nis' => '28-0001',
        'nama_lengkap' => 'Ahmad Same NIS',
        'class_id' => $smpClass->id,
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $oldStudent->id,
        'academic_year_id' => $oldYear->id,
        'school_class_id' => $sdClass->id,
        'status' => 'lulus',
    ]);
    StudentAcademicEnrollment::create([
        'student_id' => $newStudent->id,
        'academic_year_id' => $activeYear->id,
        'school_class_id' => $smpClass->id,
        'status' => 'active',
    ]);

    Livewire::test(StudentManagement::class)
        ->set('search', '28-0001')
        ->assertSee('student-'.$oldStudent->id, false)
        ->assertSee('student-'.$newStudent->id, false)
        ->assertSee($sdClass->name)
        ->assertSee($smpClass->name)
        ->assertSee('SD')
        ->assertSee('SMP')
        ->assertSee($oldYear->year)
        ->assertSee($activeYear->year)
        ->assertSee('Lulus')
        ->assertSee('Aktif');
});

it('search by class name', function () {
    $classA = SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    $classB = SchoolClass::factory()->create(['id' => 2, 'name' => 'VIII B', 'level' => 8]);
    Student::factory()->create(['class_id' => $classA->id, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260001']);
    Student::factory()->create(['class_id' => $classB->id, 'nama_lengkap' => 'Budi Santoso', 'nis' => '20260002']);

    Livewire::test(StudentManagement::class)
        ->set('search', $classA->fresh()->name)
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Budi Santoso');
});

it('unrelated students not shown during search', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260001']);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Budi Santoso', 'nis' => '20260002']);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Citra Dewi', 'nis' => '20260003']);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Budi Santoso')
        ->assertDontSee('Citra Dewi');
});

it('clearing search returns to the default newest list', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260001']);
    Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Budi Santoso', 'nis' => '20260002']);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Budi Santoso')
        ->set('search', '')
        ->assertSee('Ahmad Fauzi')
        ->assertSee('Budi Santoso');
});

it('search resets pagination to page 1', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);

    for ($i = 1; $i <= 10; $i++) {
        $student = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Siswa A'.$i, 'nis' => sprintf('2026%04d', $i)]);
        $student->forceFill(['created_at' => '2026-09-09 10:00:00', 'updated_at' => '2026-09-09 10:00:00'])->save();
    }

    for ($i = 1; $i <= 5; $i++) {
        $student = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Siswa B'.$i, 'nis' => sprintf('2026%02d', $i + 100)]);
        $student->forceFill(['created_at' => '2026-09-09 09:00:00', 'updated_at' => '2026-09-09 09:00:00'])->save();
    }

    Livewire::test(StudentManagement::class)
        ->set('search', 'Siswa')
        ->assertSee('Siswa A1')
        ->assertDontSee('Siswa B1')
        ->call('setPage', 2)
        ->assertSee('Siswa B1')
        ->assertDontSee('Siswa A1')
        ->set('search', 'Siswa A1')
        ->assertSee('Siswa A1')
        ->assertSee('Siswa A10')
        ->assertDontSee('Siswa B1');
});

it('search combined with class filter works together', function () {
    $classA = SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    $classB = SchoolClass::factory()->create(['id' => 2, 'name' => 'VIII B', 'level' => 8]);
    Student::factory()->create(['class_id' => $classA->id, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260001']);
    Student::factory()->create(['class_id' => $classB->id, 'nama_lengkap' => 'Ahmad Rizky', 'nis' => '20260002']);
    Student::factory()->create(['class_id' => $classA->id, 'nama_lengkap' => 'Budi Santoso', 'nis' => '20260003']);

    Livewire::test(StudentManagement::class)
        ->set('filterClassId', $classA->id)
        ->set('search', 'Ahmad')
        ->assertSee('Ahmad Fauzi')
        ->assertDontSee('Ahmad Rizky')
        ->assertDontSee('Budi Santoso');
});

it('edit after search targets correct student', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    $ahmad = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260001']);
    $budi = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Budi Santoso', 'nis' => '20260002']);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Ahmad')
        ->call('edit', $ahmad->id)
        ->assertSet('studentId', $ahmad->id)
        ->assertSet('nama_lengkap', 'Ahmad Fauzi')
        ->assertSet('nis', '20260001');
});

it('delete after search targets correct student', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    $ahmad = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260001']);
    $budi = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Budi Santoso', 'nis' => '20260002']);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Ahmad')
        ->call('confirmDelete', $ahmad->id)
        ->assertSet('deletingId', $ahmad->id)
        ->call('delete');

    expect(Student::find($ahmad->id))->toBeNull()
        ->and(Student::find($budi->id))->not->toBeNull();
});

it('wire:key remains student DB ID after search', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);
    $student = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Ahmad Fauzi', 'nis' => '20260001']);

    Livewire::test(StudentManagement::class)
        ->set('search', 'Ahmad')
        ->assertSee('student-'.$student->id, false);
});

it('pagination works normally with an active search', function () {
    SchoolClass::factory()->create(['id' => 1, 'name' => 'VII A', 'level' => 7]);

    for ($i = 1; $i <= 10; $i++) {
        $student = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Siswa A'.$i, 'nis' => sprintf('2026%04d', $i)]);
        $student->forceFill(['created_at' => '2026-09-09 10:00:00', 'updated_at' => '2026-09-09 10:00:00'])->save();
    }

    for ($i = 1; $i <= 5; $i++) {
        $student = Student::factory()->create(['class_id' => 1, 'nama_lengkap' => 'Siswa B'.$i, 'nis' => sprintf('2026%02d', $i + 100)]);
        $student->forceFill(['created_at' => '2026-09-09 09:00:00', 'updated_at' => '2026-09-09 09:00:00'])->save();
    }

    Livewire::test(StudentManagement::class)
        ->call('setPage', 1)
        ->set('search', 'Siswa')
        ->assertSee('Siswa A1')
        ->assertSee('Siswa A10')
        ->assertDontSee('Siswa B1')
        ->call('setPage', 2)
        ->assertSee('Siswa B1')
        ->assertDontSee('Siswa A1');
});
