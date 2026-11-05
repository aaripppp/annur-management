<?php

use App\Enums\PpdbDocumentType;
use App\Models\PpdbDocument;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function ppdbDocumentUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => $active,
    ]);
}

function ppdbDocumentContent(string $mimeType): string
{
    return match ($mimeType) {
        'application/pdf' => "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF",
        'image/png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        'image/jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==', true),
        default => 'unsupported-content',
    };
}

function makePpdbDocument(
    string $mimeType = 'application/pdf',
    string $originalName = 'dokumen.pdf',
    ?ProspectiveStudent $prospectiveStudent = null,
): PpdbDocument {
    Storage::fake('local');

    $prospectiveStudent ??= ProspectiveStudent::factory()->create();
    $extension = match ($mimeType) {
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        default => 'bin',
    };
    $path = 'ppdb/documents/2026/10/test-'.$prospectiveStudent->id.'/bukti-transfer.'.$extension;
    $content = ppdbDocumentContent($mimeType);
    Storage::disk('local')->put($path, $content);

    return PpdbDocument::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'document_type' => PpdbDocumentType::BuktiTransfer->value,
        'disk' => 'local',
        'path' => $path,
        'original_name' => $originalName,
        'mime_type' => $mimeType,
        'size_bytes' => strlen($content),
        'uploaded_by' => null,
    ]);
}

it('requires authentication for document preview and download', function (string $routeName) {
    $document = makePpdbDocument();

    $this->get(route($routeName, $document))
        ->assertRedirect(route('login'));
})->with([
    'preview' => 'ppdb.documents.preview',
    'download' => 'ppdb.documents.download',
]);

it('allows admin and super admin to preview PPDB documents', function (string $role) {
    $document = makePpdbDocument();

    $response = $this->actingAs(ppdbDocumentUser($role))
        ->get(route('ppdb.documents.preview', $document));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=ppdb-bukti_transfer-'.$document->id.'.pdf');
})->with([
    'admin' => User::ROLE_ADMIN,
    'super admin' => User::ROLE_SUPER_ADMIN,
]);

