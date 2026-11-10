<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverAssignment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JemputanDriverAssignment>
 */
class JemputanDriverAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jemputan_driver_id' => JemputanDriver::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => fn (): int => AcademicYear::query()->firstOrCreate(
                ['year' => '2026/2027'],
                ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
            )->id,
            'effective_from' => '2026-07-01',
            'effective_until' => null,
        ];
    }
}
