<?php

namespace Database\Factories;

use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentBill>
 */
class StudentBillFactory extends Factory
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
            'payment_type_id' => PaymentType::factory(),
            'amount' => $this->faker->randomElement([100000, 500000, 1500000]),
            'period_month' => $this->faker->numberBetween(1, 12),
            'period_year' => now()->year,
            'billing_frequency' => 'monthly',
            'due_date' => null,
        ];
    }
}
