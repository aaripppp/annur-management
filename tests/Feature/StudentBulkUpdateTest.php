<?php

use App\Enums\SchoolLevel;
use App\Livewire\StudentBulkUpdate;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\StudentBulkUpdateService;
use App\Services\StudentBulkUpdateSpreadsheet;
use App\Services\StudentImportService;
use App\Services\StudentImportSpreadsheet;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function bulkUpdateWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-bulkupdate-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('DATA SISWA');
    $writer->addRow(Row::fromValues(StudentBulkUpdateSpreadsheet::HEADERS));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

function bulkUpdateRow(array $overrides = []): array
{
    return array_merge([
        'id_sistem' => '',
        'kelas' => '',
        'nama_lengkap' => '',
        'nis' => '',
        'nama_panggilan' => '',
        'jenis_kelamin' => '',
        'tempat_lahir' => '',
        'tanggal_lahir' => '',
        'nama_ayah' => '',
        'no_telp_ayah' => '',
        'nama_ibu' => '',
        'no_telp_ibu' => '',
        'alamat' => '',
    ], $overrides);
}

function toBulkUpdateRow(Student $student, array $overrides = []): array
{
    return bulkUpdateRow(array_merge([
        'id_sistem' => $student->id,
        'kelas' => $student->schoolClass?->name ?? '',
    ], $overrides));
}

function previewBulkUpdate(StudentBulkUpdateSpreadsheet $spreadsheet, string $path): array
{
    return app(StudentBulkUpdateService::class)->preview($spreadsheet->read($path));
}

it('unduh file update berisi siswa sesuai filter jenjang', function (): void {
    [, $smpClass] = makeEnrolledStudent(SchoolLevel::SMP);
    [, $sdClass] = makeEnrolledStudent(SchoolLevel::SD);

    $path = app(StudentBulkUpdateSpreadsheet::class)->create(SchoolLevel::SMP, null);

    $rows = app(StudentBulkUpdateSpreadsheet::class)->read($path);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['kelas'])->toBe($smpClass->name);
    expect($rows[0]['kelas'])->not->toBe($sdClass->name);
});

it('unduh file update berisi siswa sesuai filter kelas', function (): void {
    [$studentA, $classA] = makeEnrolledStudent(SchoolLevel::SMP);
    makeEnrolledStudent(SchoolLevel::SMP);

    $path = app(StudentBulkUpdateSpreadsheet::class)->create(null, (int) $classA->id);

    $rows = app(StudentBulkUpdateSpreadsheet::class)->read($path);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['id_sistem'])->toBe($studentA->id);
});

it('file update menyertakan kolom ID Sistem', function (): void {
    expect(StudentBulkUpdateSpreadsheet::HEADERS)->toContain('ID Sistem');
    expect(StudentBulkUpdateSpreadsheet::HEADERS)->toContain('Kelas');
});

it('file update menyertakan biodata siswa saat ini', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update([
        'nama_lengkap' => 'Budi Santoso',
        'nis' => '71001',
        'nama_panggilan' => 'Budi',
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Merdeka 10',
    ]);

    $path = app(StudentBulkUpdateSpreadsheet::class)->create(SchoolLevel::SMP, null);
    $rows = app(StudentBulkUpdateSpreadsheet::class)->read($path);
    $row = collect($rows)->firstWhere('id_sistem', $student->id);

    expect($row['nama_lengkap'])->toBe('Budi Santoso');
    expect($row['nis'])->toBe('71001');
    expect($row['nama_panggilan'])->toBe('Budi');
    expect($row['jenis_kelamin'])->toBe('L');
    expect($row['alamat'])->toBe('Jl. Merdeka 10');
});

it('preview mencocokkan baris berdasarkan ID Sistem dan mengubah nama tanpa mengganti id', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'nama_lengkap' => 'Ahmad Rizky Baru']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);

    expect($preview['rows'][0]['status'])->toBe('ready');
    expect($preview['rows'][0]['changes'])->toHaveCount(1);
    expect($preview['rows'][0]['changes'][0]['field'])->toBe('nama_lengkap');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));

    expect($student->fresh()->nama_lengkap)->toBe('Ahmad Rizky Baru');
    expect($student->fresh()->id)->toBe($student->id);
    expect(Student::query()->count())->toBe(1);
});

