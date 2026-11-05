<?php

use App\Enums\PpdbDocumentType;
use App\Enums\ProspectiveStudentStatus;
use App\Livewire\ProspectiveStudentManagement;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Services\PpdbDocumentCleanupService;
use App\Services\StudentDeletionService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
});

function cleanupDocument(ProspectiveStudent $prospectiveStudent, string $path, string $disk = 'local'): PpdbDocument
{
    return PpdbDocument::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'document_type' => PpdbDocumentType::BuktiTransfer->value,
        'disk' => $disk,
        'path' => $path,
        'original_name' => 'bukti.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 20,
        'uploaded_by' => null,
    ]);
}

function cleanupUuidPath(string $filename = 'bukti.pdf'): string
{
    return 'ppdb/documents/2026/11/'.Str::uuid().'/'.$filename;
}

it('deletes applicant PPDB files only after the database transaction commits', function () {
    Storage::fake('local');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $path = cleanupUuidPath();
    $directory = dirname($path);
    Storage::disk('local')->put($path, 'private document');
    $document = cleanupDocument($prospectiveStudent, $path);

    Livewire::test(ProspectiveStudentManagement::class)
        ->call('confirmDelete', $prospectiveStudent->id)
        ->call('delete');

    expect(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
    expect(Storage::disk('local')->directoryExists($directory))->toBeFalse();
    $this->assertDatabaseMissing('ppdb_documents', ['prospective_student_id' => $prospectiveStudent->id]);
    $this->assertDatabaseHas('ppdb_document_cleanup_failures', [
        'ppdb_document_id' => $document->id,
        'reason_code' => 'scheduled',
    ]);
    expect(DB::table('ppdb_document_cleanup_failures')->value('resolved_at'))->not->toBeNull();
});

it('preserves PPDB files when the owning database transaction rolls back', function () {
    Storage::fake('local');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $path = cleanupUuidPath();
    $directory = dirname($path);
    Storage::disk('local')->put($path, 'private document');
    cleanupDocument($prospectiveStudent, $path);

    expect(function () use ($prospectiveStudent): void {
        DB::transaction(function () use ($prospectiveStudent): void {
            app(PpdbDocumentCleanupService::class)->scheduleFor($prospectiveStudent);
            $prospectiveStudent->delete();

            throw new RuntimeException('forced rollback');
        });
    })->toThrow(RuntimeException::class, 'forced rollback');

    expect(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($path);
    expect(Storage::disk('local')->directoryExists($directory))->toBeTrue();
    $this->assertDatabaseMissing('ppdb_document_cleanup_failures', [
        'prospective_student_id' => $prospectiveStudent->id,
    ]);
});

it('waits for the outermost transaction before deleting PPDB files', function () {
    Storage::fake('local');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $path = 'ppdb/documents/2026/10/outer-transaction/bukti.pdf';
    Storage::disk('local')->put($path, 'private document');
    cleanupDocument($prospectiveStudent, $path);

    DB::beginTransaction();

    try {
        DB::transaction(function () use ($prospectiveStudent): void {
            app(PpdbDocumentCleanupService::class)->scheduleFor($prospectiveStudent);
            $prospectiveStudent->delete();
        });

        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('ppdb_document_cleanup_failures', [
            'prospective_student_id' => $prospectiveStudent->id,
            'reason_code' => 'scheduled',
            'resolved_at' => null,
        ]);
        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        throw $exception;
    }

    Storage::disk('local')->assertMissing($path);
    expect(DB::table('ppdb_document_cleanup_failures')
        ->where('prospective_student_id', $prospectiveStudent->id)
        ->value('resolved_at'))->not->toBeNull();
});

it('does not delete a path that is still referenced by another document record', function () {
    Storage::fake('local');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $path = cleanupUuidPath();
    $directory = dirname($path);
    Storage::disk('local')->put($path, 'private document');
    $document = cleanupDocument($prospectiveStudent, $path);

    app(PpdbDocumentCleanupService::class)->cleanup([[
        'document_id' => $document->id + 100,
        'prospective_student_id' => $prospectiveStudent->id,
        'disk' => 'local',
        'path' => $path,
    ]]);

    Storage::disk('local')->assertExists($path);
    expect(Storage::disk('local')->directoryExists($directory))->toBeTrue();
});

it('leaves unrelated files in the same UUID directory untouched', function () {
    Storage::fake('local');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $path = cleanupUuidPath();
    $directory = dirname($path);
    $siblingPath = $directory.'/keep.txt';
    Storage::disk('local')->put($path, 'private document');
    Storage::disk('local')->put($siblingPath, 'keep');
    $document = cleanupDocument($prospectiveStudent, $path);
    $document->delete();

    app(PpdbDocumentCleanupService::class)->cleanup([[
        'document_id' => $document->id,
        'prospective_student_id' => $prospectiveStudent->id,
        'disk' => 'local',
        'path' => $path,
    ]]);

    Storage::disk('local')->assertMissing($path);
    Storage::disk('local')->assertExists($siblingPath);
    expect(Storage::disk('local')->directoryExists($directory))->toBeTrue();
});

it('records unmanaged cleanup locations without deleting unrelated files', function () {
    Storage::fake('public');
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $path = 'student-photos/unrelated.jpg';
    Storage::disk('public')->put($path, 'unrelated photo');

    app(PpdbDocumentCleanupService::class)->cleanup([[
        'document_id' => 999,
        'prospective_student_id' => $prospectiveStudent->id,
        'disk' => 'public',
        'path' => $path,
    ]]);

    Storage::disk('public')->assertExists($path);
    $this->assertDatabaseHas('ppdb_document_cleanup_failures', [
        'ppdb_document_id' => 999,
        'prospective_student_id' => $prospectiveStudent->id,
        'reason_code' => 'unmanaged_location',
        'resolved_at' => null,
    ]);
});

it('deletes the original PPDB files when a converted student is permanently deleted', function () {
    Storage::fake('local');
    Storage::fake('public');
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'status' => ProspectiveStudentStatus::Converted,
        'converted_student_id' => $student->id,
        'converted_at' => now(),
    ]);
    $path = cleanupUuidPath();
    $directory = dirname($path);
    Storage::disk('local')->put($path, 'private document');
    cleanupDocument($prospectiveStudent, $path);

    app(StudentDeletionService::class)->delete($student->id);

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($path);
    expect(Storage::disk('local')->directoryExists($directory))->toBeFalse();
});

