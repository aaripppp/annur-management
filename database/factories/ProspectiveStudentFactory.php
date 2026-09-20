<?php

namespace Database\Factories;

use App\Enums\ProspectiveStudentStatus;
use App\Models\AcademicYear;
use App\Models\ProspectiveStudent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectiveStudent>
 */
class ProspectiveStudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_number' => 'REG-'.now()->year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'nama_lengkap' => $this->faker->name(),
            'nama_panggilan' => $this->faker->firstName(),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'nama_orang_tua' => $this->faker->name('male'),
            'no_telp_orang_tua' => $this->faker->numerify('08##########'),
            'alamat' => $this->faker->address(),
            'academic_year_id' => function (): int {
                $startYear = now()->year + 1;
                $targetYear = $startYear.'/'.($startYear + 1);

                return AcademicYear::firstOrCreate(
                    ['year' => $targetYear],
                    [
                        'is_active' => false,
                        'start_date' => $startYear.'-07-01',
                        'end_date' => ($startYear + 1).'-06-30',
                    ]
                )->id;
            },
            'school_class_id' => null,
            'status' => ProspectiveStudentStatus::Registered,
            'converted_student_id' => null,
            'converted_at' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function converted(): static
    {
        return $this->state(fn (): array => [
            'status' => ProspectiveStudentStatus::Converted,
            'converted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => ProspectiveStudentStatus::Cancelled,
        ]);
    }
}
