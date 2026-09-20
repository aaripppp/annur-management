<?php

use App\Livewire\DaycareBulkUpdate;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\DaycareBulkUpdateService;
use App\Services\DaycareBulkUpdateSpreadsheet;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

function daycareUpdateRow(array $overrides = []): array
{
    return array_merge([
        'row_number' => 2,
        'id_sistem' => null,
        'kelas' => 'C',
        'nama_lengkap' => 'Ahmad Fauzan',
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

function daycareUpdateWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'annur-daycare-bulk-update-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('DATA DAYCARE');
    $writer->addRow(Row::fromValues(DaycareBulkUpdateSpreadsheet::HEADERS));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues([
            $row['id_sistem'] ?? '',
            $row['kelas'],
            $row['nama_lengkap'],
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

it('memperbarui anak yang sudah ada berdasarkan ID valid dan mengubah nilai yang diberikan', function () {
    $child = DaycareChild::factory()->create([
        'nama_lengkap' => 'Nama Lama',
        'kelas' => 'A',
        'nama_panggilan' => 'Lama',
        'tempat_lahir' => 'Solo',
    ]);

    $row = daycareUpdateRow([
        'id_sistem' => $child->id,
        'nama_lengkap' => 'Nama Baru',
        'kelas' => 'E',
        'nama_panggilan' => '',
        'tempat_lahir' => '',
        'tanggal_lahir' => '',
        'jenis_kelamin' => '',
        'alamat' => '',
        'nama_ayah' => '',
        'no_telp_ayah' => '',
        'nama_ibu' => '',
        'no_telp_ibu' => '',
    ]);

    $summary = app(DaycareBulkUpdateService::class)->preview([$row]);

    expect($summary['rows'][0]['status'])->toBe('ready')
        ->and($summary['summary']['ready'])->toBe(1);

    $updated = app(DaycareBulkUpdateService::class)->apply([$row]);

    expect($updated)->toBe(1)
        ->and(DaycareChild::query()->count())->toBe(1)
        ->and($child->refresh()->nama_lengkap)->toBe('Nama Baru')
        ->and($child->kelas)->toBe('E')
        ->and($child->nama_panggilan)->toBe('Lama')
        ->and($child->tempat_lahir)->toBe('Solo');
});

it('sel kosong pada update mempertahankan nilai yang sudah ada', function () {
    $child = DaycareChild::factory()->create([
        'nama_lengkap' => 'Ahmad',
        'kelas' => 'C',
        'nama_panggilan' => 'Ujang',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2022-01-01',
    ]);

    app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow([
            'id_sistem' => $child->id,
            'nama_lengkap' => '',
            'kelas' => 'C',
            'nama_panggilan' => '',
            'tempat_lahir' => 'Garut',
            'tanggal_lahir' => '',
            'jenis_kelamin' => '',
            'alamat' => '',
            'nama_ayah' => '',
            'no_telp_ayah' => '',
            'nama_ibu' => '',
            'no_telp_ibu' => '',
        ]),
    ]);

    expect($child->refresh()->nama_lengkap)->toBe('Ahmad')
        ->and($child->nama_panggilan)->toBe('Ujang')
        ->and($child->tanggal_lahir->format('Y-m-d'))->toBe('2022-01-01')
        ->and($child->tempat_lahir)->toBe('Garut');
});

it('menjaga nol di awal nomor telepon saat update', function () {
    $child = DaycareChild::factory()->create(['no_telp_ayah' => '082111222333']);

    app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow(['id_sistem' => $child->id, 'no_telp_ayah' => '081234567890']),
    ]);

    expect($child->refresh()->no_telp_ayah)->toBe('081234567890');
});

it('menolak ID kosong dan tidak membuat anak baru', function () {
    $preview = app(DaycareBulkUpdateService::class)->preview([
        daycareUpdateRow(['id_sistem' => null]),
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][0]['errors'])->toContain('ID Sistem tidak valid atau kosong.');

    $updated = app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow(['id_sistem' => null]),
    ]);

    expect($updated)->toBe(0)
        ->and(DaycareChild::query()->count())->toBe(0);
});

it('menolak ID yang bukan angka', function () {
    $preview = app(DaycareBulkUpdateService::class)->preview([
        daycareUpdateRow(['id_sistem' => 'abc']),
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][0]['errors'])->toContain('ID Sistem harus berupa angka.');
});

