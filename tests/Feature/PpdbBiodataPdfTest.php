<?php

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Livewire\ProspectiveStudentDetail;
use App\Models\AcademicYear;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function ppdbBiodataUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => $active,
    ]);
}

function ppdbBiodataApplicant(array $overrides = []): ProspectiveStudent
{
    $year = AcademicYear::firstOrCreate(
        ['year' => '2026/2027'],
        [
            'is_active' => true,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]
    );

    $class = SchoolClass::factory()->create(['level' => 7]);

    return ProspectiveStudent::factory()->create(array_merge([
        'registration_number' => 'REG-2026-000123',
        'nama_lengkap' => 'Ahmad Fauzan',
        'nama_panggilan' => 'Ahmad',
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Melati No. 10',
        'academic_year_id' => $year->id,
        'school_class_id' => $class->id,
        'status' => ProspectiveStudentStatus::Registered,
        'applicant_category' => PpdbApplicantCategory::ANNUR_FAMILY,
    ], $overrides));
}

function ppdbBiodataProfile(ProspectiveStudent $student, array $overrides = []): PpdbProfile
{
    return PpdbProfile::create(array_merge([
        'prospective_student_id' => $student->id,
        'tempat_lahir' => 'Bekasi',
        'tanggal_lahir' => '2015-08-17',
        'asal_sekolah' => 'TK Nurul Ilmi',
        'nik_siswa' => '3201010101010001',
        'nisn' => '0012345678',
        'nomor_kk' => '3201010101010002',
        'nomor_akta_kelahiran' => '1234/2015',
        'anak_ke' => 2,
        'jumlah_saudara' => 3,
        'hobi' => 'Membaca',
        'cita_cita' => 'Dokter',
        'jarak_tempuh_kategori' => 'more_than_1_km',
        'jarak_tempuh_detail' => '2.50',
        'moda_transportasi' => 'lainnya',
        'moda_transportasi_lainnya' => 'Perahu sekolah',
        'status_anak' => 'Kandung',
        'agama' => 'Islam',
        'bahasa_sehari_hari' => 'Bahasa Indonesia',
        'rt' => '01',
        'rw' => '02',
        'kelurahan' => 'Harapan Jaya',
        'kecamatan' => 'Bekasi Utara',
        'kabupaten_kota' => 'Bekasi',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '17124',
        'tinggi_badan' => 120.5,
        'berat_badan' => 25,
        'lingkar_kepala' => 50,
        'golongan_darah' => 'O',
        'riwayat_penyakit' => 'Tidak ada',
        'berkebutuhan_khusus' => true,
        'keterangan_kebutuhan_khusus' => 'Pendampingan belajar',
    ], $overrides));
}

function ppdbBiodataParent(ProspectiveStudent $student, string $role, array $overrides = []): PpdbParent
{
    return PpdbParent::create(array_merge([
        'prospective_student_id' => $student->id,
        'role' => $role,
        'status_hidup' => match ($role) {
            'father' => 'hidup',
            'mother' => 'wafat',
            default => null,
        },
        'nik' => '320101010101'.match ($role) {
            'father' => '0011',
            'mother' => '0012',
            default => '0013',
        },
        'tempat_lahir' => 'Bekasi',
        'tanggal_lahir' => '1985-01-02',
        'pendidikan_terakhir' => 'SMA',
        'email' => $role.'@example.test',
        'nama' => match ($role) {
            'father' => 'Budi Santoso',
            'mother' => 'Siti Rahayu',
            default => 'Dewi Lestari',
        },
        'no_telp' => '081234567890',
        'occupation' => 'Karyawan',
        'income_range' => ParentIncomeRange::OneToTwoMillion,
    ], $overrides));
}

function ppdbBiodataSibling(ProspectiveStudent $student, int $sortOrder, array $overrides = []): PpdbSibling
{
    return PpdbSibling::create(array_merge([
        'prospective_student_id' => $student->id,
        'sort_order' => $sortOrder,
        'nama_lengkap' => 'Siti Aminah',
        'tanggal_lahir' => '2018-03-05',
        'jenis_kelamin' => 'P',
        'pendidikan_pekerjaan' => 'TK B',
    ], $overrides));
}

