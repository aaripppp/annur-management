<?php

namespace App\Services;

use App\Enums\SchoolLevel;
use App\Models\PpdbParent;
use App\Models\PpdbProfile;
use App\Models\PpdbSibling;
use App\Models\ProspectiveStudent;
use App\Models\Student;
use App\Models\User;
use App\Support\PersonNameNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PpdbApplicantDataUpdater
{
    public function __construct(private readonly PpdbStudentDataSynchronizer $studentDataSynchronizer) {}

    /**
     * @param  array{
     *     applicant_category: string|null,
     *     student: array<string, mixed>,
     *     profile: array<string, mixed>,
     *     parents: array<string, array<string, mixed>>,
     *     siblings: list<array<string, mixed>>
     * }  $data
     */
    public function update(int $prospectiveStudentId, array $data, ?int $expectedStudentId = null, ?User $user = null): ProspectiveStudent
    {
        return DB::transaction(function () use ($prospectiveStudentId, $data, $expectedStudentId, $user): ProspectiveStudent {
            $prospectiveStudent = ProspectiveStudent::query()
                ->lockForUpdate()
                ->findOrFail($prospectiveStudentId);

            if ($user !== null) {
                Gate::forUser($user)->authorize('updateBiodata', $prospectiveStudent);
            }

            if ($expectedStudentId !== null && $prospectiveStudent->converted_student_id !== $expectedStudentId) {
                throw new AuthorizationException('Relasi siswa dan data PPDB tidak valid.');
            }

            $student = $this->lockedConvertedStudent($prospectiveStudent, $expectedStudentId);

            if ($user?->isTuUnit() && $student !== null) {
                Gate::forUser($user)->authorize('view', $student);
            }
            $father = $this->normalizeParent($data['parents']['father'] ?? [], true);
            $mother = $this->normalizeParent($data['parents']['mother'] ?? [], true);
            $guardian = $this->normalizeParent($data['parents']['guardian'] ?? []);
            $classLevel = $prospectiveStudent->schoolClass?->level;
            $isTk = $classLevel !== null && SchoolLevel::fromClassLevel((int) $classLevel) === SchoolLevel::TK;
            $profile = $this->normalizeProfile($data['profile'], $isTk);

            $prospectiveStudent->update([
                'applicant_category' => $this->nullableString($data['applicant_category']),
                'nama_lengkap' => trim((string) $data['student']['nama_lengkap']),
                'nama_panggilan' => $this->nullableString($data['student']['nama_panggilan'] ?? null),
                'jenis_kelamin' => $this->nullableString($data['student']['jenis_kelamin'] ?? null),
                'alamat' => $this->nullableString($data['student']['alamat'] ?? null),
                'nama_orang_tua' => $father['nama'],
                'no_telp_orang_tua' => $father['no_telp'],
            ]);

            $this->updateProfile($prospectiveStudent, $profile);
            $this->updateParent($prospectiveStudent, 'father', $father);
            $this->updateParent($prospectiveStudent, 'mother', $mother);
            $this->updateGuardian($prospectiveStudent, $guardian);
            $this->replaceSiblings($prospectiveStudent, $data['siblings']);

            if ($student !== null) {
                $this->studentDataSynchronizer->fillMappedValues($student, [
                    'nama_lengkap' => $prospectiveStudent->nama_lengkap,
                    'nama_panggilan' => $prospectiveStudent->nama_panggilan,
                    'jenis_kelamin' => $prospectiveStudent->jenis_kelamin,
                    'alamat' => $prospectiveStudent->alamat,
                ], $profile, $father, $mother);

                if ($student->isDirty()) {
                    $student->save();
                }
            }

            return $prospectiveStudent->fresh([
                'academicYear',
                'schoolClass',
                'convertedStudent',
                'ppdbProfile',
                'ppdbParents',
                'ppdbSiblings',
                'ppdbDocuments',
            ]);
        }, 3);
    }

    private function lockedConvertedStudent(ProspectiveStudent $prospectiveStudent, ?int $expectedStudentId): ?Student
    {
        if ($prospectiveStudent->converted_student_id === null) {
            if ($expectedStudentId !== null) {
                throw new AuthorizationException('Relasi siswa dan data PPDB tidak valid.');
            }

            return null;
        }

        $student = Student::query()->lockForUpdate()->find($prospectiveStudent->converted_student_id);

        if ($student === null
            || ($expectedStudentId !== null && $student->id !== $expectedStudentId)
            || ! $student->prospectiveStudent()->whereKey($prospectiveStudent->id)->exists()) {
            throw new AuthorizationException('Relasi siswa dan data PPDB tidak valid.');
        }

        return $student;
    }

    /** @param array<string, mixed> $profile */
    private function updateProfile(ProspectiveStudent $prospectiveStudent, array $profile): void
    {
        $existingProfile = PpdbProfile::query()
            ->where('prospective_student_id', $prospectiveStudent->id)
            ->lockForUpdate()
            ->first();

        if ($existingProfile !== null) {
            $existingProfile->update($profile);

            return;
        }

        if ($this->hasData($profile)) {
            $prospectiveStudent->ppdbProfile()->create($profile);
        }
    }

    /** @param array<string, mixed> $data */
    private function updateParent(ProspectiveStudent $prospectiveStudent, string $role, array $data): void
    {
        $parent = PpdbParent::query()
            ->where('prospective_student_id', $prospectiveStudent->id)
            ->where('role', $role)
            ->lockForUpdate()
            ->first();

        if ($parent !== null) {
            $parent->update($data);

            return;
        }

        if ($this->hasData($data)) {
            $prospectiveStudent->ppdbParents()->create(['role' => $role, ...$data]);
        }
    }

    /** @param array<string, mixed> $data */
    private function updateGuardian(ProspectiveStudent $prospectiveStudent, array $data): void
    {
        $guardian = PpdbParent::query()
            ->where('prospective_student_id', $prospectiveStudent->id)
            ->where('role', 'guardian')
            ->lockForUpdate()
            ->first();

        if (! $this->hasData($data)) {
            $guardian?->delete();

            return;
        }

        if ($guardian !== null) {
            $guardian->update($data);

            return;
        }

        $prospectiveStudent->ppdbParents()->create(['role' => 'guardian', ...$data]);
    }

    /** @param list<array<string, mixed>> $siblings */
    private function replaceSiblings(ProspectiveStudent $prospectiveStudent, array $siblings): void
    {
        $existing = $prospectiveStudent->ppdbSiblings()->lockForUpdate()->get()->values();
        $prospectiveStudent->ppdbSiblings()->delete();

        $index = 0;
        foreach (array_values($siblings) as $sibling) {
            $submitted = trim((string) ($sibling['nama_lengkap'] ?? ''));
            $existingSibling = $existing->get($index);

            if ($existingSibling !== null && $submitted !== '') {
                $existingRaw = (string) $existingSibling->getRawOriginal('nama_lengkap');
                if ($existingRaw === $submitted) {
                    DB::table('ppdb_siblings')->insert([
                        'prospective_student_id' => $prospectiveStudent->id,
                        'sort_order' => $index + 1,
                        'nama_lengkap' => $existingRaw,
                        'tanggal_lahir' => $sibling['tanggal_lahir'],
                        'jenis_kelamin' => $sibling['jenis_kelamin'],
                        'pendidikan_pekerjaan' => trim((string) ($sibling['pendidikan_pekerjaan'] ?? '')),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $index++;

                    continue;
                }
            }

            PpdbSibling::create([
                'prospective_student_id' => $prospectiveStudent->id,
                'sort_order' => $index + 1,
                'nama_lengkap' => $submitted === '' ? '' : PersonNameNormalizer::normalize($submitted),
                'tanggal_lahir' => $sibling['tanggal_lahir'],
                'jenis_kelamin' => $sibling['jenis_kelamin'],
                'pendidikan_pekerjaan' => trim((string) ($sibling['pendidikan_pekerjaan'] ?? '')),
            ]);
            $index++;
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeProfile(array $data, bool $isTk): array
    {
        $stringFields = [
            'tempat_lahir', 'tanggal_lahir', 'asal_sekolah', 'nik_siswa', 'nisn', 'nomor_kk', 'nomor_akta_kelahiran',
            'hobi', 'cita_cita', 'jarak_tempuh_kategori', 'moda_transportasi', 'moda_transportasi_lainnya',
            'status_anak', 'agama', 'bahasa_sehari_hari', 'rt', 'rw', 'kelurahan', 'kecamatan',
            'kabupaten_kota', 'provinsi', 'kode_pos', 'tinggi_badan', 'berat_badan',
            'lingkar_kepala', 'golongan_darah', 'riwayat_penyakit', 'keterangan_kebutuhan_khusus',
        ];
        $profile = [];

        foreach ($stringFields as $field) {
            $profile[$field] = $this->nullableString($data[$field] ?? null);
        }

        $profile['anak_ke'] = $this->nullableInteger($data['anak_ke'] ?? null);
        $profile['jumlah_saudara'] = $this->nullableInteger($data['jumlah_saudara'] ?? null);
        $profile['nisn'] = $isTk ? null : $profile['nisn'];
        $profile['jarak_tempuh_detail'] = $profile['jarak_tempuh_kategori'] === 'more_than_1_km'
            ? $this->normalizeDistance($data['jarak_tempuh_detail'] ?? null)
            : null;
        $profile['moda_transportasi_lainnya'] = $profile['moda_transportasi'] === 'lainnya'
            ? $profile['moda_transportasi_lainnya']
            : null;
        $profile['berkebutuhan_khusus'] = filled($data['berkebutuhan_khusus'] ?? null)
            ? (bool) ((int) $data['berkebutuhan_khusus'])
            : null;

        return $profile;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeParent(array $data, bool $includeLivingStatus = false): array
    {
        $parent = [
            'nama' => $this->nullableString($data['nama'] ?? null),
            'nik' => $this->nullableString($data['nik'] ?? null),
            'tempat_lahir' => $this->nullableString($data['tempat_lahir'] ?? null),
            'tanggal_lahir' => $this->nullableString($data['tanggal_lahir'] ?? null),
            'pendidikan_terakhir' => $this->nullableString($data['pendidikan_terakhir'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'no_telp' => $this->nullableString($data['no_telp'] ?? null),
            'occupation' => $this->nullableString($data['occupation'] ?? null),
            'income_range' => $this->nullableString($data['income_range'] ?? null),
        ];

        if ($includeLivingStatus) {
            $parent['status_hidup'] = $this->nullableString($data['status_hidup'] ?? null);
        }

        return $parent;
    }

    /** @param array<string, mixed> $data */
    private function hasData(array $data): bool
    {
        return collect(Arr::except($data, ['prospective_student_id', 'role']))
            ->contains(fn (mixed $value): bool => filled($value));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    private function normalizeDistance(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        if (preg_match('/\A\d{1,4}(?:\.\d{1,2})?\z/', $normalized) !== 1) {
            return $this->nullableString($normalized);
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