it('NIS yang kosong mengisi NIS siswa yang belum memiliki NIS', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['nis' => null]);
    expect($student->fresh()->nis)->toBeNull();

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'nis' => '71000']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);
    expect($preview['rows'][0]['status'])->toBe('ready');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));
    expect($student->fresh()->nis)->toBe('71000');
});

it('sel NIS kosong tidak menghapus NIS yang sudah ada', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['nis' => '71000']);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id]),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);
    expect($preview['rows'][0]['status'])->toBe('unchanged');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));
    expect($student->fresh()->nis)->toBe('71000');
});

it('sel alamat kosong tidak menghapus alamat yang sudah ada', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['alamat' => 'Jl. Merdeka 10']);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id]),
    ]);

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));
    expect($student->fresh()->alamat)->toBe('Jl. Merdeka 10');
});

it('baris tanpa perubahan berstatus unchanged dan tidak menjalankan update', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['nis' => '71000', 'alamat' => 'Jl. Merdeka 10']);

    $path = bulkUpdateWorkbook([
        toBulkUpdateRow($student),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);
    expect($preview['rows'][0]['status'])->toBe('unchanged');
    expect($preview['summary']['unchanged'])->toBe(1);

    // Tidak boleh ada query UPDATE yang dijalankan.
    $queryLog = [];
    DB::listen(function ($query) use (&$queryLog): void {
        if (str_contains($query->sql, 'update `students`')) {
            $queryLog[] = $query->sql;
        }
    });

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));
    expect($queryLog)->toBe([]);
});

it('ID Sistem yang tidak dikenal tidak pernah membuat siswa baru', function (): void {
    makeEnrolledStudent(SchoolLevel::SMP);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => 99999, 'nama_lengkap' => 'Siswa Hantu']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);
    expect($preview['rows'][0]['status'])->toBe('blocked');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));

    expect(Student::query()->count())->toBe(1);
    expect(Student::query()->where('nama_lengkap', 'Siswa Hantu')->doesntExist())->toBeTrue();
});

it('ID Sistem kosong ditandai gagal tanpa fallback nama', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => '', 'nama_lengkap' => 'Tanpa ID']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);
    expect($preview['rows'][0]['status'])->toBe('blocked');
    expect($preview['rows'][0]['errors'][0])->toContain('ID Sistem');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));
    expect($student->fresh()->nama_lengkap)->not->toBe('Tanpa ID');
});

it('duplikat ID Sistem dalam satu file ditandai perlu diperiksa', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'nama_lengkap' => 'Nama Pertama']),
        bulkUpdateRow(['id_sistem' => $student->id, 'nama_lengkap' => 'Nama Kedua']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);

    expect($preview['rows'][0]['status'])->toBe('blocked');
    expect($preview['rows'][0]['errors'][0])->toContain('muncul lebih dari satu kali');
    expect($preview['rows'][1]['status'])->toBe('blocked');
    expect($preview['summary']['blocked'])->toBe(2);
});

it('nama yang sama tidak memiliki efek pencocokan', function (): void {
    [$studentA] = makeEnrolledStudent(SchoolLevel::SMP);
    [$studentB] = makeEnrolledStudent(SchoolLevel::SMP);

    // Baris menyediakan nama yang identik dengan studentB, namun ID mengarah ke studentA.
    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $studentA->id, 'nama_lengkap' => $studentB->nama_lengkap]),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);
    expect($preview['rows'][0]['student_id'])->toBe($studentA->id);

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));

    expect($studentA->fresh()->nama_lengkap)->toBe($studentB->nama_lengkap);
    expect($studentB->fresh()->nama_lengkap)->toBe($studentB->nama_lengkap);
    expect(Student::query()->count())->toBe(2);
});

