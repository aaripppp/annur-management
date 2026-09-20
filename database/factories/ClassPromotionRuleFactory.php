<?php

namespace Database\Factories;

use App\Models\ClassPromotionRule;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassPromotionRule>
 */
class ClassPromotionRuleFactory extends Factory
{
    protected $model = ClassPromotionRule::class;

    public function definition(): array
    {
        return [
            'source_class_id' => SchoolClass::factory(),
            'target_class_id' => null,
            'action' => 'graduate',
            'is_active' => true,
        ];
    }
}
