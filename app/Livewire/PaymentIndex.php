<?php

namespace App\Livewire;

use App\Enums\ProspectiveStudentStatus;
use App\Livewire\Concerns\ManagesStudentBills;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\PaymentDeletionService;
use App\Services\StudentPhotoService;
use App\Services\StudentProfileUpdater;
use App\Services\TransactionHistoryService;
use App\Support\StudentBillbook;
use App\Support\StudentRegistrationHistory;
use App\Support\TransactionHistoryRow;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
class PaymentIndex extends Component
{
    use ManagesStudentBills;
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'student';

    public string $studentSearch = '';

    #[Url(as: 'student')]
    public ?int $selectedStudentId = null;

    public string $selectedAcademicYear = '';

    public string $summaryPeriod = '';

    public bool $isEditProfileOpen = false;

    public ?string $nis = null;

    public string $nama_lengkap = '';

    public ?string $nama_panggilan = null;

    public int|string $class_id = '';

    public ?string $jenis_kelamin = null;

    public ?string $alamat = null;

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

    public string $search = '';

    public string $bankId = '';

    public string $status = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $summaryPreset = 'all';

    public string $summaryStartDate = '';

    public string $summaryEndDate = '';

    public bool $isDeleteModalOpen = false;

    public ?int $deletingId = null;

    public string $deletingSource = '';

    public function mount(): void
    {
        if (! in_array($this->activeTab, ['student', 'history'], true)) {
            $this->activeTab = 'student';
        }

        if ($this->selectedStudentId === null) {
            return;
        }

        $studentId = $this->selectedStudentId;
        $this->selectedStudentId = null;
        $this->selectStudent($studentId);
    }

    public function selectStudent(int $studentId): void
    {
        $this->closeBillManagementModals();

        $student = Student::query()
            ->with('enrollments.academicYear')
            ->find($studentId);

        if (! $student) {
            return;
        }

        $this->selectedStudentId = $studentId;
        $this->studentSearch = '';
        $this->summaryPeriod = '';

        $latestEnrollment = $student->enrollments->sortByDesc('academic_year_id')->first();
        $this->selectedAcademicYear = $latestEnrollment?->academicYear->year
            ?? AcademicYear::active()->year
            ?? '';
    }

    public function changeStudent(): void
    {
        $this->closeBillManagementModals();
        $this->selectedStudentId = null;
        $this->studentSearch = '';
        $this->selectedAcademicYear = '';
        $this->summaryPeriod = '';
    }

