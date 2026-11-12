<?php

namespace Database\Factories;

use App\Models\JemputanDriver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JemputanDriver>
 */
class JemputanDriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'foundation_deduction_percentage' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
