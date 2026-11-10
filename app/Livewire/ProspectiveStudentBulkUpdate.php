<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Services\ProspectiveStudentBulkUpdateService;
use App\Services\ProspectiveStudentBulkUpdateSpreadsheet;
use App\Services\ProspectiveStudentUnitScope;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

#[Layout('layouts.app')]
class ProspectiveStudentBulkUpdate extends Component
{
    use WithFileUploads;

    private const PREVIEW_TTL_MINUTES = 15;

    private const PREVIEW_CACHE_PREFIX = 'ppdb-bulk-update-preview:';

    private const PREVIEW_INDEX_PREFIX = 'ppdb-bulk-update-preview-index:';

    public string $filterAcademicYearId = '';

    public string $filterLevel = '';

    public string $filterClassId = '';

    public ?TemporaryUploadedFile $file = null;

    public int $step = 1;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $rows = [];

    /** @var array<string, int> */
    #[Locked]
    public array $summary = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $result = [];

    #[Locked]
    public string $confirmationToken = '';

    public function mount(): void
    {
        $this->authorizeAccess();

        if (auth()->user()?->isTuUnit()) {
            $this->filterLevel = auth()->user()->school_level?->value ?? '';
        }
    }

    public function downloadTemplate(ProspectiveStudentBulkUpdateSpreadsheet $spreadsheet): ?BinaryFileResponse
    {
        $this->authorizeAccess();
        $academicYearId = $this->filterAcademicYearId !== '' ? (int) $this->filterAcademicYearId : null;
        $user = auth()->user();
        $level = $user?->isTuUnit()
            ? $user->school_level
            : ($this->filterLevel !== '' ? SchoolLevel::tryFrom($this->filterLevel) : null);
        $classId = $this->filterClassId !== '' ? (int) $this->filterClassId : null;

        if ($user?->isTuUnit() && $classId !== null) {
            abort_unless(app(ProspectiveStudentUnitScope::class)->classBelongsToUser($classId, $user), 403);
        }

        try {
            $path = $spreadsheet->create($academicYearId, $level, $classId, auth()->user());
        } catch (RuntimeException $exception) {
            $this->addError('export', $exception->getMessage());

            return null;
        }

        return response()
            ->download($path, 'update-data-calon-siswa.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function previewUpdate(ProspectiveStudentBulkUpdateSpreadsheet $spreadsheet, ProspectiveStudentBulkUpdateService $service): void
    {
        $this->authorizeAccess();
        $this->invalidateConfirmation();

        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:12288'],
        ], [
            'file.required' => 'File XLSX wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx.',
        ]);

        try {
            if ($this->file === null) {
                return;
            }

            $parsedRows = $spreadsheet->read($this->file->getRealPath());

            if ($parsedRows === []) {
                $this->addError('file', 'File tidak memiliki baris data calon siswa.');

                return;
            }

            $previewResult = $service->preview($parsedRows, auth()->user());
            $this->rows = $previewResult['rows'];
            $this->summary = $previewResult['summary'];
            $this->confirmationToken = $this->storeTrustedPreview($parsedRows);
            $this->step = 2;
            $this->reset('file');
            $this->resetValidation();
        } catch (RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->rows = [];
            $this->summary = [];
            $this->addError('file', 'Preview gagal disimpan dengan aman. Silakan unggah ulang file.');
        }
    }

    public function confirmUpdate(ProspectiveStudentBulkUpdateService $service): void
    {
        $this->authorizeAccess();

        if ($this->step !== 2 || $this->confirmationToken === '') {
            $this->addError('update', 'Konfirmasi tidak valid atau sudah digunakan. Unggah dan preview ulang file.');

            return;
        }

        try {
            $trustedRows = $this->consumeTrustedPreview($this->confirmationToken);
            $this->confirmationToken = '';
            $count = $service->apply($trustedRows, auth()->user());
            $this->result = [
                'updated' => $count,
            ];
            $this->step = 3;
            $this->rows = [];
            $this->summary = [];
        } catch (ValidationException $exception) {
            $this->addError('update', collect($exception->errors())->flatten()->first() ?? 'Update dibatalkan karena file tidak valid.');
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('update', 'Update gagal atau token konfirmasi tidak valid. Unggah dan preview ulang file.');
        }
    }