    public function updatedSelectedAcademicYear(): void
    {
        $this->summaryPeriod = '';
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['student', 'history'], true)) {
            return;
        }

        $this->activeTab = $tab;

        if ($tab !== 'history') {
            $this->cancelDelete();
        }
    }

    public function confirmDelete(int $paymentId): void
    {
        if ($this->activeTab !== 'history') {
            return;
        }

        $payment = Payment::query()->find($paymentId);

        if (! $payment) {
            return;
        }

        $this->deletingSource = 'student';
        $this->deletingId = $paymentId;
        $this->isDeleteModalOpen = true;
    }

    public function cancelDelete(): void
    {
        $this->isDeleteModalOpen = false;
        $this->deletingId = null;
        $this->deletingSource = '';
    }

    public function delete(PaymentDeletionService $deletionService): void
    {
        if (! $this->deletingId || $this->deletingSource !== 'student') {
            return;
        }

        $deletionService->delete($this->deletingId);

        $this->cancelDelete();
        session()->flash('success', 'Transaksi pembayaran berhasil dihapus permanen.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBankId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    public function updatedSummaryPreset(string $value): void
    {
        switch ($value) {
            case 'today':
                $this->summaryStartDate = now()->toDateString();
                $this->summaryEndDate = now()->toDateString();
                break;
            case 'yesterday':
                $this->summaryStartDate = now()->subDay()->toDateString();
                $this->summaryEndDate = now()->subDay()->toDateString();
                break;
            case 'this_month':
                $this->summaryStartDate = now()->startOfMonth()->toDateString();
                $this->summaryEndDate = now()->toDateString();
                break;
            case 'all':
            default:
                $this->summaryStartDate = '';
                $this->summaryEndDate = '';
                break;
        }
    }

    public function updatedSummaryStartDate(): void
    {
        $this->summaryPreset = 'manual';
    }

    public function updatedSummaryEndDate(): void
    {
        $this->summaryPreset = 'manual';
    }

    private function summaryTotals(): array
    {
        $this->resetValidation(['summaryStartDate', 'summaryEndDate']);

        $rules = [
            'summaryStartDate' => ['nullable'],
            'summaryEndDate' => ['nullable'],
        ];

        if ($this->summaryStartDate !== '') {
            $rules['summaryStartDate'][] = 'date';
        }

        if ($this->summaryEndDate !== '') {
            $rules['summaryEndDate'][] = 'date';
        }

        $validator = Validator::make(
            [
                'summaryStartDate' => $this->summaryStartDate,
                'summaryEndDate' => $this->summaryEndDate,
            ],
            $rules,
            [
                'summaryStartDate.date' => 'Tanggal mulai tidak valid.',
                'summaryEndDate.date' => 'Tanggal akhir tidak valid.',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return ['total_amount' => 0.0, 'total_count' => 0];
        }

        $start = $this->summaryStartDate;
        $end = $this->summaryEndDate;

        if ($this->summaryPreset !== 'manual') {
            $presetStart = match ($this->summaryPreset) {
                'today' => now()->toDateString(),
                'yesterday' => now()->subDay()->toDateString(),
                'this_month' => now()->startOfMonth()->toDateString(),
                default => null,
            };

            if ($presetStart !== null) {
                $start = $presetStart;
                $end = $this->summaryPreset === 'this_month'
                    ? now()->toDateString()
                    : $presetStart;
            }
        }

        if ($start !== '' && $end !== '' && $end < $start) {
            $this->addError('summaryEndDate', 'Tanggal akhir tidak boleh sebelum tanggal mulai.');

            return ['total_amount' => 0.0, 'total_count' => 0];
        }

        $studentRow = Payment::query()
            ->where('status', Payment::STATUS_ACTIVE)
            ->when($start !== '', static fn (Builder $query) => $query->whereDate('payment_date', '>=', $start))
            ->when($end !== '', static fn (Builder $query) => $query->whereDate('payment_date', '<=', $end))
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_amount')
            ->selectRaw('COUNT(*) AS total_count')
            ->first();

        $convertedProspectiveRow = ProspectiveStudentPayment::query()
            ->where('status', ProspectiveStudentPayment::STATUS_ACTIVE)
            ->whereHas('prospectiveStudent', fn (Builder $query) => $query->whereNotNull('converted_student_id'))
            ->when($start !== '', static fn (Builder $query) => $query->whereDate('payment_date', '>=', $start))
            ->when($end !== '', static fn (Builder $query) => $query->whereDate('payment_date', '<=', $end))
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_amount')
            ->selectRaw('COUNT(*) AS total_count')
            ->first();

        return [
            'total_amount' => (float) ($studentRow->total_amount ?? 0) + (float) ($convertedProspectiveRow->total_amount ?? 0),
            'total_count' => (int) ($studentRow->total_count ?? 0) + (int) ($convertedProspectiveRow->total_count ?? 0),
        ];
    }

    public function openEditProfile(): void
    {
        $student = Student::query()->find($this->selectedStudentId);

        if (! $student) {
            return;
        }

        $updater = app(StudentProfileUpdater::class);

        $this->nis = $student->nis;
        $this->nama_lengkap = $student->nama_lengkap;
        $this->nama_panggilan = $student->nama_panggilan;
        $this->class_id = $updater->representedClassId($student) ?? '';
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
        $this->resetValidation();
        $this->isEditProfileOpen = true;
    }

    public function closeEditProfile(): void
    {
        $this->isEditProfileOpen = false;
        $this->reset([
            'nis', 'nama_lengkap', 'nama_panggilan', 'class_id', 'jenis_kelamin', 'alamat',
            'nama_ayah', 'no_telp_ayah', 'nama_ibu', 'no_telp_ibu', 'tempat_lahir',
            'tanggal_lahir', 'entry_date', 'foto_upload', 'existing_foto', 'remove_foto',
        ]);
        $this->resetValidation();
    }

    public function saveProfile(): void
    {
        $student = Student::query()->findOrFail($this->selectedStudentId);
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

        if (array_key_exists('foto', $validatedData) && $student->foto !== $this->existing_foto) {
            $photoService->delete($this->existing_foto);
        }

        $this->closeEditProfile();
        session()->flash('success', 'Data siswa berhasil diupdate.');
    }

    public function render(): View
    {
        /** @var Collection<int, Student> $searchResults */
        $searchResults = new Collection;

        /** @var Collection<int, ProspectiveStudent> $prospectiveSearchResults */
        $prospectiveSearchResults = new Collection;
        $search = trim($this->studentSearch);

        if ($this->selectedStudentId === null && mb_strlen($search) >= 2) {
            $searchTerm = '%'.$search.'%';

            $searchResults = Student::query()
                ->with(['schoolClass', 'enrollments.academicYear'])
                ->where(function ($query) use ($searchTerm): void {
                    $query->where('nama_lengkap', 'like', $searchTerm)
                        ->orWhere('nama_panggilan', 'like', $searchTerm)
                        ->orWhere('nis', 'like', $searchTerm);
                })
                ->orderBy('nama_lengkap')
                ->orderBy('id')
                ->limit(8)
                ->get();

            $prospectiveSearchResults = ProspectiveStudent::query()
                ->with(['academicYear', 'schoolClass'])
                ->where('status', ProspectiveStudentStatus::Registered)
                ->where(function ($query) use ($searchTerm): void {
                    $query->where('registration_number', 'like', $searchTerm)
                        ->orWhere('nama_lengkap', 'like', $searchTerm)
                        ->orWhere('nama_panggilan', 'like', $searchTerm)
                        ->orWhere('nama_orang_tua', 'like', $searchTerm)
                        ->orWhere('no_telp_orang_tua', 'like', $searchTerm);
                })
                ->orderBy('nama_lengkap')
                ->orderBy('id')
                ->limit(8)
                ->get();
        }

        $selectedStudent = $this->selectedStudentId === null
            ? null
            : Student::query()
                ->with(['schoolClass', 'enrollments.academicYear'])
                ->find($this->selectedStudentId);

        $academicYearContext = null;

        if ($selectedStudent) {
            $academicStatus = $selectedStudent->academicStatus();
            $activeAcademicYear = AcademicYear::active();

            if ($academicStatus === 'calon_siswa') {
                $academicYearContext = $selectedStudent->entryYearLabel();
            } elseif ($activeAcademicYear && $selectedStudent->enrollments->contains(
                fn ($enrollment): bool => $enrollment->academic_year_id === $activeAcademicYear->id
                    && $enrollment->status === 'active'
            )) {
                $academicYearContext = $activeAcademicYear->year;
            } else {
                $academicYearContext = $selectedStudent->enrollments
                    ->sortByDesc('academic_year_id')
                    ->first()?->academicYear?->year;
            }
        }

        $billbook = null;
        $academicYearSelectorOptions = [];
        $classes = new Collection;
        $payments = null;
        $banks = new Collection;
        $deletingPayment = null;
        $registrationHistory = ['prospect' => null, 'summary' => null];
        $summary = ['total_amount' => 0.0, 'total_count' => 0];

        if ($selectedStudent) {
            $registrationHistory = StudentRegistrationHistory::forStudent($selectedStudent);

            $billbook = StudentBillbook::build(
                $selectedStudent,
                $this->selectedAcademicYear,
                $this->summaryPeriod
            );
            $this->summaryPeriod = $billbook['summaryPeriod'];
            $academicYearSelectorOptions = AcademicYear::query()
                ->orderByDesc('year')
                ->pluck('year', 'year')
                ->toArray();
        }

        if ($this->isEditProfileOpen) {
            $classes = SchoolClass::query()
                ->orderBy('level')
                ->orderBy('name')
                ->get();
        }

        if ($this->activeTab === 'history') {
            $payments = $this->historyPayments();
            $banks = Bank::query()->orderBy('name')->get();

            if ($this->isDeleteModalOpen && $this->deletingId) {
                $deletingPayment = Payment::query()
                    ->with('student')
                    ->find($this->deletingId);
            }
        }

        $summary = $this->summaryTotals();

        return view('livewire.payment.index', [
            'searchResults' => $searchResults,
            'prospectiveSearchResults' => $prospectiveSearchResults,
            'selectedStudent' => $selectedStudent,
            'academicYearContext' => $academicYearContext,
            'registeredProspect' => $registrationHistory['prospect'],
            'registrationSummary' => $registrationHistory['summary'],
            'billbook' => $billbook,
            'academicYearSelectorOptions' => $academicYearSelectorOptions,
            'classes' => $classes,
            'payments' => $payments,
            'banks' => $banks,
            'deletingPayment' => $deletingPayment,
            'summaryTotal' => $summary['total_amount'],
            'summaryCount' => $summary['total_count'],
            ...$this->manualBillFormData(),
        ]);
    }

    protected function managedBillStudent(): ?Student
    {
        if ($this->selectedStudentId === null) {
            return null;
        }

        return Student::query()->with('schoolClass')->find($this->selectedStudentId);
    }

    /** @return LengthAwarePaginator<int, TransactionHistoryRow> */
    private function historyPayments(): LengthAwarePaginator
    {
        return app(TransactionHistoryService::class)->getHistory(
            search: trim($this->search),
            bankId: $this->bankId,
            status: $this->status,
            startDate: $this->startDate,
            endDate: $this->endDate,
        );
    }
}
