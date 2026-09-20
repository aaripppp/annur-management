<?php

namespace App\Livewire;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentCreationService;
use App\Services\StudentDeletionService;
use App\Services\StudentPhotoService;
use App\Services\StudentProfileUpdater;
use Carbon\Carbon;
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

    public function updatedSearch()
    {
        $this->resetPage();
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
            'studentId', 'nis', 'nama_lengkap', 'nama_panggilan', 'class_id', 'entry_academic_year_id',
            'jenis_kelamin', 'alamat', 'nama_ayah', 'no_telp_ayah', 'nama_ibu', 'no_telp_ibu',
            'tempat_lahir', 'tanggal_lahir', 'entry_date', 'foto_upload', 'existing_foto', 'remove_foto', 'isEditing',
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

        $this->isModalOpen = true;
    }

    public function save()
    {
        if ($this->isEditing) {
            $student = Student::query()->findOrFail((int) $this->studentId);
            $updater = app(StudentProfileUpdater::class);
            $photoService = app(StudentPhotoService::class);
            $validatedData = $this->validate($updater->rules($student), $updater->messages());
            $newPhotoPath = null;

            try {
                if ($this->foto_upload) {
                    $newPhotoPath = $photoService->store($this->foto_upload);
                    $validatedData['foto'] = $newPhotoPath;
                } elseif ($this->remove_foto) {
                    $validatedData['foto'] = null;
                }

                $updater->update($student, $validatedData);
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
            'jenis_kelamin' => 'nullable|in:L,P',
            'alamat' => 'nullable|string',
            'entry_academic_year_id' => 'nullable|exists:academic_years,id',
            ...app(StudentProfileUpdater::class)->biodataRules(),
        ];

        $rules['nis'] = 'nullable|string|max:50';

        $validatedData = $this->validate($rules);

        $updater = app(StudentProfileUpdater::class);
        $photoService = app(StudentPhotoService::class);
        $data = collect($validatedData)->except(['entry_academic_year_id', 'foto_upload', 'remove_foto'])->toArray();
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

            app(StudentCreationService::class)->create($data);
        } catch (Throwable $exception) {
            $photoService->delete($photoPath);

            throw $exception;
        }
        session()->flash('success', 'Siswa baru berhasil ditambahkan; buku tagihan otomatis dibuat.');

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
        $query = Student::with(['schoolClass', 'enrollments.academicYear'])->latest();

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
            if ($activeYear) {
                $query->whereHas('enrollments', function ($q) use ($activeYear) {
                    $q->where('academic_year_id', $activeYear->id)
                        ->where('status', 'active');
                });
            }
        } elseif ($this->filterStatus === 'lulus') {
            $query->whereHas('enrollments', function ($q) {
                $q->where('status', 'lulus');
            });

            if ($activeYear) {
                $query->whereDoesntHave('enrollments', function ($q) use ($activeYear) {
                    $q->where('academic_year_id', $activeYear->id)
                        ->where('status', 'active');
                })->whereDoesntHave('enrollments', function ($q) use ($activeYear) {
                    $q->whereHas('academicYear', function ($yearQuery) use ($activeYear) {
                        $yearQuery->whereDate('start_date', '>', $activeYear->start_date);
                    });
                });
            }
        } elseif ($this->filterStatus === 'calon_siswa') {
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

        $totalStudents = Student::count();
        $totalMale = Student::where('jenis_kelamin', 'L')->count();
        $totalFemale = Student::where('jenis_kelamin', 'P')->count();

        return view('livewire.student.index', [
            'students' => $query->paginate(10),
            'classes' => SchoolClass::orderBy('level')->orderBy('name')->get(),
            'levels' => SchoolLevel::cases(),
            'totalStudents' => $totalStudents,
            'totalMale' => $totalMale,
            'totalFemale' => $totalFemale,
            'academicYears' => AcademicYear::orderByDesc('year')->get(),
        ]);
    }
}
