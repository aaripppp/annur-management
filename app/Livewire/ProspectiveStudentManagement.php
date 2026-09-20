<?php

namespace App\Livewire;

use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Livewire\Concerns\ConvertsProspectiveStudent;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ProspectiveStudentBillGenerationService;
use App\Services\ProspectiveStudentRegistrationNumberGenerator;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

    public function openModal(): void
    {
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

    public function delete(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $prospectiveStudent = ProspectiveStudent::query()->findOrFail($this->deletingId);

        if ($prospectiveStudent->isConverted()) {
            session()->flash('error', 'Calon siswa yang sudah dikonversi tidak dapat dihapus.');
            $this->cancelDelete();

            return;
        }

        if ($this->hasRecordedPayments($prospectiveStudent)) {
            session()->flash('error', 'Calon siswa dengan catatan pembayaran tidak dapat dihapus.');
            $this->cancelDelete();

            return;
        }

        $deletedName = $prospectiveStudent->nama_lengkap;

        DB::transaction(function () use ($prospectiveStudent): void {
            $prospectiveStudent->bills()->delete();
            $prospectiveStudent->delete();
        });

        $this->cancelDelete();
        $this->resetPage();
        session()->flash('success', "Calon siswa {$deletedName} berhasil dihapus.");
    }

    private function hasRecordedPayments(ProspectiveStudent $prospectiveStudent): bool
    {
        return $prospectiveStudent->payments()->exists();
    }

    public function edit(int $id): void
    {
        $prospectiveStudent = ProspectiveStudent::query()->findOrFail($id);

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
        $validated = $this->validate($this->rules(), $this->messages());
        $validated = $this->normalizeNullableFields($validated);

        if ($this->isEditing) {
            $prospectiveStudent = ProspectiveStudent::query()->findOrFail($this->prospectiveStudentId);

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

    public function render()
    {
        $term = trim($this->search);
        $filterLevel = $this->filterLevel !== '' ? SchoolLevel::tryFrom($this->filterLevel) : null;

        $query = ProspectiveStudent::query()
            ->with(['academicYear', 'schoolClass', 'bills.paymentType', 'bills.paymentDetails.payment'])
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
            ->when($this->filterBillStatus !== '', function (Builder $query): void {
                $this->applyBillStatusFilter($query, $this->filterBillStatus);
            })
            ->latest()
            ->paginate(10);

        $filterClasses = $filterLevel !== null
            ? SchoolClass::query()->whereIn('level', $filterLevel->classLevels())->orderBy('level')->orderBy('name')->get()
            : SchoolClass::query()->orderBy('level')->orderBy('name')->get();

        return view('livewire.prospective-student.index', [
            'prospectiveStudents' => $query,
            'totalRegistered' => ProspectiveStudent::query()->where('status', ProspectiveStudentStatus::Registered)->count(),
            'totalConverted' => ProspectiveStudent::query()->where('status', ProspectiveStudentStatus::Converted)->count(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'schoolClasses' => SchoolClass::query()->orderBy('level')->orderBy('name')->get(),
            'levels' => SchoolLevel::cases(),
            'registrationStatuses' => ProspectiveStudentStatus::cases(),
            'billStatusOptions' => [
                'none' => 'Belum Ada Tagihan',
                ProspectiveStudentBill::STATUS_UNPAID => 'Belum Bayar',
                ProspectiveStudentBill::STATUS_PARTIAL => 'Sebagian',
                ProspectiveStudentBill::STATUS_PAID => 'Lunas',
            ],
            'filterClasses' => $filterClasses,
        ]);
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