    public function backToSetup(): void
    {
        $this->authorizeAccess();
        $this->invalidateConfirmation();
        $this->step = 1;
        $this->rows = [];
        $this->summary = [];
        $this->result = [];
        $this->reset('file');
        $this->resetValidation();
    }

    public function updatedFilterLevel(): void
    {
        $this->authorizeAccess();
        if (auth()->user()?->isTuUnit()) {
            $this->filterLevel = auth()->user()->school_level?->value ?? '';
        }
        $this->filterClassId = '';
    }

    public function render(): View
    {
        $this->authorizeAccess();
        $user = auth()->user();
        $level = $user?->isTuUnit()
            ? $user->school_level
            : ($this->filterLevel !== '' ? SchoolLevel::tryFrom($this->filterLevel) : null);

        $classes = SchoolClass::query()
            ->when($level instanceof SchoolLevel, function ($query) use ($level) {
                $query->whereIn('level', $level->classLevels());
            })
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return view('livewire.prospective-student-bulk-update', [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'levels' => $user?->isTuUnit() && $user->school_level !== null ? [$user->school_level] : SchoolLevel::cases(),
            'classes' => $classes,
        ]);
    }

    private function authorizeAccess(): void
    {
        Gate::authorize('bulkUpdate', ProspectiveStudent::class);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function storeTrustedPreview(array $rows): string
    {
        $token = Str::random(64);
        $payload = json_encode([
            'version' => 1,
            'user_id' => (int) auth()->id(),
            'token' => $token,
            'rows' => $rows,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $cache = $this->previewCache();
        $indexKey = $this->previewIndexKey();
        $previousToken = $cache->get($indexKey);

        if (is_string($previousToken) && $previousToken !== '') {
            $cache->forget($this->previewCacheKey($previousToken));
        }

        $expiresAt = now()->addMinutes(self::PREVIEW_TTL_MINUTES);
        $stored = $cache->put(
            $this->previewCacheKey($token),
            Crypt::encryptString($payload),
            $expiresAt,
        );

        if (! $stored) {
            throw new RuntimeException('Preview gagal disimpan pada penyimpanan sementara.');
        }

        if (! $cache->put($indexKey, $token, $expiresAt)) {
            $cache->forget($this->previewCacheKey($token));

            throw new RuntimeException('Index preview gagal disimpan pada penyimpanan sementara.');
        }

        return $token;
    }

    /** @return array<int, array<string, mixed>> */
    private function consumeTrustedPreview(string $token): array
    {
        $cache = $this->previewCache();
        $key = $this->previewCacheKey($token);
        $lock = $cache->lock($key.':lock', 10);

        $rows = $lock->block(0, function () use ($cache, $key, $token): array {
            $encrypted = $cache->get($key);

            if (! is_string($encrypted) || $encrypted === '') {
                throw new RuntimeException('Token konfirmasi tidak ditemukan, kedaluwarsa, atau sudah digunakan.');
            }

            $cache->forget($key);
            if ($cache->get($this->previewIndexKey()) === $token) {
                $cache->forget($this->previewIndexKey());
            }
            $payload = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($payload)
                || ($payload['version'] ?? null) !== 1
                || ($payload['user_id'] ?? null) !== (int) auth()->id()
                || ! is_string($payload['token'] ?? null)
                || ! hash_equals($token, $payload['token'])
                || ! is_array($payload['rows'] ?? null)) {
                throw new RuntimeException('Payload konfirmasi tidak valid.');
            }

            return array_values($payload['rows']);
        });

        if (! is_array($rows)) {
            throw new RuntimeException('Konfirmasi sedang diproses atau sudah digunakan.');
        }

        return $rows;
    }

    private function invalidateConfirmation(): void
    {
        if ($this->confirmationToken !== '') {
            $cache = $this->previewCache();
            $cache->forget($this->previewCacheKey($this->confirmationToken));

            if ($cache->get($this->previewIndexKey()) === $this->confirmationToken) {
                $cache->forget($this->previewIndexKey());
            }

            $this->confirmationToken = '';
        }
    }

    private function previewCacheKey(string $token): string
    {
        return self::PREVIEW_CACHE_PREFIX.(int) auth()->id().':'.hash('sha256', $token);
    }

    private function previewIndexKey(): string
    {
        return self::PREVIEW_INDEX_PREFIX.(int) auth()->id();
    }

    private function previewCache(): Repository
    {
        return Cache::store((string) config('cache.ppdb_bulk_preview_store', 'file'));
    }
}
