<?php

namespace Database\Factories;

use App\Enums\PaymentTypeAudience;
use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentType>
 */
class PaymentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['SPP', 'Ekskul', 'Jemputan', 'OSIS', 'Uang Pangkal', 'Uang Kegiatan', 'Lain-lain']),
            'audience' => PaymentTypeAudience::Student,
            'is_active' => true,
            'is_auto_enrolled' => false,
            'is_required' => false,
        ];
    }
}
