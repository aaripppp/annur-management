<?php

use App\Livewire\DaycareImport;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentBill;
use App\Services\DaycareImportService;
use App\Services\DaycareImportSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

function daycareImportRow(array $overrides = []): array
{
    return array_merge([
        'row_number' => 2,
        'nama_lengkap' => 'Ahmad Fauzan',
        'kelas' => 'C',
        'nama_panggilan' => 'Ahmad',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2022-05-10',
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Melati No. 10',
        'nama_ayah' => 'Bapak Fauzan',
        'no_telp_ayah' => '081234567890',
        'nama_ibu' => 'Ibu Fauzan',
        'no_telp_ibu' => '+6281234567890',
    ], $overrides);
}

function daycareImportWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-daycare-import-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('DATA DAYCARE');
    $writer->addRow(Row::fromValues(DaycareImportSpreadsheet::HEADERS));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues([
            $row['nama_lengkap'],
            $row['kelas'],
            $row['nama_panggilan'],
            $row['tempat_lahir'],
            $row['tanggal_lahir'],
            $row['jenis_kelamin'],
            $row['alamat'],
            $row['nama_ayah'],
            $row['no_telp_ayah'],
            $row['nama_ibu'],
            $row['no_telp_ibu'],
        ]));
    }

    $writer->close();

    return $path;
}

it('template import hanya memiliki kolom wajib dan biodata tanpa kolom ID', function () {
    expect(DaycareImportSpreadsheet::HEADERS)->toBe([
        'Nama Lengkap',
        'Kelas',
        'Nama Panggilan',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Jenis Kelamin',
        'Alamat',
        'Nama Ayah',
        'No Telp Ayah',
        'Nama Ibu',
        'No Telp Ibu',
    ])->and(DaycareImportSpreadsheet::MINIMAL_HEADERS)->toBe(['Nama Lengkap', 'Kelas']);
});

it('membuat anak Daycare baru minimal dengan nama lengkap dan kelas', function () {
    $result = app(DaycareImportService::class)->import([
        daycareImportRow([
            'nama_lengkap' => 'Aminah',
            'kelas' => 'B',
            'nama_panggilan' => '',
            'tempat_lahir' => '',
            'tanggal_lahir' => '',
            'jenis_kelamin' => '',
            'alamat' => '',
            'nama_ayah' => '',
            'no_telp_ayah' => '',
            'nama_ibu' => '',
            'no_telp_ibu' => '',
        ]),
    ]);

    expect($result['total'])->toBe(1)
        ->and($result['created'])->toBe(1)
        ->and(DaycareChild::query()->count())->toBe(1);

    $child = DaycareChild::query()->sole();

    expect($child->nama_lengkap)->toBe('Aminah')
        ->and($child->kelas)->toBe('B')
        ->and($child->nama_panggilan)->toBeNull()
        ->and($child->is_active)->toBeTrue();
});

it('membuat anak Daycare baru dengan biodata opsional lengkap', function () {
    app(DaycareImportService::class)->import([daycareImportRow()]);

    $child = DaycareChild::query()->sole();

    expect($child->nama_lengkap)->toBe('Ahmad Fauzan')
        ->and($child->kelas)->toBe('C')
        ->and($child->nama_panggilan)->toBe('Ahmad')
        ->and($child->tempat_lahir)->toBe('Bandung')
        ->and($child->tanggal_lahir->format('Y-m-d'))->toBe('2022-05-10')
        ->and($child->jenis_kelamin)->toBe('L')
        ->and($child->alamat)->toBe('Jl. Melati No. 10')
        ->and($child->nama_ayah)->toBe('Bapak Fauzan')
        ->and($child->nama_ibu)->toBe('Ibu Fauzan');
});

it('menjaga nol di awal nomor telepon', function () {
    app(DaycareImportService::class)->import([
        daycareImportRow(['no_telp_ayah' => '081234567890']),
    ]);

    expect(DaycareChild::query()->sole()->no_telp_ayah)->toBe('081234567890');
});

it('menandai kelas yang tidak dikenal sebagai error dan membatalkan seluruh impor', function () {
    $preview = app(DaycareImportService::class)->preview([
        daycareImportRow(['kelas' => 'F']),
    ]);

    expect($preview['rows'][0]['status'])->toBe('error')
        ->and($preview['rows'][0]['errors'])->toContain('Kelas F tidak valid.')
        ->and($preview['summary']['error'])->toBe(1)
        ->and($preview['has_errors'])->toBeTrue();

    expect(fn () => app(DaycareImportService::class)->import([
        daycareImportRow(['kelas' => 'F']),
    ]))->toThrow(ValidationException::class);

    expect(DaycareChild::query()->count())->toBe(0);
});

it('menggabungkan validasi biodata sebelum membuat', function () {
    $preview = app(DaycareImportService::class)->preview([
        daycareImportRow(['tanggal_lahir' => '10-05-2022', 'jenis_kelamin' => 'X']),
    ]);

    expect($preview['rows'][0]['status'])->toBe('error')
        ->and($preview['rows'][0]['errors'])->toContain('Tanggal Lahir harus berformat YYYY-MM-DD.')
        ->and($preview['rows'][0]['errors'])->toContain('Jenis Kelamin harus L atau P.');
});

