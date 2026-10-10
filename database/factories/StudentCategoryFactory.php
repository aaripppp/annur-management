<?php

namespace Database\Factories;

use App\Models\StudentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentCategory>
 */
class StudentCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->lexify('kategori_??????'),
            'is_active' => true,
        ];
    }
}
