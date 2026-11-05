<?php

namespace App\Livewire;

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\PpdbDocumentType;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\PpdbDefaultClassMapping;
use App\Models\PpdbPublicSubmission;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use App\Services\PpdbPublicRegistrationService;
use App\Services\ProspectiveStudentBillGenerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

#[Layout('layouts.ppdb-public')]
class PpdbPublicRegistration extends Component
{
    use WithFileUploads;

    private const SUBMIT_DECAY_SECONDS = 600;

    private const SUBMIT_IP_MAX_ATTEMPTS = 30;

    private const SUBMIT_SESSION_MAX_ATTEMPTS = 5;

    public int $currentStep = 1;

    public string $academicYearId = '';

    public string $schoolLevel = '';

    public string $destinationGrade = '';

    public string $applicantCategory = '';

    public string $fullName = '';

    public string $nickname = '';

    public string $gender = '';

    public string $birthplace = '';

    public string $birthdate = '';

    public string $asalSekolah = '';

    public string $nikSiswa = '';

    public string $nomorKk = '';

    public string $nomorAktaKelahiran = '';

    public string $anakKe = '';

    public string $jumlahSaudara = '';

    public string $statusAnak = '';

    public string $agama = '';

    public string $bahasaSehariHari = '';

    public string $address = '';

    public string $rt = '';

    public string $rw = '';

    public string $kelurahan = '';

    public string $kecamatan = '';

    public string $kabupatenKota = '';

    public string $provinsi = '';

    public string $kodePos = '';

    public string $tinggiBadan = '';

    public string $beratBadan = '';

    public string $lingkarKepala = '';

    public string $golonganDarah = '';

    public string $riwayatPenyakit = '';

    public string $berkebutuhanKhusus = '';

    public string $keteranganKebutuhanKhusus = '';

    /** @var array{name: string, nik: string, birthplace: string, birthdate: string, education_last: string, email: string, phone: string, occupation: string, income_range: string} */
    public array $father = ['name' => '', 'nik' => '', 'birthplace' => '', 'birthdate' => '', 'education_last' => '', 'email' => '', 'phone' => '', 'occupation' => '', 'income_range' => ''];

    /** @var array{name: string, nik: string, birthplace: string, birthdate: string, education_last: string, email: string, phone: string, occupation: string, income_range: string} */
    public array $mother = ['name' => '', 'nik' => '', 'birthplace' => '', 'birthdate' => '', 'education_last' => '', 'email' => '', 'phone' => '', 'occupation' => '', 'income_range' => ''];

    /** @var array{name: string, nik: string, birthplace: string, birthdate: string, education_last: string, email: string, phone: string, occupation: string, income_range: string} */
    public array $guardian = ['name' => '', 'nik' => '', 'birthplace' => '', 'birthdate' => '', 'education_last' => '', 'email' => '', 'phone' => '', 'occupation' => '', 'income_range' => ''];

    /** @var list<array{key: string, full_name: string, birthdate: string, gender: string, education_occupation: string}> */
    public array $siblings = [];

    public bool $consent = false;

    /** @var array<string, mixed> */
    public array $documents = [];

    public string $idempotencyToken = '';

    public function mount(): void
    {
        $this->idempotencyToken = Str::random(64);
    }

    public function updatedSchoolLevel(): void
    {
        $this->destinationGrade = '';
        $this->resetValidation('destinationGrade');
    }

    public function addSibling(): void
    {
        $this->siblings[] = [
            'key' => (string) Str::uuid(),
            'full_name' => '',
            'birthdate' => '',
            'gender' => '',
            'education_occupation' => '',
        ];
    }

    public function removeSibling(string $key): void
    {
        foreach ($this->siblings as $index => $sibling) {
            if (($sibling['key'] ?? null) === $key) {
                unset($this->siblings[$index]);

                break;
            }
        }
    }

    public function nextStep(): void
    {
        $this->resetValidation();

        if (! AcademicYear::query()->openForPpdb()->exists()) {
            $this->addError('registration', 'Pendaftaran PPDB online sedang ditutup.');
            $this->dispatch('ppdb-validation-failed', step: $this->currentStep);

            return;
        }

        try {
            $this->validateCurrentStep();
        } catch (ValidationException $exception) {
            $this->dispatch('ppdb-validation-failed', step: $this->currentStep);
            throw $exception;
        }

        if ($this->currentStep < 6) {
            $this->currentStep++;
            $this->dispatch('ppdb-step-changed', step: $this->currentStep);
        }
    }

    public function previousStep(): void
    {
        $this->resetValidation();

        if ($this->currentStep > 1) {
            $this->currentStep--;
            $this->dispatch('ppdb-step-changed', step: $this->currentStep);
        }
    }