it('nama dan kelas yang sama tidak menimpa tetapi hanya memberi peringatan duplikat', function () {
    DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad', 'kelas' => 'C']);

    $preview = app(DaycareImportService::class)->preview([
        daycareImportRow(['nama_lengkap' => 'Ahmad', 'kelas' => 'C']),
    ]);

    expect($preview['rows'][0]['status'])->toBe('new')
        ->and($preview['rows'][0]['warnings'])->toContain('Nama dan kelas yang sama sudah ada.')
        ->and($preview['summary']['warning'])->toBe(1);

    $result = app(DaycareImportService::class)->import([
        daycareImportRow(['nama_lengkap' => 'Ahmad', 'kelas' => 'C']),
    ]);

    expect($result['created'])->toBe(1)
        ->and(DaycareChild::query()->count())->toBe(2);
});

it('tidak pernah memperbarui atau menimpa anak yang sudah ada', function () {
    $existing = DaycareChild::factory()->create([
        'nama_lengkap' => 'Ahmad',
        'kelas' => 'C',
        'nama_panggilan' => 'Ujang',
        'tempat_lahir' => 'Bandung',
    ]);

    app(DaycareImportService::class)->import([
        daycareImportRow(['nama_lengkap' => 'Ahmad', 'kelas' => 'C']),
    ]);

    expect(DaycareChild::query()->count())->toBe(2)
        ->and($existing->refresh()->nama_panggilan)->toBe('Ujang')
        ->and($existing->tempat_lahir)->toBe('Bandung');
});

it('menghitung preview new warning error dengan benar', function () {
    DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad', 'kelas' => 'C']);

    $preview = app(DaycareImportService::class)->preview([
        daycareImportRow(['row_number' => 2, 'nama_lengkap' => 'Baru 1', 'kelas' => 'A']),
        daycareImportRow(['row_number' => 3, 'nama_lengkap' => 'Ahmad', 'kelas' => 'C']),
        daycareImportRow(['row_number' => 4, 'kelas' => 'ZZ']),
    ]);

    expect($preview['summary']['total'])->toBe(3)
        ->and($preview['summary']['new'])->toBe(2)
        ->and($preview['summary']['warning'])->toBe(1)
        ->and($preview['summary']['error'])->toBe(1)
        ->and($preview['has_errors'])->toBeTrue();
});

it('impor seluruh file tidak berjalan sebagian saat ada baris bermasalah', function () {
    expect(fn () => app(DaycareImportService::class)->import([
        daycareImportRow(['row_number' => 2, 'nama_lengkap' => 'Boleh Masuk', 'kelas' => 'A']),
        daycareImportRow(['row_number' => 3, 'kelas' => 'F']),
    ]))->toThrow(ValidationException::class);

    expect(DaycareChild::query()->count())->toBe(0);
});

it('tidak membuat atau memodifikasi pembayaran Daycare', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Harus Tetap']);
    $payment = DaycarePayment::factory()->create([
        'daycare_child_id' => $child->id,
        'total_amount' => 750000,
    ]);

    app(DaycareImportService::class)->import([daycareImportRow(['nama_lengkap' => 'Baru', 'kelas' => 'A'])]);

    expect(DaycarePayment::query()->count())->toBe(1)
        ->and(DaycarePayment::query()->find($payment->id)?->total_amount)->toBe('750000.00');
});

it('tidak menyentuh domain siswa', function () {
    app(DaycareImportService::class)->import([daycareImportRow()]);

    expect(Student::query()->count())->toBe(0)
        ->and(StudentAcademicEnrollment::query()->count())->toBe(0)
        ->and(StudentBill::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

it('alur Livewire impor file langsung masuk preview NEW', function () {
    $path = daycareImportWorkbook([
        daycareImportRow(['nama_lengkap' => 'Baru 1', 'kelas' => 'A']),
    ]);

    try {
        Livewire::test(DaycareImport::class)
            ->set('file', UploadedFile::fake()->createWithContent('daycare.xlsx', file_get_contents($path)))
            ->call('previewImport')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('NEW', false);
    } finally {
        @unlink($path);
    }
});

it('template import unduhan tersedia melalui Livewire', function () {
    Livewire::test(DaycareImport::class)
        ->call('downloadTemplate')
        ->assertFileDownloaded('template-import-daycare.xlsx');
});

it('file template dapat dibaca ulang untuk kolom wajib', function () {
    $path = app(DaycareImportSpreadsheet::class)->createTemplate();

    try {
        $reader = new Reader;
        $reader->open($path);
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== 'DATA DAYCARE') {
                continue;
            }

            foreach ($sheet->getRowIterator() as $row) {
                $headers = $row->toArray();
                break;
            }

            break;
        }

        $reader->close();
    } finally {
        @unlink($path);
    }

    expect($headers)->toBe(DaycareImportSpreadsheet::HEADERS);
});
