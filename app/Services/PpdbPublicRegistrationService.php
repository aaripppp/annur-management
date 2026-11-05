<?php

namespace App\Services;

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\PpdbDocumentType;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PpdbAcademicYearSetting;
use App\Models\PpdbDefaultClassMapping;
use App\Models\PpdbDocument;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\PpdbPublicSubmission;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PpdbPublicRegistrationService
{
    private const MAX_CONCURRENCY_ATTEMPTS = 3;

    public const CONSENT_POLICY_VERSION = 'ppdb-2026-10-29-v1';

    public const DOCUMENT_DISK = 'local';

    public function __construct(
        private ProspectiveStudentRegistrationNumberGenerator $registrationNumberGenerator,
        private ProspectiveStudentBillGenerationService $billGenerationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile>  $documents
     * @return array{registration_number: string, success_reference: string}
     */
    public function register(array $data, array $documents, string $idempotencyToken): array
    {
        $this->validateIdempotencyToken($idempotencyToken);

        $idempotencyHash = hash('sha256', $idempotencyToken);
        $successReference = hash_hmac('sha256', 'ppdb-success:'.$idempotencyToken, (string) config('app.key'));
        $successReferenceHash = hash('sha256', $successReference);

        $completed = PpdbPublicSubmission::query()
            ->where('idempotency_key_hash', $idempotencyHash)
            ->whereNotNull('completed_at')
            ->first();

        if ($completed) {
            return $this->result($completed, $successReference);
        }

        $validated = $this->validateSubmission($data, $documents);
        for ($attempt = 1; $attempt <= self::MAX_CONCURRENCY_ATTEMPTS; $attempt++) {
            $storedPaths = [];

            try {
                return DB::transaction(function () use (
                    $validated,
                    $documents,
                    $idempotencyHash,
                    $successReference,
                    $successReferenceHash,
                    &$storedPaths,
                ): array {
                    DB::table('ppdb_public_submissions')->insertOrIgnore([
                        'idempotency_key_hash' => $idempotencyHash,
                        'success_reference_hash' => $successReferenceHash,
                        'expires_at' => now()->addDay(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $submission = PpdbPublicSubmission::query()
                        ->where('idempotency_key_hash', $idempotencyHash)
                        ->lockForUpdate()
                        ->first();

                    if (! $submission) {
                        throw new RuntimeException('Submission PPDB tidak dapat disiapkan.');
                    }

                    if ($submission->completed_at !== null) {
                        return $this->result($submission, $successReference);
                    }

                    $academicYear = AcademicYear::query()
                        ->whereKey($validated['academic_year_id'])
                        ->lockForUpdate()
                        ->first();

                    $yearSetting = PpdbAcademicYearSetting::query()
                        ->where('academic_year_id', $validated['academic_year_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $academicYear || ! $yearSetting?->is_open) {
                        throw ValidationException::withMessages([
                            'academicYearId' => 'Tahun ajaran tidak tersedia untuk pendaftaran PPDB.',
                        ]);
                    }

                    $grade = (int) $validated['class_level'];
                    $schoolLevel = SchoolLevel::from($validated['school_level']);

                    if (! in_array($grade, $schoolLevel->classLevels(), true)) {
                        throw ValidationException::withMessages([
                            'destinationGrade' => 'Tingkat tujuan tidak sesuai dengan jenjang sekolah.',
                        ]);
                    }

                    $mapping = PpdbDefaultClassMapping::query()
                        ->where('class_level', $grade)
                        ->lockForUpdate()
                        ->first();

                    if (! $mapping) {
                        throw ValidationException::withMessages([
                            'destinationGrade' => 'Kelas default untuk tingkat ini belum dikonfigurasi.',
                        ]);
                    }

                    $schoolClass = SchoolClass::query()
                        ->whereKey($mapping->school_class_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $schoolClass || (int) $schoolClass->level !== $grade) {
                        throw ValidationException::withMessages([
                            'destinationGrade' => 'Konfigurasi kelas default tidak sesuai dengan tingkat tujuan.',
                        ]);
                    }

                    $startYear = (int) substr($academicYear->year, 0, 4);
                    $prospectiveStudent = ProspectiveStudent::query()->create([
                        'registration_number' => $this->registrationNumberGenerator->next($startYear),
                        'nama_lengkap' => trim($validated['full_name']),
                        'nama_panggilan' => $this->nullableString($validated['nickname']),
                        'jenis_kelamin' => $validated['gender'],
                        'nama_orang_tua' => trim($validated['father']['name']),
                        'no_telp_orang_tua' => $this->nullableString($validated['father']['phone']),
                        'alamat' => trim($validated['address']),
                        'applicant_category' => $validated['applicant_category'],
                        'academic_year_id' => $academicYear->id,
                        'school_class_id' => $schoolClass->id,
                        'status' => ProspectiveStudentStatus::Registered,
                        'created_by' => null,
                    ]);

                    $this->billGenerationService->generateFor($prospectiveStudent);

                    $berkebutuhanKhusus = $validated['berkebutuhan_khusus'] ?? null;
                    if (is_bool($berkebutuhanKhusus)) {
                        $berkebutuhanKhusus = $berkebutuhanKhusus ? '1' : '0';
                    } elseif (is_string($berkebutuhanKhusus)) {
                        $berkebutuhanKhusus = in_array(strtolower($berkebutuhanKhusus), ['ya', 'true', '1'], true) ? '1' : (in_array(strtolower($berkebutuhanKhusus), ['tidak', 'false', '0'], true) ? '0' : $berkebutuhanKhusus);
                    }

                    PpdbProfile::query()->create([
                        'prospective_student_id' => $prospectiveStudent->id,
                        'tempat_lahir' => trim($validated['birthplace']),
                        'tanggal_lahir' => $validated['birthdate'],
                        'asal_sekolah' => trim($validated['asal_sekolah']),
                        'nik_siswa' => $this->nullableString($validated['nik_siswa'] ?? null),
                        'nomor_kk' => $this->nullableString($validated['nomor_kk'] ?? null),
                        'nomor_akta_kelahiran' => $this->nullableString($validated['nomor_akta_kelahiran'] ?? null),
                        'anak_ke' => $this->nullableInt($validated['anak_ke'] ?? null),
                        'jumlah_saudara' => $this->nullableInt($validated['jumlah_saudara'] ?? null),
                        'status_anak' => $this->nullableString($validated['status_anak'] ?? null),
                        'agama' => $this->nullableString($validated['agama'] ?? null),
                        'bahasa_sehari_hari' => $this->nullableString($validated['bahasa_sehari_hari'] ?? null),
                        'rt' => $this->nullableString($validated['rt'] ?? null),
                        'rw' => $this->nullableString($validated['rw'] ?? null),
                        'kelurahan' => $this->nullableString($validated['kelurahan'] ?? null),
                        'kecamatan' => $this->nullableString($validated['kecamatan'] ?? null),
                        'kabupaten_kota' => $this->nullableString($validated['kabupaten_kota'] ?? null),
                        'provinsi' => $this->nullableString($validated['provinsi'] ?? null),
                        'kode_pos' => $this->nullableString($validated['kode_pos'] ?? null),
                        'tinggi_badan' => $this->nullableNumeric($validated['tinggi_badan'] ?? null),
                        'berat_badan' => $this->nullableNumeric($validated['berat_badan'] ?? null),
                        'lingkar_kepala' => $this->nullableNumeric($validated['lingkar_kepala'] ?? null),
                        'golongan_darah' => $this->nullableString($validated['golongan_darah'] ?? null),
                        'riwayat_penyakit' => $this->nullableString($validated['riwayat_penyakit'] ?? null),
                        'berkebutuhan_khusus' => $berkebutuhanKhusus === '1' || $berkebutuhanKhusus === 1 || $berkebutuhanKhusus === true ? true : ($berkebutuhanKhusus === '0' || $berkebutuhanKhusus === 0 || $berkebutuhanKhusus === false ? false : null),
                        'keterangan_kebutuhan_khusus' => $this->nullableString($validated['keterangan_kebutuhan_khusus'] ?? null),
                    ]);

                    $this->createParent($prospectiveStudent, 'father', $validated['father']);
                    $this->createParent($prospectiveStudent, 'mother', $validated['mother']);

                    if ($this->hasParentData($validated['guardian'])) {
                        $this->createParent($prospectiveStudent, 'guardian', $validated['guardian']);
                    }

                    foreach (array_values($validated['siblings']) as $index => $sibling) {
                        PpdbSibling::query()->create([
                            'prospective_student_id' => $prospectiveStudent->id,
                            'sort_order' => $index + 1,
                            'nama_lengkap' => trim($sibling['full_name']),
                            'tanggal_lahir' => $sibling['birthdate'],
                            'jenis_kelamin' => $sibling['gender'],
                            'pendidikan_pekerjaan' => trim($sibling['education_occupation']),
                        ]);
                    }

                    foreach ($documents as $documentType => $file) {
                        $storedPath = $this->storeDocument($documentType, $file);
                        $storedPaths[] = $storedPath;

                        PpdbDocument::query()->create([
                            'prospective_student_id' => $prospectiveStudent->id,
                            'document_type' => $documentType,
                            'disk' => self::DOCUMENT_DISK,
                            'path' => $storedPath,
                            'original_name' => $this->sanitizeOriginalFilename($file->getClientOriginalName()),
                            'mime_type' => (string) $file->getMimeType(),
                            'size_bytes' => $file->getSize(),
                            'uploaded_by' => null,
                        ]);
                    }

                    $submission->update([
                        'prospective_student_id' => $prospectiveStudent->id,
                        'consented_at' => now(),
                        'consent_policy_version' => self::CONSENT_POLICY_VERSION,
                        'completed_at' => now(),
                        'expires_at' => now()->addDay(),
                    ]);

                    return [
                        'registration_number' => $prospectiveStudent->registration_number,
                        'success_reference' => $successReference,
                    ];
                });
            } catch (Throwable $exception) {
                if ($storedPaths !== []) {
                    if (Storage::disk(self::DOCUMENT_DISK)->delete($storedPaths)) {
                        foreach ($storedPaths as $storedPath) {
                            app(PpdbDocumentDirectoryCleaner::class)->removeIfEmpty(self::DOCUMENT_DISK, $storedPath);
                        }
                    }
                }

                if ($attempt < self::MAX_CONCURRENCY_ATTEMPTS && $this->isDeadlock($exception)) {
                    continue;
                }

                throw $exception;
            }
        }

        throw new RuntimeException('Pendaftaran PPDB gagal diproses setelah beberapa percobaan.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile>  $documents
     * @return array<string, mixed>
     */
    private function validateSubmission(array $data, array $documents): array
    {
        $documentTypes = PpdbDocumentType::values();
        $documentRules = [
            'documents' => ['required', 'array:'.implode(',', $documentTypes)],
        ];

        foreach ($documentTypes as $documentType) {
            $documentRules['documents.'.$documentType] = [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(2048),
            ];
        }

        $payload = array_merge($data, ['documents' => $documents]);

        return Validator::make($payload, array_merge([
            'academic_year_id' => ['required', 'integer'],
            'school_level' => ['required', Rule::enum(SchoolLevel::class)],
            'class_level' => ['required', 'integer'],
            'applicant_category' => ['required', Rule::enum(PpdbApplicantCategory::class)],
            'full_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birthplace' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'asal_sekolah' => ['required', 'string', 'max:255'],
            'nik_siswa' => ['required', 'digits:16'],
            'nomor_kk' => ['required', 'digits:16'],
            'nomor_akta_kelahiran' => ['nullable', 'string', 'max:100'],
            'anak_ke' => ['nullable', 'integer', 'min:1'],
            'jumlah_saudara' => ['nullable', 'integer', 'min:0'],
            'status_anak' => ['nullable', 'string', 'max:30'],
            'agama' => ['nullable', 'string', 'max:50'],
            'bahasa_sehari_hari' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:2000'],
            'rt' => ['nullable', 'digits_between:1,3'],
            'rw' => ['nullable', 'digits_between:1,3'],
            'kelurahan' => ['nullable', 'string', 'max:150'],
            'kecamatan' => ['nullable', 'string', 'max:150'],
            'kabupaten_kota' => ['nullable', 'string', 'max:150'],
            'provinsi' => ['nullable', 'string', 'max:150'],
            'kode_pos' => ['nullable', 'digits:5'],
            'tinggi_badan' => ['required', 'numeric', 'min:0', 'max:250'],
            'berat_badan' => ['required', 'numeric', 'min:0', 'max:200'],
            'lingkar_kepala' => ['required', 'numeric', 'min:0', 'max:100'],
            'golongan_darah' => ['required', 'string', 'max:5'],
            'riwayat_penyakit' => ['required', 'string', 'max:2000'],
            'berkebutuhan_khusus' => ['required', Rule::in(['0', '1', true, false, 'Ya', 'Tidak'])],
            'keterangan_kebutuhan_khusus' => ['nullable', 'required_if:berkebutuhan_khusus,1,true,Ya', 'string', 'max:2000'],
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
            'siblings.*.full_name' => ['required', 'string', 'max:255'],
            'siblings.*.birthdate' => ['required', 'date', 'before_or_equal:today'],
            'siblings.*.gender' => ['required', Rule::in(['L', 'P'])],
            'siblings.*.education_occupation' => ['required', 'string', 'max:255'],
            'consent' => ['accepted'],
        ], $documentRules))->validate();
    }

    private function validateIdempotencyToken(string $token): void
    {
        if (! preg_match('/\A[A-Za-z0-9]{64}\z/', $token)) {
            throw ValidationException::withMessages([
                'submission' => 'Sesi pengiriman tidak valid. Muat ulang halaman lalu coba kembali.',
            ]);
        }
    }

    /**
     * @param  array{name: string, nik?: string|null, birthplace?: string|null, birthdate?: string|null, education_last?: string|null, email?: string|null, phone: string|null, occupation: string|null, income_range: string|null}  $data
     */
    private function createParent(ProspectiveStudent $prospectiveStudent, string $role, array $data): void
    {
        PpdbParent::query()->create([
            'prospective_student_id' => $prospectiveStudent->id,
            'role' => $role,
            'nama' => trim($data['name'] ?? ''),
            'nik' => $this->nullableString($data['nik'] ?? null),
            'tempat_lahir' => $this->nullableString($data['birthplace'] ?? null),
            'tanggal_lahir' => filled($data['birthdate'] ?? null) ? $data['birthdate'] : null,
            'pendidikan_terakhir' => $this->nullableString($data['education_last'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'no_telp' => $this->nullableString($data['phone'] ?? null),
            'occupation' => $this->nullableString($data['occupation'] ?? null),
            'income_range' => $this->nullableString($data['income_range'] ?? null),
        ]);
    }

    /** @param array{name: string|null, phone: string|null, occupation: string|null, income_range: string|null} $data */
    private function hasParentData(array $data): bool
    {
        return collect($data)->contains(fn (mixed $value): bool => filled($value));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function storeDocument(string $documentType, UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw ValidationException::withMessages([
                'documents.'.$documentType => 'Format dokumen tidak didukung.',
            ]),
        };

        $directory = 'ppdb/documents/'.now()->format('Y/m').'/'.Str::uuid();
        $filename = $documentType.'-'.Str::random(40).'.'.$extension;
        $path = $file->storeAs($directory, $filename, self::DOCUMENT_DISK);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Dokumen PPDB gagal disimpan.');
        }

        return $path;
    }

    private function sanitizeOriginalFilename(string $filename): string
    {
        $sanitized = preg_replace('/[^\pL\pN._ -]/u', '_', basename($filename));
        $sanitized = Str::limit(trim((string) $sanitized), 255, '');

        return $sanitized !== '' ? $sanitized : 'dokumen';
    }

    private function isDeadlock(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '40001' || $driverCode === 1213;
    }

    /** @return array{registration_number: string, success_reference: string} */
    private function result(PpdbPublicSubmission $submission, string $successReference): array
    {
        $registrationNumber = $submission->prospectiveStudent()->value('registration_number');

        if (! is_string($registrationNumber)) {
            throw new RuntimeException('Hasil pendaftaran PPDB tidak ditemukan.');
        }

        return [
            'registration_number' => $registrationNumber,
            'success_reference' => $successReference,
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function nullableNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
