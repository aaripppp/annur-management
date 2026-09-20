<?php

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Models\PaymentType;
use App\Models\PaymentTypeSchoolLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTypeSchoolLevel>
 */
class PaymentTypeSchoolLevelFactory extends Factory
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
            'school_level' => SchoolLevel::SMP,
            'is_required' => true,
            'is_active' => true,
        ];
    }
}
