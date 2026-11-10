<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Student;
use App\Services\JemputanDriverAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class JemputanDriverManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $isDriverModalOpen = false;

    public ?int $editingDriverId = null;

    public string $driverName = '';

    public string $driverPhone = '';

    public bool $driverIsActive = true;

    public ?int $selectedDriverId = null;

    public string $selectedAcademicYearId = '';

    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public bool $isAssignmentModalOpen = false;

    public bool $isCloseModalOpen = false;

    public ?int $closingAssignmentId = null;

    public bool $isMoveModalOpen = false;

    public ?int $movingAssignmentId = null;

    public string $moveDriverId = '';

    public bool $isDriverConfirmationModalOpen = false;

    public ?int $confirmingDriverId = null;

    public string $driverConfirmationAction = '';

    public function mount(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->selectedAcademicYearId = (string) (AcademicYear::active()?->id ?? AcademicYear::query()->latest('start_date')->value('id') ?? '');
    }

    public function updatedSearch(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->resetPage('driversPage');
    }

    public function updatedSelectedAcademicYearId(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->resetPage('assignmentsPage');
        $this->reset('studentSearch', 'selectedStudentId');
    }

    public function openCreateDriver(): void
    {
        $this->authorize('create', JemputanDriver::class);
        $this->resetDriverForm();
        $this->isDriverModalOpen = true;
    }

    public function editDriver(int $driverId): void
    {
        $driver = JemputanDriver::query()->findOrFail($driverId);
        $this->authorize('update', $driver);

        $this->editingDriverId = $driver->id;
        $this->driverName = $driver->name;
        $this->driverPhone = $driver->phone ?? '';
        $this->driverIsActive = $driver->is_active;
        $this->isDriverModalOpen = true;
    }

    public function saveDriver(): void
    {
        $driver = $this->editingDriverId === null
            ? null
            : JemputanDriver::query()->findOrFail($this->editingDriverId);
        $this->authorize($driver === null ? 'create' : 'update', $driver ?? JemputanDriver::class);

        $validated = $this->validateWithToast([
            'driverName' => ['required', 'string', 'max:255'],
            'driverPhone' => ['nullable', 'string', 'max:30'],
            'driverIsActive' => ['boolean'],
        ]);

        $payload = [
            'name' => trim($validated['driverName']),
            'phone' => filled($validated['driverPhone']) ? trim($validated['driverPhone']) : null,
            'is_active' => (bool) $validated['driverIsActive'],
        ];

        if ($driver === null) {
            JemputanDriver::query()->create($payload);
            $this->notify('Sopir Jemputan berhasil ditambahkan.');
        } else {
            $driver->update($payload);
            $this->notify('Data sopir Jemputan berhasil diperbarui.');
        }

        $this->closeDriverModal();
    }

    public function closeDriverModal(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->isDriverModalOpen = false;
        $this->resetDriverForm();
    }

    public function toggleDriverStatus(int $driverId): void
    {
        $driver = JemputanDriver::query()->findOrFail($driverId);
        $this->authorize('update', $driver);
        $driver->update(['is_active' => ! $driver->is_active]);

        $this->notify($driver->is_active
            ? 'Sopir berhasil diaktifkan.'
            : 'Sopir dinonaktifkan tanpa mengubah riwayat penugasan.');
    }

    public function deleteDriver(int $driverId): void
    {
        $driver = JemputanDriver::query()->findOrFail($driverId);
        $this->authorize('delete', $driver);

        if ($driver->assignments()->exists()) {
            $this->addError('deleteDriver', 'Sopir tidak dapat dihapus karena memiliki riwayat penugasan. Nonaktifkan sopir sebagai gantinya.');
            $this->notify('Sopir tidak dapat dihapus karena memiliki riwayat penugasan.', 'error');

            return;
        }

        $driver->delete();
        $this->notify('Sopir Jemputan berhasil dihapus.');
    }

    public function openDriverConfirmation(int $driverId, string $action): void
    {
        $driver = JemputanDriver::query()->findOrFail($driverId);
        $this->authorize($action === 'delete' ? 'delete' : 'update', $driver);

        if (! in_array($action, ['toggle', 'delete'], true)) {
            abort(404);
        }

        $this->confirmingDriverId = $driver->id;
        $this->driverConfirmationAction = $action;
        $this->isDriverConfirmationModalOpen = true;
    }

    public function confirmDriverAction(): void
    {
        if ($this->driverConfirmationAction === 'toggle') {
            $this->toggleDriverStatus((int) $this->confirmingDriverId);
        } elseif ($this->driverConfirmationAction === 'delete') {
            $this->deleteDriver((int) $this->confirmingDriverId);
        } else {
            abort(404);
        }

        $this->cancelDriverConfirmation();
    }

    public function cancelDriverConfirmation(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->isDriverConfirmationModalOpen = false;
        $this->reset('confirmingDriverId', 'driverConfirmationAction');
    }

    public function viewGroup(int $driverId): void
    {
        $driver = JemputanDriver::query()->findOrFail($driverId);
        $this->authorize('view', $driver);
        $this->selectedDriverId = $driver->id;
        $this->resetPage('assignmentsPage');
    }

    public function closeGroup(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->reset('selectedDriverId', 'studentSearch', 'selectedStudentId');
    }

    public function openAssignmentModal(): void
    {
        $driver = $this->selectedDriver();
        $this->authorize('update', $driver);

        if (! $driver->is_active) {
            $this->addError('assignmentDriverId', 'Aktifkan sopir sebelum menambahkan siswa.');
            $this->notify('Aktifkan sopir sebelum menambahkan siswa.', 'error');

            return;
        }

        $this->reset('studentSearch', 'selectedStudentId');
        $this->isAssignmentModalOpen = true;
    }

    public function selectStudent(int $studentId): void
    {
        $this->authorize('update', $this->selectedDriver());
        $student = $this->eligibleStudents()->findOrFail($studentId);
        $this->selectedStudentId = $student->id;
        $this->studentSearch = '';
        $this->resetValidation('selectedStudentId');
    }

    public function changeStudent(): void
    {
        $this->authorize('update', $this->selectedDriver());
        $this->reset('selectedStudentId', 'studentSearch');
        $this->resetValidation('selectedStudentId');
    }

    public function assignStudent(JemputanDriverAssignmentService $service): void
    {
        $driver = $this->selectedDriver();
        $this->authorize('update', $driver);

        $validated = $this->validateWithToast([
            'selectedStudentId' => ['required', 'integer', 'exists:students,id'],
            'selectedAcademicYearId' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        $student = $this->eligibleStudents()->find((int) $validated['selectedStudentId']);

        if ($student === null) {
            $this->notify('Siswa tidak tersedia untuk tahun ajaran ini atau sudah menjadi anggota kelompok sopir.', 'error');

            throw ValidationException::withMessages([
                'selectedStudentId' => 'Siswa tidak tersedia untuk tahun ajaran ini atau sudah menjadi anggota kelompok sopir.',
            ]);
        }

        try {
            $service->assign(
                $driver->id,
                $student->id,
                (int) $validated['selectedAcademicYearId'],
            );
        } catch (ValidationException $exception) {
            $this->notify('Siswa tidak dapat ditambahkan. Periksa keanggotaan aktifnya.', 'error');

            throw $exception;
        }

        $this->closeAssignmentModal();
        $this->notify('Siswa berhasil ditambahkan ke kelompok sopir.');
    }

    public function closeAssignmentModal(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->isAssignmentModalOpen = false;
        $this->reset('studentSearch', 'selectedStudentId');
        $this->resetValidation();
    }

    public function openCloseAssignment(int $assignmentId): void
    {
        $assignment = $this->assignmentForSelectedDriver($assignmentId);
        $this->authorize('update', $assignment->driver);
        $this->closingAssignmentId = $assignment->id;
        $this->isCloseModalOpen = true;
    }

    public function closeAssignment(JemputanDriverAssignmentService $service): void
    {
        $assignment = $this->assignmentForSelectedDriver((int) $this->closingAssignmentId);
        $this->authorize('update', $assignment->driver);
        try {
            $service->remove($assignment->id);
        } catch (ValidationException $exception) {
            $this->notify('Siswa tidak dapat dikeluarkan dari kelompok sopir.', 'error');

            throw $exception;
        }

        $this->cancelCloseAssignment();
        $this->notify('Siswa berhasil dikeluarkan dari kelompok sopir.');
    }

    public function cancelCloseAssignment(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->isCloseModalOpen = false;
        $this->reset('closingAssignmentId');
        $this->resetValidation();
    }

    public function openMoveAssignment(int $assignmentId): void
    {
        $assignment = $this->assignmentForSelectedDriver($assignmentId);
        $this->authorize('update', $assignment->driver);
        $this->movingAssignmentId = $assignment->id;
        $this->moveDriverId = '';
        $this->isMoveModalOpen = true;
    }

    public function moveAssignment(JemputanDriverAssignmentService $service): void
    {
        $assignment = $this->assignmentForSelectedDriver((int) $this->movingAssignmentId);
        $this->authorize('update', $assignment->driver);
        $validated = $this->validateWithToast([
            'moveDriverId' => ['required', 'integer', Rule::exists('jemputan_drivers', 'id')->where('is_active', true)],
        ]);

        try {
            $service->move(
                $assignment->id,
                (int) $validated['moveDriverId'],
            );
        } catch (ValidationException $exception) {
            $this->notify('Siswa tidak dapat dipindahkan. Periksa sopir tujuan.', 'error');

            throw $exception;
        }

        $this->cancelMoveAssignment();
        $this->notify('Siswa berhasil dipindahkan ke kelompok sopir tujuan.');
    }

    public function cancelMoveAssignment(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->isMoveModalOpen = false;
        $this->reset('movingAssignmentId', 'moveDriverId');
        $this->resetValidation();
    }

    public function render(): View
    {
        $this->authorize('viewAny', JemputanDriver::class);

        $drivers = JemputanDriver::query()
            ->addSelect([
                'student_count' => JemputanDriverAssignment::query()
                    ->selectRaw('COUNT(DISTINCT student_id)')
                    ->whereColumn('jemputan_driver_id', 'jemputan_drivers.id')
                    ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                    ->currentMembership(),
            ])
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10, ['*'], 'driversPage');

        $assignments = null;
        $selectedDriver = null;

        if ($this->selectedDriverId !== null) {
            $selectedDriver = JemputanDriver::query()->find($this->selectedDriverId);

            if ($selectedDriver !== null) {
                $assignments = $selectedDriver->assignments()
                    ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                    ->currentMembership()
                    ->with([
                        'academicYear',
                        'student.schoolClass',
                        'student.enrollments' => fn ($query) => $query
                            ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                            ->with('schoolClass'),
                    ])
                    ->orderBy('effective_from')
                    ->orderBy('id')
                    ->paginate(10, ['*'], 'assignmentsPage');
            }
        }

        return view('livewire.jemputan-driver-management', [
            'drivers' => $drivers,
            'selectedDriver' => $selectedDriver,
            'assignments' => $assignments,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'year', 'start_date', 'end_date']),
            'activeDrivers' => JemputanDriver::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'studentResults' => $this->studentResults(),
            'selectedStudent' => $this->selectedStudentForPicker(),
        ]);
    }

    private function selectedDriver(): JemputanDriver
    {
        return JemputanDriver::query()->findOrFail($this->selectedDriverId);
    }

    private function assignmentForSelectedDriver(int $assignmentId): JemputanDriverAssignment
    {
        return JemputanDriverAssignment::query()
            ->with('driver')
            ->where('jemputan_driver_id', $this->selectedDriverId)
            ->where('academic_year_id', (int) $this->selectedAcademicYearId)
            ->currentMembership()
            ->findOrFail($assignmentId);
    }

    private function resetDriverForm(): void
    {
        $this->reset('editingDriverId', 'driverName', 'driverPhone');
        $this->driverIsActive = true;
        $this->resetValidation();
    }

    private function studentResults(): mixed
    {
        if (! $this->isAssignmentModalOpen || mb_strlen(trim($this->studentSearch)) < 2 || $this->selectedStudentId !== null) {
            return collect();
        }

        $term = '%'.trim($this->studentSearch).'%';

        return $this->eligibleStudents()
            ->select(['id', 'nama_lengkap', 'nis', 'class_id'])
            ->where(function (Builder $query) use ($term): void {
                $query->where('nama_lengkap', 'like', $term)
                    ->orWhere('nis', 'like', $term);
            })
            ->with(['enrollments' => fn ($query) => $query
                ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                ->with('schoolClass')])
            ->orderBy('nama_lengkap')
            ->limit(8)
            ->get();
    }

    /** @return Builder<Student> */
    private function eligibleStudents(): Builder
    {
        return Student::query()
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->where('academic_year_id', (int) $this->selectedAcademicYearId))
            ->whereDoesntHave('jemputanDriverAssignments', fn (Builder $query) => $query
                ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                ->currentMembership());
    }

    private function selectedStudentForPicker(): ?Student
    {
        if (! $this->isAssignmentModalOpen || $this->selectedStudentId === null) {
            return null;
        }

        return $this->eligibleStudents()
            ->with(['enrollments' => fn ($query) => $query
                ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                ->with('schoolClass')])
            ->find($this->selectedStudentId);
    }

    /**
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, mixed>
     */
    private function validateWithToast(array $rules): array
    {
        try {
            return $this->validate($rules);
        } catch (ValidationException $exception) {
            $this->notify('Periksa kembali data yang belum valid.', 'error');

            throw $exception;
        }
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->dispatch('annur-toast', message: $message, type: $type);
    }
}
