<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentCategory;
use App\Services\StudentCreationService;
use App\Services\StudentDeletionService;
use App\Services\StudentPhotoService;
use App\Services\StudentProfileUpdater;
use App\Services\StudentStatisticService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
class StudentManagement extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $search = '';

    #[Url(as: 'kelas')]
    public $filterClassId = '';

    #[Url(as: 'jenjang')]
    public $filterLevel = '';

    #[Url(as: 'status')]
    public $filterStatus = '';

    #[Url(as: 'kategori')]
    public string $filterCategory = '';

    // Statistik siswa (kartu) — terpisah dari filter tabel di bawah.
    public string $statYearId = '';

    public string $statLevel = '';

    public string $statClassId = '';

    // Modal state
    public $isModalOpen = false;

    public $isEditing = false;

    // Form inputs
    public $studentId = null;

    // Delete confirmation modal
    public $isDeleteModalOpen = false;

    public $deletingId = null;

    public $deletingIsConverted = false;

    public $nis = '';

    public $nama_lengkap = '';

    public $nama_panggilan = '';

    public $class_id = '';

    public $entry_academic_year_id = '';

    public string $status = StudentStatus::Active->value;

    public $jenis_kelamin = '';

    public $alamat = '';

    public string $nama_ayah = '';

    public string $no_telp_ayah = '';

    public string $nama_ibu = '';

    public string $no_telp_ibu = '';

    public string $tempat_lahir = '';

    public string $tanggal_lahir = '';

    public string $entry_date = '';

    public ?TemporaryUploadedFile $foto_upload = null;

    public ?string $existing_foto = null;

    public bool $remove_foto = false;

    /** @var array<int, int|string> */
    public array $categoryIds = [];

    /** @var array<int, string> */
    public array $inactiveCategoryNames = [];

    // Reset pagination when filter changes
    public function updatedFilterClassId()
    {
        $this->resetPage();
    }

    public function updatedFilterLevel()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    /**
     * Saat jenjang statistik berubah, kelas yang tidak lagi cocok direset.
     * Bila jenjang kosong (Semua Jenjang), kelas apa pun masih valid sehingga
     * pilihan kelas dipertahankan.
     */
    public function updatedStatLevel()
    {
        if ($this->statClassId === '') {
            return;
        }

        $level = $this->statLevel !== '' ? SchoolLevel::tryFrom($this->statLevel) : null;

        if ($level === null) {
            return;
        }

        $schoolClass = SchoolClass::find((int) $this->statClassId);

        if (! $schoolClass || ! in_array($schoolClass->level, $level->classLevels(), true)) {
            $this->statClassId = '';
        }
    }

    public function confirmDelete(int $id)
    {
        $this->deletingId = $id;
        $this->deletingIsConverted = ProspectiveStudent::query()
            ->where('converted_student_id', $id)
            ->exists();
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete()
    {
        $this->deletingId = null;
        $this->deletingIsConverted = false;
        $this->isDeleteModalOpen = false;
    }

    public function openModal()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset([
            'studentId', 'nis', 'nama_lengkap', 'nama_panggilan', 'class_id', 'entry_academic_year_id', 'status',
            'jenis_kelamin', 'alamat', 'nama_ayah', 'no_telp_ayah', 'nama_ibu', 'no_telp_ibu',
            'tempat_lahir', 'tanggal_lahir', 'entry_date', 'foto_upload', 'existing_foto', 'remove_foto', 'isEditing',
            'categoryIds', 'inactiveCategoryNames',
        ]);
        $this->resetValidation();
    }

    public function edit(Student $student)
    {
        $updater = app(StudentProfileUpdater::class);

        $this->isEditing = true;
        $this->studentId = $student->id;
        $this->nis = $student->nis;
        $this->nama_lengkap = $student->nama_lengkap;
        $this->nama_panggilan = $student->nama_panggilan;
        $this->class_id = $updater->representedClassId($student);
        $this->jenis_kelamin = $student->jenis_kelamin;
        $this->alamat = $student->alamat;
        $this->nama_ayah = $student->nama_ayah ?? '';
        $this->no_telp_ayah = $student->no_telp_ayah ?? '';
        $this->nama_ibu = $student->nama_ibu ?? '';
        $this->no_telp_ibu = $student->no_telp_ibu ?? '';
        $this->tempat_lahir = $student->tempat_lahir ?? '';
        $this->tanggal_lahir = $student->tanggal_lahir ? Carbon::parse($student->tanggal_lahir)->format('Y-m-d') : '';
        $this->entry_date = $student->entry_date ? Carbon::parse($student->entry_date)->format('Y-m-d') : '';
        $this->existing_foto = $student->foto;
        $this->foto_upload = null;
        $this->remove_foto = false;
        $categories = $student->categories()->orderBy('name')->get();
        $this->categoryIds = $categories
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        $this->inactiveCategoryNames = $categories
            ->where('is_active', false)
            ->pluck('name')
            ->values()
            ->all();

        $this->isModalOpen = true;
    }

    public function save()
    {
        if ($this->isEditing) {
            $student = Student::query()->findOrFail((int) $this->studentId);
            $updater = app(StudentProfileUpdater::class);
            $photoService = app(StudentPhotoService::class);
            $validatedData = $this->validate([
                ...$updater->rules($student),
                ...$this->categoryRules(),
            ], $updater->messages());
            $newPhotoPath = null;

            try {
                if ($this->foto_upload) {
                    $newPhotoPath = $photoService->store($this->foto_upload);
                    $validatedData['foto'] = $newPhotoPath;
                } elseif ($this->remove_foto) {
                    $validatedData['foto'] = null;
                }

                DB::transaction(function () use ($updater, $student, $validatedData): void {
                    $updater->update($student, $validatedData);
                    $student->categories()->sync($this->categoryIdsForSync($student, $validatedData['categoryIds'] ?? []));
                });
            } catch (Throwable $exception) {
                $photoService->delete($newPhotoPath);

                throw $exception;
            }

            if ($newPhotoPath !== null || $this->remove_foto) {
                $photoService->delete($this->existing_foto);
            }

            session()->flash('success', 'Data siswa berhasil diupdate.');
            $this->closeModal();

            return;
        }

        $rules = [
            'nama_lengkap' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'class_id' => 'required|exists:school_classes,id',
            'status' => 'required|in:aktif,lulus,pindah',
            'jenis_kelamin' => 'nullable|in:L,P',
            'alamat' => 'nullable|string',
            'entry_academic_year_id' => 'nullable|required_if:status,lulus,pindah|exists:academic_years,id',
            ...app(StudentProfileUpdater::class)->biodataRules(),
            ...$this->categoryRules(),
        ];

        $rules['nis'] = 'nullable|string|max:50';

        $validatedData = $this->validate($rules);

        $updater = app(StudentProfileUpdater::class);
        $photoService = app(StudentPhotoService::class);
        $data = collect($validatedData)->except(['entry_academic_year_id', 'foto_upload', 'remove_foto', 'categoryIds'])->toArray();
        $data = $updater->normalizeBiodata($data);

        if (! empty($validatedData['entry_academic_year_id'])) {
            $data['entry_academic_year_id'] = (int) $validatedData['entry_academic_year_id'];
        }

        $photoPath = null;

        try {
            if ($this->foto_upload) {
                $photoPath = $photoService->store($this->foto_upload);
                $data['foto'] = $photoPath;
            }

            DB::transaction(function () use ($data, $validatedData): void {
                $student = app(StudentCreationService::class)->create($data);
                $student->categories()->sync($validatedData['categoryIds'] ?? []);
            });
        } catch (Throwable $exception) {
            $photoService->delete($photoPath);

            throw $exception;
        }
        session()->flash(
            'success',
            $this->status === StudentStatus::Active->value
                ? 'Siswa baru berhasil ditambahkan; buku tagihan otomatis dibuat.'
                : 'Data siswa nonaktif dan riwayat akademiknya berhasil ditambahkan.'
        );

        $this->closeModal();
    }

    public function messages()
    {
        return [
            'nis.max' => 'NIS maksimal 50 karakter.',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max' => 'Nama lengkap terlalu panjang.',
            'nama_panggilan.max' => 'Nama panggilan terlalu panjang.',
            'class_id.required' => 'Kelas wajib dipilih.',
            'class_id.exists' => 'Kelas yang dipilih tidak valid.',
            'status.in' => 'Status siswa tidak valid.',
            'entry_academic_year_id.required_if' => 'Tahun ajaran terakhir wajib dipilih untuk siswa nonaktif.',
            'jenis_kelamin.in' => 'Pilihan jenis kelamin tidak valid.',
        ];
    }

    public function delete(StudentDeletionService $deletionService)
    {
        if (! $this->deletingId) {
            return;
        }

        $deletionService->delete((int) $this->deletingId);

        $this->cancelDelete();
        session()->flash('success', 'Data siswa beserta semua pembayaran terkait berhasil dihapus.');
    }

    public function render()
    {
        $query = Student::with(['schoolClass', 'enrollments.academicYear', 'categories'])->latest();

        if ($this->filterLevel !== '') {
            $level = SchoolLevel::tryFrom($this->filterLevel);

            if ($level === null) {
                $query->whereRaw('0 = 1');
            } else {
                $levelClassIds = SchoolClass::query()
                    ->whereIn('level', $level->classLevels())
                    ->pluck('id');

                $query->whereIn('class_id', $levelClassIds);
            }
        }

        if ($this->filterClassId) {
            $query->where('class_id', $this->filterClassId);
        }

        if ($this->filterCategory === 'normal') {
            $query->whereDoesntHave('categories');
        } elseif ($this->filterCategory !== '') {
            $query->whereHas('categories', function (Builder $categoryQuery): void {
                $categoryQuery
                    ->where('code', $this->filterCategory)
                    ->where('is_active', true);
            });
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('nama_lengkap', 'like', $term)
                    ->orWhere('nama_panggilan', 'like', $term)
                    ->orWhere('nis', 'like', $term)
                    ->orWhereHas('schoolClass', function ($cq) use ($term) {
                        $cq->where('name', 'like', $term);
                    });
            });
        }

        $activeYear = AcademicYear::active();

        if ($this->filterStatus === 'aktif') {
            $query->where('status', StudentStatus::Active->value);

            if ($activeYear) {
                $query->whereHas('enrollments', function ($q) use ($activeYear) {
                    $q->where('academic_year_id', $activeYear->id)
                        ->where('status', 'active');
                });
            }
        } elseif ($this->filterStatus === 'lulus') {
            $query->where('status', StudentStatus::Graduated->value);
        } elseif ($this->filterStatus === 'pindah') {
            $query->where('status', StudentStatus::Transferred->value);
        } elseif ($this->filterStatus === 'calon_siswa') {
            $query->where('status', StudentStatus::Active->value);

            // Calon Siswa: no active enrollment in active year, has future enrollment.
            if ($activeYear) {
                $query->whereDoesntHave('enrollments', function ($q) use ($activeYear) {
                    $q->where('academic_year_id', $activeYear->id)
                        ->where('status', 'active');
                })->whereHas('enrollments', function ($q) use ($activeYear) {
                    $q->whereHas('academicYear', function ($yearQuery) use ($activeYear) {
                        $yearQuery->whereDate('start_date', '>', $activeYear->start_date);
                    });
                });
            } else {
                // No active year — cannot determine calon siswa
                $query->whereRaw('0 = 1');
            }
        }

        $academicYears = AcademicYear::orderByDesc('year')->get();
        $statYear = $this->resolveStatYear($academicYears);
        $statLevelValue = $this->statLevel !== '' ? SchoolLevel::tryFrom($this->statLevel) : null;
        $statClassIdValue = $this->statClassId !== '' ? (int) $this->statClassId : null;

        $statCounts = $statYear === null
            ? ['total' => 0, 'active' => 0, 'prospective' => 0, 'graduated' => 0]
            : app(StudentStatisticService::class)->counts($statYear, $statLevelValue, $statClassIdValue);

        $statClasses = SchoolClass::query()
            ->when($this->statLevel !== '', function (Builder $query): void {
                $level = SchoolLevel::tryFrom($this->statLevel);

                if ($level === null) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->whereIn('level', $level->classLevels());
                }
            })
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return view('livewire.student.index', [
            'students' => $query->paginate(10),
            'classes' => SchoolClass::orderBy('level')->orderBy('name')->get(),
            'levels' => SchoolLevel::cases(),
            'academicYears' => $academicYears,
            'statCounts' => $statCounts,
            'statClasses' => $statClasses,
            'activeCategories' => StudentCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function categoryRules(): array
    {
        return [
            'categoryIds' => ['nullable', 'array'],
            'categoryIds.*' => [
                'integer',
                'distinct',
                Rule::exists('student_categories', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * Preserve inactive historical assignments while syncing active selections.
     *
     * @param  array<int, int|string>  $activeCategoryIds
     * @return array<int, int>
     */
    private function categoryIdsForSync(Student $student, array $activeCategoryIds): array
    {
        $inactiveCategoryIds = $student->categories()
            ->where('is_active', false)
            ->pluck('student_categories.id')
            ->all();

        return collect([...$activeCategoryIds, ...$inactiveCategoryIds])
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve tahun ajaran statistik: default tahun aktif, fallback aktif bila
     * pilihan tidak valid. Nilai properti disinkronkan agar dropdown berkorelasi.
     *
     * @param  Collection<int, AcademicYear>  $academicYears
     */
    private function resolveStatYear(Collection $academicYears): ?AcademicYear
    {
        if ($this->statYearId === '') {
            $active = $academicYears->first(fn (AcademicYear $year): bool => $year->is_active);

            if ($active !== null) {
                $this->statYearId = (string) $active->id;
            }

            return $active;
        }

        $selected = $academicYears->firstWhere('id', (int) $this->statYearId);

        if ($selected !== null) {
            return $selected;
        }

        $this->statYearId = '';

        return $this->resolveStatYear($academicYears);
    }
}
