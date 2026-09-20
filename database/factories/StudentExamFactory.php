<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\StudentExam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentExam>
 */
class StudentExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => fn (): int => AcademicYear::query()->firstOrCreate(
                ['year' => '2026/2027'],
                ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
            )->id,
            'name' => 'Ujian '.$this->faker->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
