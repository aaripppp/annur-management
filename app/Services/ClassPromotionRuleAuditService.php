<?php

namespace App\Services;

use App\Models\ClassPromotionRule;
use App\Models\ClassPromotionRuleLog;

class ClassPromotionRuleAuditService
{
    public const CREATED_MANUAL = 'created_manual';

    public const EDITED_MANUAL = 'edited_manual';

    public const RULE_DELETED = 'rule_deleted';

    public const RULE_ACTIVATED = 'rule_activated';

    public const RULE_DEACTIVATED = 'rule_deactivated';

    /**
     * Append an immutable audit entry.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?int $ruleId,
        ?int $sourceClassId,
        string $actionType,
        ?string $oldAction = null,
        ?int $oldTargetClassId = null,
        ?string $newAction = null,
        ?int $newTargetClassId = null,
        ?int $changedBy = null,
        array $metadata = []
    ): void {
        ClassPromotionRuleLog::create([
            'class_promotion_rule_id' => $ruleId,
            'source_class_id' => $sourceClassId,
            'action_type' => $actionType,
            'old_action' => $oldAction,
            'old_target_class_id' => $oldTargetClassId,
            'new_action' => $newAction,
            'new_target_class_id' => $newTargetClassId,
            'changed_by' => $changedBy,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    public function createdManual(ClassPromotionRule $rule, ?int $changedBy = null): void
    {
        $this->record(
            $rule->id,
            $rule->source_class_id,
            self::CREATED_MANUAL,
            newAction: $rule->action,
            newTargetClassId: $rule->target_class_id,
            changedBy: $changedBy
        );
    }

    /**
     * @param  array{action: string, target_class_id: int|null, is_active: bool}  $oldState
     */
    public function editedManual(ClassPromotionRule $rule, array $oldState, ?int $changedBy = null): void
    {
        $this->record(
            $rule->id,
            $rule->source_class_id,
            self::EDITED_MANUAL,
            oldAction: $oldState['action'],
            oldTargetClassId: $oldState['target_class_id'],
            newAction: $rule->action,
            newTargetClassId: $rule->target_class_id,
            changedBy: $changedBy
        );
    }

    /**
     * @param  array{action: string, target_class_id: int|null, is_active: bool}  $oldState
     */
    public function deleted(ClassPromotionRule $rule, array $oldState, ?int $changedBy = null): void
    {
        $this->record(
            $rule->id,
            $rule->source_class_id,
            self::RULE_DELETED,
            oldAction: $oldState['action'],
            oldTargetClassId: $oldState['target_class_id'],
            changedBy: $changedBy,
            metadata: ['was_active' => $oldState['is_active']]
        );
    }

    public function activated(ClassPromotionRule $rule, ?int $changedBy = null): void
    {
        $this->record(
            $rule->id,
            $rule->source_class_id,
            self::RULE_ACTIVATED,
            newAction: $rule->action,
            newTargetClassId: $rule->target_class_id,
            changedBy: $changedBy,
            metadata: ['was_active' => false, 'is_active' => true]
        );
    }

    public function deactivated(ClassPromotionRule $rule, ?int $changedBy = null): void
    {
        $this->record(
            $rule->id,
            $rule->source_class_id,
            self::RULE_DEACTIVATED,
            newAction: $rule->action,
            newTargetClassId: $rule->target_class_id,
            changedBy: $changedBy,
            metadata: ['was_active' => true, 'is_active' => false]
        );
    }
}
