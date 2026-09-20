<?php

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Models\StudentEligibilityConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentEligibilityConfig>
 */
class StudentEligibilityConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_level' => fake()->randomElement(SchoolLevel::cases()),
            'default_pooled_payment_type_ids' => null,
            'default_pooled_threshold' => 50,
            'default_pooled_is_active' => false,
        ];
    }
}
