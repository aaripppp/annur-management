<?php

use App\Enums\PpdbDocumentType;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\PpdbDocumentReplacer;
use App\Models\AcademicYear;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\PpdbBiodataRecapQuery;
use App\Services\ProspectiveStudentBulkUpdateService;
use App\Services\ProspectiveStudentBulkUpdateSpreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->year = AcademicYear::query()->where('year', '2026/2027')->first()
        ?? AcademicYear::factory()->create(['year' => '2026/2027']);
    $this->sdClass = SchoolClass::factory()->create(['name' => 'IV A7 SD', 'level' => 4]);
    $this->smpClass = SchoolClass::factory()->create(['name' => 'VIII A7 SMP', 'level' => 8]);
    $this->tu = User::factory()->create([
        'role' => User::ROLE_TU_UNIT,
        'school_level' => SchoolLevel::SD,
        'is_active' => true,
    ]);
    $this->ownApplicant = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->sdClass->id,
        'status' => ProspectiveStudentStatus::Registered,
    ]);
    $this->foreignApplicant = ProspectiveStudent::factory()->create([
        'academic_year_id' => $this->year->id,
        'school_class_id' => $this->smpClass->id,
        'status' => ProspectiveStudentStatus::Registered,
    ]);
});

afterEach(function () {
    $this->app->forgetInstance('dompdf.wrapper');
    Pdf::clearResolvedInstances();
});

it('allows only own-unit PPDB biodata PDF with private headers', function () {
    $renderer = new class
    {
        public function loadView(string $view, array $data): self
        {
            return $this;
        }

        public function setOption(array $options): self
        {
            return $this;
        }

        public function setPaper(string $paper, string $orientation = 'portrait'): self
        {
            return $this;
        }

        public function stream(string $filename): Response
        {
            return response('PDF', 200, ['Content-Type' => 'application/pdf']);
        }
    };
    Pdf::swap($renderer);

    $this->actingAs($this->tu)
        ->get(route('calon-siswa.biodata.pdf', $this->ownApplicant))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get(route('calon-siswa.biodata.pdf', $this->foreignApplicant))
        ->assertForbidden();
});

it('forces PPDB recap export to the authenticated TU level', function () {
    $response = $this->actingAs($this->tu)->get(route('calon-siswa.biodata-recap.export', [
        'academic_year_id' => $this->year->id,
        'school_level' => SchoolLevel::SD->value,
    ]));
    $path = $response->baseResponse->getFile()->getPathname();

    try {
        $response->assertOk()->assertDownload();
        expect(is_file($path))->toBeTrue();
    } finally {
        @unlink($path);
    }

    $this->get(route('calon-siswa.biodata-recap.export', [
        'academic_year_id' => $this->year->id,
        'school_level' => SchoolLevel::SMP->value,
    ]))->assertForbidden();
});

it('allows own-unit private document preview and download but denies foreign IDs', function () {
    Storage::fake('local');
    $content = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF";
    $ownPath = 'ppdb/documents/2026/11/own/document.pdf';
    $foreignPath = 'ppdb/documents/2026/11/foreign/document.pdf';
    Storage::disk('local')->put($ownPath, $content);
    Storage::disk('local')->put($foreignPath, $content);
    $ownDocument = PpdbDocument::query()->create([
        'prospective_student_id' => $this->ownApplicant->id,
        'document_type' => PpdbDocumentType::KartuKeluarga,
        'disk' => 'local',
        'path' => $ownPath,
        'original_name' => 'own.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen($content),
    ]);
    $foreignDocument = PpdbDocument::query()->create([
        'prospective_student_id' => $this->foreignApplicant->id,
        'document_type' => PpdbDocumentType::KartuKeluarga,
        'disk' => 'local',
        'path' => $foreignPath,
        'original_name' => 'foreign.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen($content),
    ]);

    $this->actingAs($this->tu)
        ->get(route('ppdb.documents.preview', $ownDocument))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=ppdb-kartu_keluarga-'.$ownDocument->id.'.pdf');
    $this->get(route('ppdb.documents.download', $ownDocument))->assertOk();
    $this->get(route('ppdb.documents.preview', $foreignDocument))->assertForbidden();
    $this->get(route('ppdb.documents.download', $foreignDocument))->assertForbidden();
});

