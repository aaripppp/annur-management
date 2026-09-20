<?php

namespace Database\Factories;

use App\Models\BillAdjustment;
use App\Models\StudentBill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillAdjustment>
 */
class BillAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_id' => StudentBill::factory(),
            'type' => BillAdjustment::TYPE_DISCOUNT,
            'amount' => -$this->faker->numberBetween(100000, 500000),
            'reason' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