function fakePpdbBiodataPdfRenderer(): object
{
    $renderer = new class
    {
        public string $view = '';

        /** @var array<string, mixed> */
        public array $data = [];

        /** @var array<string, mixed> */
        public array $options = [];

        /** @var array{0: string, 1: string}|null */
        public ?array $paper = null;

        public string $filename = '';

        /** @param array<string, mixed> $data */
        public function loadView(string $view, array $data): self
        {
            $this->view = $view;
            $this->data = $data;

            return $this;
        }

        /** @param array<string, mixed> $options */
        public function setOption(array $options): self
        {
            $this->options = $options;

            return $this;
        }

        public function setPaper(string $paper, string $orientation = 'portrait'): self
        {
            $this->paper = [$paper, $orientation];

            return $this;
        }

        public function stream(string $filename): Response
        {
            $this->filename = $filename;

            return response('PDF', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename='.$filename,
            ]);
        }
    };

    Pdf::swap($renderer);

    return $renderer;
}

function ppdbBiodataPdfPageSize(string $pdf): array
{
    preg_match('/\/MediaBox\s*\[\s*(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s*]/', $pdf, $matches);

    return [(float) ($matches[3] ?? 0), (float) ($matches[4] ?? 0)];
}

function ppdbBiodataPdfPageCount(string $pdf): int
{
    return preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf);
}

afterEach(function () {
    $this->app->forgetInstance('dompdf.wrapper');
    Pdf::clearResolvedInstances();
});

it('requires authentication for the applicant biodata pdf', function () {
    $student = ppdbBiodataApplicant();

    $this->get(route('calon-siswa.biodata.pdf', $student))
        ->assertRedirect(route('login'));
});

it('allows active admin and super admin to preview applicant biodata pdf', function (string $role) {
    $student = ppdbBiodataApplicant();
    $renderer = fakePpdbBiodataPdfRenderer();

    $response = $this->actingAs(ppdbBiodataUser($role))
        ->get(route('calon-siswa.biodata.pdf', $student));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=biodata-ppdb-REG-2026-000123.pdf')
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('private')
        ->and($renderer->view)->toBe('reports.ppdb-biodata-pdf')
        ->and($renderer->options)->toMatchArray([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ])
        ->and($renderer->paper)->toBe(['a4', 'portrait'])
        ->and($renderer->filename)->toBe('biodata-ppdb-REG-2026-000123.pdf');
})->with([
    'admin' => User::ROLE_ADMIN,
    'super admin' => User::ROLE_SUPER_ADMIN,
]);

it('denies inactive and unsupported roles from downloading applicant biodata pdf', function (string $role, bool $active) {
    $student = ppdbBiodataApplicant();
    $user = ppdbBiodataUser();

    $user->setAttribute('role', $role);
    $user->setAttribute('is_active', $active);

    $this->actingAs($user)
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertForbidden();
})->with([
    'unsupported role' => ['auditor', true],
    'inactive admin' => [User::ROLE_ADMIN, false],
]);

it('returns not found for a missing applicant', function () {
    $this->actingAs(ppdbBiodataUser())
        ->get('/calon-siswa/999999/biodata/pdf')
        ->assertNotFound();
});

it('still renders canceled and converted applicants for authorized users', function (ProspectiveStudentStatus $status) {
    fakePpdbBiodataPdfRenderer();
    $student = ppdbBiodataApplicant(['status' => $status]);

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();
})->with([
    'cancelled' => ProspectiveStudentStatus::Cancelled,
    'converted' => ProspectiveStudentStatus::Converted,
]);

