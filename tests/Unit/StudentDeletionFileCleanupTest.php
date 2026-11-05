<?php

use App\Enums\PpdbDocumentType;
use App\Enums\ProspectiveStudentStatus;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\PpdbDocument;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentReceiptFileCleanupService;
use App\Services\StudentDeletionService;
use App\Services\StudentPhotoService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    $this->filesystemManager = app('filesystem');

    $suffix = (string) Str::uuid();
    $this->publicStorageRoot = sys_get_temp_dir().'/annur-student-delete-public-'.$suffix;
    $this->privateStorageRoot = sys_get_temp_dir().'/annur-student-delete-private-'.$suffix;
    File::makeDirectory($this->publicStorageRoot, 0700, true);
    File::makeDirectory($this->privateStorageRoot, 0700, true);
    config([
        'filesystems.disks.public.root' => $this->publicStorageRoot,
        'filesystems.disks.local.root' => $this->privateStorageRoot,
    ]);
    Storage::forgetDisk('public');
    Storage::forgetDisk('local');
});

afterEach(function (): void {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    Storage::swap($this->filesystemManager);
    Storage::forgetDisk('public');
    Storage::forgetDisk('local');
    File::deleteDirectory($this->publicStorageRoot);
    File::deleteDirectory($this->privateStorageRoot);

    if (isset($this->outsideStorageRoot)) {
        File::deleteDirectory($this->outsideStorageRoot);
    }
});

function deletionCleanupPayment(Student $student, string $receipt, array $overrides = []): Payment
{
    return Payment::query()->create(array_merge([
        'receipt_number' => 'KWT-'.Str::random(20),
        'payment_kind' => Payment::KIND_BILL,
        'student_id' => $student->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-11-01',
        'total_amount' => 100000,
        'payment_method' => 'transfer',
        'receipt' => $receipt,
        'description' => 'Cleanup test',
        'status' => Payment::STATUS_ACTIVE,
        'created_by' => User::factory()->create()->id,
    ], $overrides));
}

function deletionCleanupProspectivePayment(ProspectiveStudent $prospectiveStudent, string $receipt): ProspectiveStudentPayment
{
    return ProspectiveStudentPayment::query()->create([
        'receipt_number' => 'KWT-REG-'.Str::random(20),
        'prospective_student_id' => $prospectiveStudent->id,
        'bank_id' => Bank::factory()->create()->id,
        'payment_date' => '2026-11-01',
        'total_amount' => 100000,
        'receipt' => $receipt,
        'description' => 'Cleanup test',
        'status' => ProspectiveStudentPayment::STATUS_ACTIVE,
        'created_by' => User::factory()->create()->id,
    ]);
}

function deletionCleanupConvertedProspect(Student $student): ProspectiveStudent
{
    return ProspectiveStudent::factory()->create([
        'status' => ProspectiveStudentStatus::Converted,
        'converted_student_id' => $student->id,
        'converted_at' => now(),
    ]);
}

it('removes every ordinary student receipt and photo without touching unrelated files', function () {
    $photoPath = 'student-photos/student.jpg';
    $paths = [
        'receipts/active.pdf',
        'receipts/bill-linked.pdf',
        'receipts/cancelled.pdf',
        'receipts/manual.pdf',
    ];
    $student = Student::factory()->create(['foto' => $photoPath]);
    $cancellingUser = User::factory()->create();

    foreach ([...$paths, $photoPath, 'receipts/unrelated.pdf'] as $path) {
        Storage::disk('public')->put($path, 'file');
    }

    deletionCleanupPayment($student, $paths[0]);
    $billLinkedPayment = deletionCleanupPayment($student, $paths[1]);
    deletionCleanupPayment($student, $paths[2], [
        'status' => Payment::STATUS_CANCELLED,
        'cancelled_by' => $cancellingUser->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Cancelled test payment',
    ]);
    deletionCleanupPayment($student, $paths[3], ['payment_kind' => Payment::KIND_MANUAL]);
    $bill = makeMonthlyBill($student, makeBillType('Cleanup Bill'), 100000, 11, 2026);
    $billLinkedPayment->details()->create([
        'bill_id' => $bill->id,
        'payment_type_id' => $bill->payment_type_id,
        'period_month' => 11,
        'period_year' => 2026,
        'amount' => 100000,
    ]);

    app(StudentDeletionService::class)->delete($student->id);

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(Payment::query()->where('student_id', $student->id)->exists())->toBeFalse();

    foreach ([...$paths, $photoPath] as $path) {
        Storage::disk('public')->assertMissing($path);
    }

    Storage::disk('public')->assertExists('receipts/unrelated.pdf');
});

