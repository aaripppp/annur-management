<?php

use App\Enums\PpdbDocumentType;
use App\Livewire\PpdbDocumentReplacer;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Models\User;
use App\Services\PpdbDocumentReplacer as PpdbDocumentReplacerService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function ppdbDocumentReplacerUser(string $role = User::ROLE_ADMIN, bool $active = true): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => $active,
    ]);
}

function ppdbDocumentReplacerRecord(ProspectiveStudent $prospectiveStudent): PpdbDocument
{
    $path = 'ppdb/documents/2026/10/original-'.$prospectiveStudent->id.'.pdf';
    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");

    return PpdbDocument::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'document_type' => PpdbDocumentType::BuktiTransfer->value,
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'original.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => Storage::disk('local')->size($path),
        'uploaded_by' => null,
    ]);
}

it('opens the replacement modal for a correctly linked converted student', function () {
    Storage::fake('local');
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);
    $this->actingAs(ppdbDocumentReplacerUser());

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
        'studentId' => $student->id,
    ])->dispatch(
        'ppdb-document:replace',
        prospectiveStudentId: $prospectiveStudent->id,
        studentId: $student->id,
        documentId: $document->id,
        documentType: PpdbDocumentType::BuktiTransfer->value,
    )->assertHasNoErrors()
        ->assertSet('isOpen', true)
        ->assertSet('documentId', $document->id)
        ->assertSet('documentType', PpdbDocumentType::BuktiTransfer->value)
        ->assertSet('currentOriginalName', 'original.pdf')
        ->assertSee('Ganti / Unggah Dokumen');
});

it('rejects a student that is not linked to the prospective student', function () {
    Storage::fake('local');
    $linkedStudent = Student::factory()->create();
    $otherStudent = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $linkedStudent->id,
    ]);
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);
    $this->actingAs(ppdbDocumentReplacerUser());

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
        'studentId' => $otherStudent->id,
    ])->dispatch(
        'ppdb-document:replace',
        prospectiveStudentId: $prospectiveStudent->id,
        studentId: $otherStudent->id,
        documentId: $document->id,
        documentType: PpdbDocumentType::BuktiTransfer->value,
    )->assertSet('isOpen', false)
        ->assertHasErrors(['file']);
});

it('replaces an enum-cast document without changing its row identity', function () {
    Storage::fake('local');
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);
    $documentId = $document->id;
    $originalPath = $document->path;
    $this->actingAs(ppdbDocumentReplacerUser());

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
        'studentId' => $student->id,
    ])->set('documentId', $document->id)
        ->set('documentType', PpdbDocumentType::BuktiTransfer->value)
        ->set('file', UploadedFile::fake()->image('replacement.jpg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('isOpen', false)
        ->assertDispatched('ppdb-documents-updated');

    $document->refresh();

    expect($document->id)->toBe($documentId)
        ->and($document->prospective_student_id)->toBe($prospectiveStudent->id)
        ->and($document->original_name)->toBe('replacement.jpg')
        ->and($document->mime_type)->toBe('image/jpeg')
        ->and($document->path)->not->toBe($originalPath);
    Storage::disk('local')->assertExists($document->path);
    Storage::disk('local')->assertMissing($originalPath);
    $this->assertDatabaseHas('ppdb_document_cleanup_failures', [
        'ppdb_document_id' => $documentId,
        'prospective_student_id' => $prospectiveStudent->id,
        'disk' => 'local',
        'path' => $originalPath,
        'reason_code' => 'scheduled',
    ]);
    expect(DB::table('ppdb_document_cleanup_failures')
        ->where('ppdb_document_id', $documentId)
        ->value('resolved_at'))->not->toBeNull();
});

it('rejects a mismatched enum document type and preserves the original document', function () {
    Storage::fake('local');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);
    $originalPath = $document->path;

    expect(fn () => app(PpdbDocumentReplacerService::class)->replaceOrUpload([
        'prospective_student_id' => $prospectiveStudent->id,
        'document_type' => PpdbDocumentType::PasFoto->value,
        'document_id' => $document->id,
        'expected_path' => $originalPath,
        'expected_updated_at' => $document->updated_at?->toISOString(),
    ], UploadedFile::fake()->image('replacement.jpg')))
        ->toThrow(ValidationException::class, 'Tipe dokumen tidak sesuai.');

    $document->refresh();

    expect($document->path)->toBe($originalPath)
        ->and($document->original_name)->toBe('original.pdf')
        ->and($document->document_type)->toBe(PpdbDocumentType::BuktiTransfer);
    Storage::disk('local')->assertExists($originalPath);
    expect(Storage::disk('local')->allFiles())->toBe([$originalPath]);
    expect(Storage::disk('local')->directories('ppdb/documents/'.now()->format('Y/m')))->toBe([]);
});

