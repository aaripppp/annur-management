<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\JemputanDriverDeductionRate;
use App\Models\Student;
use App\Services\JemputanDriverAssignmentService;
use App\Services\JemputanDriverDeductionRateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
class JemputanDriverManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $isDriverModalOpen = false;

    public ?int $editingDriverId = null;

    public string $driverName = '';

    public string $driverPhone = '';

    public string $driverFoundationDeductionPercentage = '';

    public string $driverDeductionEffectiveMonth = '';

    public bool $driverIsActive = true;

    public bool $isDeductionRateModalOpen = false;

    public string $deductionRatePercentage = '';

    public string $deductionRateEffectiveMonth = '';

    public string $deductionRateNotes = '';

    public bool $deductionRateRequiresConfirmation = false;

    public bool $deductionRateRetroactiveConfirmed = false;

    public bool $isEditDeductionRateModalOpen = false;

    public ?int $editingDeductionRateId = null;

    public string $editDeductionRatePercentage = '';

    public string $editDeductionRateEffectiveMonth = '';

    public string $editDeductionRateNotes = '';

    /** @var array{percentage: string, effective_year: int, effective_month: int, notes: string|null, updated_at: string|null} */
    public array $editDeductionRateOriginalSnapshot = [];

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
        $this->driverFoundationDeductionPercentage = $driver->foundation_deduction_percentage ?? '';
        $this->driverIsActive = $driver->is_active;
        $this->isDriverModalOpen = true;
    }

    public function saveDriver(): void
    {
        $driver = $this->editingDriverId === null
            ? null
            : JemputanDriver::query()->findOrFail($this->editingDriverId);
        $this->authorize($driver === null ? 'create' : 'update', $driver ?? JemputanDriver::class);

        $rules = [
            'driverName' => ['required', 'string', 'max:255'],
            'driverPhone' => ['nullable', 'string', 'max:30'],
            'driverIsActive' => ['boolean'],
        ];

        if ($driver === null) {
            $rules['driverFoundationDeductionPercentage'] = ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'];
            $rules['driverDeductionEffectiveMonth'] = [
                'required_with:driverFoundationDeductionPercentage',
                'nullable',
                'date_format:Y-m',
            ];
        }

        $validated = $this->validateWithToast($rules, [
            'driverDeductionEffectiveMonth.required_with' => 'Pilih bulan mulai berlaku untuk persentase awal sopir.',
            'driverDeductionEffectiveMonth.date_format' => 'Format bulan mulai berlaku tidak valid.',
        ]);

        $payload = [
            'name' => trim($validated['driverName']),
            'phone' => filled($validated['driverPhone']) ? trim($validated['driverPhone']) : null,
            'is_active' => (bool) $validated['driverIsActive'],
        ];

        if ($driver === null) {
            $percentage = filled($validated['driverFoundationDeductionPercentage'])
                ? (float) $validated['driverFoundationDeductionPercentage']
                : null;
            $effectiveMonth = $percentage === null
                ? null
                : CarbonImmutable::createFromFormat('Y-m', $validated['driverDeductionEffectiveMonth'])->startOfMonth();

            DB::transaction(function () use ($payload, $percentage, $effectiveMonth): void {
                $created = JemputanDriver::query()->create($payload);

                if ($percentage === null || $effectiveMonth === null) {
                    return;
                }

                JemputanDriverDeductionRate::query()->create([
                    'jemputan_driver_id' => $created->id,
                    'effective_year' => (int) $effectiveMonth->year,
                    'effective_month' => (int) $effectiveMonth->month,
                    'percentage' => $percentage,
                    'notes' => null,
                    'created_by' => auth()->id(),
                ]);
            });

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

    public function openDeductionRateModal(): void
    {
        $driver = $this->selectedDriver();
        $this->authorize('update', $driver);

        $this->resetDeductionRateForm();
        $this->deductionRateEffectiveMonth = CarbonImmutable::now()->format('Y-m');
        $this->isDeductionRateModalOpen = true;
    }

    public function closeDeductionRateModal(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->isDeductionRateModalOpen = false;
        $this->resetDeductionRateForm();
    }

    public function updatedDeductionRatePercentage(): void
    {
        $this->resetDeductionRateConfirmation();
    }

    public function updatedDeductionRateEffectiveMonth(): void
    {
        $this->resetDeductionRateConfirmation();
    }

    public function saveDeductionRate(): void
    {
        $driver = $this->selectedDriver();
        $this->authorize('update', $driver);

        $validated = $this->validateWithToast([
            'deductionRatePercentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'deductionRateEffectiveMonth' => ['required', 'date_format:Y-m'],
            'deductionRateNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'deductionRatePercentage.required' => 'Persentase baru wajib diisi.',
            'deductionRatePercentage.decimal' => 'Persentase maksimal 2 angka desimal.',
            'deductionRateEffectiveMonth.required' => 'Bulan mulai berlaku wajib diisi.',
            'deductionRateEffectiveMonth.date_format' => 'Format bulan mulai berlaku tidak valid.',
        ]);

        $effective = CarbonImmutable::createFromFormat('Y-m', $validated['deductionRateEffectiveMonth'])->startOfMonth();
        $year = (int) $effective->year;
        $month = (int) $effective->month;

        $duplicate = JemputanDriverDeductionRate::query()
            ->where('jemputan_driver_id', $driver->id)
            ->where('effective_year', $year)
            ->where('effective_month', $month)
            ->exists();

        if ($duplicate) {
            $this->deductionRatePeriodAlreadyExists($effective);

            return;
        }

        if ($this->isRetroactivePeriod($effective) && ! $this->deductionRateRetroactiveConfirmed) {
            $this->deductionRateRequiresConfirmation = true;
            $this->notify('Tarif ini berlaku mundur. Konfirmasi dampak historis sebelum menyimpan.', 'error');

            return;
        }

        try {
            DB::transaction(function () use ($driver, $validated, $year, $month): void {
                JemputanDriverDeductionRate::query()->create([
                    'jemputan_driver_id' => $driver->id,
                    'effective_year' => $year,
                    'effective_month' => $month,
                    'percentage' => (float) $validated['deductionRatePercentage'],
                    'notes' => filled($validated['deductionRateNotes']) ? trim($validated['deductionRateNotes']) : null,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (QueryException) {
            $this->deductionRatePeriodAlreadyExists($effective);

            return;
        }

        $this->isDeductionRateModalOpen = false;
        $this->resetDeductionRateForm();
        $this->notify('Tarif potongan yayasan berhasil disimpan mulai '.$this->periodLabel($year, $month).'.');
    }

    public function confirmRetroactiveDeductionRate(): void
    {
        $this->deductionRateRetroactiveConfirmed = true;
        $this->saveDeductionRate();
    }

    public function openEditDeductionRateModal(int $rateId): void
    {
        $driver = $this->selectedDriver();
        $this->authorize('update', $driver);
        $rate = JemputanDriverDeductionRate::query()
            ->where('jemputan_driver_id', $driver->id)
            ->findOrFail($rateId);

        $this->resetEditDeductionRateForm();
        $this->editingDeductionRateId = $rate->id;
        $this->editDeductionRatePercentage = rtrim(rtrim((string) $rate->percentage, '0'), '.');
        $this->editDeductionRateEffectiveMonth = sprintf('%04d-%02d', $rate->effective_year, $rate->effective_month);
        $this->editDeductionRateNotes = $rate->notes ?? '';
        $this->editDeductionRateOriginalSnapshot = $this->deductionRateConcurrencySnapshot($rate);
        $this->isEditDeductionRateModalOpen = true;
    }

    public function closeEditDeductionRateModal(): void
    {
        $this->authorize('viewAny', JemputanDriver::class);
        $this->isEditDeductionRateModalOpen = false;
        $this->resetEditDeductionRateForm();
    }

    public function saveDeductionRateCorrection(): void
    {
        $driver = $this->selectedDriver();
        $this->authorize('update', $driver);

        try {
            $validated = $this->validate([
                'editingDeductionRateId' => ['required', 'integer'],
                'editDeductionRatePercentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
                'editDeductionRateEffectiveMonth' => ['required', 'date_format:Y-m', 'regex:/^(19\d{2}|[2-9]\d{3})-(0[1-9]|1[0-2])$/'],
                'editDeductionRateNotes' => ['nullable', 'string', 'max:500'],
            ], [
                'editDeductionRatePercentage.decimal' => 'Persentase maksimal 2 angka desimal.',
                'editDeductionRateEffectiveMonth.required' => 'Bulan mulai berlaku wajib diisi.',
                'editDeductionRateEffectiveMonth.date_format' => 'Format bulan mulai berlaku tidak valid.',
                'editDeductionRateEffectiveMonth.regex' => 'Bulan atau tahun mulai berlaku tidak valid.',
            ]);
            $effective = CarbonImmutable::createFromFormat('Y-m', $validated['editDeductionRateEffectiveMonth'])->startOfMonth();

            DB::transaction(function () use ($driver, $validated, $effective): void {
                $rate = JemputanDriverDeductionRate::query()
                    ->where('jemputan_driver_id', $driver->id)
                    ->lockForUpdate()
                    ->findOrFail((int) $validated['editingDeductionRateId']);

                if ($this->deductionRateConcurrencySnapshot($rate) !== $this->editDeductionRateOriginalSnapshot) {
                    throw ValidationException::withMessages([
                        'editDeductionRate' => 'Data riwayat telah diubah oleh pengguna lain. Muat ulang data terbaru sebelum mencoba lagi.',
                    ]);
                }

                $year = (int) $effective->year;
                $month = (int) $effective->month;
                $duplicateExists = JemputanDriverDeductionRate::query()
                    ->where('jemputan_driver_id', $driver->id)
                    ->whereKeyNot($rate->id)
                    ->where('effective_year', $year)
                    ->where('effective_month', $month)
                    ->lockForUpdate()
                    ->exists();

                if ($duplicateExists) {
                    throw ValidationException::withMessages([
                        'editDeductionRateEffectiveMonth' => 'Periode tujuan sudah digunakan oleh riwayat persentase lain. Pilih bulan atau tahun lain.',
                    ]);
                }

                $rate->update([
                    'percentage' => (float) $validated['editDeductionRatePercentage'],
                    'effective_year' => $year,
                    'effective_month' => $month,
                    'notes' => filled($validated['editDeductionRateNotes'])
                        ? trim($validated['editDeductionRateNotes'])
                        : null,
                ]);
            });
        } catch (ValidationException $exception) {
            $this->notify('Gagal menyimpan koreksi persentase.', 'error');

            throw $exception;
        } catch (QueryException $exception) {
            if ($this->isDeductionRateDuplicatePeriodException($exception)) {
                $this->addError('editDeductionRateEffectiveMonth', 'Periode tujuan sudah digunakan oleh riwayat persentase lain. Pilih bulan atau tahun lain.');
            } else {
                report($exception);
                $this->addError('editDeductionRate', 'Riwayat persentase tidak dapat disimpan. Silakan coba kembali.');
            }

            $this->notify('Gagal menyimpan koreksi persentase.', 'error');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('editDeductionRate', 'Koreksi persentase tidak dapat disimpan. Silakan coba kembali.');
            $this->notify('Gagal menyimpan koreksi persentase.', 'error');

            return;
        }

        $this->isEditDeductionRateModalOpen = false;
        $this->resetEditDeductionRateForm();
        $this->notify('Riwayat persentase berhasil dikoreksi.');
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
            $this->notify('Siswa tidak tersedia untuk tahun ajaran ini atau sudah menjadi anggota sopir ini.', 'error');

            throw ValidationException::withMessages([
                'selectedStudentId' => 'Siswa tidak tersedia untuk tahun ajaran ini atau sudah menjadi anggota sopir ini.',
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

        $now = CarbonImmutable::now();
        $drivers = JemputanDriver::query()
            ->addSelect([
                'student_count' => JemputanDriverAssignment::query()
                    ->selectRaw('COUNT(DISTINCT student_id)')
                    ->whereColumn('jemputan_driver_id', 'jemputan_drivers.id')
                    ->where('academic_year_id', (int) $this->selectedAcademicYearId)
                    ->currentMembership(),
            ])
            ->with(['deductionRates' => fn ($query) => $query
                ->orderByDesc('effective_year')
                ->orderByDesc('effective_month')])
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10, ['*'], 'driversPage');

        $currentRates = $drivers->getCollection()
            ->mapWithKeys(fn (JemputanDriver $driver): array => [
                $driver->id => $this->currentDeductionRate($driver, (int) $now->year, (int) $now->month),
            ])
            ->all();

        $assignments = null;
        $selectedDriver = null;
        $selectedDriverCurrentRate = null;
        $selectedDriverRateHistory = collect();
        $deductionRatePreview = null;

        if ($this->selectedDriverId !== null) {
            $selectedDriver = JemputanDriver::query()
                ->with(['deductionRates' => fn ($query) => $query
                    ->orderBy('effective_year')
                    ->orderBy('effective_month')])
                ->find($this->selectedDriverId);

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

                $selectedDriverCurrentRate = $this->currentDeductionRate(
                    $selectedDriver,
                    (int) $now->year,
                    (int) $now->month,
                );
                $selectedDriverRateHistory = $selectedDriver->deductionRates
                    ->map(fn (JemputanDriverDeductionRate $rate): array => [
                        'id' => $rate->id,
                        'period_label' => $this->periodLabel((int) $rate->effective_year, (int) $rate->effective_month),
                        'percentage_label' => $this->percentageLabel((float) $rate->percentage),
                        'notes' => $rate->notes,
                    ])
                    ->values();

                if ($this->isDeductionRateModalOpen) {
                    $deductionRatePreview = $this->deductionRatePreview($selectedDriver);
                }

            }
        }

        return view('livewire.jemputan-driver-management', [
            'drivers' => $drivers,
            'currentRates' => $currentRates,
            'selectedDriver' => $selectedDriver,
            'selectedDriverCurrentRate' => $selectedDriverCurrentRate,
            'selectedDriverRateHistory' => $selectedDriverRateHistory,
            'deductionRatePreview' => $deductionRatePreview,
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
        $this->reset('editingDriverId', 'driverName', 'driverPhone', 'driverFoundationDeductionPercentage');
        $this->driverDeductionEffectiveMonth = CarbonImmutable::now()->format('Y-m');
        $this->driverIsActive = true;
        $this->resetValidation();
    }

    private function resetDeductionRateForm(): void
    {
        $this->reset(
            'deductionRatePercentage',
            'deductionRateEffectiveMonth',
            'deductionRateNotes',
            'deductionRateRequiresConfirmation',
            'deductionRateRetroactiveConfirmed',
        );
        $this->resetValidation();
    }

    private function resetDeductionRateConfirmation(): void
    {
        $this->deductionRateRequiresConfirmation = false;
        $this->deductionRateRetroactiveConfirmed = false;
    }

    private function resetEditDeductionRateForm(): void
    {
        $this->reset(
            'editingDeductionRateId',
            'editDeductionRatePercentage',
            'editDeductionRateEffectiveMonth',
            'editDeductionRateNotes',
            'editDeductionRateOriginalSnapshot',
        );
        $this->resetValidation();
    }

    private function isRetroactivePeriod(CarbonImmutable $effective): bool
    {
        return $effective->lt(CarbonImmutable::now()->startOfMonth());
    }

    private function deductionRatePeriodAlreadyExists(CarbonImmutable $effective): void
    {
        $message = 'Tarif untuk periode '.$effective->locale('id')->translatedFormat('F Y')
            .' sudah ada. Pilih periode lain atau periksa riwayat tarif sopir.';

        $this->addError('deductionRateEffectiveMonth', $message);
        $this->notify($message, 'error');
    }

    /**
     * Resolve the rate that applies to one driver without issuing a query when
     * the deduction rates relation has already been eager loaded.
     *
     * @return array{
     *     percentage: float|null,
     *     configured: bool,
     *     source: string,
     *     effective_year: int|null,
     *     effective_month: int|null,
     *     label: string,
     *     period_label: string|null
     * }
     */
    private function currentDeductionRate(JemputanDriver $driver, int $year, int $month): array
    {
        if (! $driver->relationLoaded('deductionRates')) {
            $driver->load(['deductionRates' => fn ($query) => $query
                ->orderByDesc('effective_year')
                ->orderByDesc('effective_month')]);
        }

        $rate = $driver->deductionRates
            ->filter(fn (JemputanDriverDeductionRate $candidate): bool => (int) $candidate->effective_year < $year
                || ((int) $candidate->effective_year === $year && (int) $candidate->effective_month <= $month))
            ->sortByDesc(fn (JemputanDriverDeductionRate $candidate): int => ((int) $candidate->effective_year * 100) + (int) $candidate->effective_month)
            ->first();

        if ($rate instanceof JemputanDriverDeductionRate) {
            return [
                'percentage' => (float) $rate->percentage,
                'configured' => true,
                'source' => JemputanDriverDeductionRateResolver::SOURCE_HISTORY,
                'effective_year' => (int) $rate->effective_year,
                'effective_month' => (int) $rate->effective_month,
                'label' => $this->percentageLabel((float) $rate->percentage),
                'period_label' => $this->periodLabel((int) $rate->effective_year, (int) $rate->effective_month),
            ];
        }

        if ($driver->foundation_deduction_percentage !== null) {
            return [
                'percentage' => (float) $driver->foundation_deduction_percentage,
                'configured' => true,
                'source' => JemputanDriverDeductionRateResolver::SOURCE_LEGACY,
                'effective_year' => null,
                'effective_month' => null,
                'label' => $this->percentageLabel((float) $driver->foundation_deduction_percentage),
                'period_label' => null,
            ];
        }

        return [
            'percentage' => null,
            'configured' => false,
            'source' => JemputanDriverDeductionRateResolver::SOURCE_NONE,
            'effective_year' => null,
            'effective_month' => null,
            'label' => 'Belum diatur',
            'period_label' => null,
        ];
    }

    private function percentageLabel(?float $percentage): string
    {
        if ($percentage === null) {
            return 'Belum diatur';
        }

        return rtrim(rtrim(number_format($percentage, 2, ',', '.'), '0'), ',').'%';
    }

    private function periodLabel(int $year, int $month): string
    {
        return CarbonImmutable::createFromDate($year, $month, 1)
            ->locale('id')
            ->translatedFormat('F Y');
    }

    /** @return array<string, mixed>|null */
    private function deductionRatePreview(JemputanDriver $driver): ?array
    {
        $now = CarbonImmutable::now();

        try {
            $effective = CarbonImmutable::createFromFormat('Y-m', $this->deductionRateEffectiveMonth)->startOfMonth();
        } catch (Throwable) {
            return null;
        }

        if ((int) $effective->year < 2000) {
            return null;
        }

        $previous = $effective->subMonthNoOverflow();
        $resolver = app(JemputanDriverDeductionRateResolver::class);
        $previousRate = $resolver->resolve($driver, (int) $previous->year, (int) $previous->month);
        $newPercentage = is_numeric($this->deductionRatePercentage)
            ? (float) $this->deductionRatePercentage
            : null;

        return [
            'current' => $this->currentDeductionRate($driver, (int) $now->year, (int) $now->month),
            'effective_label' => $this->periodLabel((int) $effective->year, (int) $effective->month),
            'previous_label' => $this->periodLabel((int) $previous->year, (int) $previous->month),
            'previous_label_percentage' => $this->percentageLabel($previousRate['percentage']),
            'previous_source' => $previousRate['source'],
            'previous_configured' => $previousRate['configured'],
            'new_label' => $newPercentage === null ? 'Belum diisi' : $this->percentageLabel($newPercentage),
            'is_retroactive' => $this->isRetroactivePeriod($effective),
            'has_new_value' => $newPercentage !== null,
            'duplicate' => JemputanDriverDeductionRate::query()
                ->where('jemputan_driver_id', $driver->id)
                ->where('effective_year', (int) $effective->year)
                ->where('effective_month', (int) $effective->month)
                ->exists(),
        ];
    }

    /** @return array{percentage: string, effective_year: int, effective_month: int, notes: string|null, updated_at: string|null} */
    private function deductionRateConcurrencySnapshot(JemputanDriverDeductionRate $rate): array
    {
        return [
            'percentage' => number_format((float) $rate->percentage, 2, '.', ''),
            'effective_year' => (int) $rate->effective_year,
            'effective_month' => (int) $rate->effective_month,
            'notes' => $rate->notes === null ? null : trim($rate->notes),
            'updated_at' => $rate->updated_at?->format('Y-m-d H:i:s.u'),
        ];
    }

    private function isDeductionRateDuplicatePeriodException(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && (str_contains($message, 'jddr_driver_effective_unique')
                || str_contains($message, 'effective_year, effective_month'));
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
                ->where('jemputan_driver_id', $this->selectedDriverId)
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
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    private function validateWithToast(array $rules, array $messages = []): array
    {
        try {
            return $this->validate($rules, $messages);
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
