<?php

namespace App\Services;

use App\Models\Student;

class PpdbStudentDataSynchronizer
{
    /** @var array<string, string> */
    private const CORE_MAPPING = [
        'nama_lengkap' => 'nama_lengkap',
        'nama_panggilan' => 'nama_panggilan',
        'jenis_kelamin' => 'jenis_kelamin',
        'alamat' => 'alamat',
    ];

    /** @var array<string, string> */
    private const PROFILE_MAPPING = [
        'tempat_lahir' => 'tempat_lahir',
        'tanggal_lahir' => 'tanggal_lahir',
    ];

    /** @var array<string, string> */
    private const FATHER_MAPPING = [
        'nama' => 'nama_ayah',
        'no_telp' => 'no_telp_ayah',
    ];

    /** @var array<string, string> */
    private const MOTHER_MAPPING = [
        'nama' => 'nama_ibu',
        'no_telp' => 'no_telp_ibu',
    ];

    /** @var list<string> */
    public const STUDENT_COLUMNS = [
        'id',
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'alamat',
        'tempat_lahir',
        'tanggal_lahir',
        'nama_ayah',
        'no_telp_ayah',
        'nama_ibu',
        'no_telp_ibu',
    ];

    /**
     * @param  array<string, mixed>  $core
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $father
     * @param  array<string, mixed>  $mother
     */
    public function fillMappedValues(Student $student, array $core, array $profile, array $father, array $mother): bool
    {
        $student->fill([
            ...$this->mapProvidedValues($core, self::CORE_MAPPING),
            ...$this->mapProvidedValues($profile, self::PROFILE_MAPPING),
            ...$this->mapProvidedValues($father, self::FATHER_MAPPING),
            ...$this->mapProvidedValues($mother, self::MOTHER_MAPPING),
        ]);

        return $student->isDirty();
    }

    /** @return array<string, mixed> */
    public function snapshotValues(Student $student): array
    {
        return collect(array_merge(
            array_values(self::CORE_MAPPING),
            array_values(self::PROFILE_MAPPING),
            array_values(self::FATHER_MAPPING),
            array_values(self::MOTHER_MAPPING),
        ))->mapWithKeys(function (string $field) use ($student): array {
            $value = $student->{$field};

            if ($field === 'tanggal_lahir') {
                return [$field => $value?->format('Y-m-d')];
            }

            return [$field => $value];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    private function mapProvidedValues(array $values, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $source => $destination) {
            if (array_key_exists($source, $values)) {
                $mapped[$destination] = $values[$source];
            }
        }

        return $mapped;
    }
}