it('allows admin and super admin to download PPDB documents as attachments', function (string $role) {
    $document = makePpdbDocument();

    $this->actingAs(ppdbDocumentUser($role))
        ->get(route('ppdb.documents.download', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=ppdb-bukti_transfer-'.$document->id.'.pdf');
})->with([
    'admin' => User::ROLE_ADMIN,
    'super admin' => User::ROLE_SUPER_ADMIN,
]);

it('denies unsupported and inactive authenticated users in the controller', function (string $role, bool $active) {
    $document = makePpdbDocument();
    $user = ppdbDocumentUser();

    $user->setAttribute('role', $role);
    $user->setAttribute('is_active', $active);

    $this->actingAs($user)
        ->get(route('ppdb.documents.preview', $document))
        ->assertForbidden();

    $this->get(route('ppdb.documents.download', $document))
        ->assertForbidden();
})->with([
    'unsupported role' => ['auditor', true],
    'inactive admin' => [User::ROLE_ADMIN, false],
]);

it('returns not found for missing metadata and missing physical files', function () {
    $user = ppdbDocumentUser();
    $document = makePpdbDocument();
    Storage::disk('local')->delete($document->path);

    $this->actingAs($user)
        ->get('/ppdb/documents/999999/preview')
        ->assertNotFound();

    $this->get(route('ppdb.documents.preview', $document))
        ->assertNotFound();
});

it('rejects document metadata outside the managed private location', function (string $disk, string $path) {
    $document = makePpdbDocument();
    $document->update(['disk' => $disk, 'path' => $path]);

    $this->actingAs(ppdbDocumentUser())
        ->get(route('ppdb.documents.preview', $document))
        ->assertNotFound();
})->with([
    'public disk' => ['public', 'ppdb/documents/2026/10/file.pdf'],
    'traversal' => ['local', 'ppdb/documents/../../private-secret.txt'],
    'backslash traversal' => ['local', 'ppdb/documents/..\\private-secret.txt'],
]);

it('uses actual file MIME for supported PDF and image previews', function (string $mimeType, string $expectedExtension) {
    $document = makePpdbDocument($mimeType);

    $this->actingAs(ppdbDocumentUser())
        ->get(route('ppdb.documents.preview', $document))
        ->assertOk()
        ->assertHeader('Content-Type', $mimeType)
        ->assertHeader('Content-Disposition', 'inline; filename=ppdb-bukti_transfer-'.$document->id.'.'.$expectedExtension);
})->with([
    'PDF' => ['application/pdf', 'pdf'],
    'JPEG' => ['image/jpeg', 'jpg'],
    'PNG' => ['image/png', 'png'],
]);

it('rejects unsupported actual content even when metadata claims an allowed MIME', function () {
    $document = makePpdbDocument();
    Storage::disk('local')->put($document->path, '<html>not a document</html>');

    $this->actingAs(ppdbDocumentUser())
        ->get(route('ppdb.documents.preview', $document))
        ->assertNotFound();
});

it('does not accept arbitrary paths or expose unsafe original filenames', function () {
    $document = makePpdbDocument('application/pdf', "evil\r\nX-Injected: yes.pdf");
    Storage::disk('local')->put('private-secret.txt', 'secret');

    $response = $this->actingAs(ppdbDocumentUser())
        ->get(route('ppdb.documents.download', $document).'?path=private-secret.txt');

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename=ppdb-bukti_transfer-'.$document->id.'.pdf')
        ->not->toContain('evil', 'Injected');
});

it('applies private response headers to document responses', function () {
    $document = makePpdbDocument();

    $response = $this->actingAs(ppdbDocumentUser())
        ->get(route('ppdb.documents.preview', $document));

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    $response->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('keeps download responses unavailable for framing', function () {
    $document = makePpdbDocument();

    $this->actingAs(ppdbDocumentUser())
        ->get(route('ppdb.documents.download', $document))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeaderMissing('Content-Security-Policy');
});

it('hides document actions from unauthorized users while retaining document status', function () {
    $document = makePpdbDocument();
    $user = ppdbDocumentUser();
    $user->setAttribute('role', 'auditor');

    $this->actingAs($user)
        ->get(route('calon-siswa.show', $document->prospectiveStudent))
        ->assertOk()
        ->assertSee('Dokumen PPDB')
        ->assertSee('Diunggah')
        ->assertDontSee(route('ppdb.documents.preview', $document), false)
        ->assertDontSee(route('ppdb.documents.download', $document), false);
});

it('renders legacy applicants and students without PPDB sidecars', function () {
    $user = ppdbDocumentUser();
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $student = Student::factory()->create();

    $this->actingAs($user)
        ->get(route('calon-siswa.show', $prospectiveStudent))
        ->assertOk()
        ->assertSeeText([
            'Bukti Transfer',
            'Kartu Keluarga (KK)',
            'Akta Kelahiran',
            'KTP Ayah',
            'KTP Ibu',
            'Pas Foto Siswa',
            'Belum diunggah',
        ]);

    $this->get(route('siswa.show', $student))
        ->assertOk()
        ->assertSee('Tidak ada dokumen PPDB')
        ->assertSeeText(['Biodata', 'Dokumen', 'Tagihan & Pembayaran', 'Total Tagihan', 'Tagihan Aktif']);
});

it('renders PPDB biodata, parents, optional guardian, and siblings on applicant detail', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'nama_lengkap' => 'Nadia An Nur',
        'nama_panggilan' => 'Nadia',
        'alamat' => 'Jalan Pendidikan 10',
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2019-04-12',
        'asal_sekolah' => 'TK AL AZHAR',
        'nik_siswa' => '3201011204190001',
        'nomor_kk' => '3201010101010001',
        'nomor_akta_kelahiran' => 'AKTA-NADIA-01',
        'anak_ke' => 2,
        'jumlah_saudara' => 4,
        'status_anak' => 'Kandung',
        'agama' => 'Islam',
        'bahasa_sehari_hari' => 'Indonesia',
        'rt' => '007',
        'rw' => '009',
        'kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibiru',
        'kabupaten_kota' => 'Kota Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '40615',
        'tinggi_badan' => 112.50,
        'berat_badan' => 20.25,
        'lingkar_kepala' => 51,
        'golongan_darah' => 'A',
        'riwayat_penyakit' => 'Tidak Ada',
        'berkebutuhan_khusus' => true,
        'keterangan_kebutuhan_khusus' => 'Pendampingan belajar',
    ]);
    foreach ([
        ['role' => 'father', 'nama' => 'Ayah Nadia', 'nik' => '3201010101010002', 'tempat_lahir' => 'Garut', 'tanggal_lahir' => '1985-02-03', 'pendidikan_terakhir' => 'S1', 'email' => 'ayah.nadia@example.test', 'no_telp' => '0811111111', 'occupation' => 'Guru', 'income_range' => '3m_to_4_999m'],
        ['role' => 'mother', 'nama' => 'Ibu Nadia', 'nik' => '3201010101010003', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1987-04-05', 'pendidikan_terakhir' => 'S2', 'email' => 'ibu.nadia@example.test', 'no_telp' => '0822222222', 'occupation' => 'Dokter', 'income_range' => '5m_to_7_499m'],
        ['role' => 'guardian', 'nama' => 'Wali Nadia', 'nik' => '3201010101010004', 'tempat_lahir' => 'Sumedang', 'tanggal_lahir' => '1980-06-07', 'pendidikan_terakhir' => 'SMA', 'email' => 'wali.nadia@example.test', 'no_telp' => '0833333333', 'occupation' => 'Wiraswasta', 'income_range' => '10m_to_14_999m'],
    ] as $parent) {
        PpdbParent::query()->create($parent + ['prospective_student_id' => $prospectiveStudent->id]);
    }
    PpdbSibling::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'sort_order' => 1,
        'nama_lengkap' => 'Kakak Nadia',
        'tanggal_lahir' => '2012-01-02',
        'jenis_kelamin' => 'L',
        'pendidikan_pekerjaan' => 'SMP',
    ]);
    PpdbSibling::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'sort_order' => 2,
        'nama_lengkap' => 'Adik Nadia',
        'tanggal_lahir' => '2021-03-04',
        'jenis_kelamin' => 'P',
        'pendidikan_pekerjaan' => 'Belum Sekolah',
    ]);

    $this->actingAs(ppdbDocumentUser())
        ->get(route('calon-siswa.show', $prospectiveStudent))
        ->assertOk()
        ->assertSeeText([
            'Data Pendaftaran PPDB',
            'PPDB Online',
            'NADIA AN NUR',
            'Bandung',
            'TK AL AZHAR',
            '3201011204190001',
            '3201010101010001',
            'AKTA-NADIA-01',
            'Kandung',
            'Indonesia',
            'Sukamaju',
            'Kota Bandung',
            '40615',
            '112,5',
            '20,25',
            'Tidak Ada',
            'Pendampingan belajar',
            'AYAH NADIA',
            '3201010101010002',
            'ayah.nadia@example.test',
            'IBU NADIA',
            '3201010101010003',
            'ibu.nadia@example.test',
            'WALI NADIA',
            '3201010101010004',
            'wali.nadia@example.test',
            'KAKAK NADIA',
            'ADIK NADIA',
            'Rp10.000.000 – Rp14.999.999',
        ])
        ->assertSee('data-ppdb-special-needs="Ya"', false);
});

