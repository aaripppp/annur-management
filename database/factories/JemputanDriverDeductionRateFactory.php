<?php

namespace Database\Factories;

use App\Models\JemputanDriver;
use App\Models\JemputanDriverDeductionRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JemputanDriverDeductionRate>
 */
class JemputanDriverDeductionRateFactory extends Factory
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
            'effective_year' => 2026,
            'effective_month' => 7,
            'percentage' => $this->faker->randomElement([0, 7, 12, 15]),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
