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
        'nomor_kk' => '3201010101010002',
        'nomor_akta_kelahiran' => '1234/2015',
        'anak_ke' => 2,
        'jumlah_saudara' => 3,
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
        ->toContain('E. DATA ORANG TUA / WALI')
        ->toContain('F. DATA SAUDARA KANDUNG')
        ->toContain('REG-2026-000123')
        ->toContain('2026/2027')
        ->toContain('SMP')
        ->toContain('VII ')
        ->toContain('Keluarga Annur')
        ->toContain('AHMAD FAUZAN')
        ->toContain('AHMAD')
        ->toContain('Laki-laki')
        ->toContain('3201010101010001')
        ->toContain('3201010101010002')
        ->toContain('1234/2015')
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
        ->toContain('Rp1.000.000')
        ->toContain('SITI AMINAH')
        ->toContain('MUHAMMAD RIZKI')
        ->toContain('TK B')
        ->toContain('Belum sekolah')
        ->toContain('<div class="guardian-block">')
        ->not->toContain('Checklist Dokumen')
        ->not->toContain('Tagihan')
        ->not->toContain('Pembayaran')
        ->not->toContain('Catatan Internal');

    expect(strpos($html, 'A. INFORMASI PENDAFTARAN'))->toBeLessThan(strpos($html, 'B. IDENTITAS CALON SISWA'))
        ->and(strpos($html, 'B. IDENTITAS CALON SISWA'))->toBeLessThan(strpos($html, 'C. ALAMAT TEMPAT TINGGAL'))
        ->and(strpos($html, 'C. ALAMAT TEMPAT TINGGAL'))->toBeLessThan(strpos($html, 'D. KEADAAN JASMANI'))
        ->and(strpos($html, 'D. KEADAAN JASMANI'))->toBeLessThan(strpos($html, 'E. DATA ORANG TUA'))
        ->and(strpos($html, 'E. DATA ORANG TUA'))->toBeLessThan(strpos($html, 'F. DATA SAUDARA KANDUNG'))
        ->and(strpos($html, 'SITI AMINAH'))->toBeLessThan(strpos($html, 'MUHAMMAD RIZKI'));
});

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
        ->toContain('Asal Sekolah')
        ->toContain('Berkebutuhan Khusus')
        ->toContain('-')
        ->not->toContain('<div class="guardian-block">')
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
        ->not->toContain('<div class="guardian-block">')
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

    preg_match('/<div class="guardian-block">(.*?)<\/table>/s', $html, $guardianBlock);

    expect($html)->toContain('<div class="guardian-block">')
        ->and($guardianBlock[1] ?? '')->toContain('Wali')
        ->toContain('DEWI LESTARI')
        ->toContain('NIK')
        ->toContain('-');
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

it('allows unusually long biodata to flow to another page without clipping', function () {
    $student = ppdbBiodataApplicant([
        'alamat' => str_repeat('Jl. Melati No. 10, RT 01/RW 02, Harapan Jaya, Bekasi Utara. ', 18),
    ]);
    ppdbBiodataProfile($student, [
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
