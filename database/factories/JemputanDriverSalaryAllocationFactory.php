<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\JemputanDriver;
use App\Models\JemputanDriverSalaryAllocation;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JemputanDriverSalaryAllocation>
 */
class JemputanDriverSalaryAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => fn (): int => AcademicYear::query()->firstOrCreate(
                ['year' => '2026/2027'],
                ['is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'],
            )->id,
            'period_year' => 2026,
            'period_month' => 7,
            'jemputan_driver_id' => JemputanDriver::factory(),
            'allocated_amount' => $this->faker->randomElement([150000, 250000, 500000]),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
