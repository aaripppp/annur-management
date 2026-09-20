<?php

namespace Database\Factories;

use App\Enums\BillFrequency;
use App\Models\PaymentType;
use App\Models\ProspectiveStudent;
use App\Models\ProspectiveStudentBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectiveStudentBill>
 */
class ProspectiveStudentBillFactory extends Factory
{
    protected $model = ProspectiveStudentBill::class;

    public function definition(): array
    {
        return [
            'prospective_student_id' => ProspectiveStudent::factory(),
            'payment_type_id' => PaymentType::factory(),
            'amount' => 350000,
            'billing_frequency' => BillFrequency::OneTime,
            'academic_year' => '2027/2028',
            'due_date' => null,
            'created_by' => null,
        ];
    }
}