    public function submit(PpdbPublicRegistrationService $registrationService): mixed
    {
        if (! $this->isCompletedSubmission() && $this->isSubmitRateLimited()) {
            $this->addError('submission', 'Terlalu banyak percobaan pengajuan. Silakan coba kembali dalam beberapa menit.');
            $this->dispatch('ppdb-validation-failed', step: $this->currentStep);

            return null;
        }

        try {
            $this->validate($this->submissionRules(), $this->validationMessages());
        } catch (ValidationException $exception) {
            $this->dispatch('ppdb-validation-failed', step: $this->currentStep);
            throw $exception;
        }

        $result = $registrationService->register([
            'academic_year_id' => $this->academicYearId,
            'school_level' => $this->schoolLevel,
            'class_level' => $this->destinationGrade,
            'applicant_category' => $this->applicantCategory,
            'full_name' => $this->fullName,
            'nickname' => $this->nickname,
            'gender' => $this->gender,
            'birthplace' => $this->birthplace,
            'birthdate' => $this->birthdate,
            'asal_sekolah' => trim($this->asalSekolah),
            'nik_siswa' => $this->nikSiswa,
            'nomor_kk' => $this->nomorKk,
            'nomor_akta_kelahiran' => $this->nomorAktaKelahiran,
            'anak_ke' => $this->anakKe,
            'jumlah_saudara' => $this->jumlahSaudara,
            'status_anak' => $this->statusAnak,
            'agama' => $this->agama,
            'bahasa_sehari_hari' => $this->bahasaSehariHari,
            'address' => $this->address,
            'rt' => $this->rt,
            'rw' => $this->rw,
            'kelurahan' => $this->kelurahan,
            'kecamatan' => $this->kecamatan,
            'kabupaten_kota' => $this->kabupatenKota,
            'provinsi' => $this->provinsi,
            'kode_pos' => $this->kodePos,
            'tinggi_badan' => $this->tinggiBadan,
            'berat_badan' => $this->beratBadan,
            'lingkar_kepala' => $this->lingkarKepala,
            'golongan_darah' => $this->golonganDarah,
            'riwayat_penyakit' => $this->riwayatPenyakit,
            'berkebutuhan_khusus' => $this->berkebutuhanKhusus,
            'keterangan_kebutuhan_khusus' => $this->keteranganKebutuhanKhusus,
            'father' => $this->father,
            'mother' => $this->mother,
            'guardian' => $this->guardian,
            'siblings' => $this->siblings,
            'consent' => $this->consent,
        ], $this->documents, $this->idempotencyToken);

        return $this->redirectRoute(
            'ppdb.public.success',
            ['reference' => $result['success_reference']],
            navigate: false,
        );
    }

    private function isCompletedSubmission(): bool
    {
        if (! preg_match('/\A[A-Za-z0-9]{64}\z/', $this->idempotencyToken)) {
            return false;
        }

        return PpdbPublicSubmission::query()
            ->where('idempotency_key_hash', hash('sha256', $this->idempotencyToken))
            ->whereNotNull('completed_at')
            ->exists();
    }

    private function isSubmitRateLimited(): bool
    {
        $request = request();
        $sessionIdentifier = $request->hasSession() ? $request->session()->getId() : '';
        $sessionKey = 'ppdb-submit-session:'.hash('sha256', $sessionIdentifier);
        $ipKey = 'ppdb-submit-ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($sessionKey, self::SUBMIT_SESSION_MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($ipKey, self::SUBMIT_IP_MAX_ATTEMPTS)) {
            return true;
        }

        RateLimiter::hit($sessionKey, self::SUBMIT_DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::SUBMIT_DECAY_SECONDS);

