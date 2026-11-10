<?php

namespace App\Livewire;

use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\Concerns\ConvertsProspectiveStudent;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\PpdbDocumentCleanupService;
use App\Services\ProspectiveStudentBillGenerationService;
use App\Services\ProspectiveStudentRegistrationNumberGenerator;
use App\Services\ProspectiveStudentUnitScope;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ProspectiveStudentManagement extends Component
{
    use ConvertsProspectiveStudent;
    use WithPagination;

    public string $search = '';

    public string $filterAcademicYearId = '';

    public string $filterLevel = '';

    public string $filterClassId = '';

    public string $filterStatus = '';

    public string $filterBillStatus = '';

    public string $filterCategory = '';

    public bool $isModalOpen = false;

    public bool $isEditing = false;

    public ?int $prospectiveStudentId = null;

    public string $registration_number = '';

    public string $nama_lengkap = '';

    public string $nama_panggilan = '';

    public string $jenis_kelamin = '';

    public string $nama_orang_tua = '';

    public string $no_telp_orang_tua = '';

    public string $alamat = '';

    public string $academic_year_id = '';

    public string $school_class_id = '';

    public string $notes = '';

    public bool $isDeleteModalOpen = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public string $deletingRegistrationNumber = '';

    public function mount(): void
    {
        if (auth()->check()) {
            $this->authorize('viewAny', ProspectiveStudent::class);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAcademicYearId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterLevel(): void
    {
        $this->filterClassId = '';
        $this->resetPage();
    }

    public function updatedFilterClassId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterBillStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function openModal(): void
    {
        $this->authorizeProspectiveStudentActionWhenAuthenticated('create', ProspectiveStudent::class);

        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $prospectiveStudent = ProspectiveStudent::query()->findOrFail($id);
        $this->authorizeProspectiveStudentActionWhenAuthenticated('delete', $prospectiveStudent);

        if ($prospectiveStudent->isConverted()) {
            session()->flash('error', 'Calon siswa yang sudah dikonversi tidak dapat dihapus.');

            return;
        }

        $this->deletingId = $prospectiveStudent->id;
        $this->deletingName = $prospectiveStudent->nama_lengkap;
        $this->deletingRegistrationNumber = $prospectiveStudent->registration_number;
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete(): void
    {
        $this->reset(['isDeleteModalOpen', 'deletingId', 'deletingName', 'deletingRegistrationNumber']);
    }

    public function delete(PpdbDocumentCleanupService $documentCleanupService): void
    {
        if ($this->deletingId === null) {
            return;
        }

        try {
            $deletedName = DB::transaction(function () use ($documentCleanupService): string {
                $prospectiveStudent = ProspectiveStudent::query()
                    ->lockForUpdate()
                    ->findOrFail($this->deletingId);
                $this->authorizeProspectiveStudentActionWhenAuthenticated('delete', $prospectiveStudent);

                if ($prospectiveStudent->isConverted()) {
                    throw ValidationException::withMessages(['delete' => 'Calon siswa yang sudah dikonversi tidak dapat dihapus.']);
                }

                if ($prospectiveStudent->payments()->lockForUpdate()->get()->isNotEmpty()) {
                    throw ValidationException::withMessages(['delete' => 'Calon siswa dengan catatan pembayaran tidak dapat dihapus.']);
                }

                $documentCleanupService->scheduleFor($prospectiveStudent);
                $prospectiveStudent->bills()->lockForUpdate()->get();
                $prospectiveStudent->bills()->delete();
                $prospectiveStudent->delete();

                return $prospectiveStudent->nama_lengkap;
            });
        } catch (ValidationException $exception) {
            session()->flash('error', $exception->errors()['delete'][0]);
            $this->cancelDelete();

            return;
        }

        $this->cancelDelete();
        $this->resetPage();
        session()->flash('success', "Calon siswa {$deletedName} berhasil dihapus.");
    }

    public function edit(int $id): void
    {
        $prospectiveStudent = ProspectiveStudent::query()->findOrFail($id);
        $this->authorizeProspectiveStudentActionWhenAuthenticated('update', $prospectiveStudent);

        if ($prospectiveStudent->isConverted()) {
            session()->flash('error', 'Calon siswa yang sudah dikonversi tidak dapat diubah.');
            $this->resetForm();

            return;
        }

        $this->isEditing = true;
        $this->prospectiveStudentId = $prospectiveStudent->id;
        $this->registration_number = $prospectiveStudent->registration_number;
        $this->nama_lengkap = $prospectiveStudent->nama_lengkap;
        $this->nama_panggilan = $prospectiveStudent->nama_panggilan ?? '';
        $this->jenis_kelamin = $prospectiveStudent->jenis_kelamin ?? '';
        $this->nama_orang_tua = $prospectiveStudent->nama_orang_tua ?? '';
        $this->no_telp_orang_tua = $prospectiveStudent->no_telp_orang_tua ?? '';
        $this->alamat = $prospectiveStudent->alamat ?? '';
        $this->academic_year_id = (string) $prospectiveStudent->academic_year_id;
        $this->school_class_id = $prospectiveStudent->school_class_id !== null ? (string) $prospectiveStudent->school_class_id : '';
        $this->notes = $prospectiveStudent->notes ?? '';
        $this->isModalOpen = true;
    }

    public function convertProspect(int $id): void
    {
        $this->prospectiveStudent = ProspectiveStudent::query()
            ->with(['academicYear', 'schoolClass'])
            ->findOrFail($id);

        $this->openConvertModal();
    }

    /**
     * Konversi dari daftar calon siswa tetap berada di halaman ini: modal
     * ditutup, state konversi dibersihkan, dan daftar diperbarui lewat
     * re-render komponen sehingga baris langsung menampilkan status terkonversi.
     */
    protected function handleSuccessfulConversion(Student $student): void
    {
        $this->closeConvertModal();
        $this->reset('prospectiveStudent');
    }

    public function save(
        ProspectiveStudentRegistrationNumberGenerator $registrationNumberGenerator,
        ProspectiveStudentBillGenerationService $billGenerationService,
    ): void {
        if (! $this->isEditing) {
            $this->authorizeProspectiveStudentActionWhenAuthenticated('create', ProspectiveStudent::class);
        }

        $this->authorizeClassAssignmentForTu($this->school_class_id);

        $validated = $this->validate($this->rules(), $this->messages());
        $validated = $this->normalizeNullableFields($validated);

        if ($this->isEditing) {
            $prospectiveStudent = ProspectiveStudent::query()->findOrFail($this->prospectiveStudentId);
            $this->authorizeProspectiveStudentActionWhenAuthenticated('update', $prospectiveStudent);

            if ($prospectiveStudent->isConverted()) {
                session()->flash('error', 'Calon siswa yang sudah dikonversi tidak dapat diubah.');
                $this->closeModal();

                return;
            }

            $prospectiveStudent->update($validated);
            $billGenerationService->sync($prospectiveStudent);
            session()->flash('success', 'Data calon siswa berhasil diperbarui.');
        } else {
            $academicYear = AcademicYear::query()->findOrFail($validated['academic_year_id']);
            $startYear = (int) substr($academicYear->year, 0, 4);

            $prospectiveStudent = ProspectiveStudent::query()->create(array_merge($validated, [
                'registration_number' => $registrationNumberGenerator->next($startYear),
                'status' => ProspectiveStudentStatus::Registered,
                'created_by' => auth()->id(),
            ]));
            $billGenerationService->generateFor($prospectiveStudent);
            session()->flash('success', "Calon siswa {$prospectiveStudent->nama_lengkap} berhasil ditambahkan.");
        }

        $this->closeModal();
    }

    /** @return array<string, string> */
    protected function rules(): array
    {
        return ProspectiveStudent::profileRules();
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return ProspectiveStudent::profileMessages();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeNullableFields(array $data): array
    {
        foreach (['nama_panggilan', 'jenis_kelamin', 'nama_orang_tua', 'no_telp_orang_tua', 'alamat', 'notes'] as $field) {
            $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
        }

        $data['school_class_id'] = filled($data['school_class_id']) ? (int) $data['school_class_id'] : null;

        return $data;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'isEditing',
            'prospectiveStudentId',
            'registration_number',
            'nama_lengkap',
            'nama_panggilan',
            'jenis_kelamin',
            'nama_orang_tua',
            'no_telp_orang_tua',
            'alamat',
            'academic_year_id',
            'school_class_id',
            'notes',
        ]);
        $this->resetValidation();
    }

    private function applyProspectiveStudentFilters(Builder $query, bool $isTuUnit): Builder
    {
        $term = trim($this->search);
        $filterLevel = $this->filterLevel !== '' ? SchoolLevel::tryFrom($this->filterLevel) : null;

        return $query
            ->when($term !== '', function (Builder $query) use ($term): void {
                $pattern = '%'.$term.'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('registration_number', 'like', $pattern)
                        ->orWhere('nama_lengkap', 'like', $pattern)
                        ->orWhere('nama_panggilan', 'like', $pattern)
                        ->orWhere('nama_orang_tua', 'like', $pattern)
                        ->orWhere('no_telp_orang_tua', 'like', $pattern);
                });
            })
            ->when($this->filterAcademicYearId !== '', function (Builder $query): void {
                $query->where('academic_year_id', (int) $this->filterAcademicYearId);
            })
            ->when($this->filterLevel !== '', function (Builder $query) use ($filterLevel): void {
                if ($filterLevel === null) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $levelClassIds = SchoolClass::query()
                    ->whereIn('level', $filterLevel->classLevels())
                    ->pluck('id');

                $query->whereIn('school_class_id', $levelClassIds);
            })
            ->when($this->filterClassId !== '', function (Builder $query): void {
                $query->where('school_class_id', (int) $this->filterClassId);
            })
            ->when($this->filterStatus !== '', function (Builder $query): void {
                $status = ProspectiveStudentStatus::tryFrom($this->filterStatus);

                if ($status === null) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->where('status', $status->value);
            })
            ->when(! $isTuUnit && $this->filterBillStatus !== '', function (Builder $query): void {
                $this->applyBillStatusFilter($query, $this->filterBillStatus);
            })
            ->when($this->filterCategory !== '', function (Builder $query): void {
                match ($this->filterCategory) {
                    PpdbApplicantCategory::ANNUR_FAMILY->value => $query->where('applicant_category', PpdbApplicantCategory::ANNUR_FAMILY->value),
                    PpdbApplicantCategory::NON_ANNUR_FAMILY->value => $query->where('applicant_category', PpdbApplicantCategory::NON_ANNUR_FAMILY->value),
                    'uncategorized' => $query->where(function (Builder $query): void {
                        $query->whereNull('applicant_category')
                            ->orWhere('applicant_category', '')
                            ->orWhereNotIn('applicant_category', [
                                PpdbApplicantCategory::ANNUR_FAMILY->value,
                                PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
                            ]);
                    }),
                    default => $query->whereRaw('0 = 1'),
                };
            });
    }

    public function render()
    {
        $user = auth()->user();

        if ($user !== null) {
            $this->authorize('viewAny', ProspectiveStudent::class);
        }

        $isTuUnit = $user?->isTuUnit() ?? false;
        $filterLevel = $this->filterLevel !== '' ? SchoolLevel::tryFrom($this->filterLevel) : null;

        $baseQuery = ProspectiveStudent::query();
        app(ProspectiveStudentUnitScope::class)->applyForUser($baseQuery, $user);
        $baseQuery = $this->applyProspectiveStudentFilters($baseQuery, $isTuUnit);

        $relations = ['academicYear', 'schoolClass'];

        if (! $isTuUnit) {
            $relations[] = 'bills.paymentType';
            $relations[] = 'bills.paymentDetails.payment';
        }

        $query = (clone $baseQuery)
            ->with($relations)
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        $totalAll = (clone $baseQuery)->count();
        $totalRegistered = (clone $baseQuery)->where('status', ProspectiveStudentStatus::Registered)->count();
        $totalConverted = (clone $baseQuery)->where('status', ProspectiveStudentStatus::Converted)->count();

        $totalAllL = (clone $baseQuery)->where('jenis_kelamin', 'L')->count();
        $totalAllP = (clone $baseQuery)->where('jenis_kelamin', 'P')->count();
        $totalRegisteredL = (clone $baseQuery)->where('status', ProspectiveStudentStatus::Registered)->where('jenis_kelamin', 'L')->count();
        $totalRegisteredP = (clone $baseQuery)->where('status', ProspectiveStudentStatus::Registered)->where('jenis_kelamin', 'P')->count();
        $totalConvertedL = (clone $baseQuery)->where('status', ProspectiveStudentStatus::Converted)->where('jenis_kelamin', 'L')->count();
        $totalConvertedP = (clone $baseQuery)->where('status', ProspectiveStudentStatus::Converted)->where('jenis_kelamin', 'P')->count();

        $totalAnnurFamily = (clone $baseQuery)->where('applicant_category', PpdbApplicantCategory::ANNUR_FAMILY->value)->count();
        $totalNonAnnurFamily = (clone $baseQuery)->where('applicant_category', PpdbApplicantCategory::NON_ANNUR_FAMILY->value)->count();

        $totalAnnurFamilyL = (clone $baseQuery)->where('applicant_category', PpdbApplicantCategory::ANNUR_FAMILY->value)->where('jenis_kelamin', 'L')->count();
        $totalAnnurFamilyP = (clone $baseQuery)->where('applicant_category', PpdbApplicantCategory::ANNUR_FAMILY->value)->where('jenis_kelamin', 'P')->count();
        $totalNonAnnurFamilyL = (clone $baseQuery)->where('applicant_category', PpdbApplicantCategory::NON_ANNUR_FAMILY->value)->where('jenis_kelamin', 'L')->count();
        $totalNonAnnurFamilyP = (clone $baseQuery)->where('applicant_category', PpdbApplicantCategory::NON_ANNUR_FAMILY->value)->where('jenis_kelamin', 'P')->count();

        $totalUncategorized = max(0, $totalAll - $totalAnnurFamily - $totalNonAnnurFamily);

        $unitLevels = $isTuUnit ? ($user->school_level?->classLevels() ?? []) : null;

        $filterClasses = SchoolClass::query()
            ->when($isTuUnit, fn (Builder $query) => $query->whereIn('level', $unitLevels))
            ->when($filterLevel !== null, fn (Builder $query) => $query->whereIn('level', $filterLevel->classLevels()))
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $schoolClasses = SchoolClass::query()
            ->when($isTuUnit, fn (Builder $query) => $query->whereIn('level', $unitLevels))
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return view('livewire.prospective-student.index', [
            'prospectiveStudents' => $query,
            'isTuUnit' => $isTuUnit,
            'totalAll' => $totalAll,
            'totalRegistered' => $totalRegistered,
            'totalConverted' => $totalConverted,
            'totalAllL' => $totalAllL,
            'totalAllP' => $totalAllP,
            'totalRegisteredL' => $totalRegisteredL,
            'totalRegisteredP' => $totalRegisteredP,
            'totalConvertedL' => $totalConvertedL,
            'totalConvertedP' => $totalConvertedP,
            'totalAnnurFamily' => $totalAnnurFamily,
            'totalNonAnnurFamily' => $totalNonAnnurFamily,
            'totalAnnurFamilyL' => $totalAnnurFamilyL,
            'totalAnnurFamilyP' => $totalAnnurFamilyP,
            'totalNonAnnurFamilyL' => $totalNonAnnurFamilyL,
            'totalNonAnnurFamilyP' => $totalNonAnnurFamilyP,
            'totalUncategorized' => $totalUncategorized,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'schoolClasses' => $schoolClasses,
            'levels' => $isTuUnit ? [$user->school_level] : SchoolLevel::cases(),
            'registrationStatuses' => ProspectiveStudentStatus::cases(),
            'billStatusOptions' => $isTuUnit ? [] : [
                'none' => 'Belum Ada Tagihan',
                ProspectiveStudentBill::STATUS_UNPAID => 'Belum Bayar',
                ProspectiveStudentBill::STATUS_PARTIAL => 'Sebagian',
                ProspectiveStudentBill::STATUS_PAID => 'Lunas',
            ],
            'filterClasses' => $filterClasses,
        ]);
    }

    private function authorizeProspectiveStudentActionWhenAuthenticated(string $ability, ProspectiveStudent|string $subject): void
    {
        if (auth()->check()) {
            $this->authorize($ability, $subject);
        }
    }

    private function authorizeClassAssignmentForTu(mixed $schoolClassId): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->isTuUnit()) {
            return;
        }

        $classId = filter_var($schoolClassId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        abort_unless(
            app(ProspectiveStudentUnitScope::class)->classBelongsToUser($classId, $user),
            403,
        );
    }

    /**
     * Terapkan filter Status Tagihan di level query. Pembayaran yang dibatalkan
     * tidak pernah dihitung sebagai pembayaran aktif.
     */
    private function applyBillStatusFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'none' => $query->whereDoesntHave('bills'),
            'unpaid' => $query->whereHas('bills')->whereNotExists($this->billHasActivePaymentClosure()),
            'partial' => $query->whereExists($this->billHasActivePaymentClosure())
                ->whereExists($this->billHasOutstandingBalanceClosure()),
            'paid' => $query->whereHas('bills')->whereNotExists($this->billHasOutstandingBalanceClosure()),
            default => $query->whereRaw('0 = 1'),
        };
    }

    private function billHasActivePaymentClosure(): Closure
    {
        return function ($subquery): void {
            $subquery->selectRaw('1')
                ->from('prospective_student_bills as b')
                ->join('prospective_student_payment_details as d', 'd.prospective_student_bill_id', '=', 'b.id')
                ->join('prospective_student_payments as p', 'p.id', '=', 'd.prospective_student_payment_id')
                ->whereColumn('b.prospective_student_id', 'prospective_students.id')
                ->where('p.status', ProspectiveStudentPayment::STATUS_ACTIVE)
                ->where('d.amount', '>', 0);
        };
    }

    private function billHasOutstandingBalanceClosure(): Closure
    {
        return function ($subquery): void {
            $subquery->selectRaw('1')
                ->from('prospective_student_bills as b')
                ->leftJoin('prospective_student_payment_details as d', 'd.prospective_student_bill_id', '=', 'b.id')
                ->leftJoin('prospective_student_payments as p', 'p.id', '=', 'd.prospective_student_payment_id')
                ->whereColumn('b.prospective_student_id', 'prospective_students.id')
                ->groupBy('b.id')
                ->havingRaw('COALESCE(SUM(CASE WHEN p.status = ? THEN d.amount END), 0) < MAX(b.amount)', [ProspectiveStudentPayment::STATUS_ACTIVE]);
        };
    }
}
