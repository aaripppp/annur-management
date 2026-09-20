<?php

namespace Database\Factories;

use App\Models\ClassPromotionRule;
use App\Models\ClassPromotionRuleLog;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassPromotionRuleLog>
 */
class ClassPromotionRuleLogFactory extends Factory
{
    protected $model = ClassPromotionRuleLog::class;

    public function definition(): array
    {
        return [
            'source_class_id' => SchoolClass::factory(),
            'class_promotion_rule_id' => ClassPromotionRule::factory(),
            'action_type' => 'created_manual',
            'old_action' => null,
            'old_target_class_id' => null,
            'new_action' => 'promote',
            'new_target_class_id' => null,
            'changed_by' => User::factory(),
            'metadata' => null,
        ];
    }
}
