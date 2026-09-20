<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectiveStudentPayment>
 */
class ProspectiveStudentPaymentFactory extends Factory
{
    protected $model = ProspectiveStudentPayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => 'KWT-REG-'.$this->faker->numberBetween(2024, 2030).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'prospective_student_id' => ProspectiveStudent::factory(),
            'bank_id' => Bank::factory(),
            'payment_date' => $this->faker->date(),
            'total_amount' => $this->faker->randomElement([150000, 250000, 350000]),
            'receipt' => null,
            'description' => $this->faker->sentence(4),
            'status' => ProspectiveStudentPayment::STATUS_ACTIVE,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'created_by' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => ProspectiveStudentPayment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
