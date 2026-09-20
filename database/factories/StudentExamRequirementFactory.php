<?php

namespace Database\Factories;

use App\Enums\BillFrequency;
use App\Enums\SchoolLevel;
use App\Models\PaymentType;
use App\Models\StudentExam;
use App\Models\StudentExamRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentExamRequirement>
 */
class StudentExamRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_exam_id' => StudentExam::factory(),
            'school_level' => SchoolLevel::SMP,
            'payment_type_id' => PaymentType::factory(),
            'billing_frequency' => BillFrequency::Monthly,
            'start_month' => '2026-08-01',
            'end_month' => '2026-09-01',
            'required_percentage' => 100,
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (): array => [
            'billing_frequency' => BillFrequency::Yearly,
            'start_month' => null,
            'end_month' => null,
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (): array => [
            'billing_frequency' => BillFrequency::OneTime,
            'start_month' => null,
            'end_month' => null,
        ]);
    }
}