it('shows the print button for canceled applicants to authorized users', function () {
    $student = ppdbBiodataApplicant(['status' => ProspectiveStudentStatus::Cancelled]);
    ppdbBiodataProfile($student);

    Livewire::actingAs(ppdbBiodataUser())
        ->test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $student])
        ->assertSee('Cetak Biodata PDF')
        ->assertSee(route('calon-siswa.biodata.pdf', $student), false)
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('rel="noopener"');
});

it('hides the print button from unsupported users', function () {
    $student = ppdbBiodataApplicant();
    $user = ppdbBiodataUser();
    $user->setAttribute('role', 'auditor');

    Livewire::actingAs($user)
        ->test(ProspectiveStudentDetail::class, ['prospectiveStudent' => $student])
        ->assertDontSee('Cetak Biodata PDF');
});

it('renders all biodata sections and canonical field values', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student);
    ppdbBiodataParent($student, 'father');
    ppdbBiodataParent($student, 'mother');
    ppdbBiodataParent($student, 'guardian');
    ppdbBiodataSibling($student, 2, [
        'nama_lengkap' => 'Muhammad Rizki',
        'tanggal_lahir' => '2020-06-10',
        'jenis_kelamin' => 'L',
        'pendidikan_pekerjaan' => 'Belum sekolah',
    ]);
    ppdbBiodataSibling($student, 1, [
        'nama_lengkap' => 'Siti Aminah',
        'tanggal_lahir' => '2018-03-05',
        'jenis_kelamin' => 'P',
        'pendidikan_pekerjaan' => 'TK B',
    ]);

    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->toContain('BIODATA CALON SISWA PPDB')
        ->toContain('A. INFORMASI PENDAFTARAN')
        ->toContain('B. IDENTITAS CALON SISWA')
        ->toContain('C. ALAMAT TEMPAT TINGGAL')
        ->toContain('D. KEADAAN JASMANI &amp; KESEHATAN')
        ->toContain('E. DATA ORANG TUA')
        ->not->toContain('DATA ORANG TUA / WALI')
        ->toContain('F. DATA WALI')
        ->toContain('G. DATA SAUDARA KANDUNG')
        ->toContain('REG-2026-000123')
        ->toContain('2026/2027')
        ->toContain('SMP')
        ->toContain('VII ')
        ->toContain('Keluarga Annur')
        ->toContain('AHMAD FAUZAN')
        ->toContain('AHMAD')
        ->toContain('Laki-laki')
        ->toContain('3201010101010001')
        ->toContain('0012345678')
        ->toContain('3201010101010002')
        ->toContain('1234/2015')
        ->toContain('Membaca')
        ->toContain('Dokter')
        ->toContain('Lebih dari 1 km')
        ->toContain('2,50 km')
        ->toContain('Lainnya')
        ->toContain('Perahu sekolah')
        ->toContain('Bekasi')
        ->toContain('17 Agustus 2015')
        ->toContain('TK Nurul Ilmi')
        ->toContain('Kandung')
        ->toContain('Islam')
        ->toContain('Bahasa Indonesia')
        ->toContain('Jl. Melati No. 10')
        ->toContain('Harapan Jaya')
        ->toContain('Bekasi Utara')
        ->toContain('Jawa Barat')
        ->toContain('17124')
        ->toContain('120,5')
        ->toContain('25')
        ->toContain('50')
        ->toContain('Tidak ada')
        ->toContain('Pendampingan belajar')
        ->toContain('BUDI SANTOSO')
        ->toContain('SITI RAHAYU')
        ->toContain('DEWI LESTARI')
        ->toContain('Hidup')
        ->toContain('Wafat')
        ->toContain('Rp1.000.000')
        ->toContain('SITI AMINAH')
        ->toContain('MUHAMMAD RIZKI')
        ->toContain('TK B')
        ->toContain('Belum sekolah')
        ->toContain('<div class="section guardian-block">')
        ->toContain('<table class="kv guardian-table">')
        ->not->toContain('more_than_1_km')
        ->not->toContain('lainnya')
        ->not->toContain('Dokumen Persyaratan')
        ->not->toContain('Checklist Dokumen')
        ->not->toContain('Tagihan')
        ->not->toContain('Pembayaran')
        ->not->toContain('Catatan Internal');

    expect(strpos($html, 'A. INFORMASI PENDAFTARAN'))->toBeLessThan(strpos($html, 'B. IDENTITAS CALON SISWA'))
        ->and(strpos($html, 'B. IDENTITAS CALON SISWA'))->toBeLessThan(strpos($html, 'C. ALAMAT TEMPAT TINGGAL'))
        ->and(strpos($html, 'C. ALAMAT TEMPAT TINGGAL'))->toBeLessThan(strpos($html, 'D. KEADAAN JASMANI'))
        ->and(strpos($html, 'D. KEADAAN JASMANI'))->toBeLessThan(strpos($html, 'E. DATA ORANG TUA'))
        ->and(strpos($html, 'E. DATA ORANG TUA'))->toBeLessThan(strpos($html, 'F. DATA WALI'))
        ->and(strpos($html, 'F. DATA WALI'))->toBeLessThan(strpos($html, 'G. DATA SAUDARA KANDUNG'))
        ->and(strpos($html, 'SITI AMINAH'))->toBeLessThan(strpos($html, 'MUHAMMAD RIZKI'));

    expect(strpos($html, 'NIK Siswa'))->toBeLessThan(strpos($html, 'NISN'));

    preg_match('/<table class="kv identity-table">(.*?)<\/table>/s', $html, $identityTable);
    preg_match_all('/<tr data-identity-row>(.*?)<\/tr>/s', $identityTable[1] ?? '', $identityRows);

    expect($identityTable[1] ?? '')
        ->not->toContain('colspan')
        ->and($identityRows[1])->toHaveCount(9)
        ->and(collect($identityRows[1])->every(fn (string $row): bool => substr_count($row, '<td') === 4))->toBeTrue()
        ->and(collect($identityRows[1])->slice(0, -1)->every(fn (string $row): bool => ! str_contains($row, 'data-identity-empty')))->toBeTrue()
        ->and($identityRows[1][8])->toContain('Bahasa Sehari-hari')
        ->toContain('data-identity-empty');

    preg_match('/F\. DATA WALI(.*?)G\. DATA SAUDARA KANDUNG/s', $html, $guardianSection);
    expect($guardianSection[1] ?? '')
        ->toContain('DEWI LESTARI')
        ->not->toContain('Status Hidup');
});