it('removes student and PPDB receipts plus PPDB document UUID directory for a converted student', function () {
    $studentReceipt = 'receipts/student.pdf';
    $prospectiveReceipt = 'receipts/prospective.pdf';
    $student = Student::factory()->create();
    $prospectiveStudent = deletionCleanupConvertedProspect($student);
    deletionCleanupPayment($student, $studentReceipt);
    deletionCleanupProspectivePayment($prospectiveStudent, $prospectiveReceipt);
    Storage::disk('public')->put($studentReceipt, 'student receipt');
    Storage::disk('public')->put($prospectiveReceipt, 'prospective receipt');

    $documentDirectory = 'ppdb/documents/2026/11/'.Str::uuid();
    $documentPath = $documentDirectory.'/bukti.pdf';
    Storage::disk('local')->put($documentPath, 'private document');
    $document = PpdbDocument::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'document_type' => PpdbDocumentType::BuktiTransfer->value,
        'disk' => 'local',
        'path' => $documentPath,
        'original_name' => 'bukti.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 20,
        'uploaded_by' => null,
    ]);

    app(StudentDeletionService::class)->delete($student->id);

    Storage::disk('public')->assertMissing($studentReceipt);
    Storage::disk('public')->assertMissing($prospectiveReceipt);
    Storage::disk('local')->assertMissing($documentPath);
    expect(Storage::disk('local')->directoryExists($documentDirectory))->toBeFalse()
        ->and(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeFalse()
        ->and(DB::table('ppdb_document_cleanup_failures')->where('ppdb_document_id', $document->id)->value('resolved_at'))
        ->not->toBeNull();
});

it('preserves shared student and PPDB receipts and a shared student photo', function () {
    $studentReceipt = 'receipts/shared-student.pdf';
    $prospectiveReceipt = 'receipts/shared-prospective.pdf';
    $photoPath = 'student-photos/shared.jpg';
    $target = Student::factory()->create(['foto' => $photoPath]);
    $survivingStudent = Student::factory()->create(['foto' => $photoPath]);
    $survivingProspect = ProspectiveStudent::factory()->create();
    deletionCleanupPayment($target, $studentReceipt);
    deletionCleanupPayment($target, $prospectiveReceipt);
    deletionCleanupPayment($survivingStudent, $studentReceipt);
    deletionCleanupProspectivePayment($survivingProspect, $prospectiveReceipt);

    foreach ([$studentReceipt, $prospectiveReceipt, $photoPath] as $path) {
        Storage::disk('public')->put($path, 'shared');
    }

    app(StudentDeletionService::class)->delete($target->id);

    Storage::disk('public')->assertExists($studentReceipt);
    Storage::disk('public')->assertExists($prospectiveReceipt);
    Storage::disk('public')->assertExists($photoPath);
    expect(Student::query()->whereKey($survivingStudent->id)->exists())->toBeTrue()
        ->and(ProspectiveStudent::query()->whereKey($survivingProspect->id)->exists())->toBeTrue();
});

it('rejects invalid traversal absolute nested and non-managed paths', function () {
    $student = Student::factory()->create(['foto' => 'documents/photo.jpg']);
    $paths = [
        'documents/receipt.pdf',
        'receipts/../outside.pdf',
        'receipts/nested/receipt.pdf',
        '/absolute/receipt.pdf',
    ];

    foreach ($paths as $path) {
        deletionCleanupPayment($student, $path);
    }

    Storage::disk('public')->put('documents/receipt.pdf', 'keep');
    Storage::disk('public')->put('outside.pdf', 'keep');
    Storage::disk('public')->put('receipts/nested/receipt.pdf', 'keep');
    Storage::disk('public')->put('documents/photo.jpg', 'keep');

    app(StudentDeletionService::class)->delete($student->id);

    Storage::disk('public')->assertExists('documents/receipt.pdf');
    Storage::disk('public')->assertExists('outside.pdf');
    Storage::disk('public')->assertExists('receipts/nested/receipt.pdf');
    Storage::disk('public')->assertExists('documents/photo.jpg');
});