it('keeps a non-empty old UUID directory without failing replacement', function () {
    Storage::fake('local');
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);
    Storage::disk('local')->delete($document->path);
    $oldDirectory = 'ppdb/documents/2026/11/'.Str::uuid();
    $oldPath = $oldDirectory.'/original.pdf';
    $siblingPath = $oldDirectory.'/keep.txt';
    Storage::disk('local')->put($oldPath, "%PDF-1.4\n%%EOF");
    Storage::disk('local')->put($siblingPath, 'keep');
    $document->update(['path' => $oldPath]);
    $this->actingAs(ppdbDocumentReplacerUser());

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
        'studentId' => $student->id,
    ])->set('documentId', $document->id)
        ->set('documentType', PpdbDocumentType::BuktiTransfer->value)
        ->set('file', UploadedFile::fake()->image('replacement.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($siblingPath);
    expect(Storage::disk('local')->directoryExists($oldDirectory))->toBeTrue();
});

it('reports unexpected save exceptions while showing only a safe error', function () {
    Storage::fake('local');
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $student->id,
    ]);
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);
    $originalPath = $document->path;
    $exception = new RuntimeException('simulated replacement failure');
    $this->actingAs(ppdbDocumentReplacerUser());
    $service = Mockery::mock(PpdbDocumentReplacerService::class);
    $service->shouldReceive('replaceOrUpload')->once()->andThrow($exception);
    $component = Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
        'studentId' => $student->id,
    ])->instance();
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $exceptionHandler->shouldReceive('report')->once()->with($exception);
    $this->app->instance(ExceptionHandler::class, $exceptionHandler);

    $component->isOpen = true;
    $component->documentId = $document->id;
    $component->documentType = PpdbDocumentType::BuktiTransfer->value;
    $component->file = UploadedFile::fake()->image('replacement.jpg');
    $component->save($service);

    expect($component->isOpen)->toBeTrue()
        ->and($component->getErrorBag()->first('file'))->toBe('Gagal mengganti dokumen. Silakan coba kembali.')
        ->and($component->getErrorBag()->first('file'))->not->toContain('simulated replacement failure')
        ->and($document->fresh()->path)->toBe($originalPath);
    Storage::disk('local')->assertExists($originalPath);
});

it('rejects student and applicant tampering on save', function () {
    Storage::fake('local');
    $linkedStudent = Student::factory()->create();
    $otherStudent = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->converted()->create([
        'converted_student_id' => $linkedStudent->id,
    ]);
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);
    $this->actingAs(ppdbDocumentReplacerUser());
    $this->mock(PpdbDocumentReplacerService::class, function ($mock): void {
        $mock->shouldNotReceive('replaceOrUpload');
    });

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
        'studentId' => $otherStudent->id,
    ])->set('documentId', $document->id)
        ->set('documentType', PpdbDocumentType::BuktiTransfer->value)
        ->set('file', UploadedFile::fake()->image('tampered-student.png'))
        ->call('save')
        ->assertHasErrors(['file']);

    $otherProspectiveStudent = ProspectiveStudent::factory()->create();
    $otherDocument = ppdbDocumentReplacerRecord($otherProspectiveStudent);

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
        'studentId' => $linkedStudent->id,
    ])->set('documentId', $otherDocument->id)
        ->set('documentType', PpdbDocumentType::BuktiTransfer->value)
        ->set('file', UploadedFile::fake()->image('tampered-document.png'))
        ->call('save')
        ->assertHasErrors(['file']);

    expect($document->fresh()->original_name)->toBe('original.pdf')
        ->and($otherDocument->fresh()->original_name)->toBe('original.pdf');
});

it('continues to open for a prospective student without student context', function () {
    Storage::fake('local');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);
    $this->actingAs(ppdbDocumentReplacerUser());

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
    ])->dispatch(
        'ppdb-document:replace',
        prospectiveStudentId: $prospectiveStudent->id,
        studentId: null,
        documentId: $document->id,
        documentType: PpdbDocumentType::BuktiTransfer->value,
    )->assertHasNoErrors()
        ->assertSet('isOpen', true)
        ->assertSet('studentId', null)
        ->assertSet('documentId', $document->id);
});

it('prevents unauthorized and inactive users from opening or saving', function (?string $role, bool $active) {
    Storage::fake('local');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $document = ppdbDocumentReplacerRecord($prospectiveStudent);

    if ($role !== null) {
        $this->actingAs(ppdbDocumentReplacerUser($role, $active));
    }

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
    ])->dispatch(
        'ppdb-document:replace',
        prospectiveStudentId: $prospectiveStudent->id,
        studentId: null,
        documentId: $document->id,
        documentType: PpdbDocumentType::BuktiTransfer->value,
    )->assertForbidden();

    Livewire::test(PpdbDocumentReplacer::class, [
        'prospectiveStudentId' => $prospectiveStudent->id,
    ])->set('documentId', $document->id)
        ->set('documentType', PpdbDocumentType::BuktiTransfer->value)
        ->set('file', UploadedFile::fake()->image('unauthorized.png'))
        ->call('save')
        ->assertForbidden();
})->with([
    'guest' => [null, true],
    'inactive admin' => [User::ROLE_ADMIN, false],
]);
