<?php

namespace Database\Factories;

use App\Models\DaycareChild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DaycareChild>
 */
class DaycareChildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_lengkap' => $this->faker->name(),
            'nama_panggilan' => $this->faker->firstName(),
            'tempat_lahir' => $this->faker->city(),
            'tanggal_lahir' => $this->faker->dateTimeBetween('-6 years', '-2 years')->format('Y-m-d'),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'alamat' => $this->faker->address(),
            'kelas' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']),
            'nama_ayah' => null,
            'no_telp_ayah' => null,
            'nama_ibu' => null,
            'no_telp_ibu' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