        return false;
    }

    public function render(ProspectiveStudentBillGenerationService $billGenerationService): View
    {
        $openAcademicYears = AcademicYear::query()
            ->openForPpdb()
            ->orderBy('start_date')
            ->get(['id', 'year', 'start_date']);

        $configuredGrades = PpdbDefaultClassMapping::query()
            ->join('school_classes', 'school_classes.id', '=', 'ppdb_default_class_mappings.school_class_id')
            ->whereColumn('school_classes.level', 'ppdb_default_class_mappings.class_level')
            ->orderBy('ppdb_default_class_mappings.class_level')
            ->pluck('ppdb_default_class_mappings.class_level')
            ->map(fn (mixed $level): int => (int) $level)
            ->all();

        $gradeGroups = $this->gradeGroups();
        $selectedLevel = SchoolLevel::tryFrom($this->schoolLevel);
        $gradeOptions = $selectedLevel ? $gradeGroups[$selectedLevel->value] : [];
        $selectedYear = $openAcademicYears->firstWhere('id', (int) $this->academicYearId);
        $mappedClass = $this->mappedClassForSelectedGrade();
        $registrationFees = collect();

        if ($selectedYear && $mappedClass) {
            $previewProspect = new ProspectiveStudent([
                'academic_year_id' => $selectedYear->id,
                'school_class_id' => $mappedClass->id,
            ]);
            $registrationFees = $billGenerationService->applicableBillsFor($previewProspect);
        }

        $paymentBankMatches = Bank::query()
            ->where('type', Bank::TYPE_BANK)
            ->where('is_active', true)
            ->where('name', 'BSI')
            ->where('account_number', 'like', '%2018%')
            ->get();
        $paymentBank = $paymentBankMatches->count() === 1
            && filled($paymentBankMatches->first()?->account_number)
            && filled($paymentBankMatches->first()?->account_name)
                ? $paymentBankMatches->first()
                : null;

        return view('livewire.ppdb.public-registration.index', [
            'applicantCategories' => PpdbApplicantCategory::cases(),
            'configuredGrades' => $configuredGrades,
            'documentFields' => $this->documentFields(),
            'gradeGroups' => $gradeGroups,
            'gradeOptions' => $gradeOptions,
            'incomeOptions' => ParentIncomeRange::options(),
            'openAcademicYears' => $openAcademicYears,
            'paymentBank' => $paymentBank,
            'paymentBankAmbiguous' => $paymentBankMatches->count() > 1,
            'registrationFees' => $registrationFees,
            'registrationFeeTotal' => $registrationFees->sum('amount'),
            'selectedGradeLabel' => $gradeOptions[(int) $this->destinationGrade] ?? '-',
            'selectedApplicantCategoryLabel' => PpdbApplicantCategory::tryFrom($this->applicantCategory)?->label() ?? '-',
            'selectedYearLabel' => $selectedYear?->year ?? '-',
            'stepTitles' => $this->stepTitles(),
        ]);
    }

    private function validateCurrentStep(): void
    {
        match ($this->currentStep) {
            1 => $this->validatePaymentStep(),
            2 => $this->validateStudentStep(),
            3 => $this->validateParentStep(),
            4 => $this->validateSiblingStep(),
            5 => $this->validateDocuments(),
            default => null,
        };
    }

    private function validatePaymentStep(): void
    {
        $this->validateDestination();
        $this->validateDocument('bukti_transfer');
    }

    private function validateDocument(string $documentType): void
    {
        $this->validate([
            'documents.'.$documentType => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(2048)],
        ], $this->validationMessages());
    }

    private function validateDocuments(): void
    {
        $this->validate($this->documentRules(), $this->validationMessages());
    }

    private function validateStudentStep(): void
    {
        $this->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birthplace' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'asalSekolah' => ['required', 'string', 'max:255'],
            'nikSiswa' => ['required', 'digits:16'],
            'nomorKk' => ['required', 'digits:16'],
            'nomorAktaKelahiran' => ['nullable', 'string', 'max:100'],
            'anakKe' => ['nullable', 'integer', 'min:1'],
            'jumlahSaudara' => ['nullable', 'integer', 'min:0'],
            'statusAnak' => ['nullable', 'string', 'max:30'],
            'agama' => ['nullable', 'string', 'max:50'],
            'bahasaSehariHari' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:2000'],
            'rt' => ['nullable', 'digits_between:1,3'],
            'rw' => ['nullable', 'digits_between:1,3'],
            'kelurahan' => ['nullable', 'string', 'max:150'],
            'kecamatan' => ['nullable', 'string', 'max:150'],
            'kabupatenKota' => ['nullable', 'string', 'max:150'],
            'provinsi' => ['nullable', 'string', 'max:150'],
            'kodePos' => ['nullable', 'digits:5'],
            'tinggiBadan' => ['required', 'numeric', 'min:0', 'max:250'],
            'beratBadan' => ['required', 'numeric', 'min:0', 'max:200'],
            'lingkarKepala' => ['required', 'numeric', 'min:0', 'max:100'],
            'golonganDarah' => ['required', 'string', 'max:5'],
            'riwayatPenyakit' => ['required', 'string', 'max:2000'],
            'berkebutuhanKhusus' => ['required', Rule::in(['0', '1', 'Ya', 'Tidak'])],
            'keteranganKebutuhanKhusus' => ['nullable', 'required_if:berkebutuhanKhusus,1,Ya', 'string', 'max:2000'],
        ], $this->validationMessages());
    }

    private function validateDestination(): void
    {
        $this->validate([
            'academicYearId' => ['required', 'integer'],
            'schoolLevel' => ['required', Rule::enum(SchoolLevel::class)],
            'destinationGrade' => ['required', 'integer'],
            'applicantCategory' => ['required', Rule::enum(PpdbApplicantCategory::class)],
        ], $this->validationMessages());

        if (! AcademicYear::query()->openForPpdb()->whereKey((int) $this->academicYearId)->exists()) {
            throw ValidationException::withMessages([
                'academicYearId' => 'Tahun ajaran tidak tersedia untuk pendaftaran PPDB.',
            ]);
        }

        $level = SchoolLevel::tryFrom($this->schoolLevel);
        $grade = (int) $this->destinationGrade;

        if (! $level || ! in_array($grade, $level->classLevels(), true)) {
            throw ValidationException::withMessages([
                'destinationGrade' => 'Tingkat tujuan tidak sesuai dengan jenjang sekolah.',
            ]);
        }

        $mappingExists = PpdbDefaultClassMapping::query()
            ->where('class_level', $grade)
            ->whereHas('schoolClass', fn (Builder $query): Builder => $query->where('level', $grade))
            ->exists();

        if (! $mappingExists) {
            throw ValidationException::withMessages([
                'destinationGrade' => 'Kelas default untuk tingkat ini belum dikonfigurasi.',
            ]);
        }
    }

    private function validateParentStep(): void
    {
        $this->validate([
            'father.name' => ['required', 'string', 'max:255'],
            'father.nik' => ['required', 'digits:16'],
            'father.birthplace' => ['required', 'string', 'max:150'],
            'father.birthdate' => ['required', 'date'],
            'father.education_last' => ['required', 'string', 'max:100'],
            'father.email' => ['nullable', 'email', 'max:254'],
            'father.phone' => ['required', 'string', 'max:20', 'regex:/\A[0-9+().\-\s]{8,20}\z/'],
            'father.occupation' => ['required', 'string', 'max:255'],
            'father.income_range' => ['required', Rule::enum(ParentIncomeRange::class)],
            'mother.name' => ['required', 'string', 'max:255'],
            'mother.nik' => ['required', 'digits:16'],
            'mother.birthplace' => ['required', 'string', 'max:150'],
            'mother.birthdate' => ['required', 'date'],
            'mother.education_last' => ['required', 'string', 'max:100'],
            'mother.email' => ['nullable', 'email', 'max:254'],
            'mother.phone' => ['required', 'string', 'max:20', 'regex:/\A[0-9+().\-\s]{8,20}\z/'],
            'mother.occupation' => ['required', 'string', 'max:255'],
            'mother.income_range' => ['required', Rule::enum(ParentIncomeRange::class)],
            'guardian.name' => ['nullable', 'required_with:guardian.nik,guardian.phone,guardian.occupation,guardian.income_range,guardian.email,guardian.education_last', 'string', 'max:255'],
            'guardian.nik' => ['nullable', 'required_with:guardian.name', 'digits:16'],
            'guardian.birthplace' => ['nullable', 'string', 'max:150'],
            'guardian.birthdate' => ['nullable', 'date'],
            'guardian.education_last' => ['nullable', 'required_with:guardian.name', 'string', 'max:100'],
            'guardian.email' => ['nullable', 'required_with:guardian.name', 'email', 'max:254'],
            'guardian.phone' => ['nullable', 'required_with:guardian.name', 'string', 'max:20', 'regex:/\A[0-9+().\-\s]{8,20}\z/'],
            'guardian.occupation' => ['nullable', 'required_with:guardian.name', 'string', 'max:255'],
            'guardian.income_range' => ['nullable', 'required_with:guardian.name', Rule::enum(ParentIncomeRange::class)],
        ], $this->validationMessages());
    }

    private function validateSiblingStep(): void
    {
        $this->validate([
            'siblings' => ['array', 'max:20'],
            'siblings.*.key' => ['required', 'uuid'],
            'siblings.*.full_name' => ['required', 'string', 'max:255'],
            'siblings.*.birthdate' => ['required', 'date', 'before_or_equal:today'],
            'siblings.*.gender' => ['required', Rule::in(['L', 'P'])],
            'siblings.*.education_occupation' => ['required', 'string', 'max:255'],
        ], $this->validationMessages());
    }

    /** @return array<string, mixed> */
    private function submissionRules(): array
    {
        return array_merge([
            'academicYearId' => ['required', 'integer'],
            'schoolLevel' => ['required', Rule::enum(SchoolLevel::class)],
            'destinationGrade' => ['required', 'integer'],
            'applicantCategory' => ['required', Rule::enum(PpdbApplicantCategory::class)],
            'fullName' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birthplace' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'asalSekolah' => ['required', 'string', 'max:255'],
            'nikSiswa' => ['required', 'digits:16'],
            'nomorKk' => ['required', 'digits:16'],
            'nomorAktaKelahiran' => ['nullable', 'string', 'max:100'],
            'anakKe' => ['nullable', 'integer', 'min:1'],
            'jumlahSaudara' => ['nullable', 'integer', 'min:0'],
            'statusAnak' => ['nullable', 'string', 'max:30'],
            'agama' => ['nullable', 'string', 'max:50'],
            'bahasaSehariHari' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:2000'],
            'rt' => ['nullable', 'digits_between:1,3'],
            'rw' => ['nullable', 'digits_between:1,3'],
            'kelurahan' => ['nullable', 'string', 'max:150'],
            'kecamatan' => ['nullable', 'string', 'max:150'],
            'kabupatenKota' => ['nullable', 'string', 'max:150'],
            'provinsi' => ['nullable', 'string', 'max:150'],
            'kodePos' => ['nullable', 'digits:5'],
            'tinggiBadan' => ['required', 'numeric', 'min:0', 'max:250'],
            'beratBadan' => ['required', 'numeric', 'min:0', 'max:200'],
            'lingkarKepala' => ['required', 'numeric', 'min:0', 'max:100'],
            'golonganDarah' => ['required', 'string', 'max:5'],
            'riwayatPenyakit' => ['required', 'string', 'max:2000'],
            'berkebutuhanKhusus' => ['required', Rule::in(['0', '1', 'Ya', 'Tidak'])],
            'keteranganKebutuhanKhusus' => ['nullable', 'required_if:berkebutuhanKhusus,1,Ya', 'string', 'max:2000'],
            'father.name' => ['required', 'string', 'max:255'],
            'father.nik' => ['required', 'digits:16'],
            'father.birthplace' => ['required', 'string', 'max:150'],
            'father.birthdate' => ['required', 'date'],
            'father.education_last' => ['required', 'string', 'max:100'],
            'father.email' => ['nullable', 'email', 'max:254'],
            'father.phone' => ['required', 'string', 'max:20', 'regex:/\A[0-9+().\-\s]{8,20}\z/'],
            'father.occupation' => ['required', 'string', 'max:255'],
            'father.income_range' => ['required', Rule::enum(ParentIncomeRange::class)],
            'mother.name' => ['required', 'string', 'max:255'],
            'mother.nik' => ['required', 'digits:16'],
            'mother.birthplace' => ['required', 'string', 'max:150'],
            'mother.birthdate' => ['required', 'date'],
            'mother.education_last' => ['required', 'string', 'max:100'],
            'mother.email' => ['nullable', 'email', 'max:254'],
            'mother.phone' => ['required', 'string', 'max:20', 'regex:/\A[0-9+().\-\s]{8,20}\z/'],
            'mother.occupation' => ['required', 'string', 'max:255'],
            'mother.income_range' => ['required', Rule::enum(ParentIncomeRange::class)],
            'guardian.name' => ['nullable', 'required_with:guardian.nik,guardian.phone,guardian.occupation,guardian.income_range,guardian.email,guardian.education_last', 'string', 'max:255'],
            'guardian.nik' => ['nullable', 'required_with:guardian.name', 'digits:16'],
            'guardian.birthplace' => ['nullable', 'string', 'max:150'],
            'guardian.birthdate' => ['nullable', 'date'],
            'guardian.education_last' => ['nullable', 'required_with:guardian.name', 'string', 'max:100'],
            'guardian.email' => ['nullable', 'required_with:guardian.name', 'email', 'max:254'],
            'guardian.phone' => ['nullable', 'required_with:guardian.name', 'string', 'max:20', 'regex:/\A[0-9+().\-\s]{8,20}\z/'],
            'guardian.occupation' => ['nullable', 'required_with:guardian.name', 'string', 'max:255'],
            'guardian.income_range' => ['nullable', 'required_with:guardian.name', Rule::enum(ParentIncomeRange::class)],
            'siblings' => ['array', 'max:20'],
            'siblings.*.key' => ['required', 'uuid'],
            'siblings.*.full_name' => ['required', 'string', 'max:255'],
            'siblings.*.birthdate' => ['required', 'date', 'before_or_equal:today'],
            'siblings.*.gender' => ['required', Rule::in(['L', 'P'])],
            'siblings.*.education_occupation' => ['required', 'string', 'max:255'],
            'consent' => ['accepted'],
        ], $this->documentRules());
    }

    /** @return array<string, mixed> */
    private function documentRules(): array
    {
        $rules = [
            'documents' => ['required', 'array:'.implode(',', PpdbDocumentType::values())],
        ];

        foreach (PpdbDocumentType::values() as $documentType) {
            $rules['documents.'.$documentType] = [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(2048),
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function validationMessages(): array
    {
        $documentLabels = [
            'bukti_transfer' => 'Bukti transfer',
            'kk' => 'Kartu Keluarga (KK)',
            'kartu_keluarga' => 'Kartu Keluarga (KK)',
            'akta_lahir' => 'Akta Kelahiran',
            'akta_kelahiran' => 'Akta Kelahiran',
            'ktp_ayah' => 'KTP Ayah',
            'ktp_ibu' => 'KTP Ibu',
            'ijazah_rapor' => 'Ijazah/Rapor',
            'pas_foto' => 'Pas foto siswa',
        ];

        $messages = [
            'required' => ':attribute wajib diisi.',
            'required_with' => ':attribute wajib diisi jika data wali digunakan.',
            'required_without' => ':attribute wajib diisi.',
            'integer' => ':attribute tidak valid.',
            'numeric' => ':attribute harus berupa angka.',
            'string' => ':attribute harus berupa teks.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'before' => ':attribute harus sebelum hari ini.',
            'before_or_equal' => ':attribute tidak boleh melewati hari ini.',
            'after' => ':attribute harus setelah tanggal yang ditentukan.',
            'after_or_equal' => ':attribute harus setelah atau sama dengan tanggal yang ditentukan.',
            'max' => ':attribute melebihi batas yang diperbolehkan.',
            'min' => ':attribute kurang dari batas yang diperbolehkan.',
            'regex' => ':attribute memiliki format yang tidak valid.',
            'enum' => ':attribute tidak valid.',
            'in' => ':attribute tidak valid.',
            'exists' => ':attribute tidak valid.',
            'uuid' => 'Data saudara kandung tidak valid.',
            'file' => ':attribute wajib diunggah.',
            'image' => 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.',
            'mimes' => 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.',
            'mimetypes' => 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.',
            'max.file' => 'Ukuran file maksimal 2 MB.',
            'max.string' => ':attribute maksimal :max karakter.',
            'accepted' => 'Persetujuan wajib dicentang.',
            'boolean' => ':attribute tidak valid.',
            'array' => ':attribute tidak valid.',
            'academicYearId.required' => 'Tahun ajaran wajib dipilih.',
            'academicYearId.integer' => 'Tahun ajaran tidak valid.',
            'schoolLevel.required' => 'Jenjang sekolah wajib dipilih.',
            'schoolLevel.enum' => 'Jenjang sekolah tidak valid.',
            'destinationGrade.required' => 'Tingkat tujuan wajib dipilih.',
            'destinationGrade.integer' => 'Tingkat tujuan tidak valid.',
            'applicantCategory.required' => 'Kategori pendaftar wajib dipilih.',
            'applicantCategory.enum' => 'Kategori pendaftar tidak valid.',
            'fullName.required' => 'Nama lengkap calon siswa wajib diisi.',
            'fullName.string' => 'Nama lengkap calon siswa tidak valid.',
            'fullName.max' => 'Nama lengkap calon siswa maksimal 255 karakter.',
            'nickname.string' => 'Nama panggilan tidak valid.',
            'nickname.max' => 'Nama panggilan maksimal 100 karakter.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'birthplace.required' => 'Tempat lahir wajib diisi.',
            'birthplace.string' => 'Tempat lahir tidak valid.',
            'birthdate.required' => 'Tanggal lahir wajib diisi.',
            'birthdate.date' => 'Tanggal lahir harus berupa tanggal yang valid.',
            'birthdate.before' => 'Tanggal lahir harus sebelum hari ini.',
            'asalSekolah.required' => 'Asal sekolah wajib diisi.',
            'asalSekolah.string' => 'Asal sekolah tidak valid.',
            'asalSekolah.max' => 'Asal sekolah maksimal 255 karakter.',
            'address.required' => 'Alamat lengkap wajib diisi.',
            'address.string' => 'Alamat lengkap tidak valid.',
            'address.max' => 'Alamat lengkap maksimal 2000 karakter.',
            'father.name.required' => 'Nama Ayah wajib diisi.',
            'father.name.string' => 'Nama Ayah tidak valid.',
            'father.name.max' => 'Nama Ayah maksimal 255 karakter.',
            'father.phone.required' => 'Nomor telepon/WhatsApp Ayah wajib diisi.',
            'father.phone.string' => 'Nomor telepon/WhatsApp Ayah tidak valid.',
            'father.phone.regex' => 'Nomor telepon/WhatsApp Ayah memiliki format yang tidak valid.',
            'father.phone.max' => 'Nomor telepon/WhatsApp Ayah maksimal 20 karakter.',
            'father.occupation.required' => 'Pekerjaan Ayah wajib diisi.',
            'father.occupation.string' => 'Pekerjaan Ayah tidak valid.',
            'father.occupation.max' => 'Pekerjaan Ayah maksimal 255 karakter.',
            'father.income_range.required' => 'Rentang penghasilan Ayah wajib dipilih.',
            'father.income_range.enum' => 'Rentang penghasilan Ayah tidak valid.',
            'mother.name.required' => 'Nama Ibu wajib diisi.',
            'mother.name.string' => 'Nama Ibu tidak valid.',
            'mother.name.max' => 'Nama Ibu maksimal 255 karakter.',
            'mother.phone.required' => 'Nomor telepon/WhatsApp Ibu wajib diisi.',
            'mother.phone.string' => 'Nomor telepon/WhatsApp Ibu tidak valid.',
            'mother.phone.regex' => 'Nomor telepon/WhatsApp Ibu memiliki format yang tidak valid.',
            'mother.phone.max' => 'Nomor telepon/WhatsApp Ibu maksimal 20 karakter.',
            'mother.occupation.required' => 'Pekerjaan Ibu wajib diisi.',
            'mother.occupation.string' => 'Pekerjaan Ibu tidak valid.',
            'mother.occupation.max' => 'Pekerjaan Ibu maksimal 255 karakter.',
            'mother.income_range.required' => 'Rentang penghasilan Ibu wajib dipilih.',
            'mother.income_range.enum' => 'Rentang penghasilan Ibu tidak valid.',
            'guardian.name.required_with' => 'Nama Wali wajib diisi jika data wali digunakan.',
            'guardian.name.string' => 'Nama Wali tidak valid.',
            'guardian.name.max' => 'Nama Wali maksimal 255 karakter.',
            'guardian.phone.required_with' => 'Nomor telepon/WhatsApp Wali wajib diisi jika data wali digunakan.',
            'guardian.phone.string' => 'Nomor telepon/WhatsApp Wali tidak valid.',
            'guardian.phone.regex' => 'Nomor telepon/WhatsApp Wali memiliki format yang tidak valid.',
            'guardian.phone.max' => 'Nomor telepon/WhatsApp Wali maksimal 20 karakter.',
            'guardian.occupation.required_with' => 'Pekerjaan Wali wajib diisi jika data wali digunakan.',
            'guardian.occupation.string' => 'Pekerjaan Wali tidak valid.',
            'guardian.occupation.max' => 'Pekerjaan Wali maksimal 255 karakter.',
            'guardian.income_range.required_with' => 'Rentang penghasilan Wali wajib dipilih jika data wali digunakan.',
            'guardian.income_range.enum' => 'Rentang penghasilan Wali tidak valid.',
            'siblings.array' => 'Data saudara kandung tidak valid.',
            'siblings.max' => 'Jumlah saudara kandung melebihi batas yang diperbolehkan.',
            'siblings.*.key.required' => 'Data saudara kandung tidak valid.',
            'siblings.*.key.uuid' => 'Data saudara kandung tidak valid.',
            'siblings.*.full_name.required' => 'Nama lengkap saudara kandung wajib diisi.',
            'siblings.*.full_name.string' => 'Nama lengkap saudara kandung tidak valid.',
            'siblings.*.full_name.max' => 'Nama lengkap saudara kandung maksimal 255 karakter.',
            'siblings.*.birthdate.required' => 'Tanggal lahir saudara kandung wajib diisi.',
            'siblings.*.birthdate.date' => 'Tanggal lahir saudara kandung harus berupa tanggal yang valid.',
            'siblings.*.birthdate.before_or_equal' => 'Tanggal lahir saudara kandung tidak boleh melewati hari ini.',
            'siblings.*.gender.required' => 'Jenis kelamin saudara kandung wajib dipilih.',
            'siblings.*.gender.in' => 'Jenis kelamin saudara kandung tidak valid.',
            'siblings.*.education_occupation.required' => 'Pendidikan atau pekerjaan saudara kandung wajib diisi.',
            'siblings.*.education_occupation.string' => 'Pendidikan atau pekerjaan saudara kandung tidak valid.',
            'siblings.*.education_occupation.max' => 'Pendidikan atau pekerjaan saudara kandung maksimal 255 karakter.',
            'nikSiswa.required' => 'NIK siswa wajib diisi.',
            'nikSiswa.digits' => 'NIK siswa harus terdiri dari tepat 16 digit.',
            'nomorKk.required' => 'Nomor KK wajib diisi.',
            'nomorKk.digits' => 'Nomor KK harus terdiri dari tepat 16 digit.',
            'father.nik.required' => 'NIK Ayah wajib diisi.',
            'father.nik.digits' => 'NIK Ayah harus terdiri dari tepat 16 digit.',
            'mother.nik.required' => 'NIK Ibu wajib diisi.',
            'mother.nik.digits' => 'NIK Ibu harus terdiri dari tepat 16 digit.',
            'guardian.nik.digits' => 'NIK Wali harus terdiri dari tepat 16 digit.',
            'guardian.nik.required_with' => 'NIK Wali wajib diisi jika data wali digunakan.',
            'keteranganKebutuhanKhusus.required_if' => 'Keterangan kebutuhan khusus wajib diisi jika berkebutuhan khusus.',
            'riwayatPenyakit.required' => 'Riwayat penyakit wajib diisi. Isi dengan "Tidak Ada" jika tidak ada.',
            'tinggiBadan.required' => 'Tinggi badan wajib diisi.',
            'beratBadan.required' => 'Berat badan wajib diisi.',
            'lingkarKepala.required' => 'Lingkar kepala wajib diisi.',
            'golonganDarah.required' => 'Golongan darah wajib diisi.',
            'berkebutuhanKhusus.required' => 'Pilih Ya/Tidak untuk berkebutuhan khusus.',
            'consent.accepted' => 'Persetujuan wajib dicentang.',
            'consent.boolean' => 'Persetujuan wajib dicentang.',
            'submission.required' => 'Pengajuan pendaftaran wajib diproses.',
            'documents.required' => 'Dokumen pendaftaran wajib diunggah.',
            'documents.array' => 'Dokumen pendaftaran tidak valid.',
        ];

        foreach ($documentLabels as $key => $label) {
            $messages['documents.'.$key.'.required'] = $label.' wajib diunggah.';
            $messages['documents.'.$key.'.file'] = $label.' wajib diunggah.';
            $messages['documents.'.$key.'.uploaded'] = 'File gagal diunggah. Silakan coba lagi.';
            $messages['documents.'.$key.'.mimes'] = 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.';
            $messages['documents.'.$key.'.mimetypes'] = 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.';
            $messages['documents.'.$key.'.image'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
            $messages['documents.'.$key.'.max'] = 'Ukuran file maksimal 2 MB.';
            $messages['documents.'.$key.'.max.file'] = 'Ukuran file maksimal 2 MB.';
        }

        $messages['documents.akta_lahir.required'] = 'Akta Kelahiran wajib diunggah.';
        $messages['documents.akta_lahir.file'] = 'Akta Kelahiran wajib diunggah.';
        $messages['documents.akta_lahir.mimes'] = 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.';
        $messages['documents.akta_lahir.mimetypes'] = 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.';
        $messages['documents.akta_lahir.max'] = 'Ukuran file maksimal 2 MB.';
        $messages['documents.akta_lahir.max.file'] = 'Ukuran file maksimal 2 MB.';
        $messages['documents.akta_kelahiran.required'] = 'Akta Kelahiran wajib diunggah.';
        $messages['documents.akta_kelahiran.file'] = 'Akta Kelahiran wajib diunggah.';
        $messages['documents.akta_kelahiran.mimes'] = 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.';
        $messages['documents.akta_kelahiran.mimetypes'] = 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.';
        $messages['documents.akta_kelahiran.max'] = 'Ukuran file maksimal 2 MB.';
        $messages['documents.akta_kelahiran.max.file'] = 'Ukuran file maksimal 2 MB.';
        $messages['documents.kartu_keluarga.required'] = 'Kartu Keluarga (KK) wajib diunggah.';
        $messages['documents.kartu_keluarga.file'] = 'Kartu Keluarga (KK) wajib diunggah.';
        $messages['documents.kartu_keluarga.mimes'] = 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.';
        $messages['documents.kartu_keluarga.mimetypes'] = 'Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.';
        $messages['documents.kartu_keluarga.max'] = 'Ukuran file maksimal 2 MB.';
        $messages['documents.kk.required'] = 'Kartu Keluarga (KK) wajib diunggah.';
        $messages['documents.kk.file'] = 'Kartu Keluarga (KK) wajib diunggah.';
        $messages['documents.bukti_transfer.required'] = 'Bukti transfer wajib diunggah.';
        $messages['documents.bukti_transfer.file'] = 'Bukti transfer wajib diunggah.';
        $messages['documents.ktp_ayah.required'] = 'KTP Ayah wajib diunggah.';
        $messages['documents.ktp_ayah.file'] = 'KTP Ayah wajib diunggah.';
        $messages['documents.ktp_ibu.required'] = 'KTP Ibu wajib diunggah.';
        $messages['documents.ktp_ibu.file'] = 'KTP Ibu wajib diunggah.';
        $messages['documents.ijazah_rapor.required'] = 'Ijazah/Rapor wajib diunggah.';
        $messages['documents.ijazah_rapor.file'] = 'Ijazah/Rapor wajib diunggah.';
        $messages['documents.pas_foto.required'] = 'Pas foto siswa wajib diunggah.';
        $messages['documents.pas_foto.file'] = 'Pas foto siswa wajib diunggah.';
        $messages['documents.pas_foto.mimes'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
        $messages['documents.pas_foto.mimetypes'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
        $messages['documents.pas_foto.max'] = 'Ukuran file maksimal 2 MB.';

        if (is_array($this->siblings)) {
            foreach (array_keys($this->siblings) as $position => $index) {
                $i = $position + 1;
                $messages['siblings.'.$index.'.full_name.required'] = 'Nama lengkap saudara kandung ke-'.$i.' wajib diisi.';
                $messages['siblings.'.$index.'.birthdate.required'] = 'Tanggal lahir saudara kandung ke-'.$i.' wajib diisi.';
                $messages['siblings.'.$index.'.gender.required'] = 'Jenis kelamin saudara kandung ke-'.$i.' wajib dipilih.';
                $messages['siblings.'.$index.'.education_occupation.required'] = 'Pendidikan atau pekerjaan saudara kandung ke-'.$i.' wajib diisi.';
                $messages['siblings.'.$index.'.key.required'] = 'Data saudara kandung ke-'.$i.' tidak valid.';
                $messages['siblings.'.$index.'.key.uuid'] = 'Data saudara kandung ke-'.$i.' tidak valid.';
            }
        }

        return $messages;
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        $attributes = [
            'academicYearId' => 'Tahun ajaran',
            'schoolLevel' => 'Jenjang sekolah',
            'destinationGrade' => 'Tingkat tujuan',
            'applicantCategory' => 'Kategori pendaftar',
            'fullName' => 'Nama lengkap calon siswa',
            'nickname' => 'Nama panggilan',
            'gender' => 'Jenis kelamin',
            'birthplace' => 'Tempat lahir',
            'birthdate' => 'Tanggal lahir',
            'asalSekolah' => 'Asal sekolah',
            'nikSiswa' => 'NIK siswa',
            'nomorKk' => 'Nomor KK',
            'nomorAktaKelahiran' => 'Nomor akta kelahiran',
            'anakKe' => 'Anak ke-',
            'jumlahSaudara' => 'Jumlah saudara',
            'statusAnak' => 'Status anak',
            'agama' => 'Agama',
            'bahasaSehariHari' => 'Bahasa sehari-hari',
            'address' => 'Alamat lengkap',
            'rt' => 'RT',
            'rw' => 'RW',
            'kelurahan' => 'Kelurahan / Desa',
            'kecamatan' => 'Kecamatan',
            'kabupatenKota' => 'Kabupaten / Kota',
            'provinsi' => 'Provinsi',
            'kodePos' => 'Kode pos',
            'tinggiBadan' => 'Tinggi badan',
            'beratBadan' => 'Berat badan',
            'lingkarKepala' => 'Lingkar kepala',
            'golonganDarah' => 'Golongan darah',
            'riwayatPenyakit' => 'Riwayat penyakit',
            'berkebutuhanKhusus' => 'Berkebutuhan khusus',
            'keteranganKebutuhanKhusus' => 'Keterangan kebutuhan khusus',
            'father.name' => 'Nama Ayah',
            'father.nik' => 'NIK Ayah',
            'father.birthplace' => 'Tempat lahir Ayah',
            'father.birthdate' => 'Tanggal lahir Ayah',
            'father.education_last' => 'Pendidikan terakhir Ayah',
            'father.email' => 'Email Ayah',
            'father.phone' => 'Nomor telepon/WhatsApp Ayah',
            'father.occupation' => 'Pekerjaan Ayah',
            'father.income_range' => 'Rentang penghasilan Ayah',
            'mother.name' => 'Nama Ibu',
            'mother.nik' => 'NIK Ibu',
            'mother.birthplace' => 'Tempat lahir Ibu',
            'mother.birthdate' => 'Tanggal lahir Ibu',
            'mother.education_last' => 'Pendidikan terakhir Ibu',
            'mother.email' => 'Email Ibu',
            'mother.phone' => 'Nomor telepon/WhatsApp Ibu',
            'mother.occupation' => 'Pekerjaan Ibu',
            'mother.income_range' => 'Rentang penghasilan Ibu',
            'guardian.name' => 'Nama Wali',
            'guardian.nik' => 'NIK Wali',
            'guardian.birthplace' => 'Tempat lahir Wali',
            'guardian.birthdate' => 'Tanggal lahir Wali',
            'guardian.education_last' => 'Pendidikan terakhir Wali',
            'guardian.email' => 'Email Wali',
            'guardian.phone' => 'Nomor telepon/WhatsApp Wali',
            'guardian.occupation' => 'Pekerjaan Wali',
            'guardian.income_range' => 'Rentang penghasilan Wali',
            'siblings' => 'Daftar saudara kandung',
            'siblings.*.key' => 'Data saudara kandung',
            'siblings.*.full_name' => 'Nama lengkap saudara kandung',
            'siblings.*.birthdate' => 'Tanggal lahir saudara kandung',
            'siblings.*.gender' => 'Jenis kelamin saudara kandung',
            'siblings.*.education_occupation' => 'Pendidikan atau pekerjaan saudara kandung',
            'consent' => 'Persetujuan',
            'submission' => 'Pengajuan pendaftaran',
            'documents' => 'Dokumen pendaftaran',
            'documents.bukti_transfer' => 'Bukti transfer',
            'documents.kk' => 'Kartu Keluarga (KK)',
            'documents.kartu_keluarga' => 'Kartu Keluarga (KK)',
            'documents.akta_lahir' => 'Akta Kelahiran',
            'documents.akta_kelahiran' => 'Akta Kelahiran',
            'documents.ktp_ayah' => 'KTP Ayah',
            'documents.ktp_ibu' => 'KTP Ibu',
            'documents.ijazah_rapor' => 'Ijazah/Rapor',
            'documents.pas_foto' => 'Pas foto siswa',
        ];

        if (is_array($this->siblings)) {
            foreach (array_keys($this->siblings) as $position => $index) {
                $i = $position + 1;
                $attributes['siblings.'.$index.'.full_name'] = 'Nama lengkap saudara kandung ke-'.$i;
                $attributes['siblings.'.$index.'.birthdate'] = 'Tanggal lahir saudara kandung ke-'.$i;
                $attributes['siblings.'.$index.'.gender'] = 'Jenis kelamin saudara kandung ke-'.$i;
                $attributes['siblings.'.$index.'.education_occupation'] = 'Pendidikan atau pekerjaan saudara kandung ke-'.$i;
                $attributes['siblings.'.$index.'.key'] = 'Data saudara kandung ke-'.$i;
            }
        }

        return $attributes;
    }

    private function mappedClassForSelectedGrade(): ?SchoolClass
    {
        if ($this->destinationGrade === '') {
            return null;
        }

        $grade = (int) $this->destinationGrade;

        return PpdbDefaultClassMapping::query()
            ->where('class_level', $grade)
            ->whereHas('schoolClass', fn (Builder $query): Builder => $query->where('level', $grade))
            ->with('schoolClass')
            ->first()?->schoolClass;
    }

    /** @return array<string, array<int, string>> */
    private function gradeGroups(): array
    {
        return [
            SchoolLevel::TK->value => [-3 => 'KB', -2 => 'TK A', -1 => 'TK B'],
            SchoolLevel::SD->value => collect(range(1, 6))->mapWithKeys(fn (int $grade): array => [$grade => 'Kelas '.$grade])->all(),
            SchoolLevel::SMP->value => collect(range(7, 9))->mapWithKeys(fn (int $grade): array => [$grade => 'Kelas '.$grade])->all(),
            SchoolLevel::SMA->value => collect(range(10, 12))->mapWithKeys(fn (int $grade): array => [$grade => 'Kelas '.$grade])->all(),
        ];
    }

    /** @return array<int, string> */
    private function stepTitles(): array
    {
        return [
            1 => 'Bukti Pembayaran',
            2 => 'Data Calon Siswa',
            3 => 'Data Orang Tua',
            4 => 'Data Saudara Kandung',
            5 => 'Dokumen',
            6 => 'Konfirmasi',
        ];
    }

    /** @return array<string, string> */
    private function documentFields(): array
    {
        return [
            'kartu_keluarga' => 'Kartu Keluarga (KK)',
            'akta_kelahiran' => 'Akta Kelahiran',
            'ktp_ayah' => 'KTP Ayah',
            'ktp_ibu' => 'KTP Ibu',
            'pas_foto' => 'Pas Foto Siswa',
        ];
    }
}