it('menolak ID yang tidak ditemukan meskipun nama dan kelas cocok', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad', 'kelas' => 'C']);

    $preview = app(DaycareBulkUpdateService::class)->preview([
        daycareUpdateRow(['id_sistem' => 99999, 'nama_lengkap' => 'Ahmad', 'kelas' => 'C']),
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][0]['errors'])->toContain('ID Sistem tidak ditemukan.');

    app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow(['id_sistem' => 99999, 'nama_lengkap' => 'Ahmad Aneh', 'kelas' => 'C']),
    ]);

    expect(DaycareChild::query()->count())->toBe(1)
        ->and($child->refresh()->nama_lengkap)->toBe('Ahmad');
});

it('identitas update hanya dari ID, bukan dari nama + kelas', function () {
    $first = DaycareChild::factory()->create(['nama_lengkap' => 'Sama', 'kelas' => 'B']);
    $second = DaycareChild::factory()->create(['nama_lengkap' => 'Sama', 'kelas' => 'B']);

    $updated = app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow(['id_sistem' => $first->id, 'nama_lengkap' => 'Berubah']),
    ]);

    expect($updated)->toBe(1)
        ->and($first->refresh()->nama_lengkap)->toBe('Berubah')
        ->and($second->refresh()->nama_lengkap)->toBe('Sama');
});

it('kelas yang diisi diperbarui dan kelas tidak valid ditolak', function () {
    $child = DaycareChild::factory()->create(['kelas' => 'A']);

    $updated = app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow(['id_sistem' => $child->id, 'kelas' => 'D']),
    ]);

    expect($updated)->toBe(1)
        ->and($child->refresh()->kelas)->toBe('D');

    $preview = app(DaycareBulkUpdateService::class)->preview([
        daycareUpdateRow(['id_sistem' => $child->id, 'kelas' => 'F']),
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][0]['errors'][0])->toContain('Kelas');
});

it('menolak tanggal lahir yang tidak valid', function () {
    $child = DaycareChild::factory()->create();

    $preview = app(DaycareBulkUpdateService::class)->preview([
        daycareUpdateRow(['id_sistem' => $child->id, 'tanggal_lahir' => '10-05-2022']),
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][0]['errors'])->toContain('Tanggal Lahir harus berformat YYYY-MM-DD.');
});

it('ID yang sama muncul berkali-kali ditolak', function () {
    $child = DaycareChild::factory()->create();

    $preview = app(DaycareBulkUpdateService::class)->preview([
        daycareUpdateRow(['row_number' => 2, 'id_sistem' => $child->id, 'nama_lengkap' => 'X']),
        daycareUpdateRow(['row_number' => 3, 'id_sistem' => $child->id, 'nama_lengkap' => 'Y']),
    ]);

    expect($preview['rows'][0]['status'])->toBe('blocked')
        ->and($preview['rows'][1]['status'])->toBe('blocked')
        ->and($preview['summary']['blocked'])->toBe(2);
});

it('menerapkan hanya baris siap update dan menghitung preview dengan benar', function () {
    $ready = DaycareChild::factory()->create(['nama_lengkap' => 'Berubah', 'kelas' => 'A']);
    $unchanged = DaycareChild::factory()->create(['nama_lengkap' => 'Tetap', 'kelas' => 'C']);

    $rows = [
        daycareUpdateRow(['row_number' => 2, 'id_sistem' => $ready->id, 'nama_lengkap' => 'Berubah Lagi']),
        daycareUpdateRow([
            'row_number' => 3,
            'id_sistem' => $unchanged->id,
            'kelas' => '',
            'nama_lengkap' => '',
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
        daycareUpdateRow(['row_number' => 4, 'id_sistem' => 99999]),
    ];

    $preview = app(DaycareBulkUpdateService::class)->preview($rows);

    expect($preview['summary']['total'])->toBe(3)
        ->and($preview['summary']['ready'])->toBe(1)
        ->and($preview['summary']['unchanged'])->toBe(1)
        ->and($preview['summary']['blocked'])->toBe(1);

    $updated = app(DaycareBulkUpdateService::class)->apply($rows);

    expect($updated)->toBe(1)
        ->and($ready->refresh()->nama_lengkap)->toBe('Berubah Lagi')
        ->and($unchanged->refresh()->nama_lengkap)->toBe('Tetap');
});

it('export per kelas hanya berisi anak pada kelas tersebut dengan ID stabil', function () {
    $childA = DaycareChild::factory()->create(['nama_lengkap' => 'Anak Kelas A', 'kelas' => 'A']);
    DaycareChild::factory()->create(['nama_lengkap' => 'Anak Kelas B', 'kelas' => 'B']);

    $path = app(DaycareBulkUpdateSpreadsheet::class)->create('A');
    $rows = [];

    try {
        $reader = new Reader;
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== 'DATA DAYCARE') {
                continue;
            }

            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index === 1) {
                    continue;
                }

                $rows[] = $row->toArray();
            }
        }

        $reader->close();
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and($rows[0][0])->toBe((int) $childA->id)
        ->and($rows[0][1])->toBe('A')
        ->and($rows[0][2])->toBe('Anak Kelas A');
});