it('hides NISN for actual TK applicants', function () {
    $student = ppdbBiodataApplicant();
    $tkClass = SchoolClass::factory()->create(['level' => -1]);
    $student->update(['school_class_id' => $tkClass->id]);
    ppdbBiodataProfile($student, ['nisn' => '0012345678']);
    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->not->toContain('>NISN<')
        ->not->toContain('0012345678');

    preg_match('/<table class="kv identity-table">(.*?)<\/table>/s', $html, $identityTable);
    preg_match_all('/<tr data-identity-row>(.*?)<\/tr>/s', $identityTable[1] ?? '', $identityRows);

    expect($identityTable[1] ?? '')
        ->not->toContain('colspan')
        ->not->toContain('data-identity-empty')
        ->and($identityRows[1])->toHaveCount(8)
        ->and(collect($identityRows[1])->every(fn (string $row): bool => substr_count($row, '<td') === 4))->toBeTrue();
});

it('hides inactive distance and transportation details', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student, [
        'jarak_tempuh_kategori' => 'less_than_1_km',
        'jarak_tempuh_detail' => '8.50',
        'moda_transportasi' => 'motor',
        'moda_transportasi_lainnya' => 'Tidak boleh tampil',
    ]);
    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->toContain('Kurang dari 1 km')
        ->toContain('Motor')
        ->not->toContain('Detail Jarak')
        ->not->toContain('8,50 km')
        ->not->toContain('Transportasi Lainnya')
        ->not->toContain('Tidak boleh tampil')
        ->not->toContain('less_than_1_km');
});

