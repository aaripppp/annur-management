<?php

namespace Database\Factories;

use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentPaymentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPaymentSetting>
 */
class StudentPaymentSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'payment_type_id' => PaymentType::factory(),
            'is_active' => true,
            'started_at' => now()->startOfYear()->toDateString(),
            'ended_at' => null,
        ];
    }
}
