<?php

namespace App\Services;

use App\Enums\ParentIncomeRange;
use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Models\User;
use App\Support\PersonNameNormalizer;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProspectiveStudentBulkUpdateService
{
    public function __construct(private readonly PpdbStudentDataSynchronizer $studentDataSynchronizer) {}

    public const MAX_DATA_ROWS = 500;

    public const QUERY_CHUNK_SIZE = 250;

    public const CLEAR_MARKER = '#CLEAR#';

    public const STATUS_READY = 'ready';

    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_BLOCKED = 'blocked';

    public const DISTANCE_OPTIONS = [
        'less_than_1_km' => 'Kurang dari 1 km',
        'more_than_1_km' => 'Lebih dari 1 km',
    ];

    public const TRANSPORTATION_OPTIONS = [
        'jalan_kaki' => 'Jalan kaki',
        'sepeda' => 'Sepeda',
        'motor' => 'Motor',
        'mobil_pribadi' => 'Mobil pribadi',
        'angkutan_umum' => 'Angkutan umum',
        'antar_jemput' => 'Antar-jemput',
        'lainnya' => 'Lainnya',
    ];

    /** @var list<string> */
    public const CORE_FIELDS = [
        'applicant_category',
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'nama_orang_tua',
        'no_telp_orang_tua',
        'alamat',
        'notes',
    ];

    /** @var list<string> */
    public const PROFILE_FIELDS = [
        'tempat_lahir',
        'tanggal_lahir',
        'asal_sekolah',
        'nik_siswa',
        'nisn',
        'nomor_kk',
        'nomor_akta_kelahiran',
        'anak_ke',
        'jumlah_saudara',
        'hobi',
        'cita_cita',
        'status_anak',
        'agama',
        'bahasa_sehari_hari',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kabupaten_kota',
        'provinsi',
        'kode_pos',
        'jarak_tempuh_kategori',
        'jarak_tempuh_detail',
        'moda_transportasi',
        'moda_transportasi_lainnya',
        'tinggi_badan',
        'berat_badan',
        'lingkar_kepala',
        'golongan_darah',
        'riwayat_penyakit',
        'berkebutuhan_khusus',
        'keterangan_kebutuhan_khusus',
    ];

    /** @var array<string, string> */
    public const PARENT_ROLES = [
        'father' => 'Ayah',
        'mother' => 'Ibu',
        'guardian' => 'Wali',
    ];

    /** @var list<string> */
    public const PARENT_FIELDS = [
        'father_status_hidup',
        'father_nama',
        'father_nik',
        'father_tempat_lahir',
        'father_tanggal_lahir',
        'father_pendidikan_terakhir',
        'father_email',
        'father_no_telp',
        'father_occupation',
        'father_income_range',
        'mother_status_hidup',
        'mother_nama',
        'mother_nik',
        'mother_tempat_lahir',
        'mother_tanggal_lahir',
        'mother_pendidikan_terakhir',
        'mother_email',
        'mother_no_telp',
        'mother_occupation',
        'mother_income_range',
        'guardian_nama',
        'guardian_nik',
        'guardian_tempat_lahir',
        'guardian_tanggal_lahir',
        'guardian_pendidikan_terakhir',
        'guardian_email',
        'guardian_no_telp',
        'guardian_occupation',
        'guardian_income_range',
    ];

    /** @var list<string> */
    public const UPDATEABLE_FIELDS = [
        ...self::CORE_FIELDS,
        ...self::PROFILE_FIELDS,
        ...self::PARENT_FIELDS,
    ];

    /**
     * @var array<string, array{label: string, section: string, nullable: bool, type: string, max?: int, min?: float, values?: list<string>}>
     */
    private const FIELD_SPECS = [
        'applicant_category' => ['label' => 'Kategori Pendaftar', 'section' => 'registration', 'nullable' => true, 'type' => 'category'],
        'nama_lengkap' => ['label' => 'Nama Lengkap', 'section' => 'identity', 'nullable' => false, 'type' => 'name', 'max' => 255],
        'nama_panggilan' => ['label' => 'Nama Panggilan', 'section' => 'identity', 'nullable' => true, 'type' => 'name', 'max' => 100],
        'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'section' => 'identity', 'nullable' => true, 'type' => 'upper', 'values' => ['L', 'P']],
        'nama_orang_tua' => ['label' => 'Nama Orang Tua', 'section' => 'identity', 'nullable' => true, 'type' => 'name', 'max' => 255],
        'no_telp_orang_tua' => ['label' => 'No Telp Orang Tua', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 50],
        'alamat' => ['label' => 'Alamat', 'section' => 'address', 'nullable' => true, 'type' => 'string', 'max' => 2000],
        'notes' => ['label' => 'Catatan', 'section' => 'identity', 'nullable' => true, 'type' => 'string'],
        'tempat_lahir' => ['label' => 'Tempat Lahir', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 255],
        'tanggal_lahir' => ['label' => 'Tanggal Lahir', 'section' => 'identity', 'nullable' => true, 'type' => 'date'],
        'asal_sekolah' => ['label' => 'Asal Sekolah', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 255],
        'nik_siswa' => ['label' => 'NIK Siswa', 'section' => 'identity', 'nullable' => true, 'type' => 'digits', 'max' => 16, 'min' => 16],
        'nisn' => ['label' => 'NISN', 'section' => 'identity', 'nullable' => true, 'type' => 'digits', 'max' => 10, 'min' => 10],
        'nomor_kk' => ['label' => 'Nomor KK', 'section' => 'identity', 'nullable' => true, 'type' => 'digits', 'max' => 16, 'min' => 16],
        'nomor_akta_kelahiran' => ['label' => 'Nomor Akta Kelahiran', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 100],
        'anak_ke' => ['label' => 'Anak Ke', 'section' => 'identity', 'nullable' => true, 'type' => 'integer', 'min' => 1],
        'jumlah_saudara' => ['label' => 'Jumlah Saudara', 'section' => 'identity', 'nullable' => true, 'type' => 'integer', 'min' => 0],
        'hobi' => ['label' => 'Hobi', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 255],
        'cita_cita' => ['label' => 'Cita-cita', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 255],
        'status_anak' => ['label' => 'Status Anak', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 30],
        'agama' => ['label' => 'Agama', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 50],
        'bahasa_sehari_hari' => ['label' => 'Bahasa Sehari-hari', 'section' => 'identity', 'nullable' => true, 'type' => 'string', 'max' => 100],
        'rt' => ['label' => 'RT', 'section' => 'address', 'nullable' => true, 'type' => 'digits_range', 'min' => 1, 'max' => 3],
        'rw' => ['label' => 'RW', 'section' => 'address', 'nullable' => true, 'type' => 'digits_range', 'min' => 1, 'max' => 3],
        'kelurahan' => ['label' => 'Kelurahan', 'section' => 'address', 'nullable' => true, 'type' => 'string', 'max' => 150],
        'kecamatan' => ['label' => 'Kecamatan', 'section' => 'address', 'nullable' => true, 'type' => 'string', 'max' => 150],
        'kabupaten_kota' => ['label' => 'Kabupaten/Kota', 'section' => 'address', 'nullable' => true, 'type' => 'string', 'max' => 150],
        'provinsi' => ['label' => 'Provinsi', 'section' => 'address', 'nullable' => true, 'type' => 'string', 'max' => 150],
        'kode_pos' => ['label' => 'Kode Pos', 'section' => 'address', 'nullable' => true, 'type' => 'digits', 'min' => 5, 'max' => 5],
        'jarak_tempuh_kategori' => ['label' => 'Kategori Jarak Tempuh', 'section' => 'address', 'nullable' => true, 'type' => 'string', 'values' => ['less_than_1_km', 'more_than_1_km']],
        'jarak_tempuh_detail' => ['label' => 'Detail Jarak Tempuh (km)', 'section' => 'address', 'nullable' => true, 'type' => 'distance'],
        'moda_transportasi' => ['label' => 'Moda Transportasi', 'section' => 'address', 'nullable' => true, 'type' => 'string', 'values' => ['jalan_kaki', 'sepeda', 'motor', 'mobil_pribadi', 'angkutan_umum', 'antar_jemput', 'lainnya']],
        'moda_transportasi_lainnya' => ['label' => 'Moda Transportasi Lainnya', 'section' => 'address', 'nullable' => true, 'type' => 'string', 'max' => 255],
        'tinggi_badan' => ['label' => 'Tinggi Badan (cm)', 'section' => 'health', 'nullable' => true, 'type' => 'decimal', 'min' => 0, 'max' => 250],
        'berat_badan' => ['label' => 'Berat Badan (kg)', 'section' => 'health', 'nullable' => true, 'type' => 'decimal', 'min' => 0, 'max' => 200],
        'lingkar_kepala' => ['label' => 'Lingkar Kepala (cm)', 'section' => 'health', 'nullable' => true, 'type' => 'decimal', 'min' => 0, 'max' => 100],
        'golongan_darah' => ['label' => 'Golongan Darah', 'section' => 'health', 'nullable' => true, 'type' => 'upper', 'max' => 5],
        'riwayat_penyakit' => ['label' => 'Riwayat Penyakit', 'section' => 'health', 'nullable' => true, 'type' => 'string', 'max' => 2000],
        'berkebutuhan_khusus' => ['label' => 'Berkebutuhan Khusus', 'section' => 'health', 'nullable' => true, 'type' => 'boolean'],
        'keterangan_kebutuhan_khusus' => ['label' => 'Keterangan Kebutuhan Khusus', 'section' => 'health', 'nullable' => true, 'type' => 'string', 'max' => 2000],
    ];

    /**
     * @var array<string, array{label: string, nullable: bool, type: string, max?: int, min?: float, values?: list<string>}>
     */
    private const PARENT_FIELD_SPECS = [
        'status_hidup' => ['label' => 'Status Hidup', 'nullable' => true, 'type' => 'lower', 'values' => ['hidup', 'wafat']],
        'nama' => ['label' => 'Nama', 'nullable' => true, 'type' => 'name', 'max' => 255],
        'nik' => ['label' => 'NIK', 'nullable' => true, 'type' => 'digits', 'min' => 16, 'max' => 16],
        'tempat_lahir' => ['label' => 'Tempat Lahir', 'nullable' => true, 'type' => 'string', 'max' => 150],
        'tanggal_lahir' => ['label' => 'Tanggal Lahir', 'nullable' => true, 'type' => 'date_any'],
        'pendidikan_terakhir' => ['label' => 'Pendidikan Terakhir', 'nullable' => true, 'type' => 'string', 'max' => 100],
        'email' => ['label' => 'Email', 'nullable' => true, 'type' => 'email', 'max' => 254],
        'no_telp' => ['label' => 'No. Telepon', 'nullable' => true, 'type' => 'phone', 'max' => 20],
        'occupation' => ['label' => 'Pekerjaan', 'nullable' => true, 'type' => 'string', 'max' => 255],
        'income_range' => ['label' => 'Penghasilan', 'nullable' => true, 'type' => 'income'],
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, ready: int, unchanged: int, blocked: int}}
     */
    public function preview(array $rows, ?User $user = null): array
    {
        $this->ensureRowLimit($rows);

        return $this->buildPreview($rows, $this->fetchProspects($rows, user: $user));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, ProspectiveStudent>  $prospectMap
     * @return array{rows: array<int, array<string, mixed>>, summary: array{total: int, ready: int, unchanged: int, blocked: int}}
     */
    private function buildPreview(array $rows, Collection $prospectMap): array
    {
        $idCounts = $this->idCounts($rows);

        $previewRows = collect($rows)->map(function (array $row) use ($prospectMap, $idCounts): array {
            $id = $row['id_sistem'] ?? null;
            $errors = [];
            $prospect = null;

            if ($id === null || trim((string) $id) === '') {
                $errors[] = 'ID Sistem tidak valid atau kosong.';
            } elseif (! ctype_digit((string) $id)) {
                $errors[] = 'ID Sistem harus berupa angka.';
            } else {
                $prospect = $prospectMap->get((int) $id);

                if ($prospect === null) {
                    $errors[] = 'ID Sistem tidak ditemukan.';
                } elseif (($idCounts[(int) $id] ?? 0) > 1) {
                    $errors[] = "ID Sistem {$id} muncul lebih dari satu kali di file.";
                } elseif (! $this->hasSupportedStatus($prospect)) {
                    $errors[] = 'Hanya calon siswa berstatus Terdaftar atau Dikonversi yang dapat diubah melalui bulk update.';
                } elseif ($this->statusValue($prospect) === ProspectiveStudentStatus::Converted->value) {
                    $student = $prospect->convertedStudent;

                    if ($prospect->converted_student_id === null
                        || ! $student instanceof Student
                        || $student->id !== $prospect->converted_student_id) {
                        $errors[] = 'Relasi siswa hasil konversi tidak valid atau siswa tidak ditemukan.';
                    }
                }
            }

            $changes = [];
            $update = [
                'core' => [],
                'profile' => [],
                'parents' => ['father' => [], 'mother' => [], 'guardian' => []],
            ];

            if ($prospect !== null && $errors === []) {
                $errors = array_merge($errors, $this->referenceErrors($prospect, $row));
            }

            if ($prospect !== null && $errors === []) {
                [$changes, $update, $fieldErrors] = $this->compareProspect(
                    $prospect,
                    $this->providedValueMap($row),
                );
                $errors = array_merge($errors, $fieldErrors);
            }

            $status = $errors !== []
                ? self::STATUS_BLOCKED
                : ($changes === [] ? self::STATUS_UNCHANGED : self::STATUS_READY);

            return [
                'row_number' => (int) $row['row_number'],
                'id_sistem' => $id === null ? '' : (string) $id,
                'prospective_student_id' => $prospect?->id,
                'no_pendaftaran' => $prospect?->registration_number ?? ($row['no_pendaftaran'] ?? ''),
                'nama_lengkap' => $prospect?->nama_lengkap ?? '',
                'kelas' => $prospect?->schoolClass?->name ?? ($row['kelas'] ?? ''),
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'errors' => array_values(array_unique($errors)),
                'warnings' => [],
                'changes' => $changes,
                'update' => $update,
            ];
        })->values();

        return [
            'rows' => $previewRows->all(),
            'summary' => [
                'total' => $previewRows->count(),
                'ready' => $previewRows->where('status', self::STATUS_READY)->count(),
                'unchanged' => $previewRows->where('status', self::STATUS_UNCHANGED)->count(),
                'blocked' => $previewRows->where('status', self::STATUS_BLOCKED)->count(),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function apply(array $rows, ?User $user = null): int
    {
        $this->ensureRowLimit($rows);

        return DB::transaction(function () use ($rows, $user): int {
            $prospectMap = $this->fetchProspects($rows, lockForUpdate: true, user: $user);
            $preview = $this->buildPreview($rows, $prospectMap);

            if ($preview['summary']['blocked'] > 0) {
                throw ValidationException::withMessages([
                    'update' => 'Update dibatalkan karena file memiliki baris yang gagal divalidasi.',
                ]);
            }

            $updated = 0;

            foreach ($preview['rows'] as $row) {
                if ($row['status'] !== self::STATUS_READY || $row['prospective_student_id'] === null) {
                    continue;
                }

                $prospect = $prospectMap->get($row['prospective_student_id']);
                if (! $prospect instanceof ProspectiveStudent) {
                    throw ValidationException::withMessages([
                        'update' => 'Update dibatalkan karena calon siswa tidak lagi tersedia.',
                    ]);
                }

                $coreChanged = $this->saveCoreUpdate($prospect, $row['update']['core']);
                $profileChanged = $this->saveProfileUpdate($prospect, $row['update']['profile']);
                $parentsChanged = $this->saveParentUpdates($prospect, $row['update']['parents']);
                $studentChanged = $this->saveConvertedStudentUpdate($prospect, $row['update']);

                if ($coreChanged || $profileChanged || $parentsChanged || $studentChanged) {
                    $updated++;
                }
            }

            return $updated;
        }, 3);
    }

    /** @param array<string, mixed> $update */
    private function saveCoreUpdate(ProspectiveStudent $prospect, array $update): bool
    {
        if ($update === []) {
            return false;
        }

        $prospect->fill($update);
        if (! $prospect->isDirty()) {
            return false;
        }

        $prospect->save();

        return true;
    }

    /** @param array<string, mixed> $update */
    private function saveProfileUpdate(ProspectiveStudent $prospect, array $update): bool
    {
        if ($update === []) {
            return false;
        }

        $profile = $prospect->ppdbProfile;
        if (! $profile instanceof PpdbProfile) {
            $profile = new PpdbProfile(['prospective_student_id' => $prospect->id]);
        }

        $profile->fill($update);
        if (! $profile->isDirty()) {
            return false;
        }

        $profile->save();
        $prospect->setRelation('ppdbProfile', $profile);

        return true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $updates
     */
    private function saveParentUpdates(ProspectiveStudent $prospect, array $updates): bool
    {
        $changed = false;
        $parents = $this->parentsByRole($prospect);

        foreach (array_keys(self::PARENT_ROLES) as $role) {
            $update = $updates[$role] ?? [];
            if ($update === []) {
                continue;
            }

            $parent = $parents->get($role);
            if (! $parent instanceof PpdbParent) {
                if (! collect($update)->contains(fn (mixed $value): bool => $value !== null)) {
                    continue;
                }

                $parent = new PpdbParent([
                    'prospective_student_id' => $prospect->id,
                    'role' => $role,
                ]);
            }

            $parent->fill($update);
            if (! $parent->isDirty()) {
                continue;
            }

            $parent->save();
            $parents->put($role, $parent);
            $changed = true;
        }

        $prospect->setRelation('ppdbParents', $parents->values());

        return $changed;
    }

    /**
     * @param  array{core: array<string, mixed>, profile: array<string, mixed>, parents: array<string, array<string, mixed>>}  $updates
     */
    private function saveConvertedStudentUpdate(ProspectiveStudent $prospect, array $updates): bool
    {
        if ($this->statusValue($prospect) !== ProspectiveStudentStatus::Converted->value) {
            return false;
        }

        $student = $prospect->convertedStudent;
        if (! $student instanceof Student || $student->id !== $prospect->converted_student_id) {
            throw ValidationException::withMessages([
                'update' => 'Update dibatalkan karena relasi siswa hasil konversi tidak valid.',
            ]);
        }

        $this->studentDataSynchronizer->fillMappedValues(
            $student,
            $updates['core'],
            $updates['profile'],
            $updates['parents']['father'] ?? [],
            $updates['parents']['mother'] ?? [],
        );

        if (! $student->isDirty()) {
            return false;
        }

        $student->save();

        return true;
    }

    /** @return Collection<int, ProspectiveStudent> */
    private function fetchProspects(array $rows, bool $lockForUpdate = false, ?User $user = null): Collection
    {
        $ids = collect($rows)
            ->pluck('id_sistem')
            ->filter(fn (mixed $value): bool => $value !== null && trim((string) $value) !== '' && ctype_digit((string) $value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->sort()
            ->values();

        $prospects = collect();
        $activeYear = $user?->isTuUnit() ? AcademicYear::active() : null;

        foreach ($ids->chunk(self::QUERY_CHUNK_SIZE) as $idChunk) {
            $query = ProspectiveStudent::query()
                ->with(['academicYear', 'schoolClass'])
                ->whereIn('id', $idChunk->all())
                ->orderBy('id');

            if ($user?->isTuUnit()) {
                $query = app(ProspectiveStudentUnitScope::class)->applyForUser($query, $user);
            }

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $chunkProspects = $query->get();
            $profileQuery = PpdbProfile::query()
                ->whereIn('prospective_student_id', $idChunk->all())
                ->orderBy('prospective_student_id')
                ->orderBy('id');

            if ($lockForUpdate) {
                $profileQuery->lockForUpdate();
            }

            $profiles = $profileQuery->get()->keyBy('prospective_student_id');
            $parentQuery = PpdbParent::query()
                ->whereIn('prospective_student_id', $idChunk->all())
                ->whereIn('role', array_keys(self::PARENT_ROLES))
                ->orderBy('prospective_student_id')
                ->orderBy('role')
                ->orderBy('id');

            if ($lockForUpdate) {
                $parentQuery->lockForUpdate();
            }

            $parents = $parentQuery->get()->groupBy('prospective_student_id');
            $convertedStudentIds = $chunkProspects
                ->filter(fn (ProspectiveStudent $prospect): bool => $this->statusValue($prospect) === ProspectiveStudentStatus::Converted->value)
                ->pluck('converted_student_id')
                ->filter(fn (mixed $id): bool => $id !== null)
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            $students = collect();

            if ($convertedStudentIds->isNotEmpty()) {
                $studentQuery = Student::query()
                    ->select(PpdbStudentDataSynchronizer::STUDENT_COLUMNS)
                    ->whereIn('id', $convertedStudentIds->all())
                    ->orderBy('id');

                if ($user?->isTuUnit()) {
                    $studentQuery = app(StudentUnitScope::class)->applyForUser($studentQuery, $user, $activeYear);
                }

                if ($lockForUpdate) {
                    $studentQuery->lockForUpdate();
                }

                $students = $studentQuery->get()->keyBy('id');
            }

            foreach ($chunkProspects as $prospect) {
                $prospect->setRelation('ppdbProfile', $profiles->get($prospect->id));
                $prospect->setRelation('ppdbParents', $parents->get($prospect->id, collect())->values());
                $prospect->setRelation('convertedStudent', $students->get($prospect->converted_student_id));
                $prospects->put($prospect->id, $prospect);
            }
        }

        return $prospects;
    }

    /** @return Collection<int, int> */
    private function idCounts(array $rows): Collection
    {
        return collect($rows)
            ->pluck('id_sistem')
            ->filter(fn (mixed $value): bool => $value !== null && ctype_digit((string) $value))
            ->map(fn (mixed $value): int => (int) $value)
            ->countBy();
    }

    /** @return array<string, mixed> */
    private function providedValueMap(array $row): array
    {
        $provided = [];

        foreach (self::UPDATEABLE_FIELDS as $field) {
            $raw = $row[$field] ?? null;

            if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                continue;
            }

            $provided[$field] = is_string($raw) ? trim($raw) : $raw;
        }

        return $provided;
    }

    /**
     * @param  array<string, mixed>  $provided
     * @return array{0: array<int, array<string, mixed>>, 1: array{core: array<string, mixed>, profile: array<string, mixed>, parents: array<string, array<string, mixed>>}, 2: array<int, string>}
     */
    private function compareProspect(ProspectiveStudent $prospect, array $provided): array
    {
        $changes = [];
        $updates = [
            'core' => [],
            'profile' => [],
            'parents' => ['father' => [], 'mother' => [], 'guardian' => []],
        ];
        $errors = [];

        foreach ($provided as $field => $incoming) {
            if (in_array($field, ['nama_orang_tua', 'no_telp_orang_tua', 'father_nama', 'father_no_telp'], true)) {
                continue;
            }

            $spec = $this->fieldSpec($field);
            $current = $this->currentValue($prospect, $field);
            [$normalized, $error] = $this->normalizeProvidedValue($field, $incoming);

            if ($field === 'nisn' && $this->isTk($prospect) && $normalized !== null) {
                $errors[] = 'NISN harus tetap kosong untuk calon siswa jenjang TK.';

                continue;
            }

            if ($this->valuesEqual($current, $normalized)) {
                continue;
            }

            if ($error !== null) {
                $errors[] = $error;

                continue;
            }

            $parentParts = $this->parentFieldParts($field);
            if ($parentParts !== null) {
                [$role, $attribute] = $parentParts;
                $updates['parents'][$role][$attribute] = $normalized;
            } else {
                $target = in_array($field, self::CORE_FIELDS, true) ? 'core' : 'profile';
                $updates[$target][$field] = $normalized;
            }

            $changes[] = $this->change($field, $current, $normalized, explicitClear: $incoming === self::CLEAR_MARKER);
        }

        $this->reconcileFatherAliases($prospect, $provided, $updates, $changes, $errors);
        $this->validateConditionalProfile($prospect, $provided, $updates['profile'], $changes, $errors);

        return [$changes, $updates, $errors];
    }

    /**
     * @param  array<string, mixed>  $provided
     * @param  array{core: array<string, mixed>, profile: array<string, mixed>, parents: array<string, array<string, mixed>>}  $updates
     * @param  array<int, array<string, mixed>>  $changes
     * @param  array<int, string>  $errors
     */
    private function reconcileFatherAliases(ProspectiveStudent $prospect, array $provided, array &$updates, array &$changes, array &$errors): void
    {
        foreach ([
            ['alias' => 'nama_orang_tua', 'father' => 'father_nama', 'attribute' => 'nama'],
            ['alias' => 'no_telp_orang_tua', 'father' => 'father_no_telp', 'attribute' => 'no_telp'],
        ] as $mapping) {
            $aliasField = $mapping['alias'];
            $fatherField = $mapping['father'];
            $aliasProvided = array_key_exists($aliasField, $provided);
            $fatherProvided = array_key_exists($fatherField, $provided);

            if (! $aliasProvided && ! $fatherProvided) {
                continue;
            }

            $aliasCurrent = $this->currentValue($prospect, $aliasField);
            $fatherCurrent = $this->currentValue($prospect, $fatherField);
            [$aliasValue, $aliasError] = $aliasProvided
                ? $this->normalizeProvidedValue($aliasField, $provided[$aliasField])
                : [$aliasCurrent, null];
            [$fatherValue, $fatherError] = $fatherProvided
                ? $this->normalizeProvidedValue($fatherField, $provided[$fatherField])
                : [$fatherCurrent, null];
            $aliasChanged = $aliasProvided && ! $this->valuesEqual($aliasCurrent, $aliasValue);
            $fatherChanged = $fatherProvided && ! $this->valuesEqual($fatherCurrent, $fatherValue);

            if ($aliasChanged && $aliasError !== null) {
                $errors[] = $aliasError;

                continue;
            }

            if ($fatherChanged && $fatherError !== null) {
                $errors[] = $fatherError;

                continue;
            }

            if (! $aliasChanged && ! $fatherChanged) {
                continue;
            }

            if ($aliasChanged && $fatherChanged && ! $this->valuesEqual($aliasValue, $fatherValue)) {
                $errors[] = "Input {$this->fieldSpec($aliasField)['label']} bertentangan dengan {$this->fieldSpec($fatherField)['label']}. Samakan nilainya atau ubah hanya salah satu kolom.";

                continue;
            }

            $authoritativeValue = $fatherChanged ? $fatherValue : $aliasValue;

            if (! $this->valuesEqual($aliasCurrent, $authoritativeValue)) {
                $updates['core'][$aliasField] = $authoritativeValue;
                $changes[] = $this->change(
                    $aliasField,
                    $aliasCurrent,
                    $authoritativeValue,
                    explicitClear: $aliasChanged && $provided[$aliasField] === self::CLEAR_MARKER,
                    automatic: ! $aliasChanged,
                );
            }

            if (! $this->valuesEqual($fatherCurrent, $authoritativeValue)) {
                $updates['parents']['father'][$mapping['attribute']] = $authoritativeValue;
                $changes[] = $this->change(
                    $fatherField,
                    $fatherCurrent,
                    $authoritativeValue,
                    explicitClear: $fatherChanged && $provided[$fatherField] === self::CLEAR_MARKER,
                    automatic: ! $fatherChanged,
                );
            }
        }
    }

    /** @return array{0: mixed, 1: string|null} */
    private function normalizeProvidedValue(string $field, mixed $incoming): array
    {
        $spec = $this->fieldSpec($field);

        if ($incoming === self::CLEAR_MARKER) {
            if (! $spec['nullable']) {
                return [null, "Marker #CLEAR# tidak dapat digunakan untuk {$spec['label']} yang wajib diisi."];
            }

            return [null, null];
        }

        return $this->normalizeAndValidate($field, $incoming);
    }

    /**
     * @param  array<string, mixed>  $provided
     * @param  array<string, mixed>  $profileUpdate
     * @param  array<int, array<string, mixed>>  $changes
     * @param  array<int, string>  $errors
     */
    private function validateConditionalProfile(ProspectiveStudent $prospect, array $provided, array &$profileUpdate, array &$changes, array &$errors): void
    {
        $current = $this->profileValues($prospect->ppdbProfile);
        $effective = array_replace($current, $profileUpdate);

        if ($this->isTk($prospect) && $current['nisn'] !== null && $provided !== [] && ! array_key_exists('nisn', $profileUpdate)) {
            $this->addAutomaticClear('nisn', $current, $effective, $profileUpdate, $changes);
        }

        $distanceAffected = $this->hasAnyKey($profileUpdate, ['jarak_tempuh_kategori', 'jarak_tempuh_detail']);
        if ($distanceAffected && $effective['jarak_tempuh_kategori'] !== 'more_than_1_km') {
            if (array_key_exists('jarak_tempuh_detail', $provided)
                && ($profileUpdate['jarak_tempuh_detail'] ?? null) !== null) {
                $errors[] = 'Detail Jarak Tempuh hanya boleh diisi ketika Kategori Jarak Tempuh bernilai more_than_1_km.';
            } else {
                $this->addAutomaticClear('jarak_tempuh_detail', $current, $effective, $profileUpdate, $changes);
            }
        } elseif ($distanceAffected && $effective['jarak_tempuh_kategori'] === 'more_than_1_km' && $effective['jarak_tempuh_detail'] === null) {
            $errors[] = 'Detail Jarak Tempuh wajib diisi ketika kategori jarak lebih dari 1 km.';
        } elseif ($distanceAffected && $effective['jarak_tempuh_kategori'] === 'more_than_1_km') {
            $error = $this->validationError('jarak_tempuh_detail', $effective['jarak_tempuh_detail']);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        $transportAffected = $this->hasAnyKey($profileUpdate, ['moda_transportasi', 'moda_transportasi_lainnya']);
        if ($transportAffected && $effective['moda_transportasi'] !== 'lainnya') {
            if (array_key_exists('moda_transportasi_lainnya', $provided)
                && ($profileUpdate['moda_transportasi_lainnya'] ?? null) !== null) {
                $errors[] = 'Moda Transportasi Lainnya hanya boleh diisi ketika moda transportasi bernilai lainnya.';
            } else {
                $this->addAutomaticClear('moda_transportasi_lainnya', $current, $effective, $profileUpdate, $changes);
            }
        } elseif ($transportAffected && $effective['moda_transportasi'] === 'lainnya' && $effective['moda_transportasi_lainnya'] === null) {
            $errors[] = 'Moda Transportasi Lainnya wajib diisi ketika moda transportasi bernilai lainnya.';
        } elseif ($transportAffected && $effective['moda_transportasi'] === 'lainnya') {
            $error = $this->validationError('moda_transportasi_lainnya', $effective['moda_transportasi_lainnya']);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        $specialNeedsAffected = $this->hasAnyKey($profileUpdate, ['berkebutuhan_khusus', 'keterangan_kebutuhan_khusus']);
        if ($specialNeedsAffected && $effective['berkebutuhan_khusus'] !== true) {
            if (array_key_exists('keterangan_kebutuhan_khusus', $provided)
                && ($profileUpdate['keterangan_kebutuhan_khusus'] ?? null) !== null) {
                $errors[] = 'Keterangan Kebutuhan Khusus hanya boleh diisi ketika Berkebutuhan Khusus bernilai 1.';
            } else {
                $this->addAutomaticClear('keterangan_kebutuhan_khusus', $current, $effective, $profileUpdate, $changes);
            }
        } elseif ($specialNeedsAffected && $effective['berkebutuhan_khusus'] === true && $effective['keterangan_kebutuhan_khusus'] === null) {
            $errors[] = 'Keterangan Kebutuhan Khusus wajib diisi ketika Berkebutuhan Khusus bernilai 1.';
        } elseif ($specialNeedsAffected && $effective['berkebutuhan_khusus'] === true) {
            $error = $this->validationError('keterangan_kebutuhan_khusus', $effective['keterangan_kebutuhan_khusus']);

            if ($error !== null) {
                $errors[] = $error;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $effective
     * @param  array<string, mixed>  $profileUpdate
     * @param  array<int, array<string, mixed>>  $changes
     */
    private function addAutomaticClear(string $field, array $current, array &$effective, array &$profileUpdate, array &$changes): void
    {
        if ($effective[$field] === null) {
            return;
        }

        $profileUpdate[$field] = null;
        $effective[$field] = null;

        $existingIndex = collect($changes)->search(fn (array $change): bool => $change['field'] === $field);
        if ($existingIndex !== false) {
            $changes[$existingIndex] = $this->change($field, $current[$field], null, automatic: true);

            return;
        }

        $changes[] = $this->change($field, $current[$field], null, automatic: true);
    }

    /** @return array{0: mixed, 1: string|null} */
    private function normalizeAndValidate(string $field, mixed $incoming): array
    {
        $spec = $this->fieldSpec($field);
        $value = trim((string) $incoming);

        $normalized = match ($spec['type']) {
            'name' => PersonNameNormalizer::normalize($value) ?? $value,
            'upper' => mb_strtoupper($value),
            'lower' => mb_strtolower($value),
            'integer' => preg_match('/\A\d+\z/', $value) === 1 ? (int) $value : $value,
            'decimal' => is_numeric(str_replace(',', '.', $value)) ? number_format((float) str_replace(',', '.', $value), 2, '.', '') : $value,
            'distance' => preg_match('/\A\d{1,4}(?:[.,]\d{1,2})?\z/', $value) === 1 ? number_format((float) str_replace(',', '.', $value), 2, '.', '') : $value,
            'boolean' => $this->normalizeBoolean($value),
            default => $value,
        };

        return [$normalized, $this->validationError($field, $normalized)];
    }

    private function validationError(string $field, mixed $value): ?string
    {
        $spec = $this->fieldSpec($field);
        $label = $spec['label'];

        if ($spec['type'] === 'category' && PpdbApplicantCategory::tryFrom((string) $value) === null) {
            return "Nilai {$label} tidak valid.";
        }

        if ($spec['type'] === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
            if ($date === false || $date->format('Y-m-d') !== $value || $date >= new DateTimeImmutable('today')) {
                return "{$label} harus berupa tanggal valid sebelum hari ini dengan format YYYY-MM-DD.";
            }
        }

        if ($spec['type'] === 'date_any') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
            if ($date === false || $date->format('Y-m-d') !== $value) {
                return "{$label} harus berupa tanggal valid dengan format YYYY-MM-DD.";
            }
        }

        if ($spec['type'] === 'digits' && (preg_match('/\A\d+\z/', (string) $value) !== 1
            || strlen((string) $value) < (int) $spec['min']
            || strlen((string) $value) > (int) $spec['max'])) {
            return "{$label} harus terdiri dari {$spec['min']} digit.";
        }

        if ($spec['type'] === 'digits_range' && (preg_match('/\A\d+\z/', (string) $value) !== 1
            || strlen((string) $value) < (int) $spec['min']
            || strlen((string) $value) > (int) $spec['max'])) {
            return "{$label} harus terdiri dari {$spec['min']} sampai {$spec['max']} digit.";
        }

        if ($spec['type'] === 'integer' && ! is_int($value)) {
            return "{$label} harus berupa bilangan bulat.";
        }

        if ($spec['type'] === 'integer' && $value < (int) $spec['min']) {
            return "{$label} minimal {$spec['min']}.";
        }

        if ($spec['type'] === 'distance' && (! is_string($value) || preg_match('/\A\d{1,4}\.\d{2}\z/', $value) !== 1 || (float) $value <= 1 || (float) $value > 9999.99)) {
            return "{$label} harus lebih dari 1 dan maksimal 9999,99 dengan maksimal dua desimal.";
        }

        if ($spec['type'] === 'decimal' && (! is_string($value) || ! is_numeric($value)
            || (float) $value < (float) $spec['min'] || (float) $value > (float) $spec['max'])) {
            return "{$label} harus bernilai {$spec['min']} sampai {$spec['max']}.";
        }

        if ($spec['type'] === 'boolean' && ! is_bool($value)) {
            return "{$label} harus bernilai 1/0 atau Ya/Tidak.";
        }

        if ($spec['type'] === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return "{$label} harus berupa alamat email yang valid.";
        }

        if ($spec['type'] === 'phone' && preg_match('/\A[0-9+().\-\s]{8,20}\z/', (string) $value) !== 1) {
            return "{$label} harus berupa nomor telepon valid sepanjang 8 sampai 20 karakter.";
        }

        if ($spec['type'] === 'income' && ParentIncomeRange::tryFrom((string) $value) === null) {
            return "Nilai {$label} tidak valid.";
        }

        if (isset($spec['values']) && ! in_array((string) $value, $spec['values'], true)) {
            if ($field === 'jenis_kelamin') {
                return 'Nilai Jenis Kelamin tidak valid (harus L atau P).';
            }

            return "Nilai {$label} tidak valid.";
        }

        if (isset($spec['max']) && is_string($value) && ! in_array($spec['type'], ['digits', 'digits_range'], true) && mb_strlen($value) > $spec['max']) {
            return "{$label} terlalu panjang (maksimal {$spec['max']} karakter).";
        }

        return null;
    }

    private function normalizeBoolean(string $value): bool|string
    {
        return match (mb_strtolower($value)) {
            '1', 'ya', 'true' => true,
            '0', 'tidak', 'false' => false,
            default => $value,
        };
    }

    /** @return array<int, string> */
    private function referenceErrors(ProspectiveStudent $prospect, array $row): array
    {
        $errors = [];
        $registrationNumber = trim((string) ($row['no_pendaftaran'] ?? ''));
        $academicYear = trim((string) ($row['tahun_ajaran'] ?? ''));
        $class = trim((string) ($row['kelas'] ?? ''));
        $snapshot = trim((string) ($row['snapshot_token'] ?? ''));

        if ($registrationNumber === '') {
            $errors[] = 'No. Pendaftaran wajib tersedia sebagai pemeriksaan konsistensi.';
        } elseif ($registrationNumber !== $prospect->registration_number) {
            $errors[] = 'No. Pendaftaran tidak cocok dengan ID Sistem.';
        }

        if ($academicYear === '') {
            $errors[] = 'Tahun Ajaran Tujuan wajib tersedia sebagai pemeriksaan konsistensi.';
        } elseif ($academicYear !== ($prospect->academicYear?->year ?? '')) {
            $errors[] = 'Tahun Ajaran Tujuan tidak cocok dengan data calon siswa saat ini.';
        }

        $currentClass = $prospect->schoolClass?->name ?? '-';
        if ($class !== '' && mb_strtolower($class) !== mb_strtolower($currentClass)) {
            $errors[] = 'Kelas Tujuan tidak cocok dengan data calon siswa saat ini.';
        }

        if ($snapshot === '' || ! $this->snapshotIsValid($prospect, $snapshot)) {
            $errors[] = 'Data calon siswa telah berubah sejak file diunduh atau token snapshot tidak valid.';
        }

        return $errors;
    }

    public function snapshotToken(ProspectiveStudent $prospect): string
    {
        return hash_hmac('sha256', $this->canonicalSnapshot($prospect), $this->signingKey());
    }

    public function snapshotIsValid(ProspectiveStudent $prospect, string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $token) === 1
            && hash_equals($this->snapshotToken($prospect), $token);
    }

    private function canonicalSnapshot(ProspectiveStudent $prospect): string
    {
        if (! $prospect->relationLoaded('ppdbProfile')
            || ! $prospect->relationLoaded('ppdbParents')
            || ! $prospect->relationLoaded('convertedStudent')) {
            $prospect->loadMissing([
                'ppdbProfile',
                'ppdbParents',
                'convertedStudent' => fn ($query) => $query->select(PpdbStudentDataSynchronizer::STUDENT_COLUMNS),
            ]);
        }

        $parents = $this->parentsByRole($prospect);
        $student = $prospect->convertedStudent;

        return json_encode([
            'version' => 4,
            'id' => $prospect->id,
            'registration_number' => $prospect->registration_number,
            'academic_year_id' => $prospect->academic_year_id,
            'school_class_id' => $prospect->school_class_id,
            'status' => $this->statusValue($prospect),
            'converted_student_id' => $prospect->converted_student_id,
            'converted_student' => [
                'exists' => $student instanceof Student,
                'values' => $student instanceof Student
                    ? $this->studentDataSynchronizer->snapshotValues($student)
                    : null,
            ],
            'applicant_category' => $prospect->applicant_category instanceof PpdbApplicantCategory ? $prospect->applicant_category->value : $prospect->applicant_category,
            'core' => collect(self::CORE_FIELDS)
                ->reject(fn (string $field): bool => $field === 'applicant_category')
                ->mapWithKeys(fn (string $field): array => [$field => $this->currentValue($prospect, $field)])
                ->all(),
            'profile_present' => $prospect->ppdbProfile instanceof PpdbProfile,
            'profile' => $this->profileValues($prospect->ppdbProfile),
            'parents' => collect(self::PARENT_ROLES)->mapWithKeys(function (string $label, string $role) use ($parents): array {
                $parent = $parents->get($role);

                return [$role => [
                    'present' => $parent instanceof PpdbParent,
                    'values' => $this->parentValues($parent, $role),
                ]];
            })->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function signingKey(): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY wajib tersedia untuk membuat snapshot bulk update.');
        }

        return $key;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function ensureRowLimit(array $rows): void
    {
        if (count($rows) > self::MAX_DATA_ROWS) {
            throw ValidationException::withMessages([
                'file' => 'File melebihi batas maksimal '.self::MAX_DATA_ROWS.' baris data.',
            ]);
        }
    }

    private function currentValue(ProspectiveStudent $prospect, string $field): mixed
    {
        if (in_array($field, self::PROFILE_FIELDS, true)) {
            return $this->profileValues($prospect->ppdbProfile)[$field];
        }

        if ($field === 'applicant_category') {
            return $prospect->applicant_category instanceof PpdbApplicantCategory
                ? $prospect->applicant_category->value
                : $prospect->applicant_category;
        }

        $parentParts = $this->parentFieldParts($field);
        if ($parentParts !== null) {
            [$role, $attribute] = $parentParts;

            return $this->parentValues($this->parentsByRole($prospect)->get($role), $role)[$attribute];
        }

        return $prospect->{$field};
    }

    /** @return array<string, mixed> */
    private function profileValues(?PpdbProfile $profile): array
    {
        return collect(self::PROFILE_FIELDS)->mapWithKeys(function (string $field) use ($profile): array {
            if ($profile === null) {
                return [$field => null];
            }

            $value = $profile->{$field};

            if ($field === 'tanggal_lahir') {
                return [$field => $value?->format('Y-m-d')];
            }

            return [$field => $value];
        })->all();
    }

    /** @return Collection<string, PpdbParent> */
    private function parentsByRole(ProspectiveStudent $prospect): Collection
    {
        if (! $prospect->relationLoaded('ppdbParents')) {
            $prospect->load('ppdbParents');
        }

        return $prospect->ppdbParents
            ->filter(fn (PpdbParent $parent): bool => isset(self::PARENT_ROLES[$parent->role]))
            ->keyBy('role');
    }

    /** @return array<string, mixed> */
    private function parentValues(?PpdbParent $parent, string $role): array
    {
        return collect($this->parentAttributesForRole($role))->mapWithKeys(function (string $attribute) use ($parent): array {
            if ($parent === null) {
                return [$attribute => null];
            }

            $value = $parent->{$attribute};

            if ($attribute === 'tanggal_lahir') {
                return [$attribute => $value?->format('Y-m-d')];
            }

            if ($attribute === 'income_range') {
                return [$attribute => $value instanceof ParentIncomeRange ? $value->value : $value];
            }

            return [$attribute => $value];
        })->all();
    }

    /** @return list<string> */
    private function parentAttributesForRole(string $role): array
    {
        $attributes = array_keys(self::PARENT_FIELD_SPECS);

        return $role === 'guardian'
            ? array_values(array_diff($attributes, ['status_hidup']))
            : $attributes;
    }

    /** @return array<string, mixed> */
    private function change(string $field, mixed $old, mixed $new, bool $explicitClear = false, bool $automatic = false): array
    {
        $spec = $this->fieldSpec($field);

        return [
            'field' => $field,
            'label' => $spec['label'],
            'section' => $spec['section'],
            'section_label' => $this->sectionLabel($spec['section']),
            'old' => $old,
            'new' => $new,
            'old_display' => $this->displayValue($field, $old),
            'new_display' => $this->displayValue($field, $new),
            'explicit_clear' => $explicitClear,
            'automatic' => $automatic,
        ];
    }

    private function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Belum diisi';
        }

        if ($field === 'applicant_category') {
            return PpdbApplicantCategory::tryFrom((string) $value)?->label() ?? (string) $value;
        }

        if ($field === 'berkebutuhan_khusus') {
            return $value ? 'Ya' : 'Tidak';
        }

        if (str_ends_with($field, '_income_range')) {
            return ParentIncomeRange::tryFrom((string) $value)?->label() ?? (string) $value;
        }

        if (str_ends_with($field, '_status_hidup')) {
            return ucfirst((string) $value);
        }

        return (string) $value;
    }

    private function valuesEqual(mixed $current, mixed $incoming): bool
    {
        return $current === $incoming || ($current !== null && $incoming !== null && (string) $current === (string) $incoming);
    }

    /** @param list<string> $keys */
    private function hasAnyKey(array $values, array $keys): bool
    {
        return collect($keys)->contains(fn (string $key): bool => array_key_exists($key, $values));
    }

    private function isTk(ProspectiveStudent $prospect): bool
    {
        $level = $prospect->schoolClass?->level;

        return $level !== null && SchoolLevel::fromClassLevel((int) $level) === SchoolLevel::TK;
    }

    private function hasSupportedStatus(ProspectiveStudent $prospect): bool
    {
        return in_array($this->statusValue($prospect), [
            ProspectiveStudentStatus::Registered->value,
            ProspectiveStudentStatus::Converted->value,
        ], true);
    }

    private function statusValue(ProspectiveStudent $prospect): string
    {
        return (string) $prospect->getRawOriginal('status');
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            'registration' => 'Registrasi & Kategori',
            'address' => 'Alamat',
            'health' => 'Kesehatan',
            'father' => 'Ayah',
            'mother' => 'Ibu',
            'guardian' => 'Wali',
            default => 'Identitas Siswa',
        };
    }

    /**
     * @return array{label: string, section: string, nullable: bool, type: string, max?: int, min?: float, values?: list<string>}
     */
    private function fieldSpec(string $field): array
    {
        if (isset(self::FIELD_SPECS[$field])) {
            return self::FIELD_SPECS[$field];
        }

        $parts = $this->parentFieldParts($field);
        if ($parts === null) {
            throw new RuntimeException("Field bulk update {$field} tidak dikenali.");
        }

        [$role, $attribute] = $parts;
        $spec = self::PARENT_FIELD_SPECS[$attribute];

        return [
            ...$spec,
            'label' => self::PARENT_ROLES[$role].' - '.$spec['label'],
            'section' => $role,
        ];
    }

    /** @return array{0: string, 1: string}|null */
    private function parentFieldParts(string $field): ?array
    {
        foreach (array_keys(self::PARENT_ROLES) as $role) {
            $prefix = $role.'_';
            if (! str_starts_with($field, $prefix)) {
                continue;
            }

            $attribute = substr($field, strlen($prefix));

            return isset(self::PARENT_FIELD_SPECS[$attribute]) ? [$role, $attribute] : null;
        }

        return null;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_READY => 'SIAP UPDATE',
            self::STATUS_UNCHANGED => 'TANPA PERUBAHAN',
            default => 'GAGAL',
        };
    }
}
