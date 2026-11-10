<?php

namespace App\Services;

use App\Enums\PpdbApplicantCategory;
use App\Enums\ProspectiveStudentStatus;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use App\Models\SchoolClass;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class PpdbBiodataRecapQuery
{
    public const CHUNK_SIZE = 200;

    public const GROUP_IKHWAN_ANNUR = 'ikhwan_annur';

    public const GROUP_AKHWAT_ANNUR = 'akhwat_annur';

    public const GROUP_IKHWAN_NON_ANNUR = 'ikhwan_non_annur';

    public const GROUP_AKHWAT_NON_ANNUR = 'akhwat_non_annur';

    public const GROUP_UNCLASSIFIED = 'unclassified';

    /** @var list<string> */
    public const SHEET_NAMES = [
        'REKAP',
        'IKHWAN KELUARGA ANNUR',
        'AKHWAT KELUARGA ANNUR',
        'IKHWAN NON-KELUARGA',
        'AKHWAT NON-KELUARGA',
        'BELUM DIKATEGORIKAN',
    ];

    /**
     * @var array<string, array{sheet: string, gender: string|null, category: string|null}>
     */
    public const GROUPS = [
        self::GROUP_IKHWAN_ANNUR => [
            'sheet' => 'IKHWAN KELUARGA ANNUR',
            'gender' => 'L',
            'category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
        ],
        self::GROUP_AKHWAT_ANNUR => [
            'sheet' => 'AKHWAT KELUARGA ANNUR',
            'gender' => 'P',
            'category' => PpdbApplicantCategory::ANNUR_FAMILY->value,
        ],
        self::GROUP_IKHWAN_NON_ANNUR => [
            'sheet' => 'IKHWAN NON-KELUARGA',
            'gender' => 'L',
            'category' => PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
        ],
        self::GROUP_AKHWAT_NON_ANNUR => [
            'sheet' => 'AKHWAT NON-KELUARGA',
            'gender' => 'P',
            'category' => PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
        ],
        self::GROUP_UNCLASSIFIED => [
            'sheet' => 'BELUM DIKATEGORIKAN',
            'gender' => null,
            'category' => null,
        ],
    ];

    /**
     * @var list<array{key: string, label: string, section: string}>
     */
    public const DETAIL_COLUMNS = [
        ['key' => 'row_number', 'label' => 'No.', 'section' => 'registration'],
        ['key' => 'registration_number', 'label' => 'No. Pendaftaran', 'section' => 'registration'],
        ['key' => 'registered_at', 'label' => 'Tanggal Pendaftaran', 'section' => 'registration'],
        ['key' => 'academic_year', 'label' => 'Tahun Ajaran', 'section' => 'registration'],
        ['key' => 'school_level', 'label' => 'Jenjang', 'section' => 'registration'],
        ['key' => 'school_class', 'label' => 'Kelas Tujuan', 'section' => 'registration'],
        ['key' => 'registration_status', 'label' => 'Status Pendaftaran', 'section' => 'registration'],
        ['key' => 'applicant_category', 'label' => 'Kategori Pendaftar', 'section' => 'registration'],
        ['key' => 'full_name', 'label' => 'Nama Lengkap', 'section' => 'identity'],
        ['key' => 'nickname', 'label' => 'Nama Panggilan', 'section' => 'identity'],
        ['key' => 'gender', 'label' => 'Jenis Kelamin', 'section' => 'identity'],
        ['key' => 'birthplace', 'label' => 'Tempat Lahir', 'section' => 'identity'],
        ['key' => 'birthdate', 'label' => 'Tanggal Lahir', 'section' => 'identity'],
        ['key' => 'previous_school', 'label' => 'Asal Sekolah', 'section' => 'identity'],
        ['key' => 'student_nik', 'label' => 'NIK Siswa', 'section' => 'identity'],
        ['key' => 'nisn', 'label' => 'NISN', 'section' => 'identity'],
        ['key' => 'family_card_number', 'label' => 'Nomor KK', 'section' => 'identity'],
        ['key' => 'birth_certificate_number', 'label' => 'Nomor Akta Kelahiran', 'section' => 'identity'],
        ['key' => 'child_order', 'label' => 'Anak Ke', 'section' => 'identity'],
        ['key' => 'sibling_count', 'label' => 'Jumlah Saudara', 'section' => 'identity'],
        ['key' => 'hobby', 'label' => 'Hobi', 'section' => 'identity'],
        ['key' => 'aspiration', 'label' => 'Cita-cita', 'section' => 'identity'],
        ['key' => 'child_status', 'label' => 'Status Anak', 'section' => 'identity'],
        ['key' => 'religion', 'label' => 'Agama', 'section' => 'identity'],
        ['key' => 'daily_language', 'label' => 'Bahasa Sehari-hari', 'section' => 'identity'],
        ['key' => 'address', 'label' => 'Alamat', 'section' => 'address'],
        ['key' => 'rt', 'label' => 'RT', 'section' => 'address'],
        ['key' => 'rw', 'label' => 'RW', 'section' => 'address'],
        ['key' => 'village', 'label' => 'Kelurahan', 'section' => 'address'],
        ['key' => 'district', 'label' => 'Kecamatan', 'section' => 'address'],
        ['key' => 'city', 'label' => 'Kabupaten/Kota', 'section' => 'address'],
        ['key' => 'province', 'label' => 'Provinsi', 'section' => 'address'],
        ['key' => 'postal_code', 'label' => 'Kode Pos', 'section' => 'address'],
        ['key' => 'distance_category', 'label' => 'Kategori Jarak Tempuh', 'section' => 'address'],
        ['key' => 'distance_detail', 'label' => 'Detail Jarak Tempuh (km)', 'section' => 'address'],
        ['key' => 'transportation', 'label' => 'Moda Transportasi', 'section' => 'address'],
        ['key' => 'other_transportation', 'label' => 'Moda Transportasi Lainnya', 'section' => 'address'],
        ['key' => 'height', 'label' => 'Tinggi Badan (cm)', 'section' => 'health'],
        ['key' => 'weight', 'label' => 'Berat Badan (kg)', 'section' => 'health'],
        ['key' => 'head_circumference', 'label' => 'Lingkar Kepala (cm)', 'section' => 'health'],
        ['key' => 'blood_type', 'label' => 'Golongan Darah', 'section' => 'health'],
        ['key' => 'medical_history', 'label' => 'Riwayat Penyakit', 'section' => 'health'],
        ['key' => 'has_special_needs', 'label' => 'Berkebutuhan Khusus', 'section' => 'health'],
        ['key' => 'special_needs_notes', 'label' => 'Keterangan Kebutuhan Khusus', 'section' => 'health'],
        ['key' => 'father_living_status', 'label' => 'Ayah - Status Hidup', 'section' => 'father'],
        ['key' => 'father_name', 'label' => 'Ayah - Nama', 'section' => 'father'],
        ['key' => 'father_nik', 'label' => 'Ayah - NIK', 'section' => 'father'],
        ['key' => 'father_birthplace', 'label' => 'Ayah - Tempat Lahir', 'section' => 'father'],
        ['key' => 'father_birthdate', 'label' => 'Ayah - Tanggal Lahir', 'section' => 'father'],
        ['key' => 'father_education', 'label' => 'Ayah - Pendidikan Terakhir', 'section' => 'father'],
        ['key' => 'father_email', 'label' => 'Ayah - Email', 'section' => 'father'],
        ['key' => 'father_phone', 'label' => 'Ayah - No. Telepon', 'section' => 'father'],
        ['key' => 'father_occupation', 'label' => 'Ayah - Pekerjaan', 'section' => 'father'],
        ['key' => 'father_income', 'label' => 'Ayah - Penghasilan', 'section' => 'father'],
        ['key' => 'mother_living_status', 'label' => 'Ibu - Status Hidup', 'section' => 'mother'],
        ['key' => 'mother_name', 'label' => 'Ibu - Nama', 'section' => 'mother'],
        ['key' => 'mother_nik', 'label' => 'Ibu - NIK', 'section' => 'mother'],
        ['key' => 'mother_birthplace', 'label' => 'Ibu - Tempat Lahir', 'section' => 'mother'],
        ['key' => 'mother_birthdate', 'label' => 'Ibu - Tanggal Lahir', 'section' => 'mother'],
        ['key' => 'mother_education', 'label' => 'Ibu - Pendidikan Terakhir', 'section' => 'mother'],
        ['key' => 'mother_email', 'label' => 'Ibu - Email', 'section' => 'mother'],
        ['key' => 'mother_phone', 'label' => 'Ibu - No. Telepon', 'section' => 'mother'],
        ['key' => 'mother_occupation', 'label' => 'Ibu - Pekerjaan', 'section' => 'mother'],
        ['key' => 'mother_income', 'label' => 'Ibu - Penghasilan', 'section' => 'mother'],
        ['key' => 'guardian_name', 'label' => 'Wali - Nama', 'section' => 'guardian'],
        ['key' => 'guardian_nik', 'label' => 'Wali - NIK', 'section' => 'guardian'],
        ['key' => 'guardian_birthplace', 'label' => 'Wali - Tempat Lahir', 'section' => 'guardian'],
        ['key' => 'guardian_birthdate', 'label' => 'Wali - Tanggal Lahir', 'section' => 'guardian'],
        ['key' => 'guardian_education', 'label' => 'Wali - Pendidikan Terakhir', 'section' => 'guardian'],
        ['key' => 'guardian_email', 'label' => 'Wali - Email', 'section' => 'guardian'],
        ['key' => 'guardian_phone', 'label' => 'Wali - No. Telepon', 'section' => 'guardian'],
        ['key' => 'guardian_occupation', 'label' => 'Wali - Pekerjaan', 'section' => 'guardian'],
        ['key' => 'guardian_income', 'label' => 'Wali - Penghasilan', 'section' => 'guardian'],
        ['key' => 'detailed_sibling_count', 'label' => 'Jumlah Record Detail Saudara', 'section' => 'siblings'],
        ['key' => 'sibling_names', 'label' => 'Nama Saudara', 'section' => 'siblings'],
        ['key' => 'sibling_birthdates', 'label' => 'Tanggal Lahir Saudara', 'section' => 'siblings'],
        ['key' => 'sibling_genders', 'label' => 'Jenis Kelamin Saudara', 'section' => 'siblings'],
        ['key' => 'sibling_education_occupations', 'label' => 'Pendidikan/Pekerjaan Saudara', 'section' => 'siblings'],
    ];

    /** @return list<string> */
    public static function detailHeaders(): array
    {
        return array_column(self::DETAIL_COLUMNS, 'label');
    }

    public function academicYear(int $academicYearId): AcademicYear
    {
        return AcademicYear::query()->findOrFail($academicYearId);
    }

    /**
     * @param  Closure(string, Collection<int, ProspectiveStudent>): void  $callback
     * @param  (Closure(AcademicYear, array<string, int>): void)|null  $initialize
     * @return array{
     *     academic_year: AcademicYear,
     *     aggregate_counts: array{ikhwan_annur: int, akhwat_annur: int, ikhwan_non_annur: int, akhwat_non_annur: int, unclassified: int, grand_total: int},
     *     retrieved_counts: array<string, int>
     * }
     */
    public function processSnapshot(
        int $academicYearId,
        SchoolLevel $schoolLevel,
        Closure $callback,
        ?Closure $initialize = null,
    ): array {
        return DB::transaction(function () use ($academicYearId, $schoolLevel, $callback, $initialize): array {
            $academicYear = $this->academicYear($academicYearId);
            $aggregateCounts = $this->counts($academicYearId, $schoolLevel);
            $initialize?->__invoke($academicYear, $aggregateCounts);
            $retrievedCounts = $this->eachGroup($academicYearId, $schoolLevel, $callback);

            foreach (array_keys(self::GROUPS) as $group) {
                if ($retrievedCounts[$group] !== $aggregateCounts[$group]) {
                    throw new LogicException("Jumlah data grup {$group} tidak sesuai dengan hasil rekap.");
                }
            }

            if (array_sum($retrievedCounts) !== $aggregateCounts['grand_total']) {
                throw new LogicException('Jumlah data yang diambil tidak sama dengan grand total rekap PPDB.');
            }

            return [
                'academic_year' => $academicYear,
                'aggregate_counts' => $aggregateCounts,
                'retrieved_counts' => $retrievedCounts,
            ];
        });
    }

    /**
     * @return array{ikhwan_annur: int, akhwat_annur: int, ikhwan_non_annur: int, akhwat_non_annur: int, unclassified: int, grand_total: int}
     */
    public function counts(int $academicYearId, SchoolLevel $schoolLevel): array
    {
        $annur = PpdbApplicantCategory::ANNUR_FAMILY->value;
        $nonAnnur = PpdbApplicantCategory::NON_ANNUR_FAMILY->value;
        $validClassification = '(jenis_kelamin = ? AND applicant_category = ?) OR (jenis_kelamin = ? AND applicant_category = ?) OR (jenis_kelamin = ? AND applicant_category = ?) OR (jenis_kelamin = ? AND applicant_category = ?)';
        $result = $this->baseQuery($academicYearId, $schoolLevel)
            ->toBase()
            ->selectRaw(
                'SUM(CASE WHEN jenis_kelamin = ? AND applicant_category = ? THEN 1 ELSE 0 END) AS ikhwan_annur,
                SUM(CASE WHEN jenis_kelamin = ? AND applicant_category = ? THEN 1 ELSE 0 END) AS akhwat_annur,
                SUM(CASE WHEN jenis_kelamin = ? AND applicant_category = ? THEN 1 ELSE 0 END) AS ikhwan_non_annur,
                SUM(CASE WHEN jenis_kelamin = ? AND applicant_category = ? THEN 1 ELSE 0 END) AS akhwat_non_annur,
                SUM(CASE WHEN '.$validClassification.' THEN 0 ELSE 1 END) AS unclassified,
                COUNT(*) AS grand_total',
                [
                    'L', $annur,
                    'P', $annur,
                    'L', $nonAnnur,
                    'P', $nonAnnur,
                    'L', $annur,
                    'P', $annur,
                    'L', $nonAnnur,
                    'P', $nonAnnur,
                ],
            )
            ->first();

        $counts = [];
        foreach ([...array_keys(self::GROUPS), 'grand_total'] as $key) {
            $counts[$key] = (int) ($result->{$key} ?? 0);
        }

        $classifiedTotal = array_sum(array_intersect_key($counts, self::GROUPS));
        if ($classifiedTotal !== $counts['grand_total']) {
            throw new LogicException('Jumlah kelompok rekap PPDB tidak sama dengan grand total.');
        }

        return $counts;
    }

    /**
     * @param  Closure(Collection<int, ProspectiveStudent>): void  $callback
     */
    public function eachGroupChunk(
        int $academicYearId,
        SchoolLevel $schoolLevel,
        string $group,
        Closure $callback,
    ): int {
        $this->assertValidGroup($group);
        $retrievedCount = 0;
        $lastCreatedAt = null;
        $lastId = null;

        do {
            $query = $this->groupQuery($academicYearId, $schoolLevel, $group);

            if ($lastCreatedAt !== null && $lastId !== null) {
                $query->where(function (Builder $query) use ($lastCreatedAt, $lastId): void {
                    $query->where('created_at', '<', $lastCreatedAt)
                        ->orWhere(function (Builder $query) use ($lastCreatedAt, $lastId): void {
                            $query->where('created_at', $lastCreatedAt)
                                ->where('id', '<', $lastId);
                        });
                });
            }

            $chunk = $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(self::CHUNK_SIZE + 1)
                ->get();
            $hasMore = $chunk->count() > self::CHUNK_SIZE;

            if ($hasMore) {
                $chunk->pop();
            }

            if ($chunk->isEmpty()) {
                break;
            }

            $chunk->load($this->relationsForExport());
            $retrievedCount += $chunk->count();
            $lastApplicant = $chunk->last();
            $lastCreatedAt = (string) $lastApplicant->getRawOriginal('created_at');
            $lastId = $lastApplicant->id;
            $callback($chunk);
            unset($chunk);
        } while ($hasMore);

        return $retrievedCount;
    }

    /**
     * @param  Closure(string, Collection<int, ProspectiveStudent>): void  $callback
     * @return array<string, int>
     */
    public function eachGroup(int $academicYearId, SchoolLevel $schoolLevel, Closure $callback): array
    {
        $retrievedCounts = [];

        foreach (array_keys(self::GROUPS) as $group) {
            $retrievedCounts[$group] = $this->eachGroupChunk(
                $academicYearId,
                $schoolLevel,
                $group,
                fn (Collection $chunk) => $callback($group, $chunk),
            );
        }

        return $retrievedCounts;
    }

    public function nisnForExport(ProspectiveStudent $applicant, SchoolLevel $schoolLevel): string
    {
        if ($schoolLevel === SchoolLevel::TK) {
            return '';
        }

        return (string) ($applicant->ppdbProfile?->nisn ?? '');
    }

    public function applicantCategoryForExport(ProspectiveStudent $applicant): string
    {
        return (string) ($applicant->getRawOriginal('applicant_category') ?? '');
    }

    public function genderForExport(ProspectiveStudent $applicant): string
    {
        return (string) ($applicant->getRawOriginal('jenis_kelamin') ?? '');
    }

    /**
     * @return array{count: int, names: string, birthdates: string, genders: string, education_occupations: string}
     */
    public function siblingTextColumns(ProspectiveStudent $applicant): array
    {
        $siblings = $applicant->ppdbSiblings;
        $names = [];
        $birthdates = [];
        $genders = [];
        $educationOccupations = [];

        foreach ($siblings as $index => $sibling) {
            $prefix = ($index + 1).'. ';
            $names[] = $prefix.$sibling->nama_lengkap;
            $birthdates[] = $prefix.($sibling->tanggal_lahir?->format('Y-m-d') ?? '-');
            $rawGender = (string) ($sibling->getRawOriginal('jenis_kelamin') ?? '');
            $genders[] = $prefix.match ($rawGender) {
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                '' => '-',
                default => $rawGender,
            };
            $educationOccupations[] = $prefix.($sibling->pendidikan_pekerjaan ?: '-');
        }

        return [
            'count' => $siblings->count(),
            'names' => implode("\n", $names),
            'birthdates' => implode("\n", $birthdates),
            'genders' => implode("\n", $genders),
            'education_occupations' => implode("\n", $educationOccupations),
        ];
    }

    /** @return Builder<ProspectiveStudent> */
    private function baseQuery(int $academicYearId, SchoolLevel $schoolLevel): Builder
    {
        return ProspectiveStudent::query()
            ->where('academic_year_id', $academicYearId)
            ->whereIn('status', [
                ProspectiveStudentStatus::Registered->value,
                ProspectiveStudentStatus::Converted->value,
            ])
            ->whereIn(
                'school_class_id',
                SchoolClass::query()
                    ->whereIn('level', $schoolLevel->classLevels())
                    ->select('id'),
            );
    }

    /** @return Builder<ProspectiveStudent> */
    private function groupQuery(int $academicYearId, SchoolLevel $schoolLevel, string $group): Builder
    {
        $query = $this->baseQuery($academicYearId, $schoolLevel)->select([
            'id',
            'registration_number',
            'nama_lengkap',
            'nama_panggilan',
            'jenis_kelamin',
            'nama_orang_tua',
            'no_telp_orang_tua',
            'alamat',
            'applicant_category',
            'academic_year_id',
            'school_class_id',
            'status',
            'created_at',
        ]);
        $definition = self::GROUPS[$group];

        if ($group !== self::GROUP_UNCLASSIFIED) {
            return $query
                ->where('jenis_kelamin', $definition['gender'])
                ->where('applicant_category', $definition['category']);
        }

        return $query->where(function (Builder $query): void {
            $query->whereNull('jenis_kelamin')
                ->orWhereNotIn('jenis_kelamin', ['L', 'P'])
                ->orWhereNull('applicant_category')
                ->orWhereNotIn('applicant_category', [
                    PpdbApplicantCategory::ANNUR_FAMILY->value,
                    PpdbApplicantCategory::NON_ANNUR_FAMILY->value,
                ]);
        });
    }

    /**
     * @return array<string, Closure(Relation): void>
     */
    private function relationsForExport(): array
    {
        return [
            'schoolClass' => fn (Relation $query) => $query->select(['id', 'name', 'level']),
            'ppdbProfile' => fn (Relation $query) => $query->select([
                'id', 'prospective_student_id', 'tempat_lahir', 'tanggal_lahir', 'asal_sekolah',
                'nik_siswa', 'nisn', 'nomor_kk', 'nomor_akta_kelahiran', 'anak_ke', 'jumlah_saudara',
                'hobi', 'cita_cita', 'status_anak', 'agama', 'bahasa_sehari_hari', 'rt', 'rw',
                'kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
                'jarak_tempuh_kategori', 'jarak_tempuh_detail', 'moda_transportasi',
                'moda_transportasi_lainnya', 'tinggi_badan', 'berat_badan', 'lingkar_kepala',
                'golongan_darah', 'riwayat_penyakit', 'berkebutuhan_khusus',
                'keterangan_kebutuhan_khusus',
            ]),
            'ppdbParents' => fn (Relation $query) => $query->select([
                'id', 'prospective_student_id', 'role', 'status_hidup', 'nik', 'tempat_lahir',
                'tanggal_lahir', 'pendidikan_terakhir', 'email', 'nama', 'no_telp', 'occupation',
                'income_range',
            ]),
            'ppdbSiblings' => fn (Relation $query) => $query->select([
                'id', 'prospective_student_id', 'sort_order', 'nama_lengkap', 'tanggal_lahir',
                'jenis_kelamin', 'pendidikan_pekerjaan',
            ]),
        ];
    }

    private function assertValidGroup(string $group): void
    {
        if (! array_key_exists($group, self::GROUPS)) {
            throw new InvalidArgumentException("Grup rekap PPDB tidak valid: {$group}.");
        }
    }
}