it('renders nullable historical PPDB biodata without fabricating special-needs status', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'berkebutuhan_khusus' => null,
    ]);

    $this->actingAs(ppdbDocumentUser())
        ->get(route('calon-siswa.show', $prospectiveStudent))
        ->assertOk()
        ->assertSeeText(['Asal Sekolah', 'NIK Siswa', 'Nomor KK', 'Keadaan Jasmani'])
        ->assertSeeTextInOrder(['Asal Sekolah', '-', 'Anak ke-'])
        ->assertSee('data-ppdb-special-needs="-"', false);

    $prospectiveStudent->ppdbProfile()->update(['berkebutuhan_khusus' => false]);

    $this->get(route('calon-siswa.show', $prospectiveStudent))
        ->assertOk()
        ->assertSee('data-ppdb-special-needs="Tidak"', false);
});

it('renders accessible tabs and independent biodata accordions on applicant detail', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();

    $this->actingAs(ppdbDocumentUser())
        ->get(route('calon-siswa.show', $prospectiveStudent))
        ->assertOk()
        ->assertSee('role="tablist"', false)
        ->assertSee('data-detail-tab="biodata"', false)
        ->assertSee('data-detail-tab="documents"', false)
        ->assertSee('data-detail-tab="finance"', false)
        ->assertSeeText(['Biodata', 'Dokumen', 'Tagihan & Pembayaran'])
        ->assertSee('data-accordion="student"', false)
        ->assertSee('data-accordion="father"', false)
        ->assertSee('data-accordion="mother"', false)
        ->assertSee('data-accordion="guardian"', false)
        ->assertSee('data-accordion="siblings"', false)
        ->assertSee('data-accordion-open-all', false)
        ->assertSee('data-accordion-close-all', false)
        ->assertSee('x-data="ppdbBiodataAccordions()"', false)
        ->assertSee('x-show="sections.student"', false)
        ->assertSee('x-show="sections.father"', false)
        ->assertSee('x-show="sections.mother"', false)
        ->assertSee('x-show="sections.guardian"', false)
        ->assertSee('x-show="sections.siblings"', false)
        ->assertDontSee('openSection', false)
        ->assertSeeText(['Buka Semua', 'Tutup Semua'])
        ->assertSee('Tidak ada data wali.')
        ->assertSee('Tidak ada data saudara kandung.')
        ->assertSeeText(['Tagihan Pendaftaran', 'Riwayat Pembayaran']);
});