it('rejects symlinked receipt and photo files that resolve outside the public root', function () {
    $this->outsideStorageRoot = sys_get_temp_dir().'/annur-student-delete-outside-'.Str::uuid();
    File::makeDirectory($this->outsideStorageRoot, 0700, true);
    File::put($this->outsideStorageRoot.'/receipt.pdf', 'outside receipt');
    File::put($this->outsideStorageRoot.'/photo.jpg', 'outside photo');
    File::makeDirectory($this->publicStorageRoot.'/receipts', 0700, true);
    File::makeDirectory($this->publicStorageRoot.'/student-photos', 0700, true);
    symlink($this->outsideStorageRoot.'/receipt.pdf', $this->publicStorageRoot.'/receipts/link.pdf');
    symlink($this->outsideStorageRoot.'/photo.jpg', $this->publicStorageRoot.'/student-photos/link.jpg');

    $student = Student::factory()->create(['foto' => 'student-photos/link.jpg']);
    deletionCleanupPayment($student, 'receipts/link.pdf');

    app(StudentDeletionService::class)->delete($student->id);

    expect(is_link($this->publicStorageRoot.'/receipts/link.pdf'))->toBeTrue()
        ->and(is_link($this->publicStorageRoot.'/student-photos/link.jpg'))->toBeTrue()
        ->and(File::get($this->outsideStorageRoot.'/receipt.pdf'))->toBe('outside receipt')
        ->and(File::get($this->outsideStorageRoot.'/photo.jpg'))->toBe('outside photo');
});

it('waits for the outermost commit before deleting receipts and photo', function () {
    $receiptPath = 'receipts/outer-commit.pdf';
    $photoPath = 'student-photos/outer-commit.jpg';
    $student = Student::factory()->create(['foto' => $photoPath]);
    deletionCleanupPayment($student, $receiptPath);
    Storage::disk('public')->put($receiptPath, 'receipt');
    Storage::disk('public')->put($photoPath, 'photo');

    DB::beginTransaction();

    try {
        app(StudentDeletionService::class)->delete($student->id);

        Storage::disk('public')->assertExists($receiptPath);
        Storage::disk('public')->assertExists($photoPath);
        expect(Student::query()->whereKey($student->id)->exists())->toBeFalse();

        DB::commit();
    } catch (Throwable $exception) {
        DB::rollBack();

        throw $exception;
    }

    Storage::disk('public')->assertMissing($receiptPath);
    Storage::disk('public')->assertMissing($photoPath);
});

it('preserves receipts photo and PPDB document when the outermost transaction rolls back', function () {
    $studentReceipt = 'receipts/student-rollback.pdf';
    $prospectiveReceipt = 'receipts/prospective-rollback.pdf';
    $photoPath = 'student-photos/rollback.jpg';
    $student = Student::factory()->create(['foto' => $photoPath]);
    $prospectiveStudent = deletionCleanupConvertedProspect($student);
    deletionCleanupPayment($student, $studentReceipt);
    deletionCleanupProspectivePayment($prospectiveStudent, $prospectiveReceipt);
    $documentDirectory = 'ppdb/documents/2026/11/'.Str::uuid();
    $documentPath = $documentDirectory.'/bukti.pdf';
    PpdbDocument::query()->create([
        'prospective_student_id' => $prospectiveStudent->id,
        'document_type' => PpdbDocumentType::BuktiTransfer->value,
        'disk' => 'local',
        'path' => $documentPath,
        'original_name' => 'bukti.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 20,
        'uploaded_by' => null,
    ]);

    foreach ([$studentReceipt, $prospectiveReceipt, $photoPath] as $path) {
        Storage::disk('public')->put($path, 'file');
    }
    Storage::disk('local')->put($documentPath, 'private document');

    DB::beginTransaction();
    app(StudentDeletionService::class)->delete($student->id);

    Storage::disk('public')->assertExists($studentReceipt);
    Storage::disk('public')->assertExists($prospectiveReceipt);
    Storage::disk('public')->assertExists($photoPath);
    Storage::disk('local')->assertExists($documentPath);

    DB::rollBack();

    Storage::disk('public')->assertExists($studentReceipt);
    Storage::disk('public')->assertExists($prospectiveReceipt);
    Storage::disk('public')->assertExists($photoPath);
    Storage::disk('local')->assertExists($documentPath);
    expect(Storage::disk('local')->directoryExists($documentDirectory))->toBeTrue()
        ->and(Student::query()->whereKey($student->id)->exists())->toBeTrue()
        ->and(ProspectiveStudent::query()->whereKey($prospectiveStudent->id)->exists())->toBeTrue();
});

