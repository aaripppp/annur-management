<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\DaycareChild;
use App\Models\DaycarePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DaycarePayment>
 */
class DaycarePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => $this->faker->unique()->numerify('KWT-DC-2026-######'),
            'daycare_child_id' => DaycareChild::factory(),
            'bank_id' => Bank::factory(),
            'payment_date' => $this->faker->date(),
            'total_amount' => $this->faker->numberBetween(50_000, 1_000_000),
            'proof_path' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
