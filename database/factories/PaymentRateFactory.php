<?php

namespace Database\Factories;

use App\Enums\BillFrequency;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRate>
 */
class PaymentRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_type_id' => PaymentType::factory(),
            'class_level' => $this->faker->numberBetween(7, 9),
            'amount' => $this->faker->randomElement([100000, 150000, 500000, 1500000]),
            'is_monthly' => true,
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_until' => null,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => ['billing_frequency' => BillFrequency::Monthly]);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['billing_frequency' => BillFrequency::Yearly]);
    }

    public function oneTime(): static
    {
        return $this->state(fn () => ['billing_frequency' => BillFrequency::OneTime]);
    }
}