it('controls biodata accordion sections independently', function () {
    $script = <<<'JS'
        import { createPpdbBiodataAccordions } from './resources/js/ppdb-biodata-accordions.js';

        const state = createPpdbBiodataAccordions();
        const initial = { ...state.sections };

        state.toggleSection('father');
        state.toggleSection('mother');
        const independent = { ...state.sections };

        state.openAllSections();
        const allOpen = Object.values(state.sections).every(Boolean);

        state.closeAllSections();
        const allClosed = Object.values(state.sections).every((section) => !section);

        process.stdout.write(JSON.stringify({ initial, independent, allOpen, allClosed }));
        JS;

    $result = Process::path(base_path())->run(['node', '--input-type=module', '--eval', $script]);
    $state = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    expect($result->successful())->toBeTrue()
        ->and($state['initial'])->toBe([
            'student' => true,
            'father' => false,
            'mother' => false,
            'guardian' => false,
            'siblings' => false,
        ])
        ->and($state['independent']['student'])->toBeTrue()
        ->and($state['independent']['father'])->toBeTrue()
        ->and($state['independent']['mother'])->toBeTrue()
        ->and($state['allOpen'])->toBeTrue()
        ->and($state['allClosed'])->toBeTrue();
});

it('renders the shared document modal without exposing private paths', function () {
    $document = makePpdbDocument();

    $response = $this->actingAs(ppdbDocumentUser())
        ->get(route('calon-siswa.show', $document->prospectiveStudent));

    $response->assertOk()
        ->assertSee('data-document-preview', false)
        ->assertSee('data-document-preview-modal', false)
        ->assertSee('ppdbDocumentPreview()', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="true"', false)
        ->assertSee('@keydown.tab="trapPreviewFocus($event)"', false)
        ->assertSee('<iframe', false)
        ->assertSee('<img', false)
        ->assertSee(str_replace('/', '\\/', route('ppdb.documents.preview', $document)), false)
        ->assertSee(route('ppdb.documents.download', $document), false)
        ->assertDontSee('target="_blank" rel="noopener noreferrer"', false)
        ->assertDontSee($document->path, false)
        ->assertDontSee('prospective_student_id')
        ->assertDontSee('document_type');
});

it('renders the same tabs and modal controls on converted student detail', function () {
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $document = makePpdbDocument(prospectiveStudent: $prospectiveStudent);

    $this->actingAs(ppdbDocumentUser())
        ->get(route('siswa.show', $student))
        ->assertOk()
        ->assertSee('data-detail-tab="biodata"', false)
        ->assertSee('data-detail-tab="documents"', false)
        ->assertSee('data-detail-tab="finance"', false)
        ->assertSee('x-data="ppdbBiodataAccordions()"', false)
        ->assertSee('data-accordion-open-all', false)
        ->assertSee('data-accordion-close-all', false)
        ->assertSee('x-show="sections.student"', false)
        ->assertSee('x-show="sections.father"', false)
        ->assertSee('x-show="sections.mother"', false)
        ->assertSee('x-show="sections.guardian"', false)
        ->assertSee('x-show="sections.siblings"', false)
        ->assertSee('data-document-preview-modal', false)
        ->assertSee(str_replace('/', '\\/', route('ppdb.documents.preview', $document)), false)
        ->assertDontSee($document->path, false);
});

it('renders linked extended PPDB biodata on converted student detail', function () {
    $student = Student::factory()->create([
        'nama_lengkap' => 'Nama Siswa Saat Ini',
    ]);
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
        'nama_lengkap' => 'Nama Saat PPDB',
    ]);
    PpdbProfile::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'nik_siswa' => '3201010101010099',
        'nomor_kk' => '3201010101010098',
        'jumlah_saudara' => 3,
        'berkebutuhan_khusus' => false,
    ]);
    PpdbParent::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'role' => 'father',
        'nama' => 'Ayah Saat PPDB',
        'nik' => '3201010101010097',
        'pendidikan_terakhir' => 'S1',
        'email' => 'ayah.converted@example.test',
    ]);

    $this->actingAs(ppdbDocumentUser())
        ->get(route('siswa.show', $student))
        ->assertOk()
        ->assertSeeText([
            'Data pendaftaran PPDB yang tersimpan dan data keluarga.',
            'NAMA SAAT PPDB',
            '3201010101010099',
            '3201010101010098',
            'AYAH SAAT PPDB',
            '3201010101010097',
            'ayah.converted@example.test',
            'Jumlah Saudara (data terpisah)',
        ])
        ->assertSee('data-ppdb-special-needs="Tidak"', false);
});

it('defines shared Alpine tab and modal behavior without starting another Alpine instance', function () {
    $source = file_get_contents(resource_path('js/app.js'));

    expect($source)
        ->toContain('window.ppdbDetailTabs')
        ->toContain('window.ppdbBiodataAccordions')
        ->toContain('window.ppdbDocumentPreview')
        ->toContain('triggerElement?.focus()')
        ->toContain('trapPreviewFocus(event)')
        ->toContain("document.documentElement.style.overflow = 'hidden'")
        ->not->toContain('Alpine.start()');
});

it('shows the original PPDB document on converted student detail without copying it', function () {
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $document = makePpdbDocument(prospectiveStudent: $prospectiveStudent);

    $this->actingAs(ppdbDocumentUser())
        ->get(route('siswa.show', $student))
        ->assertOk()
        ->assertSee('Dokumen PPDB')
        ->assertSee(str_replace('/', '\\/', route('ppdb.documents.preview', $document)), false);

    expect(PpdbDocument::query()->count())->toBe(1)
        ->and($student->fresh()->prospectiveStudent?->ppdbDocuments()->sole()->path)->toBe($document->path);
});
