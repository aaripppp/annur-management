<?php

namespace Database\Factories;

use App\Models\DaycarePayment;
use App\Models\DaycarePaymentDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DaycarePaymentDetail>
 */
class DaycarePaymentDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daycare_payment_id' => DaycarePayment::factory(),
            'description' => $this->faker->sentence(3),
            'amount' => $this->faker->numberBetween(50_000, 1_000_000),
        ];
    }
}