it('preserves converted student PPDB files when deep deletion rolls back', function () {
    Storage::fake('local');
    Storage::fake('public');
    $student = Student::factory()->create();
    $prospectiveStudent = ProspectiveStudent::factory()->create([
        'status' => ProspectiveStudentStatus::Converted,
        'converted_student_id' => $student->id,
        'converted_at' => now(),
    ]);
    $path = cleanupUuidPath();
    $directory = dirname($path);
    Storage::disk('local')->put($path, 'private document');
    cleanupDocument($prospectiveStudent, $path);

    $dispatcher = Student::getEventDispatcher();
    Student::deleting(function (Student $deleting) use ($student): void {
        if ($deleting->is($student)) {
            throw new RuntimeException('forced student delete failure');
        }
    });

    try {
        expect(fn () => app(StudentDeletionService::class)->delete($student->id))
            ->toThrow(RuntimeException::class, 'forced student delete failure');
    } finally {
        $dispatcher->forget('eloquent.deleting: '.Student::class);
    }

    expect(Student::query()->whereKey($student->id)->exists())->toBeTrue()
        ->and(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($path);
    expect(Storage::disk('local')->directoryExists($directory))->toBeTrue();
});

it('records storage deletion failures for restricted retry without throwing', function () {
    $prospectiveStudent = ProspectiveStudent::factory()->create();
    $path = 'ppdb/documents/2026/10/failure/bukti.pdf';
    $storage = Mockery::mock(FilesystemAdapter::class);
    $storage->shouldReceive('exists')->once()->with($path)->andReturnTrue();
    $storage->shouldReceive('delete')->once()->with($path)->andReturnFalse();
    Storage::shouldReceive('disk')->once()->with('local')->andReturn($storage);

    app(PpdbDocumentCleanupService::class)->cleanup([[
        'document_id' => 1001,
        'prospective_student_id' => $prospectiveStudent->id,
        'disk' => 'local',
        'path' => $path,
    ]]);

    $this->assertDatabaseHas('ppdb_document_cleanup_failures', [
        'ppdb_document_id' => 1001,
        'prospective_student_id' => $prospectiveStudent->id,
        'reason_code' => 'storage_delete_failed',
        'attempts' => 1,
        'resolved_at' => null,
    ]);
});

it('retries recorded cleanup failures through the restricted Artisan command', function () {
    Storage::fake('local');
    $path = cleanupUuidPath();
    $directory = dirname($path);
    Storage::disk('local')->makeDirectory($directory);
    DB::table('ppdb_document_cleanup_failures')->insert([
        'ppdb_document_id' => 1002,
        'prospective_student_id' => 2002,
        'disk' => 'local',
        'path' => $path,
        'path_hash' => hash('sha256', "local\0{$path}"),
        'reason_code' => 'storage_delete_failed',
        'attempts' => 1,
        'last_attempted_at' => now(),
        'resolved_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('ppdb:retry-document-cleanup', ['--limit' => 1])
        ->expectsOutputToContain('Attempted: 1. Resolved: 1. Pending: 0.')
        ->assertSuccessful();

    Storage::disk('local')->assertMissing($path);
    expect(Storage::disk('local')->directoryExists($directory))->toBeFalse();
    $this->assertDatabaseHas('ppdb_document_cleanup_failures', [
        'ppdb_document_id' => 1002,
        'attempts' => 1,
    ]);
    expect(DB::table('ppdb_document_cleanup_failures')->where('ppdb_document_id', 1002)->value('resolved_at'))
        ->not->toBeNull();
});