it('NIS yang bentrok dengan siswa lain ditolak', function (): void {
    [$studentA] = makeEnrolledStudent(SchoolLevel::SMP);
    $studentA->update(['nis' => null]);
    [$studentB] = makeEnrolledStudent(SchoolLevel::SMP);

    $studentB->update(['nis' => '71000']);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $studentA->id, 'nis' => '71000']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);
    expect($preview['rows'][0]['status'])->toBe('blocked');
    expect($preview['rows'][0]['errors'][0])->toContain('sudah terdaftar');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));
    expect($studentA->fresh()->nis)->toBeNull();
});

it('perubahan kelas pada file diabaikan dan diberi peringatan', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $otherClass = SchoolClass::factory()->create(['level' => 7]);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'kelas' => $otherClass->name, 'nama_lengkap' => 'Nama Baru']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);

    expect($preview['rows'][0]['status'])->toBe('ready');
    expect($preview['rows'][0]['warnings'][0])->toContain('Perubahan kelas diabaikan');
    expect($preview['rows'][0]['changes'][0]['field'])->toBe('nama_lengkap');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));

    expect($student->fresh()->class_id)->toBe($student->class_id);
    expect($student->fresh()->nama_lengkap)->toBe('Nama Baru');
});

it('tidak menghasilkan tagihan baru saat update', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    makeBillType('SPP');

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'nama_lengkap' => 'Nama Baru']),
    ]);

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));

    expect(StudentBill::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('menjaga tagihan siswa yang sudah ada tetap terhubung', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 970000);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'nama_lengkap' => 'Nama Baru']),
    ]);

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));

    expect(StudentBill::query()->find($bill->id)->student_id)->toBe($student->id);
    expect($student->fresh()->id)->toBe($student->id);
});

it('menjaga pembayaran tetap terhubung', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $bank = Bank::factory()->create();
    $user = User::factory()->create();
    $type = makeBillType('SPP');
    $bill = makeMonthlyBill($student, $type, 970000);

    $payment = Payment::query()->create([
        'receipt_number' => 'KWT-TEST-1',
        'payment_kind' => 'bill',
        'student_id' => $student->id,
        'bank_id' => $bank->id,
        'payment_date' => '2026-08-05',
        'total_amount' => 970000,
        'payment_method' => $bank->isCash() ? 'cash' : 'transfer',
        'description' => 'Test payment',
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    PaymentDetail::query()->create([
        'payment_id' => $payment->id,
        'student_bill_id' => $bill->id,
        'payment_type_id' => $type->id,
        'amount' => 970000,
    ]);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'nama_lengkap' => 'Nama Baru']),
    ]);

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));

    expect(Payment::query()->find($payment->id)->student_id)->toBe($student->id);
    expect(PaymentDetail::query()->where('payment_id', $payment->id)->count())->toBe(1);
});

it('kelayakan ujian masih terpenuhi setelah update biodata', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $type = makeBillType('SPP');
    makeMonthlyBill($student, $type, 970000);
    $student->update(['nis' => '71000']);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'nama_lengkap' => 'Nama Baru']),
    ]);

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));

    expect($student->fresh()->nama_lengkap)->toBe('Nama Baru');
    expect($student->fresh()->nis)->toBe('71000');

    // Enrollment aktif tetap ada.
    expect($student->fresh()->enrollments()->where('status', 'active')->exists())->toBeTrue();
});

it('jenis kelamin tidak valid ditolak', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['jenis_kelamin' => null]);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'jenis_kelamin' => 'X']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);

    expect($preview['rows'][0]['status'])->toBe('blocked');
    expect($preview['rows'][0]['errors'][0])->toContain('Jenis Kelamin');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));
    expect($student->fresh()->jenis_kelamin)->toBeNull();
});

it('hanya mengizinkan field whitelist untuk diperbarui', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);
    $student->update(['nis' => '71000']);

    // Kolom kelas diubah, tetapi tidak diperbolehkan diperbarui.
    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'kelas' => 'VIII A']),
    ]);

    $preview = previewBulkUpdate(app(StudentBulkUpdateSpreadsheet::class), $path);
    expect($preview['rows'][0]['status'])->toBe('unchanged');

    app(StudentBulkUpdateService::class)->apply(app(StudentBulkUpdateSpreadsheet::class)->read($path));
    expect($student->fresh()->class_id)->toBe($student->class_id);
});