it('export Semua Kelas berisi seluruh anak', function () {
    $childA = DaycareChild::factory()->create(['kelas' => 'A']);
    $childB = DaycareChild::factory()->create(['kelas' => 'B']);

    $path = app(DaycareBulkUpdateSpreadsheet::class)->create(null);
    $parsed = app(DaycareBulkUpdateSpreadsheet::class)->read($path);

    try {
        expect($parsed)->toHaveCount(2)
            ->and(collect($parsed)->pluck('id_sistem')->map(fn ($id) => (int) $id)->all())
            ->toContain($childA->id, $childB->id);
    } finally {
        @unlink($path);
    }
});

it('hasil export yang dibaca ulang dapat dikirim kembali untuk update', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Ahmad', 'kelas' => 'B']);

    $path = app(DaycareBulkUpdateSpreadsheet::class)->create(null);

    try {
        $rows = app(DaycareBulkUpdateSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]['id_sistem'])->toBe($child->id);

    $rows[0]['nama_lengkap'] = 'Ahmad Diperbarui';

    $updated = app(DaycareBulkUpdateService::class)->apply($rows);

    expect($updated)->toBe(1)
        ->and($child->refresh()->nama_lengkap)->toBe('Ahmad Diperbarui');
});

it('tidak membuat atau memodifikasi pembayaran Daycare', function () {
    $child = DaycareChild::factory()->create();
    $payment = DaycarePayment::factory()->create([
        'daycare_child_id' => $child->id,
        'total_amount' => 750000,
    ]);

    app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow(['id_sistem' => $child->id, 'nama_lengkap' => 'Berubah']),
    ]);

    expect(DaycarePayment::query()->count())->toBe(1)
        ->and(DaycarePayment::query()->find($payment->id)?->total_amount)->toBe('750000.00')
        ->and(DaycarePayment::query()->find($payment->id)?->daycare_child_id)->toBe($child->id);
});

it('tidak menyentuh domain siswa', function () {
    $child = DaycareChild::factory()->create([
        'nama_lengkap' => 'Bukan Siswa',
        'nama_ayah' => '',
    ]);

    app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow(['id_sistem' => $child->id, 'nama_ayah' => 'Ayah Baru']),
    ]);

    expect(Student::query()->count())->toBe(0)
        ->and(StudentBill::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

it('alur Livewire update dengan filter kelas, preview, dan konfirmasi', function () {
    $child = DaycareChild::factory()->create(['nama_lengkap' => 'Siti', 'kelas' => 'B']);

    $component = Livewire::test(DaycareBulkUpdate::class)
        ->set('filterKelas', 'B');

    $component->call('downloadTemplate')
        ->assertFileDownloaded('update-data-daycare.xlsx');

    $path = daycareUpdateWorkbook([
        daycareUpdateRow(['id_sistem' => $child->id, 'nama_lengkap' => 'Siti Updated']),
    ]);

    try {
        $component
            ->set('file', UploadedFile::fake()->createWithContent('update.xlsx', file_get_contents($path)))
            ->call('previewUpdate')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSee('SIAP UPDATE', false)
            ->call('confirmUpdate')
            ->assertSet('step', 3)
            ->assertSee('Update Selesai');
    } finally {
        @unlink($path);
    }

    expect($child->refresh()->nama_lengkap)->toBe('Siti Updated');
});

it('memperbarui anak tanpa mengubah is_active', function () {
    $child = DaycareChild::factory()->create(['is_active' => true, 'nama_lengkap' => 'Aktif']);

    app(DaycareBulkUpdateService::class)->apply([
        daycareUpdateRow(['id_sistem' => $child->id, 'nama_lengkap' => 'Aktif Baru']),
    ]);

    expect($child->refresh()->nama_lengkap)->toBe('Aktif Baru')
        ->and($child->is_active)->toBeTrue();
});