it('shows dashes for missing active distance and other transportation details', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student, [
        'jarak_tempuh_kategori' => 'more_than_1_km',
        'jarak_tempuh_detail' => null,
        'moda_transportasi' => 'lainnya',
        'moda_transportasi_lainnya' => null,
    ]);
    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->toContain('<td class="label">Detail Jarak</td><td class="value">-</td>')
        ->toContain('<td class="label">Transportasi Lainnya</td><td class="value">-</td>');
});

it('uses readable transportation labels', function (string $storedValue, string $expectedLabel) {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student, [
        'moda_transportasi' => $storedValue,
        'moda_transportasi_lainnya' => $storedValue === 'lainnya' ? 'Perahu' : null,
    ]);
    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->toContain('<td class="label">Moda Transportasi</td><td class="value">'.$expectedLabel.'</td>')
        ->not->toContain('<td class="value">'.$storedValue.'</td>');
})->with([
    'walk' => ['jalan_kaki', 'Jalan kaki'],
    'bicycle' => ['sepeda', 'Sepeda'],
    'motorcycle' => ['motor', 'Motor'],
    'private car' => ['mobil_pribadi', 'Mobil pribadi'],
    'public transportation' => ['angkutan_umum', 'Angkutan umum'],
    'shuttle' => ['antar_jemput', 'Antar-jemput'],
    'other' => ['lainnya', 'Lainnya'],
]);

it('keeps parent cards and sibling rows together with page-break safeguards', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student);
    ppdbBiodataParent($student, 'father');
    ppdbBiodataParent($student, 'mother');
    ppdbBiodataSibling($student, 1);

    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->toContain('class="parents-group"')
        ->toContain('class="layout parents-row"')
        ->toContain('class="parent-card"')
        ->toContain('page-break-inside: avoid')
        ->toContain('page-break-after: avoid')
        ->toContain('display: table-header-group');
});

it('omits guardian section and shows a dash when optional data is missing', function () {
    $student = ppdbBiodataApplicant([
        'nama_panggilan' => null,
        'alamat' => null,
    ]);
    ppdbBiodataProfile($student, [
        'asal_sekolah' => null,
        'berkebutuhan_khusus' => null,
        'keterangan_kebutuhan_khusus' => null,
        'riwayat_penyakit' => null,
    ]);
    ppdbBiodataParent($student, 'father', ['nama' => null]);

    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->toContain('Tidak ada data saudara kandung.')
        ->toContain('F. DATA SAUDARA KANDUNG')
        ->not->toContain('F. DATA WALI')
        ->not->toContain('G. DATA SAUDARA KANDUNG')
        ->toContain('Asal Sekolah')
        ->toContain('Berkebutuhan Khusus')
        ->toContain('-')
        ->not->toContain('<div class="section guardian-block">')
        ->not->toContain('>Wali</div>')
        ->not->toContain('DEWI LESTARI')
        ->not->toContain('MUHAMMAD RIZKI');
});

it('omits the guardian block when a guardian row has no biodata content', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student);
    ppdbBiodataParent($student, 'father');
    ppdbBiodataParent($student, 'mother');
    ppdbBiodataParent($student, 'guardian', [
        'nama' => null,
        'nik' => null,
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'pendidikan_terakhir' => null,
        'email' => null,
        'no_telp' => null,
        'occupation' => null,
        'income_range' => null,
    ]);

    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->not->toContain('<div class="section guardian-block">')
        ->not->toContain('>Wali</div>')
        ->toContain('Ayah')
        ->toContain('Ibu');
});

it('renders a partial guardian block with dashes for missing fields', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student);
    ppdbBiodataParent($student, 'father');
    ppdbBiodataParent($student, 'mother');
    ppdbBiodataParent($student, 'guardian', [
        'nama' => 'Dewi Lestari',
        'nik' => null,
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'pendidikan_terakhir' => null,
        'email' => null,
        'no_telp' => null,
        'occupation' => null,
        'income_range' => null,
    ]);

    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    preg_match('/<div class="section guardian-block">(.*?)<\/table>/s', $html, $guardianBlock);

    expect($html)->toContain('<div class="section guardian-block">')
        ->and($guardianBlock[1] ?? '')->toContain('F. DATA WALI')
        ->toContain('DEWI LESTARI')
        ->toContain('NIK')
        ->toContain('-')
        ->not->toContain('Status Hidup');
});

