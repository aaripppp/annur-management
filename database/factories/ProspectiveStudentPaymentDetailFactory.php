<?php

namespace Database\Factories;

use App\Models\PaymentType;
use App\Models\ProspectiveStudentBill;
use App\Models\ProspectiveStudentPayment;
use App\Models\ProspectiveStudentPaymentDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectiveStudentPaymentDetail>
 */
class ProspectiveStudentPaymentDetailFactory extends Factory
{
    protected $model = ProspectiveStudentPaymentDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospective_student_payment_id' => ProspectiveStudentPayment::factory(),
            'prospective_student_bill_id' => ProspectiveStudentBill::factory(),
            'payment_type_id' => PaymentType::factory(),
            'amount' => $this->faker->randomElement([150000, 250000, 350000]),
            'description' => null,
        ];
    }
}
