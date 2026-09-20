<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['BSI', 'BCA', 'Mandiri', 'BRI', 'BNI']),
            'type' => Bank::TYPE_BANK,
            'account_number' => $this->faker->unique()->numerify('#######'),
            'account_name' => 'Yayasan Sekolah',
            'is_active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Tunai',
            'type' => Bank::TYPE_CASH,
            'account_number' => null,
            'account_name' => null,
        ]);
    }
}