it('reauthorizes own-unit document replacement under lock and rejects foreign replacement', function () {
    Storage::fake('local');

    Livewire::actingAs($this->tu)
        ->test(PpdbDocumentReplacer::class, ['prospectiveStudentId' => $this->ownApplicant->id])
        ->call('openReplace', prospectiveStudentId: $this->ownApplicant->id, documentType: PpdbDocumentType::PasFoto->value)
        ->set('file', UploadedFile::fake()->image('photo.jpg', 10, 10))
        ->call('save')
        ->assertHasNoErrors();

    expect(PpdbDocument::query()
        ->where('prospective_student_id', $this->ownApplicant->id)
        ->where('document_type', PpdbDocumentType::PasFoto->value)
        ->exists())->toBeTrue();

    Livewire::actingAs($this->tu)
        ->test(PpdbDocumentReplacer::class, ['prospectiveStudentId' => $this->foreignApplicant->id])
        ->call('openReplace', prospectiveStudentId: $this->foreignApplicant->id, documentType: PpdbDocumentType::PasFoto->value)
        ->assertForbidden();
});

it('scopes the PPDB bulk template to the TU unit regardless of filter state', function () {
    $spreadsheet = app(ProspectiveStudentBulkUpdateSpreadsheet::class);
    $path = $spreadsheet->create($this->year->id, SchoolLevel::SMP, null, $this->tu);

    try {
        $rows = $spreadsheet->read($path);
        expect(collect($rows)->pluck('id_sistem')->all())
            ->toContain($this->ownApplicant->id)
            ->not->toContain($this->foreignApplicant->id);
    } finally {
        @unlink($path);
    }
});

it('blocks mixed-unit PPDB bulk files atomically and rechecks converted Students', function () {
    $service = app(ProspectiveStudentBulkUpdateService::class);
    $this->ownApplicant->load(['academicYear', 'schoolClass', 'ppdbProfile', 'ppdbParents', 'convertedStudent']);
    $this->foreignApplicant->load(['academicYear', 'schoolClass', 'ppdbProfile', 'ppdbParents', 'convertedStudent']);
    $row = fn (ProspectiveStudent $applicant, string $name): array => [
        'row_number' => $applicant->id + 2,
        'id_sistem' => $applicant->id,
        'no_pendaftaran' => $applicant->registration_number,
        'tahun_ajaran' => $applicant->academicYear->year,
        'kelas' => $applicant->schoolClass->name,
        'snapshot_token' => $service->snapshotToken($applicant),
        'nama_lengkap' => $name,
    ];
    $rows = [
        $row($this->ownApplicant, 'Own Changed A7'),
        $row($this->foreignApplicant, 'Foreign Changed A7'),
    ];

    $preview = $service->preview($rows, $this->tu);
    expect($preview['summary']['blocked'])->toBe(1);
    expect(fn () => $service->apply($rows, $this->tu))->toThrow(ValidationException::class);
    expect($this->ownApplicant->refresh()->nama_lengkap)->not->toBe('OWN CHANGED A7');

    $foreignStudent = Student::factory()->create(['class_id' => $this->smpClass->id]);
    $this->ownApplicant->update([
        'status' => ProspectiveStudentStatus::Converted,
        'converted_student_id' => $foreignStudent->id,
        'converted_at' => now(),
    ]);
    $this->ownApplicant->refresh()->load(['academicYear', 'schoolClass', 'ppdbProfile', 'ppdbParents', 'convertedStudent']);

    expect($service->preview([$row($this->ownApplicant, 'Protected Converted')], $this->tu)['summary']['blocked'])->toBe(1);
});

it('allows own-unit Student billing PDF while import operations stay denied for TU', function () {
    $student = Student::factory()->create(['class_id' => $this->sdClass->id]);

    $this->actingAs($this->tu);
    $this->get(route('siswa.bills.pdf', $student))->assertOk();
    $this->get(route('siswa.import'))->assertRedirect(route('tu.pending'));
    $this->get(route('siswa.update'))->assertRedirect(route('tu.pending'));
    $this->get(route('calon-siswa.import'))->assertRedirect(route('tu.pending'));
});

it('preserves exactly six recap sheets and 78 biodata columns', function () {
    expect(PpdbBiodataRecapQuery::SHEET_NAMES)->toHaveCount(6)
        ->and(PpdbBiodataRecapQuery::DETAIL_COLUMNS)->toHaveCount(78);
});