it('renders missing and unknown new biodata values safely', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student, [
        'nisn' => null,
        'hobi' => null,
        'cita_cita' => null,
        'jarak_tempuh_kategori' => 'legacy_distance',
        'jarak_tempuh_detail' => null,
        'moda_transportasi' => 'legacy_transportation',
        'moda_transportasi_lainnya' => null,
    ]);
    ppdbBiodataParent($student, 'father', ['status_hidup' => null]);
    ppdbBiodataParent($student, 'mother', ['status_hidup' => 'legacy_status']);
    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->toContain('<td class="label">NISN</td>')
        ->toContain('<td class="label">Hobi</td>')
        ->toContain('<td class="label">Cita-cita</td>')
        ->toContain('<td class="label">Jarak Tempuh</td><td class="value">-</td>')
        ->toContain('<td class="label">Moda Transportasi</td><td class="value">-</td>')
        ->not->toContain('legacy_distance')
        ->not->toContain('legacy_transportation')
        ->not->toContain('legacy_status');
});

it('shows the special needs description only when the status is explicitly Ya', function (
    mixed $storedValue,
    mixed $description,
    bool $expectDescriptionRow,
    string $expectedStatus,
) {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student, [
        'berkebutuhan_khusus' => $storedValue,
        'keterangan_kebutuhan_khusus' => $description,
    ]);

    if (is_string($storedValue)) {
        DB::table('ppdb_profiles')
            ->where('prospective_student_id', $student->id)
            ->update(['berkebutuhan_khusus' => $storedValue]);
    }

    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();
    $statusRow = '<td class="label">Berkebutuhan Khusus</td><td class="value">'.$expectedStatus.'</td>';
    $descriptionRow = '<td class="label">Keterangan Kebutuhan Khusus</td>';

    expect($html)
        ->toContain('D. KEADAAN JASMANI')
        ->toContain('<td class="label">Tinggi Badan (cm)</td><td class="value">120,5</td>')
        ->toContain('<td class="label">Berat Badan (kg)</td><td class="value">25</td>')
        ->toContain('<td class="label">Lingkar Kepala (cm)</td><td class="value">50</td>')
        ->toContain('<td class="label">Golongan Darah</td><td class="value">O</td>')
        ->toContain('<td class="label">Riwayat Penyakit</td><td class="value pre-line">Tidak ada</td>')
        ->toContain($statusRow)
        ->not->toContain('<td class="label"></td>')
        ->not->toContain('<td class="value pre-line"></td>');

    if ($expectDescriptionRow) {
        expect($html)->toContain($descriptionRow);

        if ($description === null || $description === '') {
            expect($html)->toContain('<td class="value pre-line">-</td>');
        } else {
            expect($html)->toContain((string) $description);
        }

        return;
    }

    expect($html)->not->toContain($descriptionRow);

    if (is_string($description) && $description !== '') {
        expect($html)->not->toContain($description);
    }
})->with([
    'true with description' => [true, 'Pendampingan belajar', true, 'Ya'],
    'true without description' => [true, '', true, 'Ya'],
    'false with description' => [false, 'Pendampingan belajar', false, 'Tidak'],
    'false without description' => [false, null, false, 'Tidak'],
    'null with description' => [null, 'Pendampingan belajar', false, '-'],
    'string one with description' => ['1', 'Pendampingan belajar', true, 'Ya'],
    'string zero with description' => ['0', 'Pendampingan belajar', false, 'Tidak'],
]);