it('deduplicates receipt paths before attempting cleanup', function () {
    $path = 'receipts/duplicate.pdf';
    $absolutePath = $this->publicStorageRoot.'/'.$path;
    File::makeDirectory(dirname($absolutePath), 0700, true);
    File::put($absolutePath, 'receipt');
    $storage = Mockery::mock(FilesystemAdapter::class);
    $storage->shouldReceive('path')->once()->with('')->andReturn($this->publicStorageRoot);
    $storage->shouldReceive('path')->once()->with($path)->andReturn($absolutePath);
    $storage->shouldReceive('exists')->once()->with($path)->andReturnTrue();
    $storage->shouldReceive('delete')->once()->with($path)->andReturnTrue();
    Storage::shouldReceive('disk')->once()->with('public')->andReturn($storage);

    DB::transaction(function () use ($path): void {
        app(PaymentReceiptFileCleanupService::class)->scheduleAfterCommit([$path, $path, '', null]);
    });

    expect(true)->toBeTrue();
});

it('reports delete failures per file and continues without restoring deleted database rows', function () {
    $paths = [
        'receipts/returns-false.pdf',
        'receipts/throws.pdf',
        'receipts/still-runs.pdf',
    ];
    $student = Student::factory()->create();

    foreach ($paths as $path) {
        deletionCleanupPayment($student, $path);
        $absolutePath = $this->publicStorageRoot.'/'.$path;
        File::ensureDirectoryExists(dirname($absolutePath), 0700, true);
        File::put($absolutePath, 'receipt');
    }

    $storage = Mockery::mock(FilesystemAdapter::class);
    $storage->shouldReceive('path')->times(3)->with('')->andReturn($this->publicStorageRoot);

    foreach ($paths as $path) {
        $storage->shouldReceive('path')->once()->with($path)->andReturn($this->publicStorageRoot.'/'.$path);
        $storage->shouldReceive('exists')->once()->with($path)->andReturnTrue();
    }

    $storage->shouldReceive('delete')->once()->with($paths[0])->andReturnFalse();
    $storage->shouldReceive('delete')->once()->with($paths[1])->andThrow(new RuntimeException('simulated storage exception'));
    $storage->shouldReceive('delete')->once()->with($paths[2])->andReturnTrue();
    Storage::shouldReceive('disk')->times(3)->with('public')->andReturn($storage);
    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $exceptionHandler->shouldReceive('report')->twice()->with(Mockery::type(RuntimeException::class));
    $this->app->instance(ExceptionHandler::class, $exceptionHandler);

    app(StudentDeletionService::class)->delete($student->id);

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(Payment::query()->where('student_id', $student->id)->exists())->toBeFalse();
});

it('requires active transactions when scheduling managed receipt and photo cleanup', function () {
    expect(fn () => app(PaymentReceiptFileCleanupService::class)
        ->scheduleAfterCommit(['receipts/file.pdf']))
        ->toThrow(LogicException::class);

    expect(fn () => app(StudentPhotoService::class)
        ->scheduleDeletionAfterCommit('student-photos/file.jpg'))
        ->toThrow(LogicException::class);
});