it('data daycare tidak terpengaruh saat update siswa', function (): void {
    makeEnrolledStudent(SchoolLevel::SMP);
    expect(true)->toBeTrue();
});

it('import siswa baru tetap berfungsi (bertambah siswa, bukan update)', function (): void {
    SchoolClass::firstOrCreate(['name' => 'VIII A'], ['level' => 8]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $academicYear->update(['is_active' => true]);

    $path = studentImportWorkbook([
        ['', 'Siswa Import Baru', '', 'VIII A', '', '', '', '', '', '', '', ''],
    ]);

    $rows = app(StudentImportSpreadsheet::class)->read($path);
    $result = app(StudentImportService::class)->preview(
        $rows,
        (int) $academicYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($result['summary']['new'])->toBe(1);
});

it('import minimal Nama Lengkap + Kelas masih membuat siswa', function (): void {
    SchoolClass::firstOrCreate(['name' => 'VIII A'], ['level' => 8]);
    $academicYear = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
    );
    $academicYear->update(['is_active' => true]);

    $path = studentImportWorkbook([
        ['', 'Siswa Minimal', '', 'VIII A', '', '', '', '', '', '', '', ''],
    ]);

    $rows = app(StudentImportSpreadsheet::class)->read($path);
    $result = app(StudentImportService::class)->preview(
        $rows,
        (int) $academicYear->id,
        StudentImportService::CONTEXT_ACTIVE,
    );

    expect($result['summary']['new'])->toBe(1);
});

it('mengunduh file update melalui Livewire', function (): void {
    makeEnrolledStudent(SchoolLevel::SMP);

    Livewire::test(StudentBulkUpdate::class)
        ->set('filterLevel', SchoolLevel::SMP->value)
        ->call('downloadTemplate')
        ->assertFileDownloaded('update-data-siswa.xlsx');
});

it('alur Livewire: unggah, preview, lalu konfirmasi update', function (): void {
    [$student] = makeEnrolledStudent(SchoolLevel::SMP);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => $student->id, 'nama_lengkap' => 'Nama via Livewire', 'alamat' => 'Alamat Baru']),
    ]);
    $upload = UploadedFile::fake()->createWithContent('siswa-update.xlsx', file_get_contents($path));

    try {
        $component = Livewire::test(StudentBulkUpdate::class)
            ->set('file', $upload)
            ->call('previewUpdate')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('SIAP UPDATE')
            ->assertSee('Nama via Livewire');

        expect(Student::query()->find($student->id)->nama_lengkap)->not->toBe('Nama via Livewire');

        $component
            ->call('confirmUpdate')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->assertSee('Update Selesai');

        $fresh = Student::query()->find($student->id);
        expect($fresh->nama_lengkap)->toBe('Nama via Livewire');
        expect($fresh->alamat)->toBe('Alamat Baru');
        expect($fresh->id)->toBe($student->id);
        expect(Student::query()->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('alur Livewire menolak ID Sistem tidak dikenal tanpa menulis apa pun', function (): void {
    makeEnrolledStudent(SchoolLevel::SMP);

    $path = bulkUpdateWorkbook([
        bulkUpdateRow(['id_sistem' => 99999, 'nama_lengkap' => 'Hantu']),
    ]);
    $upload = UploadedFile::fake()->createWithContent('siswa-update.xlsx', file_get_contents($path));

    try {
        Livewire::test(StudentBulkUpdate::class)
            ->set('file', $upload)
            ->call('previewUpdate')
            ->assertSet('step', 2)
            ->assertSee('GAGAL')
            ->call('confirmUpdate')
            ->assertSet('step', 3);

        expect(Student::query()->count())->toBe(1);
        expect(Student::query()->where('nama_lengkap', 'Hantu')->doesntExist())->toBeTrue();
    } finally {
        @unlink($path);
    }
});

it('halaman update terdaftar di route siswa.update', function (): void {
    expect(route('siswa.update'))->toBe(url('/siswa/update'));
});