it('renders unknown legacy category and income values without throwing', function () {
    $student = ppdbBiodataApplicant();
    $parent = ppdbBiodataParent($student, 'father');

    DB::table('prospective_students')
        ->where('id', $student->id)
        ->update(['applicant_category' => 'legacy_category']);

    DB::table('ppdb_parents')
        ->where('id', $parent->id)
        ->update(['income_range' => 'legacy_income']);

    $renderer = fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk();

    $html = view($renderer->view, $renderer->data)->render();

    expect($html)
        ->toContain('legacy_category')
        ->toContain('legacy_income');
});

it('sanitizes the registration number in the pdf filename', function () {
    $student = ppdbBiodataApplicant(['registration_number' => 'REG/2026 000 123']);
    fakePpdbBiodataPdfRenderer();

    $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=biodata-ppdb-REG-2026-000-123.pdf');
});

it('fits a typical biodata on one A4 page', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student);
    ppdbBiodataParent($student, 'father');
    ppdbBiodataParent($student, 'mother');
    ppdbBiodataParent($student, 'guardian');
    ppdbBiodataSibling($student, 1);
    ppdbBiodataSibling($student, 2, [
        'nama_lengkap' => 'Muhammad Rizki',
        'tanggal_lahir' => '2020-06-10',
        'jenis_kelamin' => 'L',
        'pendidikan_pekerjaan' => 'Belum sekolah',
    ]);

    $response = $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student));

    $response->assertOk();
    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF-')
        ->and(ppdbBiodataPdfPageCount($pdf))->toBe(1);
});

it('renders the compact identity table through Dompdf for TK and non-TK applicants', function (int $classLevel) {
    $student = ppdbBiodataApplicant();
    $class = SchoolClass::factory()->create(['level' => $classLevel]);
    $student->update(['school_class_id' => $class->id]);
    ppdbBiodataProfile($student);

    $response = $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF-')
        ->and(ppdbBiodataPdfPageCount($pdf))->toBe(1);
})->with([
    'TK' => -1,
    'non-TK' => 7,
]);

it('allows unusually long biodata to flow to another page without clipping', function () {
    $student = ppdbBiodataApplicant([
        'alamat' => str_repeat('Jl. Melati No. 10, RT 01/RW 02, Harapan Jaya, Bekasi Utara. ', 18),
    ]);
    ppdbBiodataProfile($student, [
        'hobi' => str_repeat('Membaca, berenang, dan kegiatan seni. ', 30),
        'cita_cita' => str_repeat('Menjadi tenaga profesional yang bermanfaat. ', 25),
        'riwayat_penyakit' => str_repeat('Riwayat kesehatan perlu perhatian khusus. ', 60),
        'keterangan_kebutuhan_khusus' => str_repeat('Pendampingan belajar dan observasi. ', 40),
    ]);
    ppdbBiodataParent($student, 'father');
    ppdbBiodataParent($student, 'mother');

    for ($index = 1; $index <= 12; $index++) {
        ppdbBiodataSibling($student, $index, [
            'nama_lengkap' => 'Saudara '.$index,
            'tanggal_lahir' => '2018-03-05',
            'jenis_kelamin' => $index % 2 === 0 ? 'L' : 'P',
            'pendidikan_pekerjaan' => 'TK B',
        ]);
    }

    $response = $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student));

    $response->assertOk();
    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF-')
        ->and(ppdbBiodataPdfPageCount($pdf))->toBeGreaterThanOrEqual(2);
});

it('streams a valid A4 portrait pdf', function () {
    $student = ppdbBiodataApplicant();
    ppdbBiodataProfile($student);

    $response = $this->actingAs(ppdbBiodataUser())
        ->get(route('calon-siswa.biodata.pdf', $student));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=biodata-ppdb-REG-2026-000123.pdf');

    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF-');

    [$width, $height] = ppdbBiodataPdfPageSize($pdf);

    expect($width)->toEqualWithDelta(595.28, 1.0)
        ->and($height)->toEqualWithDelta(841.89, 1.0)
        ->and($height)->toBeGreaterThan($width);
});
